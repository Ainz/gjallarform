<?php
/*-------------------------------------------------------+
| Gjallarform - Contact Form Solution
| Copyright (C) 2025-2026 Rikard Malmborg / CONRAM.IT
| https://conram.it/
+--------------------------------------------------------+
| Filename: gjallarform.php
| Version:  0.96-beta
| Author:   Rikard Malmborg
+--------------------------------------------------------+
| Released under GNU General Public License v3.0
| https://www.gnu.org/licenses/gpl-3.0.html
| https://github.com/Ainz/gjallarform
+--------------------------------------------------------*/
declare(strict_types=1);

# ============================================================================
# CONFIG - edit ONLY this block (no need to touch anything below)
# ============================================================================
$CFG = [
  // Emergency kill switch
  'form_disabled' => true,  // Set to false only after configuration and testing

  // Mail routing / deliverability
  'to'          => 'admin@yoursite.tld',
  'from'        => 'form-engine@yoursite.tld', // Must be on your domain
  'fromDisplay' => 'YourSite contact form',
  'replyDisplay'=> 'YourSite',                 // Used on optional confirmation copies
  'sendConfirmation' => false,                 // Opt in only after rate-limit testing

  // Branding / site
  'siteName'    => 'YourSite',
  'siteUrl'     => 'https://www.yoursite.tld',
  'httpHost'    => 'www.yoursite.tld',         // Fallback when siteUrl is blank
  'contactPage' => 'contact.html',
  'thankYouPage'=> 'thankyou.html',
  'timezone'    => 'UTC',

  // Server-only signing secret. Never put this value in HTML or JavaScript.
  // Generate at least 32 random characters, for example with:
  // php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
  'formSecret' => 'replace-with-at-least-32-random-characters',

  // Signed challenge: protects the question index and server-issued timestamp.
  'challengeTtlSec' => 7200,
  'timeTrapEnabled' => true,
  'timeTrapMinMs'   => 2000,
  'timeTrapGraceMs' => 100,
  'mathChallenge'   => true,

  // Fixed-window, per-IP rate limiting. IPs are stored only as keyed hashes.
  'rateLimitEnabled'     => true,
  'rateLimitMaxAttempts' => 5,
  'rateLimitWindowSec'   => 600,
  'rateLimitStorageDir'  => '', // Empty = system temp/gjallarform-rate-limit

  // Privacy: include the visitor IP in the admin email only when needed and disclosed.
  'includeIpInEmail' => false,
];

$math_questions = [
  ['question' => 'What is 5 + 3?',   'answer' => '8'],
  ['question' => 'What is 10 - 4?',  'answer' => '6'],
  ['question' => 'What is 6 x 2?',   'answer' => '12'],
  ['question' => 'What is 15 / 3?',  'answer' => '5'],
  ['question' => 'What is 7 + 8?',   'answer' => '15'],
  ['question' => 'What is 20 - 11?', 'answer' => '9'],
  ['question' => 'What is 4 x 3?',   'answer' => '12'],
  ['question' => 'What is 9 + 6?',   'answer' => '15'],
  ['question' => 'What is 13 - 5?',  'answer' => '8'],
  ['question' => 'What is 3 x 7?',   'answer' => '21'],
  ['question' => 'What is 8 + 4?',   'answer' => '12'],
  ['question' => 'What is 17 - 9?',  'answer' => '8'],
  ['question' => 'What is 5 x 4?',   'answer' => '20'],
  ['question' => 'What is 6 + 9?',   'answer' => '15'],
  ['question' => 'What is 18 - 7?',  'answer' => '11'],
];

function gjf_base64url_encode(string $value): string {
  return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function gjf_base64url_decode(string $value): string|false {
  if ($value === '' || preg_match('/[^A-Za-z0-9_-]/', $value)) return false;
  $padding = (4 - strlen($value) % 4) % 4;
  return base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);
}

