<?php
/**
 * Contactulus — minimal contact form handler (PRG + mail)
 * © 2025–present Conram.it. All rights reserved.
 * SPDX-License-Identifier: Proprietary
 * https://www.conram.it
 */
declare(strict_types=1);

// Minimal Contactulus — fallback build (keep it simple, keep it working)

// --- CONFIG (edit these 3 if needed) ---
$to        = 'rikard.malmborg@conram.it';   // where the message goes
$from      = 'form-engine@conram.it';       // must be on your domain, can be the same as $to 
$siteName  = 'www.conram.it';                      // used in subject/headers in the sent mail
// ----------------------------------------

// Method gate
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

// Build absolute root URL for PRG redirect
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'www.conram.it';
$baseUrl = $scheme . '://' . $host;
function thank_you_url($baseUrl) { return rtrim($baseUrl, '/') . '/thank-you.html'; } // This is the thankyou page URL that shows up after a correct form submission.
function back_url($baseUrl)      { return rtrim($baseUrl, '/') . '/contact-form.html'; } // This is the URL of the contact form itself.
function back_with_err($baseUrl, $code) {
  $b = back_url($baseUrl);
  header('Location: ' . $b . (strpos($b,'?')!==false?'&':'?') . 'err=' . urlencode($code), true, 303);
  exit;
}

// Read inputs (flat, no fancy sanitizers)
$name     = isset($_POST['fullname']) ? trim($_POST['fullname']) : (isset($_POST['name']) ? trim($_POST['name']) : '');
$email    = isset($_POST['email'])    ? trim($_POST['email'])    : '';
$subject  = isset($_POST['subject'])  ? trim($_POST['subject'])  : '';
$phone    = isset($_POST['phone'])    ? trim($_POST['phone'])    : '';
$message  = isset($_POST['message'])  ? trim($_POST['message'])  : '';
$website  = isset($_POST['website'])  ? trim($_POST['website'])  : '';
$key      = isset($_POST['form_key']) ? trim($_POST['form_key']) : '';
$rts      = isset($_POST['render_ts'])? (int)$_POST['render_ts'] : 0;

// Honeypot = silent success
if ($website !== '') {
  header('Location: ' . thank_you_url($baseUrl), true, 303);
  exit;
}

// Light anti-abuse (match your form key & a 2s time trap if present)
if ($key !== '' && $key !== 'conram_v1_2025_09') back_with_err($baseUrl, 'validation');
if ($rts > 0) {
  $elapsed = (int)(microtime(true)*1000) - $rts;
  if ($elapsed < 2000) back_with_err($baseUrl, 'too_fast');
}

// Minimal validation
if ($name === '' || $email === '' || $message === '') back_with_err($baseUrl, 'validation');
// ASCII-ish email (browser already checks; this is a simple guard)
if (!preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $email)) {
  back_with_err($baseUrl, 'validation');
}
if ($subject === '') $subject = $siteName . ' Contact';

// Compose
$ref = strtoupper(substr(md5(uniqid('', true)), 0, 6));
$tag = '[' . $siteName . ' Contact]';
$subj = $tag . ' [' . $ref . '] ' . $subject;

$body  = "Reference: {$ref}\n";
$body .= "Name: {$name}\n";
$body .= "Email: {$email}\n";
$body .= "Phone: " . ($phone !== '' ? $phone : '—') . "\n";
$body .= "IP: " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0') . "\n\n";
$body .= "Message:\n{$message}\n";

// Headers for ADMIN mail (no -f param in headers; use envelope via 5th arg)
$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'From: ' . $siteName . ' Forms <' . $from . '>';
$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
$headers_str = implode("\r\n", $headers);

// Set envelope sender (Return-Path) to our domain for SPF/DMARC alignment
$envelope = "-f {$from}";

// ── Send ADMIN mail (hard requirement). If it fails, bounce back.
@$ok = mail($to, $subj, $body, $headers_str, $envelope);
if (!$ok) back_with_err($baseUrl, 'send_failed');

// ───────── Confirmation to submitter (best-effort; structured copy) ─────────
$brandLabel = 'Conram';                  // human brand shown to the user
$siteUrl    = 'https://www.conram.it';   // canonical site URL

// Local timestamp (Europe/Stockholm)
$tz = new DateTimeZone('Europe/Stockholm');
$ts = (new DateTime('now', $tz))->format('Y-m-d H:i T');

$firstName = trim($name) !== '' ? preg_split('/\s+/', trim($name))[0] : 'there';

// Subject with brand + reference
$confirmSubject = "Copy of your message — {$brandLabel} [{$ref}]";

// Well-ordered receipt body
$confirmBody =
  "Hi {$firstName},\n\n" .
  "We’ve received your message to {$brandLabel}. Below is a copy for your records.\n\n" .

  "— Summary —\n" .
  "Reference: {$ref}\n" .
  "Received:  {$ts}\n" .
  "Subject:   {$subject}\n" .
  "From:      {$name}\n" .
  "Email:     {$email}\n" .
  "Phone:     " . ($phone !== '' ? $phone : '—') . "\n\n" .

  "--- Your message ---\n" .
  "{$message}\n\n" .

  "If you didn’t submit this, you can ignore this email.\n\n" .
  "— {$brandLabel}\n{$siteUrl}\n";

// Separate headers for the confirmation mail (unchanged pattern)
$confirmHeaders = [];
$confirmHeaders[] = 'MIME-Version: 1.0';
$confirmHeaders[] = 'Content-Type: text/plain; charset=UTF-8';
$confirmHeaders[] = 'From: ' . $siteName . ' Forms <' . $from . '>';
$confirmHeaders[] = 'Reply-To: ' . $siteName . ' <' . $to . '>'; // replies go to site inbox
$confirmHeaders[] = 'Auto-Submitted: auto-replied';
$confirmHeaders[] = 'Precedence: auto-reply';
$confirmHeaders[] = 'X-Auto-Response-Suppress: All';
$confirmHeaders_str = implode("\r\n", $confirmHeaders);

// Send (best-effort); uses same $envelope = "-f {$from}";
@mail($email, $confirmSubject, $confirmBody, $confirmHeaders_str, $envelope);

// Final PRG redirect
header('Location: ' . thank_you_url($baseUrl), true, 303);
exit;
