<?php
/**
 * Conram form relay (sendmail)
 * - Accepts posts from conram.it; tolerant CORS (Origin or Referer, or none)
 * - Anti-abuse: honeypot, token, time-trap, rate limit
 * - Validation + charset/emoji rules
 * - Mail via sendmail (-f form-engine@conram.it)
 * - Responses:
 *     • Non-AJAX: 303 → thank-you.html (success) or → contact-form.html?err=... (fail)
 *     • AJAX (only when X-Requested-With: XMLHttpRequest): JSON
 */

/* ───────── Request context ───────── */
$origin  = $_SERVER['HTTP_ORIGIN']   ?? '';
$referer = $_SERVER['HTTP_REFERER']  ?? '';
$method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* Strict but tolerant source check */
$allowed = ['https://www.conram.it', 'https://conram.it'];
$referer_ok = false;
foreach ($allowed as $base) {
  if ($referer && stripos($referer, $base . '/') === 0) { $referer_ok = true; break; }
}
$origin_ok = in_array($origin, $allowed, true);

/* For plain browser form posts, CORS headers are unnecessary; do not 403 if Origin is empty */
if ($origin_ok) {
  header('Access-Control-Allow-Origin: ' . $origin);
  header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST'); // informative

/* Only treat as AJAX when the classic header is set */
$wantsJson = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');

/* Reject obviously foreign sources (but allow no-origin posts) */
if ($method !== 'POST') {
  http_response_code(405);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}
if ($origin && !$origin_ok && !$wantsJson) { // only block when Origin is present AND not allowed
  http_response_code(403);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'forbidden_origin']);
  exit;
}

/* Public URLs */
function thank_you_url(string $siteTag): string {
  static $map = [
    '[www.conram.it Contact form]' => 'https://www.conram.it/thank-you.html',
  ];
  return $map[$siteTag] ?? 'https://www.conram.it/thank-you.html';
}
function contact_form_url(): string {
  return 'https://www.conram.it/contact-form.html';
}
function redirect_back_with_error(string $code): void {
  $back = contact_form_url();
  $sep  = (strpos($back, '?') !== false) ? '&' : '?';
  header('Location: ' . $back . $sep . 'err=' . urlencode($code), true, 303);
  exit;
}

/* ───────── Rate limit: 10 posts / 30 min / IP (temp-file) ───────── */
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$now = time();
$rlf = sys_get_temp_dir() . '/svc_form_rl_' . md5($ip);
$win = 1800; $lim = 10;
$hits = @json_decode(@file_get_contents($rlf), true) ?: [];
$hits = array_values(array_filter($hits, fn($t) => ($now - (int)$t) < $win));
if (count($hits) >= $lim) {
  if ($wantsJson) {
    http_response_code(429);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'rate_limited']);
  } else {
    redirect_back_with_error('rate_limited');
  }
  exit;
}

/* ───────── Helpers ───────── */
function strip_controls(string $s): string { return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? ''; }
function no_crlf(string $s): string { return str_replace(["\r", "\n"], ' ', $s); }
function sanitize_line(string $s, int $max = 160): string {
  $s = trim(strip_controls($s)); $s = preg_replace('/\s{2,}/u', ' ', $s) ?? $s; return mb_substr($s, 0, $max);
}
function sanitize_textblock(string $s, int $max = 4000): string {
  $s = trim(strip_controls($s));
  $s = preg_replace_callback('/\S{200,}/u', static fn($m) => wordwrap($m[0], 80, ' ', true), $s) ?? $s;
  return mb_substr($s, 0, $max);
}
function sanitize_phone(string $s): string {
  $s = preg_replace('/[^\d\+\-\s\(\)\/]/u', '', $s) ?? ''; return trim(preg_replace('/\s{2,}/u', ' ', $s));
}
function is_safe_line_unicode(string $s): bool {
  return (bool)preg_match('/^[\p{L}\p{M}\p{N}\s\-\_\.\,\:\;\!\?\(\)\'"\/&]+$/u', $s);
}
function contains_emoji(string $s): bool {
  return (bool)preg_match('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}]/u', $s);
}

/* ───────── Read POST ───────── */
$name     = sanitize_line($_POST['name'] ?? $_POST['fullname'] ?? $_POST['your-name'] ?? '', 120);
$email    = trim($_POST['email'] ?? '');
$subject  = sanitize_line($_POST['subject'] ?? '', 140);
$phoneRaw = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
$phone    = $phoneRaw;
$message  = sanitize_textblock($_POST['message'] ?? '', 4000);
$website  = trim($_POST['website'] ?? '');
$key      = trim($_POST['form_key'] ?? '');
$siteTag  = sanitize_line($_POST['site_tag'] ?? '', 60);
$renderTs = isset($_POST['render_ts']) ? (int)$_POST['render_ts'] : 0;

