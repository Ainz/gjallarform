<?php
/**
 * Relmin — minimal contact form handler (PRG + PHP sendmail)
 * © 2025–present Conram.it. All rights reserved.
 * SPDX-License-Identifier: GPL-3.0-or-later
 * https://www.conram.it
 */
declare(strict_types=1);

# ============================================================================
# CONFIG — edit ONLY this block (no need to touch anything below)
# ============================================================================
$CFG = [
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
  'formKey'     => 'yoursite-random123',        // Must match hidden form field ('' disables)
  
  // Locale / anti-abuse by time limitations and submissions per time
'timeTrapEnabled' => true,   // turn the time-trap on/off
'timeTrapMinMs'   => 2000,   // minimum render→submit time in milliseconds
'timeTrapGraceMs' => 50,     // optional jitter allowance to avoid edge false-positives

  // Math challenge (Standard tier spam defense)
  'math_challenge' => true,    // Enable simple math verification question
];

# ============================================================================
# DO NOT EDIT BELOW THIS LINE
# ============================================================================

/** Fail fast on non-POST */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

/** Base URL helpers (thank-you + back URLs derived from config) */

/**
 * Detects the HTTP scheme (http or https) for the current request.
 *
 * Checks if HTTPS is enabled via the $_SERVER['HTTPS'] variable. If HTTPS is
 * detected and not 'off', returns 'https'. Otherwise, defaults to 'https' as
 * the safest option for public-facing forms to avoid mixed-content warnings.
 *
 * @return string Either 'https' (always https for maximum security)
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
  $page = $CFG['thankYouPage'] ?? 'thankyou.html';
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
  $page = $CFG['contactPage'] ?? 'contact.html';
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
$math_index  = (int)($_POST['math_index'] ?? 0);

/** Math challenge questions (simple arithmetic) */
$math_questions = [
  ['question' => 'What is 5 + 3?', 'answer' => '8'],
  ['question' => 'What is 10 - 4?', 'answer' => '6'],
  ['question' => 'What is 6 × 2?', 'answer' => '12'],
  ['question' => 'What is 15 ÷ 3?', 'answer' => '5'],
  ['question' => 'What is 7 + 8?', 'answer' => '15'],
  ['question' => 'What is 20 - 11?', 'answer' => '9'],
  ['question' => 'What is 4 × 3?', 'answer' => '12'],
];

/**
 * Selects a math question deterministically based on form key.
 * Same form key = same question for consistency.
 *
 * @param array $questions Array of math question/answer pairs
 * @param string $formKey The form key to use as seed
 * @return int The index of the question to use
 */
function get_math_question_index(array $questions, string $formKey): int {
  if (empty($formKey)) {
    // If no form key, use a random selection
    return mt_rand(0, count($questions) - 1);
  }
  // Use CRC32 hash of form key to deterministically select question
  $hash = crc32($formKey);
  return abs($hash) % count($questions);
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
  // Validate that the question index is within bounds
  if ($math_index < 0 || $math_index >= count($math_questions)) {
    back_with_err('math_invalid');
  }

  // Get the expected answer for this question
  $expected_answer = $math_questions[$math_index]['answer'];

  // Case-insensitive comparison, trim whitespace
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
$tz   = new DateTimeZone($CFG['timezone'] ?: 'UTC');
$when = (new DateTime('now', $tz))->format('Y-m-d H:i T');
$ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$ref  = strtoupper(substr(md5(uniqid('', true)), 0, 6));               // short reference
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
$first = trim($name) !== '' ? preg_split('/\s+/', trim($name))[0] : 'there';
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
$confirmHeaders[] = 'Auto-Submitted: auto-replied';
$confirmHeaders[] = 'Precedence: auto-reply';
$confirmHeaders[] = 'X-Auto-Response-Suppress: All';
$confirmHeaders_str = implode("\r\n", $confirmHeaders);
@mail($email, $confirmSubject, $confirmBody, $confirmHeaders_str, $envelope);

/** Final PRG redirect */
header('Location: ' . thank_you_url(), true, 303);
exit;
