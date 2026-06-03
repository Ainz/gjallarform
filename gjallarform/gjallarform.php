<?php
/*-------------------------------------------------------+
| Gjallarform - Contact Form Solution
| Copyright (C) 2025 Rikard Malmborg / CONRAM.IT
| https://conram.it/
+--------------------------------------------------------+
| Filename: gjallarform.php
| Version:  0.95
| Author:   Rikard Malmborg
+--------------------------------------------------------+
| This program is free software released under the
| GNU General Public License v3.0 (GPL-3.0).
| https://www.gnu.org/licenses/gpl-3.0.html
| https://github.com/Ainz/gjallarform
+--------------------------------------------------------*/
declare(strict_types=1);

# ============================================================================
# CONFIG — edit ONLY this block (no need to touch anything below)
# ============================================================================
$CFG = [
  // Emergency kill switch
  'form_disabled' => true,  // Set to false to re-enable form

  // Mail routing / deliverability
  'to'          => 'admin@yoursite.tld',        // Where the mail ultimately gets sent (required)
  'from'        => 'form-engine@yoursite.tld',  // Must be on your domain, can be a custom e-mail, but also the same as the "to" e-mail
  'fromDisplay' => 'YourSite contact form',     // Display name for From:
  'replyDisplay'=> 'YourSite',                  // Display name for Reply-To: (admin mailbox)

  // Branding / site
  'siteName'    => 'YourSite',                  // Used in subjects and labels
  'siteUrl'     => 'https://www.yoursite.tld',  // Leave '' to auto-detect (uses httpHost)
  'httpHost'    => 'www.yoursite.tld',          // Fallback host if siteUrl is blank
  'contactPage' => 'contact.html',              // Contact form page filename (can include path like 'forms/contact.html')
  'thankYouPage'=> 'thankyou.html',             // Thank you page filename (can include path like 'forms/thankyou.html')

  // Locale / anti-abuse
  'timezone'    => 'UTC',                       // Used for timestamps in receipts
  'formKey'     => 'yoursite-change-this-to-a-long-random-secret', // Fixed secret string — must match the hidden form_key field in HTML. Keep private. ('' disables)
  
  // Time trap (anti-abuse by submission speed)
  'timeTrapEnabled' => true,   // turn the time-trap on/off
  'timeTrapMinMs'   => 2000,   // minimum render→submit time in milliseconds
  'timeTrapGraceMs' => 50,     // optional jitter allowance to avoid edge false-positives

  // Math challenge (Standard tier spam defense)
  'math_challenge' => true,    // Enable simple math verification question
];

# ============================================================================
# MATH CHALLENGE QUESTIONS — any edit here must be mirrored in gjallarform.js
# ============================================================================
$math_questions = [
  ['question' => 'What is 5 + 3?',   'answer' => '8'],
  ['question' => 'What is 10 - 4?',  'answer' => '6'],
  ['question' => 'What is 6 × 2?',   'answer' => '12'],
  ['question' => 'What is 15 ÷ 3?',  'answer' => '5'],
  ['question' => 'What is 7 + 8?',   'answer' => '15'],
  ['question' => 'What is 20 - 11?', 'answer' => '9'],
  ['question' => 'What is 4 × 3?',   'answer' => '12'],
  ['question' => 'What is 9 + 6?',   'answer' => '15'],
  ['question' => 'What is 13 - 5?',  'answer' => '8'],
  ['question' => 'What is 3 × 7?',   'answer' => '21'],
  ['question' => 'What is 8 + 4?',   'answer' => '12'],
  ['question' => 'What is 17 - 9?',  'answer' => '8'],
  ['question' => 'What is 5 × 4?',   'answer' => '20'],
  ['question' => 'What is 6 + 9?',   'answer' => '15'],
  ['question' => 'What is 18 - 7?',  'answer' => '11'],
];

# ============================================================================
# EMERGENCY KILL SWITCH CHECK
# ============================================================================
if ($CFG['form_disabled'] ?? false) {
    http_response_code(503);
    exit('Contact form temporarily disabled due to spam protection. Please try again later.');
}

# ============================================================================
# No changes are normally needed below this line
# ============================================================================

/** Fail fast on non-POST */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

