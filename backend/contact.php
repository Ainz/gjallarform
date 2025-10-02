<?php
/**
 * Conram form relay (sendmail)
 * - CORS: only accepts POSTs from conram.it
 * - Anti-abuse: honeypot, token, time-trap, basic rate limiting
 * - Validation:
 *     • Name/Subject: extended Latin letters allowed, emojis blocked
 *     • Email: RFC-ish basic validation
 *     • Phone: optional; allows digits, space, + - ( ) / ; 6–32 chars
 *     • Message: cleans control chars; optional emoji block
 * - Mail: uses sendmail with -f (DMARC alignment via form-engine@conram.it)
 * - Returns JSON: { ok: true } on success; { ok:false, error:"..." } on failure
 */

header('Content-Type: application/json; charset=utf-8');

// ─────────────────────────────────────────────────────────────────────────────
// CORS: only allow your live origins
// ─────────────────────────────────────────────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = ['https://www.conram.it', 'https://conram.it'];
if (!in_array($origin, $allowed_origins, true)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'forbidden_origin']);
  exit;
}
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');

// ─────────────────────────────────────────────────────────────────────────────
/** Rate limit: per-IP sliding window (10 posts / 30 min) */
// ─────────────────────────────────────────────────────────────────────────────
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$now = time();
$rlf = sys_get_temp_dir() . '/svc_form_rl_' . md5($ip);
$win = 1800; // 30 minutes
$lim = 10;   // max hits in window
$hits = @json_decode(@file_get_contents($rlf), true) ?: [];
// keep only hits inside the window
$filtered = [];
foreach ($hits as $t) { if (($now - (int)$t) < $win) { $filtered[] = (int)$t; } }
$hits = $filtered;
if (count($hits) >= $lim) {
  http_response_code(429);
  echo json_encode(['ok' => false, 'error' => 'rate_limited']);
  exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers (sanitization & policy)
// ─────────────────────────────────────────────────────────────────────────────

/** Strip ASCII control chars (except LF/CR we re-map later) */
function strip_controls(string $s): string {
  return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
}

/** Prevent header injection via CR/LF in header fields */
function no_crlf(string $s): string {
  return str_replace(["\r", "\n"], ' ', $s);
}

/** Single-line field cleaner; trims, collapses whitespace, length caps */
function sanitize_line(string $s, int $max = 160): string {
  $s = trim(strip_controls($s));
  $s = preg_replace('/\s{2,}/u', ' ', $s) ?? $s;
  return mb_substr($s, 0, $max);
}

/** Multi-line text cleaner; trims, strips controls, wraps overlong “words” */
function sanitize_textblock(string $s, int $max = 4000): string {
  $s = trim(strip_controls($s));
  // Wrap extremely long unbroken sequences to avoid filters/abuse patterns
  $s = preg_replace_callback('/\S{200,}/u', static function($m) {
    return wordwrap($m[0], 80, ' ', true);
  }, $s) ?? $s;
  return mb_substr($s, 0, $max);
}

/** Keep only digits/space/+ - ( ) / ; collapse multi-spaces */
function sanitize_phone(string $s): string {
  $s = preg_replace('/[^\d\+\-\s\(\)\/]/u', '', $s) ?? '';
  return trim(preg_replace('/\s{2,}/u', ' ', $s));
}

/**
 * Allow extended Latin letters, marks, numbers, spaces, and common safe punctuation.
 * Blocks emoji/pictographs and unusual symbols.
 *   Allowed punct: - _ . , : ; ! ? ( ) ' " / &
 */
function is_safe_line_unicode(string $s): bool {
  return (bool)preg_match('/^[\p{L}\p{M}\p{N}\s\-\_\.\,\:\;\!\?\(\)\'"\/&]+$/u', $s);
}

/** Emoji/pictograph/pictorial ranges (broad but pragmatic) */
function contains_emoji(string $s): bool {
  return (bool)preg_match('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}]/u', $s);
}

// ─────────────────────────────────────────────────────────────────────────────
// Read POST fields (keep front-end flexible for the name field)
// ─────────────────────────────────────────────────────────────────────────────
$name     = sanitize_line($_POST['name'] ?? $_POST['fullname'] ?? $_POST['your-name'] ?? '', 120);
$email    = trim($_POST['email'] ?? '');
$subject  = sanitize_line($_POST['subject'] ?? '', 140);
$phoneRaw = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
$phone    = $phoneRaw; // keep raw for validation first
$message  = sanitize_textblock($_POST['message'] ?? '', 4000);
$website  = trim($_POST['website'] ?? '');              // honeypot
$key      = trim($_POST['form_key'] ?? '');             // anti-CSRF token (static)
$siteTag  = sanitize_line($_POST['site_tag'] ?? '', 60);
$renderTs = isset($_POST['render_ts']) ? (int)$_POST['render_ts'] : 0; // ms since epoch

// ─────────────────────────────────────────────────────────────────────────────
// Honeypot / token / time-trap
// ─────────────────────────────────────────────────────────────────────────────
if ($website !== '') {
  // Silent success for bots: don't leak that the honeypot exists
  echo json_encode(['ok' => true]);
  exit;
}
if ($key !== 'conram_v1_2025_09') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'bad_key']);
  exit;
}
// time-trap: require at least ~2s from render to submit (bots submit instantly)
if ($renderTs > 0) {
  $elapsedMs = (int)(microtime(true) * 1000) - $renderTs;
  if ($elapsedMs < 2000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'too_fast']);
    exit;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Required-field validation
