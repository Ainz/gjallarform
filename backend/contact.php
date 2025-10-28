<?php
/**
 * Contactulus — minimal contact form handler (PRG + PHP sendmail)
 * © 2025–present Conram.it. All rights reserved.
 * SPDX-License-Identifier: Proprietary
 * https://www.conram.it
 */
declare(strict_types=1);

# ============================================================================
# CONFIG — edit ONLY this block (no need to touch anything below)
# ============================================================================
$CFG = [
  // Mail routing / deliverability
  'to'          => 'rikard.malmborg@conram.it', // Wger the mail ultimately gets send (required)
  'from'        => 'form-engine@conram.it',     // Must be on your domain , can be a custom e-mail, but also the same as the "to" e-mail
  'fromDisplay' => 'Conram.it contact form',    // Display name for From:
  'replyDisplay'=> 'Conram.it',                 // Display name for Reply-To: (admin mailbox)

  // Branding / site
  'siteName'    => 'Conram.it',                 // Used in subjects and labels
  'siteUrl'     => 'https://www.conram.it',     // Leave '' to auto-detect (uses httpHost)
  'httpHost'    => 'www.conram.it',             // Fallback host if siteUrl is blank

  // Locale / anti-abuse
  'timezone'    => 'Europe/Stockholm',          // Used for timestamps in receipts
  'formKey'     => 'conram-871297',             // Must match hidden form field ('' disables)
  
  // Locale / anti-abuse by time limitations and submissions per time
'timeTrapEnabled' => true,   // turn the time-trap on/off
'timeTrapMinMs'   => 2000,   // minimum render→submit time in milliseconds
'timeTrapGraceMs' => 50,     // optional jitter allowance to avoid edge false-positives
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
function detect_scheme(): string {
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return 'https';
  return 'https'; // safest public default
}
function compute_base_url(array $CFG): string {
  if (!empty($CFG['siteUrl'])) return rtrim($CFG['siteUrl'], '/');
  $host = $CFG['httpHost'] ?: ($_SERVER['HTTP_HOST'] ?? 'localhost');
  return detect_scheme() . '://' . $host;
}
$BASE_URL = compute_base_url($CFG);
function base_url(): string      { global $BASE_URL; return $BASE_URL; }
function thank_you_url(): string { return base_url() . '/thank-you.html'; }
function back_url(): string      { return base_url() . '/contact-form.html'; }
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

/** Honeypot: silent success (looks successful to bots, no mail sent) */
if ($website !== '') {
  header('Location: ' . thank_you_url(), true, 303);
  exit;
}

/** Optional CSRF-like shared secret */
if (!empty($CFG['formKey']) && $key !== $CFG['formKey']) back_with_err('validation');

/** Render-time trap (basic bot throttle): require >= MinMs (CFG) */
$rts = (int)($_POST['render_ts'] ?? 0);
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

/** Subject default */
if ($subject === '') $subject = $CFG['siteName'] . ' Contact';

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