/** Base URL helpers (thank-you + back URLs derived from config) */

/**
 * Returns the HTTP scheme for URL construction.
 *
 * Always returns 'https'. The conditional on $_SERVER['HTTPS'] is retained for
 * explicitness but both branches produce 'https' — public-facing forms are
 * always served over HTTPS to avoid mixed-content warnings.
 *
 * @return string Always 'https'
 */
function detect_scheme(): string {
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return 'https';
  return 'https'; // safest public default
}
/**
 * Computes the base URL for the site from configuration.
 *
 * Attempts to construct the site's base URL using the following priority:
 * 1. Uses $CFG['siteUrl'] if explicitly set (most secure)
 * 2. Falls back to $CFG['httpHost'] with detected scheme
 *
 * Security note: This function deliberately does NOT use $_SERVER['HTTP_HOST']
 * to prevent host header injection attacks. Either siteUrl or httpHost must
 * be explicitly configured in $CFG.
 *
 * @param array $CFG Configuration array containing siteUrl and httpHost
 * @return string The base URL without trailing slash (e.g., 'https://example.com')
 * @throws void Exits with HTTP 500 if neither siteUrl nor httpHost is configured
 */
function compute_base_url(array $CFG): string {
  if (!empty($CFG['siteUrl'])) return rtrim($CFG['siteUrl'], '/');
  // Security: Never trust HTTP_HOST header - require explicit configuration
  if (empty($CFG['httpHost'])) {
    http_response_code(500);
    exit('Configuration error: siteUrl or httpHost must be set');
  }
  return detect_scheme() . '://' . $CFG['httpHost'];
}
$BASE_URL = compute_base_url($CFG);

// Strip CR/LF from every $CFG value that will appear in an email header.
// $subject gets the same treatment later (see subject sanitization below);
// doing these here ensures siteName, fromDisplay, and replyDisplay are clean
// before any use downstream.
foreach (['siteName', 'fromDisplay', 'replyDisplay'] as $_hdrKey) {
  if (isset($CFG[$_hdrKey])) {
    $CFG[$_hdrKey] = preg_replace('/[\r\n]+/', ' ', $CFG[$_hdrKey]);
  }
}
unset($_hdrKey);

/**
 * Returns the computed base URL for the site.
 *
 * This is a convenience wrapper around the global $BASE_URL variable which
 * is computed once during script initialization. Avoids re-computation on
 * every call.
 *
 * @return string The base URL (e.g., 'https://example.com')
 */
function base_url(): string      { global $BASE_URL; return $BASE_URL; }

/**
 * Returns the full URL to the thank you page.
 *
 * Used for redirecting after successful form submission. Points to the
 * configured thank you page where users see confirmation and submission
 * details rendered by JavaScript.
 *
 * The page filename is configurable via $CFG['thankYouPage'], allowing
 * users to customize the filename or path without modifying code.
 *
 * @return string Full URL to thank you page (e.g., 'https://example.com/thankyou.html')
 */
function thank_you_url(): string {
  global $CFG;
  $page = $CFG['thankYouPage'];
  return base_url() . '/' . ltrim($page, '/');
}

/**
 * Returns the full URL to the contact form page.
 *
 * Used for redirecting back to the form when validation fails or errors occur.
 * Points to the configured contact form page.
 *
 * The page filename is configurable via $CFG['contactPage'], allowing
 * users to customize the filename or path without modifying code.
 *
 * @return string Full URL to contact page (e.g., 'https://example.com/contact.html')
 */
function back_url(): string      {
  global $CFG;
  $page = $CFG['contactPage'];
  return base_url() . '/' . ltrim($page, '/');
}
/**
 * Redirects back to the contact form with an error code in the query string.
 *
 * Performs a POST-Redirect-GET (PRG) pattern redirect to the contact form,
 * appending an error code parameter that JavaScript can detect to:
 * - Display appropriate error messages to the user
 * - Restore draft form data from sessionStorage
 * - Focus the problematic field
 *
 * Uses HTTP 303 (See Other) status code to ensure the browser makes a GET
 * request, preventing form resubmission on page refresh.
 *
 * @param string $code Error code to pass (e.g., 'email_invalid', 'too_fast', 'name_missing')
 * @return void Exits script execution after sending redirect header
 */