/* ───────── Honeypot / token / time ───────── */
if ($website !== '') {
  // Silent success for bots
  if ($wantsJson) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => true]); }
  else { header('Location: ' . thank_you_url($siteTag), true, 303); }
  exit;
}
if ($key !== 'conram_v1_2025_09') {
  if ($wantsJson) { http_response_code(400); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'error'=>'bad_key']); }
  else { redirect_back_with_error('validation'); }
  exit;
}
if ($renderTs > 0) {
  $elapsedMs = (int)(microtime(true) * 1000) - $renderTs;
  if ($elapsedMs < 2000) {
    if ($wantsJson) { http_response_code(400); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'error'=>'too_fast']); }
    else { redirect_back_with_error('too_fast'); }
    exit;
  }
}

/* ───────── Field validation ───────── */
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $subject === '' || $message === '') {
  if ($wantsJson) { http_response_code(400); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'error'=>'validation']); }
  else { redirect_back_with_error('validation'); }
  exit;
}
if (!is_safe_line_unicode($name)) {
  if ($wantsJson) { http_response_code(400); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'error'=>'name_charset']); }
  else { redirect_back_with_error('validation'); }
  exit;
}
if (!is_safe_line_unicode($subject)) {
  if ($wantsJson) { http_response_code(400); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'error'=>'subject_charset']); }
  else { redirect_back_with_error('validation'); }
  exit;
}
if (contains_emoji($message)) {
  if ($wantsJson) { http_response_code(400); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'error'=>'message_emoji_blocked']); }
  else { redirect_back_with_error('validation'); }
  exit;
}
if ($phone !== '') {
  if (!preg_match('/^\+?[0-9\s\-\(\)\/]{6,32}$/u', $phone) || preg_match('/[A-Za-z]/', $phone)) {
    if ($wantsJson) { http_response_code(400); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'error'=>'phone_invalid']); }
    else { redirect_back_with_error('validation'); }
    exit;
  }
  $phone = preg_replace('/\s+/u', ' ', trim($phone));
}

/* ───────── Compose & send ───────── */
$to   = 'rikard.malmborg@conram.it';
$from = 'form-engine@conram.it';
$tag  = $siteTag !== '' ? $siteTag : '[Conram.it Contact]';

/* Short reference ID */
try { $ref = strtoupper(bin2hex(random_bytes(3))); }
catch (\Throwable $e) { $ref = strtoupper(substr(md5(uniqid('', true)), 0, 6)); }

$subj = no_crlf($tag . ' [' . $ref . '] ' . $subject);

$body  = "Reference: {$ref}\n";
$body .= "Name: {$name}\n";
$body .= "Email: {$email}\n";
$body .= "Phone: {$phone}\n";
$body .= "IP: {$ip}\n\n";
$body .= "Message:\n{$message}\n";

$headers = [];
$headers[] = "From: Conram Forms <{$from}>";
$headers[] = "Reply-To: " . no_crlf("{$name} <{$email}>");
$headers[] = "Content-Type: text/plain; charset=UTF-8";
$headers[] = "X-Origin: service.conram.it";
$headers[] = "X-Form-Tag: {$tag}";
$headers[] = "X-Form-Ref: {$ref}";
$headers_str = implode("\r\n", $headers);

/* Admin mail */
$ok = @mail($to, $subj, $body, $headers_str, "-f {$from}");

if ($ok) {
  /* Rate-limit bookkeeping */
  $hits[] = $now; @file_put_contents($rlf, json_encode($hits), LOCK_EX);

  /* Confirmation to submitter (fire-and-forget) */
  $firstName = explode(' ', trim($name))[0] ?: 'there';
  $confirmSubject = "We received your message [{$ref}]";
  $confirmBody =
    "Hi {$firstName},\n\n" .
    "Thanks for contacting Conram. We’ve received your message and will get back to you.\n\n" .
    "Subject: {$subject}\n" .
    "Reference: {$ref}\n\n" .
    "If you didn’t submit this, you can ignore this email.\n\n" .
    "— Conram\n";
  $confirmHeaders = [];
  $confirmHeaders[] = "From: Conram Forms <{$from}>";
  $confirmHeaders[] = "Reply-To: " . no_crlf("Rikard <{$to}>");
  $confirmHeaders[] = "Content-Type: text/plain; charset=UTF-8";
  $confirmHeaders[] = "Auto-Submitted: auto-replied";
  $confirmHeaders[] = "Precedence: auto-reply";
  $confirmHeaders[] = "X-Auto-Response-Suppress: All";
  $confirmHeaders[] = "X-Form-Ref: {$ref}";
  $confirmHeaders_str = implode("\r\n", $confirmHeaders);
  @mail($email, $confirmSubject, $confirmBody, $confirmHeaders_str, "-f {$from}");

  /* Success: JSON for AJAX, redirect for others */
  if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'ref' => $ref], JSON_UNESCAPED_UNICODE);
  } else {
    header('Location: ' . thank_you_url($siteTag), true, 303);
  }
  exit;

} else {
  if ($wantsJson) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'send_failed']);
  } else {
    redirect_back_with_error('tempfail');
  }
  exit;
}
