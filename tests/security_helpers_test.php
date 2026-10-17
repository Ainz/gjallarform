<?php
declare(strict_types=1);

define('GJALLARFORM_TEST_MODE', true);
require dirname(__DIR__) . '/gjallarform/gjallarform.php';

$failures = 0;
function check(bool $condition, string $message): void {
  global $failures;
  if ($condition) {
    echo "PASS: {$message}\n";
  } else {
    fwrite(STDERR, "FAIL: {$message}\n");
    $failures++;
  }
}

$testConfig = $CFG;
$testConfig['formSecret'] = str_repeat('a1', 32);
$testConfig['challengeTtlSec'] = 120;

$issuedAt = 1_700_000_000_000;
$challenge = gjf_create_challenge_token($testConfig, count($math_questions), $issuedAt);
$valid = gjf_verify_challenge_token($challenge['token'], $testConfig, count($math_questions), $issuedAt + 2_000);
check($valid['ok'] === true, 'a signed challenge verifies');
check($valid['payload']['iat'] === $issuedAt, 'the server-issued timestamp is preserved');
check($valid['payload']['q'] === $challenge['questionIndex'], 'the signed question index is preserved');

$last = substr($challenge['token'], -1);
$tampered = substr($challenge['token'], 0, -1) . ($last === 'A' ? 'B' : 'A');
$invalid = gjf_verify_challenge_token($tampered, $testConfig, count($math_questions), $issuedAt + 2_000);
check($invalid['ok'] === false && $invalid['reason'] === 'signature', 'a modified token is rejected');

$expired = gjf_verify_challenge_token($challenge['token'], $testConfig, count($math_questions), $issuedAt + 121_000);
check($expired['ok'] === false && $expired['reason'] === 'expired', 'an expired token is rejected');

$futureChallenge = gjf_create_challenge_token($testConfig, count($math_questions), $issuedAt + 31_000);
$future = gjf_verify_challenge_token($futureChallenge['token'], $testConfig, count($math_questions), $issuedAt);
check($future['ok'] === false && $future['reason'] === 'future', 'a token issued too far in the future is rejected');

$placeholderConfig = $testConfig;
$placeholderConfig['formSecret'] = 'replace-with-at-least-32-random-characters';
check(gjf_secret_is_configured($placeholderConfig) === false, 'the shipped placeholder cannot enable the form');

$rateDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gjallarform-test-' . bin2hex(random_bytes(6));
$rateConfig = $testConfig;
$rateConfig['rateLimitEnabled'] = true;
$rateConfig['rateLimitMaxAttempts'] = 2;
$rateConfig['rateLimitWindowSec'] = 60;
$rateConfig['rateLimitStorageDir'] = $rateDir;
$first = gjf_rate_limit_check($rateConfig, '192.0.2.42', 1_000);
$second = gjf_rate_limit_check($rateConfig, '192.0.2.42', 1_001);
$blocked = gjf_rate_limit_check($rateConfig, '192.0.2.42', 1_002);
$reset = gjf_rate_limit_check($rateConfig, '192.0.2.42', 1_060);
check($first['allowed'] && $second['allowed'], 'requests inside the configured limit are allowed');
check(!$blocked['allowed'] && $blocked['retryAfter'] === 58, 'the next request is blocked with a retry interval');
check($reset['allowed'], 'the limiter resets after its window');

$files = glob($rateDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
check(count($files) === 1 && !str_contains(basename($files[0]), '192.0.2.42'), 'rate-limit filenames do not expose raw IP addresses');
foreach ($files as $file) @unlink($file);
@rmdir($rateDir);

exit($failures === 0 ? 0 : 1);