function back_with_err(string $code): void {
  $b = back_url();
  $sep = (strpos($b, '?') !== false) ? '&' : '?';
  header('Location: ' . $b . $sep . 'err=' . rawurlencode($code), true, 303);
  exit;
}

/** Read inputs (flat, predictable) */
$name     = isset($_POST['fullname']) ? trim((string)$_POST['fullname'])
         : (isset($_POST['name']) ? trim((string)$_POST['name']) : '');
$email    = trim((string)($_POST['email']    ?? ''));
$subject  = trim((string)($_POST['subject']  ?? ''));
$message  = trim((string)($_POST['message']  ?? ''));
$website  = trim((string)($_POST['website']  ?? ''));  // honeypot
$key      = trim((string)($_POST['form_key'] ?? ''));
$rts      = (int)($_POST['render_ts'] ?? 0);
$math_answer = trim((string)($_POST['math_answer'] ?? ''));

/**
 * Selects a math question index deterministically from the form key and host.
 *
 * Seeds from formKey + HTTP_HOST so two installations with the same formKey
 * but different domains land on different questions, reducing collision
 * probability across sites. Falls back to random selection when formKey is
 * absent (formKey disabled in config).
 *
 * @param array  $questions Array of math question/answer pairs
 * @param string $formKey   The site's configured form key
 * @return int Index into $questions
 */
function get_math_question_index(array $questions, string $formKey): int {
  if (empty($formKey)) {
    return random_int(0, count($questions) - 1);
  }
  $host = $_SERVER['HTTP_HOST'] ?? '';
  $seed = hexdec(substr(md5($formKey . $host), 0, 8));
  return (int)($seed % count($questions));
}

/** Honeypot: silent success (looks successful to bots, no mail sent) */
if ($website !== '') {
  header('Location: ' . thank_you_url(), true, 303);
  exit;
}

/** Optional CSRF-like shared secret */
if (!empty($CFG['formKey']) && !hash_equals($CFG['formKey'], $key)) back_with_err('validation');

/** Math challenge validation (Standard tier spam defense) */
if (!empty($CFG['math_challenge'])) {
  // When formKey is set, derive the expected question index server-side so the
  // client cannot choose a different (potentially easier) question by sending
  // an arbitrary math_index. Without formKey, fall back to the client-reported
  // index so the form still works when the key is intentionally left blank.
  if (!empty($CFG['formKey'])) {
    $math_index = get_math_question_index($math_questions, $CFG['formKey']);
  } else {
    $math_index = (int)($_POST['math_index'] ?? 0);
  }

  if ($math_index < 0 || $math_index >= count($math_questions)) {
    back_with_err('math_invalid');
  }

  $expected_answer = $math_questions[$math_index]['answer'];

  if (strcasecmp(trim($math_answer), $expected_answer) !== 0) {
    back_with_err('math_wrong');
  }
}

/** Render-time trap (basic bot throttle): require >= MinMs (CFG) */
if (!empty($CFG['timeTrapEnabled']) && $rts > 0) {
  // Accept both ms and s inputs; convert seconds to ms if it "looks" short.
  if ($rts < 1_000_000_000_000) { // < ~2001-09-09 in ms → treat as seconds
    $rts *= 1000;
  }
  $elapsed = (int)(microtime(true) * 1000) - $rts;
  $min = (int)($CFG['timeTrapMinMs'] ?? 2000);
  $grace = (int)($CFG['timeTrapGraceMs'] ?? 0);

  if ($elapsed + $grace < $min) {
    back_with_err('too_fast');
  }
}

/** Field presence */
if ($name === '')    back_with_err('name_missing');
if ($email === '')   back_with_err('email_missing');
if ($message === '') back_with_err('message_missing');

/** Field length limits (server-side safety net; HTML maxlength mirrors these) */
if (strlen($name)    > 200)   back_with_err('name_too_long');
if (strlen($email)   > 254)   back_with_err('email_too_long');    // RFC 5321 max
if (strlen($subject) > 300)   back_with_err('subject_too_long');
if (strlen($message) > 10000) back_with_err('message_too_long');

