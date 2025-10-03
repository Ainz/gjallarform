<?php
/**
 * Conram form relay (sendmail)
 *
 * Features:
 * - CORS: only accepts POSTs from conram.it
 * - Anti-abuse: honeypot, token, time-trap, basic rate limiting
 * - Validation:
 *     • Name/Subject: extended Latin letters allowed, emojis blocked
 *     • Email: RFC-style validation
 *     • Phone: optional; digits, space, + - ( ) / ; 6–32 chars
 *     • Message: strips control chars; blocks emojis
 * - Mail: uses sendmail with -f (DMARC alignment via form-engine@conram.it)
 * - Responses:
 *     • AJAX/fetch callers → JSON
 *     • Normal form POST  → 303 redirect to thank-you.html (PRG pattern)
 *
 * NEW IN 1.1.2
 * - 303 redirect to /thank-you.html for normal posts (no JSON blob shown to users)
 * - Short Reference ID added and included in admin/confirmation emails
 * - Confirmation email sent to submitter after successful admin mail
 * - Conditional Content-Type headers (JSON only when returning JSON)
 */

// ─────────────────────────────────────────────────────────────────────────────
// CORS: only allow your live origins
// ─────────────────────────────────────────────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = ['https://www.conram.it', 'https://conram.it'];
if (!in_array($origin, $allowed_origins, true)) {
  http_response_code(403);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'forbidden_origin']);
  exit;
}
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');
header('Access-Control-Allow-Methods: POST'); // informative

// Decide if caller expects JSON (AJAX) or is a normal form POST
$wantsJson = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
          || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');

// Map site_tag → thank-you URL (whitelist to avoid open redirects)
function thank_you_url(string $siteTag): string {
  static $map = [
    '[www.conram.it Contact form]' => 'https://www.conram.it/thank-you.html',
    // Add more mappings if you post from other sites:
    // '[conram.se Contact form]' => 'https://www.conram.se/thank-you.html',
  ];
  return $map[$siteTag] ?? 'https://www.conram.it/thank-you.html';
}

// ─────────────────────────────────────────────────────────────────────────────
// Rate limit: per-IP sliding window (10 posts / 30 min)
// ─────────────────────────────────────────────────────────────────────────────
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$now = time();
$rlf = sys_get_temp_dir() . '/svc_form_rl_' . md5($ip);
$win = 1800; // 30 minutes
$lim = 10;   // max hits in window
$hits = @json_decode(@file_get_contents($rlf), true) ?: [];
$filtered = [];
foreach ($hits as $t) { if (($now - (int)$t) < $win) { $filtered[] = (int)$t; } }
$hits = $filtered;
if (count($hits) >= $lim) {
  http_response_code(429);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'rate_limited']);
  exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers (sanitization & policy)
// ─────────────────────────────────────────────────────────────────────────────

function redirect_back_with_error(string $code): void {
  // point to your public contact form
  $back = 'https://www.conram.it/contact-form.html';
  // add err=code (no payloads, privacy-safe)
  $sep = (strpos($back,'?')!==false) ? '&' : '?';
  header('Location: ' . $back . $sep . 'err=' . urlencode($code), true, 303);
  exit;
}

function strip_controls(string $s): string {
  return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
}
function no_crlf(string $s): string {
  return str_replace(["\r", "\n"], ' ', $s);
}
function sanitize_line(string $s, int $max = 160): string {
  $s = trim(strip_controls($s));
  $s = preg_replace('/\s{2,}/u', ' ', $s) ?? $s;
  return mb_substr($s, 0, $max);
}
function sanitize_textblock(string $s, int $max = 4000): string {
  $s = trim(strip_controls($s));
  // Wrap extremely long unbroken sequences to avoid filters/abuse patterns
  $s = preg_replace_callback('/\S{200,}/u', static function($m) {
    return wordwrap($m[0], 80, ' ', true);
  }, $s) ?? $s;
  return mb_substr($s, 0, $max);
}
function sanitize_phone(string $s): string {
  $s = preg_replace('/[^\d\+\-\s\(\)\/]/u', '', $s) ?? '';
  return trim(preg_replace('/\s{2,}/u', ' ', $s));
}
function is_safe_line_unicode(string $s): bool {
  // Allow letters, marks, numbers, spaces, and common punctuation; block emoji/pictographs
  return (bool)preg_match('/^[\p{L}\p{M}\p{N}\s\-\_\.\,\:\;\!\?\(\)\'"\/&]+$/u', $s);
}
function contains_emoji(string $s): bool {
  return (bool)preg_match('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}]/u', $s);
}

// ─────────────────────────────────────────────────────────────────────────────
// Read POST fields (flexible name field aliases)
// ─────────────────────────────────────────────────────────────────────────────
$name     = sanitize_line($_POST['name'] ?? $_POST['fullname'] ?? $_POST['your-name'] ?? '', 120);
$email    = trim($_POST['email'] ?? '');
$subject  = sanitize_line($_POST['subject'] ?? '', 140);
$phoneRaw = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
$phone    = $phoneRaw; // validate raw first
$message  = sanitize_textblock($_POST['message'] ?? '', 4000);
$website  = trim($_POST['website'] ?? '');              // honeypot
$key      = trim($_POST['form_key'] ?? '');             // anti-CSRF token (static)
$siteTag  = sanitize_line($_POST['site_tag'] ?? '', 60);
$renderTs = isset($_POST['render_ts']) ? (int)$_POST['render_ts'] : 0; // ms since epoch