function gjf_secret_is_configured(array $cfg): bool {
  $secret = (string)($cfg['formSecret'] ?? '');
  return strlen($secret) >= 32 && !str_starts_with($secret, 'replace-with-');
}

/** @return array{token:string, questionIndex:int, issuedAtMs:int} */
function gjf_create_challenge_token(array $cfg, int $questionCount, ?int $issuedAtMs = null): array {
  if (!gjf_secret_is_configured($cfg)) {
    throw new RuntimeException('formSecret must contain at least 32 non-placeholder characters');
  }
  if ($questionCount < 1) {
    throw new RuntimeException('At least one math question is required');
  }

  $payload = [
    'iat' => $issuedAtMs ?? (int)floor(microtime(true) * 1000),
    'q' => random_int(0, $questionCount - 1),
    'n' => bin2hex(random_bytes(8)),
  ];
  $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  $body = gjf_base64url_encode($json);
  $signature = hash_hmac('sha256', $body, (string)$cfg['formSecret'], true);

  return [
    'token' => $body . '.' . gjf_base64url_encode($signature),
    'questionIndex' => $payload['q'],
    'issuedAtMs' => $payload['iat'],
  ];
}

/** @return array{ok:bool, reason:string, payload?:array{iat:int,q:int,n:string}} */
function gjf_verify_challenge_token(
  string $token,
  array $cfg,
  int $questionCount,
  ?int $nowMs = null
): array {
  if (!gjf_secret_is_configured($cfg)) return ['ok' => false, 'reason' => 'configuration'];

  $parts = explode('.', $token);
  if (count($parts) !== 2) return ['ok' => false, 'reason' => 'malformed'];
  [$body, $encodedSignature] = $parts;
  $signature = gjf_base64url_decode($encodedSignature);
  $json = gjf_base64url_decode($body);
  if ($signature === false || $json === false) return ['ok' => false, 'reason' => 'malformed'];

  $expected = hash_hmac('sha256', $body, (string)$cfg['formSecret'], true);
  if (!hash_equals($expected, $signature)) return ['ok' => false, 'reason' => 'signature'];

  try {
    $payload = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
  } catch (JsonException $e) {
    return ['ok' => false, 'reason' => 'malformed'];
  }
  if (!is_array($payload)
      || !isset($payload['iat'], $payload['q'], $payload['n'])
      || !is_int($payload['iat'])
      || !is_int($payload['q'])
      || !is_string($payload['n'])
      || !preg_match('/^[a-f0-9]{16}$/', $payload['n'])
      || $payload['q'] < 0
      || $payload['q'] >= $questionCount) {
    return ['ok' => false, 'reason' => 'malformed'];
  }

  $now = $nowMs ?? (int)floor(microtime(true) * 1000);
  if ($payload['iat'] > $now + 30_000) return ['ok' => false, 'reason' => 'future'];
  $ttlMs = max(60, (int)($cfg['challengeTtlSec'] ?? 7200)) * 1000;
  if ($now - $payload['iat'] > $ttlMs) return ['ok' => false, 'reason' => 'expired'];

  return ['ok' => true, 'reason' => 'ok', 'payload' => $payload];
}