// ─────────────────────────────────────────────────────────────────────────────
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $subject === '' || $message === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'validation']);
  exit;
}

// Charset/emoji policy for Name & Subject (allow å/ä/ö/ø/æ/é etc., block emojis)
if (!is_safe_line_unicode($name)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'name_charset']);
  exit;
}
if (!is_safe_line_unicode($subject)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'subject_charset']);
  exit;
}

// Message: optionally block emojis (you already strip controls in sanitize_textblock)
if (contains_emoji($message)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'message_emoji_blocked']);
  exit;
}

// Phone (optional):
// Allow ONLY digits, space, +, -, parentheses, slash; total length 6–32.
// We validate the RAW input first to catch any letters/dots/etc.
if ($phone !== '') {
  // Reject if any disallowed char is present (letters, dots, etc.)
  if (!preg_match('/^\+?[0-9\s\-\(\)\/]{6,32}$/u', $phone)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'phone_invalid']);
    exit;
  }
  // Optional: reject explicitly if any letter sneaked in (belt & suspenders)
  if (preg_match('/[A-Za-z]/', $phone)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'phone_invalid']);
    exit;
  }
  // Now normalize (light): collapse whitespace
  $phone = preg_replace('/\s+/u', ' ', trim($phone));
}


// Optional: block links in message (uncomment to enforce)
// if (preg_match('/https?:\/\/|www\./i', $message)) {
//   http_response_code(400);
//   echo json_encode(['ok' => false, 'error' => 'links_not_allowed']);
//   exit;
// }

// ─────────────────────────────────────────────────────────────────────────────
// Compose and send
// ─────────────────────────────────────────────────────────────────────────────
$to      = 'rikard.malmborg@conram.it';    // ← updated recipient
$from    = 'form-engine@conram.it';        // DMARC-aligned envelope & From
$tag     = $siteTag !== '' ? $siteTag : '[Conram.it Contact]';
$subj    = no_crlf($tag . ' ' . $subject); // guard against header injection

$body  = "Name: {$name}\n";
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
$headers_str = implode("\r\n", $headers);

// Use sendmail with -f to keep SPF/DMARC aligned
$ok = @mail($to, $subj, $body, $headers_str, "-f {$from}");

if ($ok) {
  $hits[] = $now;
  @file_put_contents($rlf, json_encode($hits), LOCK_EX);
  echo json_encode(['ok' => true]);
} else {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'send_failed']);
}