// ─────────────────────────────────────────────────────────────────────────────
// Honeypot / token / time-trap
// ─────────────────────────────────────────────────────────────────────────────
if ($website !== '') {
  // Silent "success" for bots: do not leak honeypot existence
  if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
  } else {
    header('Location: ' . thank_you_url($siteTag), true, 303);
  }
  exit;
}

if ($key !== 'conram_v1_2025_09') {
  http_response_code(400);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'bad_key']);
  exit;
}

// Require at least ~2s from render to submit (bots submit instantly)
if ($renderTs > 0) {
  $elapsedMs = (int)(microtime(true) * 1000) - $renderTs;
  if ($elapsedMs < 2000) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'too_fast']);
    exit;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Required-field validation
// ─────────────────────────────────────────────────────────────────────────────
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $subject === '' || $message === '') {
  http_response_code(400);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'validation']);
  exit;
}

// Name/Subject charset policy (allow å/ä/ö/ø/æ/é etc., block emojis/pictographs)
if (!is_safe_line_unicode($name)) {
  http_response_code(400);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'name_charset']);
  exit;
}
if (!is_safe_line_unicode($subject)) {
  http_response_code(400);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'subject_charset']);
  exit;
}

// Message: block emojis (controls already stripped in sanitize_textblock)
if (contains_emoji($message)) {
  http_response_code(400);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'message_emoji_blocked']);
  exit;
}

// Phone (optional): only digits/space/+ - ( ) / ; length 6–32
if ($phone !== '') {
  if (!preg_match('/^\+?[0-9\s\-\(\)\/]{6,32}$/u', $phone)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'phone_invalid']);
    exit;
  }
  if (preg_match('/[A-Za-z]/', $phone)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'phone_invalid']);
    exit;
  }
  // Normalize spacing
  $phone = preg_replace('/\s+/u', ' ', trim($phone));
}

// Optional link block (disabled by default)
// if (preg_match('/https?:\/\/|www\./i', $message)) {
//   http_response_code(400);
//   header('Content-Type: application/json; charset=utf-8');
//   echo json_encode(['ok' => false, 'error' => 'links_not_allowed']);
//   exit;
// }

// ─────────────────────────────────────────────────────────────────────────────
// Compose and send
// ─────────────────────────────────────────────────────────────────────────────
$to      = 'rikard.malmborg@conram.it';    // Admin recipient
$from    = 'form-engine@conram.it';        // DMARC-aligned envelope & From
$tag     = $siteTag !== '' ? $siteTag : '[Conram.it Contact]';

// Generate a short Reference ID (6 hex chars, e.g., 9KX7Q2)
try {
  $ref = strtoupper(bin2hex(random_bytes(3)));
} catch (\Throwable $e) {
  // Fallback if random_bytes is unavailable
  $ref = strtoupper(substr(md5(uniqid('', true)), 0, 6));
}

// Admin mail subject/body (include [REF])
$subj    = no_crlf($tag . ' [' . $ref . '] ' . $subject); // guard against header injection

$body  = "Reference: {$ref}\n";
$body .= "Name: {$name}\n";
$body .= "Email: {$email}\n";
$body .= "Phone: {$phone}\n";
$body .= "IP: {$ip}\n\n";
$body .= "Message:\n{$message}\n";

// Admin headers
$headers = [];
$headers[] = "From: Conram Forms <{$from}>";                  // aligned with SPF/DMARC
$headers[] = "Reply-To: " . no_crlf("{$name} <{$email}>");    // replies go to submitter
$headers[] = "Content-Type: text/plain; charset=UTF-8";
$headers[] = "X-Origin: service.conram.it";
$headers[] = "X-Form-Tag: {$tag}";
$headers[] = "X-Form-Ref: {$ref}";
$headers_str = implode("\r\n", $headers);

// Send admin mail (use -f to keep envelope aligned)
$ok = @mail($to, $subj, $body, $headers_str, "-f {$from}");

if ($ok) {
  // Rate-limit bookkeeping
  $hits[] = $now;
  @file_put_contents($rlf, json_encode($hits), LOCK_EX);

  // ─ Confirmation email to submitter (fire-and-forget) ──────────────────────
  $firstName = explode(' ', trim($name))[0] ?: 'there';
  $confirmTo = $email;

  $confirmSubject = "We received your message [{$ref}]";
  $confirmBody =
    "Hi {$firstName},\n\n" .
    "Thanks for contacting Conram. We’ve received your message and will get back to you.\n\n" .
    "Subject: {$subject}\n" .
    "Reference: {$ref}\n\n" .
    "If you didn’t submit this, you can ignore this email.\n\n" .
    "— Conram\n";

  $confirmHeaders = [];
  $confirmHeaders[] = "From: Conram Forms <{$from}>";             // aligned with SPF/DMARC
  $confirmHeaders[] = "Reply-To: " . no_crlf("Rikard <{$to}>");   // replies to you
  $confirmHeaders[] = "Content-Type: text/plain; charset=UTF-8";
  // Suppress auto-reply loops in MTAs
  $confirmHeaders[] = "Auto-Submitted: auto-replied";
  $confirmHeaders[] = "Precedence: auto-reply";
  $confirmHeaders[] = "X-Auto-Response-Suppress: All";
  $confirmHeaders[] = "X-Form-Ref: {$ref}";
  $confirmHeaders_str = implode("\r\n", $confirmHeaders);

  @mail($confirmTo, $confirmSubject, $confirmBody, $confirmHeaders_str, "-f {$from}");

  // ─ Return/redirect based on caller type ───────────────────────────────────
  if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'ref' => $ref], JSON_UNESCAPED_UNICODE);
  } else {
    header('Location: ' . thank_you_url($siteTag), true, 303); // PRG redirect to thank-you.html
  }
  exit;

} else {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'send_failed']);
  exit;
}