/** @return array{allowed:bool,retryAfter:int,storageError:bool} */
function gjf_rate_limit_check(array $cfg, string $clientIp, ?int $now = null): array {
  if (empty($cfg['rateLimitEnabled'])) {
    return ['allowed' => true, 'retryAfter' => 0, 'storageError' => false];
  }

  $window = max(60, (int)($cfg['rateLimitWindowSec'] ?? 600));
  $maximum = max(1, (int)($cfg['rateLimitMaxAttempts'] ?? 5));
  $timestamp = $now ?? time();
  $configuredDir = trim((string)($cfg['rateLimitStorageDir'] ?? ''));
  $directory = $configuredDir !== ''
    ? rtrim($configuredDir, '/\\')
    : rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
      . 'gjallarform-rate-limit-' . substr(hash('sha256', (string)$cfg['formSecret']), 0, 12);

  if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
    error_log('Gjallarform: cannot create rate-limit storage directory');
    return ['allowed' => false, 'retryAfter' => $window, 'storageError' => true];
  }

  $key = hash_hmac('sha256', $clientIp, (string)$cfg['formSecret']);
  $path = $directory . DIRECTORY_SEPARATOR . $key . '.json';
  $handle = @fopen($path, 'c+');
  if ($handle === false || !flock($handle, LOCK_EX)) {
    if (is_resource($handle)) fclose($handle);
    error_log('Gjallarform: cannot lock rate-limit state');
    return ['allowed' => false, 'retryAfter' => $window, 'storageError' => true];
  }

  $raw = stream_get_contents($handle);
  $state = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
  if (!is_array($state)
      || !isset($state['windowStart'], $state['count'])
      || !is_int($state['windowStart'])
      || !is_int($state['count'])
      || $timestamp - $state['windowStart'] >= $window
      || $timestamp < $state['windowStart']) {
    $state = ['windowStart' => $timestamp, 'count' => 0];
  }

  $allowed = $state['count'] < $maximum;
  $storageError = false;
  if ($allowed) {
    $state['count']++;
    rewind($handle);
    ftruncate($handle, 0);
    $encodedState = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $written = fwrite($handle, $encodedState);
    fflush($handle);
    if ($written === false || $written !== strlen($encodedState)) {
      $allowed = false;
      $storageError = true;
      error_log('Gjallarform: cannot write rate-limit state');
    }
  }

  $retryAfter = $allowed ? 0 : max(1, $window - ($timestamp - $state['windowStart']));
  flock($handle, LOCK_UN);
  fclose($handle);
  @chmod($path, 0600);

  // Opportunistically remove stale counters without adding a scheduled task.
  if (random_int(1, 100) === 1) {
    $checked = 0;
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $candidate) {
      if (++$checked > 200) break;
      $modified = @filemtime($candidate);
      if ($modified !== false && $modified < $timestamp - ($window * 2)) @unlink($candidate);
    }
  }

  return ['allowed' => $allowed, 'retryAfter' => $retryAfter, 'storageError' => $storageError];
}

function gjf_post_string(string $name): string {
  $value = $_POST[$name] ?? '';
  return is_string($value) || is_numeric($value) ? trim((string)$value) : '';
}

function gjf_compute_base_url(array $cfg): string {
  if (!empty($cfg['siteUrl'])) return rtrim((string)$cfg['siteUrl'], '/');
  if (empty($cfg['httpHost'])) {
    http_response_code(500);
    exit('Configuration error: siteUrl or httpHost must be set');
  }
  return 'https://' . $cfg['httpHost'];
}

function gjf_page_url(string $baseUrl, string $page): string {
  return $baseUrl . '/' . ltrim($page, '/');
}

function gjf_back_with_error(string $baseUrl, array $cfg, string $code): void {
  $url = gjf_page_url($baseUrl, (string)$cfg['contactPage']);
  $separator = str_contains($url, '?') ? '&' : '?';
  header('Location: ' . $url . $separator . 'err=' . rawurlencode($code), true, 303);
  exit;
}