/** Email validation (ASCII only, single "@", sane shape) */
if (preg_match('/[^\x00-\x7F]/', $email))                back_with_err('email_ascii_only');
if (substr_count($email, '@') !== 1)                     back_with_err('email_invalid_at');
if (!preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $email))
  back_with_err('email_invalid');

/** Subject default & sanitization */
if ($subject === '') $subject = $CFG['siteName'] . ' Contact';
// Security: Strip newlines to prevent email header injection
$subject = preg_replace('/[\r\n]+/', ' ', $subject);

/** Compose */
// DateTimeZone throws on invalid timezone strings; fall back to UTC so a
// misconfigured 'timezone' value never produces an unhandled fatal error.
try {
  $tz = new DateTimeZone($CFG['timezone'] ?: 'UTC');
} catch (\Exception $e) {
  $tz = new DateTimeZone('UTC');
}
$when = (new DateTime('now', $tz))->format('Y-m-d H:i T');
$ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$ref  = strtoupper(bin2hex(random_bytes(3)));  // 6-char hex ref via CSPRNG
$tag  = '[' . $CFG['siteName'] . ' Contact]';
$subj = $tag . ' [' . $ref . '] ' . $subject;

$adminBody  = "Reference: {$ref}\n";
$adminBody .= "Received: {$when}\n";
$adminBody .= "IP:       {$ip}\n\n";
$adminBody .= "From:     {$name}\n";
$adminBody .= "Email:    {$email}\n\n";
$adminBody .= "Subject:  {$subject}\n";
$adminBody .= "Message:\n{$message}\n";

/** Headers (admin) */
$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'From: ' . $CFG['fromDisplay']  . ' <' . $CFG['from'] . '>';
$headers[] = 'Reply-To: ' . $CFG['replyDisplay'] . ' <' . $CFG['to']   . '>';
$headers_str = implode("\r\n", $headers);

/** Envelope sender for SPF/DMARC alignment */
$envelope = '-f ' . $CFG['from'];

/** Send admin mail (required) */
$ok = @mail($CFG['to'], $subj, $adminBody, $headers_str, $envelope);
if (!$ok) back_with_err('send_failed');

/** Confirmation to submitter (best-effort) */
// preg_split() returns false on error; guard against TypeError on false[0].
$_parts = preg_split('/\s+/', $name);
$first  = ($_parts !== false && isset($_parts[0]) && $_parts[0] !== '') ? $_parts[0] : 'there';
unset($_parts);
$confirmSubject = "Copy of your message — {$CFG['siteName']} [{$ref}]";
$confirmBody =
  "Hi {$first},\n\n" .
  "We’ve received your message to {$CFG['siteName']}. Below is a copy for your records.\n\n" .
  "— Summary —\n" .
  "Reference: {$ref}\n" .
  "Received:  {$when}\n" .
  "Subject:   {$subject}\n" .
  "From:      {$name}\n" .
  "Email:     {$email}\n\n" .
  "--- Your message ---\n" .
  "{$message}\n\n" .
  "If you didn’t submit this, you can ignore this email.\n\n" .
  "— {$CFG['siteName']}\n" .
  ($CFG['siteUrl'] ? $CFG['siteUrl'] . "\n" : '');

$confirmHeaders = [];
$confirmHeaders[] = 'MIME-Version: 1.0';
$confirmHeaders[] = 'Content-Type: text/plain; charset=UTF-8';
$confirmHeaders[] = 'From: ' . $CFG['fromDisplay'] . ' <' . $CFG['from'] . '>';
$confirmHeaders[] = 'Reply-To: ' . $CFG['replyDisplay'] . ' <' . $CFG['to'] . '>'; // replies to site inbox
// Prevent auto-responders (vacation replies, mailing lists) from looping back.
$confirmHeaders[] = 'Auto-Submitted: auto-replied';
$confirmHeaders[] = 'Precedence: auto-reply';
$confirmHeaders[] = 'X-Auto-Response-Suppress: All';
$confirmHeaders_str = implode("\r\n", $confirmHeaders);
@mail($email, $confirmSubject, $confirmBody, $confirmHeaders_str, $envelope);

/** Final PRG redirect */
header('Location: ' . thank_you_url(), true, 303);
exit;