function gjf_send_challenge(array $cfg, array $questions): void {
  header('Content-Type: application/json; charset=UTF-8');
  header('Cache-Control: no-store, max-age=0');
  header('X-Content-Type-Options: nosniff');

  try {
    $challenge = gjf_create_challenge_token($cfg, count($questions));
  } catch (Throwable $e) {
    error_log('Gjallarform challenge configuration error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'challenge_unavailable']);
    exit;
  }

  $mathEnabled = !empty($cfg['mathChallenge']);
  echo json_encode([
    'token' => $challenge['token'],
    'question' => $mathEnabled ? $questions[$challenge['questionIndex']]['question'] : null,
    'mathEnabled' => $mathEnabled,
    'confirmationEnabled' => !empty($cfg['sendConfirmation']),
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

// Unit tests include this file to exercise helpers without handling a request.
if (defined('GJALLARFORM_TEST_MODE') && GJALLARFORM_TEST_MODE === true) return;

if ($CFG['form_disabled'] ?? false) {
  http_response_code(503);
  header('Retry-After: 3600');
  exit('Contact form temporarily disabled. Please try again later.');
}

if (!gjf_secret_is_configured($CFG)) {
  error_log('Gjallarform: configure a unique formSecret before enabling the form');
  http_response_code(500);
  exit('Contact form configuration error.');
}

$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($requestMethod === 'GET' && isset($_GET['gjf_challenge'])) {
  gjf_send_challenge($CFG, $math_questions);
}
if ($requestMethod !== 'POST') {
  header('Allow: GET, POST');
  http_response_code(405);
  exit('Method Not Allowed');
}

$BASE_URL = gjf_compute_base_url($CFG);
foreach (['siteName', 'fromDisplay', 'replyDisplay'] as $headerKey) {
  if (isset($CFG[$headerKey])) {
    $CFG[$headerKey] = trim((string)preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)$CFG[$headerKey]));
  }
}
$configuredEmailPattern = '/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/';
if (!preg_match($configuredEmailPattern, (string)($CFG['to'] ?? ''))
    || !preg_match($configuredEmailPattern, (string)($CFG['from'] ?? ''))) {
  error_log('Gjallarform: invalid to/from email configuration');
  http_response_code(500);
  exit('Contact form configuration error.');
}

$name = gjf_post_string('fullname');
if ($name === '') $name = gjf_post_string('name');
$email = gjf_post_string('email');
$subject = gjf_post_string('subject');
$message = gjf_post_string('message');
$website = gjf_post_string('website');
$challengeToken = gjf_post_string('challenge_token');
$mathAnswer = gjf_post_string('math_answer');

// Honeypot submissions look successful but never consume rate-limit capacity or send mail.
if ($website !== '') {
  header('Location: ' . gjf_page_url($BASE_URL, (string)$CFG['thankYouPage']), true, 303);
  exit;
}

$challengeRequired = !empty($CFG['timeTrapEnabled']) || !empty($CFG['mathChallenge']);
$challengePayload = null;
if ($challengeRequired) {
  $verification = gjf_verify_challenge_token($challengeToken, $CFG, count($math_questions));
  if (!$verification['ok']) {
    $code = $verification['reason'] === 'expired' ? 'challenge_expired' : 'challenge_invalid';
    gjf_back_with_error($BASE_URL, $CFG, $code);
  }
  $challengePayload = $verification['payload'];
}

if (!empty($CFG['timeTrapEnabled']) && is_array($challengePayload)) {
  $elapsed = (int)floor(microtime(true) * 1000) - $challengePayload['iat'];
  $minimum = max(0, (int)($CFG['timeTrapMinMs'] ?? 2000));
  $grace = max(0, (int)($CFG['timeTrapGraceMs'] ?? 0));
  if ($elapsed + $grace < $minimum) gjf_back_with_error($BASE_URL, $CFG, 'too_fast');
}

if (!empty($CFG['mathChallenge']) && is_array($challengePayload)) {
  $questionIndex = $challengePayload['q'];
  if (!hash_equals($math_questions[$questionIndex]['answer'], $mathAnswer)) {
    gjf_back_with_error($BASE_URL, $CFG, 'math_wrong');
  }
}

if ($name === '') gjf_back_with_error($BASE_URL, $CFG, 'name_missing');
if ($email === '') gjf_back_with_error($BASE_URL, $CFG, 'email_missing');
if ($message === '') gjf_back_with_error($BASE_URL, $CFG, 'message_missing');
if (strlen($name) > 200) gjf_back_with_error($BASE_URL, $CFG, 'name_too_long');
if (strlen($email) > 254) gjf_back_with_error($BASE_URL, $CFG, 'email_too_long');
if (strlen($subject) > 300) gjf_back_with_error($BASE_URL, $CFG, 'subject_too_long');
if (strlen($message) > 10000) gjf_back_with_error($BASE_URL, $CFG, 'message_too_long');
if (preg_match('/[^\x00-\x7F]/', $email)) gjf_back_with_error($BASE_URL, $CFG, 'email_ascii_only');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) gjf_back_with_error($BASE_URL, $CFG, 'email_invalid');

// Count only submissions that have passed anti-abuse and field validation.
$clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rateLimit = gjf_rate_limit_check($CFG, $clientIp);
if (!$rateLimit['allowed']) {
  header('Retry-After: ' . $rateLimit['retryAfter']);
  gjf_back_with_error($BASE_URL, $CFG, 'rate_limited');
}

if ($subject === '') $subject = $CFG['siteName'] . ' Contact';
$subject = trim((string)preg_replace('/[\x00-\x1F\x7F]+/', ' ', $subject));

try {
  $timezone = new DateTimeZone((string)($CFG['timezone'] ?: 'UTC'));
} catch (Exception $e) {
  $timezone = new DateTimeZone('UTC');
}
$when = (new DateTime('now', $timezone))->format('Y-m-d H:i T');
$reference = strtoupper(bin2hex(random_bytes(3)));
$mailSubject = '[' . $CFG['siteName'] . ' Contact] [' . $reference . '] ' . $subject;

$adminBody = "Reference: {$reference}\nReceived: {$when}\n";
if (!empty($CFG['includeIpInEmail'])) $adminBody .= "IP:       {$clientIp}\n";
$adminBody .= "\nFrom:     {$name}\nEmail:    {$email}\n\nSubject:  {$subject}\nMessage:\n{$message}\n";

$adminHeaders = [
  'MIME-Version: 1.0',
  'Content-Type: text/plain; charset=UTF-8',
  'From: ' . $CFG['fromDisplay'] . ' <' . $CFG['from'] . '>',
  'Reply-To: ' . $email,
  'Auto-Submitted: auto-generated',
];
$envelope = '-f ' . $CFG['from'];
$sent = @mail($CFG['to'], $mailSubject, $adminBody, implode("\r\n", $adminHeaders), $envelope);
if (!$sent) gjf_back_with_error($BASE_URL, $CFG, 'send_failed');

if (!empty($CFG['sendConfirmation'])) {
  $parts = preg_split('/\s+/', $name);
  $first = ($parts !== false && isset($parts[0]) && $parts[0] !== '') ? $parts[0] : 'there';
  $confirmationSubject = "Copy of your message - {$CFG['siteName']} [{$reference}]";
  $confirmationBody =
    "Hi {$first},\n\n" .
    "We received your message to {$CFG['siteName']}. Below is a copy for your records.\n\n" .
    "Reference: {$reference}\nReceived: {$when}\nSubject: {$subject}\n\n" .
    "--- Your message ---\n{$message}\n\n" .
    "If you did not submit this, you can ignore this email.\n\n- {$CFG['siteName']}\n" .
    ($CFG['siteUrl'] ? $CFG['siteUrl'] . "\n" : '');
  $confirmationHeaders = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: ' . $CFG['fromDisplay'] . ' <' . $CFG['from'] . '>',
    'Reply-To: ' . $CFG['replyDisplay'] . ' <' . $CFG['to'] . '>',
    'Auto-Submitted: auto-replied',
    'Precedence: auto-reply',
    'X-Auto-Response-Suppress: All',
  ];
  if (!@mail($email, $confirmationSubject, $confirmationBody, implode("\r\n", $confirmationHeaders), $envelope)) {
    error_log('Gjallarform: confirmation copy could not be sent');
  }
}

header('Location: ' . gjf_page_url($BASE_URL, (string)$CFG['thankYouPage']), true, 303);
exit;
