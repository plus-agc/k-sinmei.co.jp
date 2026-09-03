<?php

require dirname(__DIR__) . '/public/form-security.php';

$now = 1800000000;
$rateDir = sys_get_temp_dir() . '/sinmei-form-security-test-' . getmypid();
mkdir($rateDir, 0700, true);

$validPost = array(
	'_company_website' => '',
	'_form_started_at' => (string)(($now - 10) * 1000),
	'お名前' => '動作確認',
	'所属名' => '株式会社プラス',
	'Email' => 'test@example.com',
	'tel' => '000-0000-0000',
	'お問い合わせ内容' => 'フォームセキュリティのテストです。',
);
$validServer = array(
	'REQUEST_METHOD' => 'POST',
	'CONTENT_LENGTH' => '512',
	'HTTP_ORIGIN' => 'https://www.k-sinmei.co.jp',
	'REMOTE_ADDR' => '192.0.2.1',
);

$assert = static function (bool $condition, string $label): void {
	if (!$condition) {
		fwrite(STDERR, "FAIL: {$label}\n");
		exit(1);
	}
	echo "PASS: {$label}\n";
};

$result = sinmei_validate_form_request($validPost, $validServer, $now, $rateDir);
$assert($result['ok'], 'valid same-origin submission');
$assert(!array_key_exists('_company_website', $result['post']), 'guard fields removed');

$botPost = $validPost;
$botPost['_company_website'] = 'https://spam.example';
$result = sinmei_validate_form_request($botPost, $validServer, $now, $rateDir);
$assert(!$result['ok'] && $result['status'] === 400, 'honeypot rejection');

$fastPost = $validPost;
$fastPost['_form_started_at'] = (string)($now * 1000);
$result = sinmei_validate_form_request($fastPost, $validServer, $now, $rateDir);
$assert(!$result['ok'] && $result['status'] === 400, 'too-fast rejection');

$crossOrigin = $validServer;
$crossOrigin['HTTP_ORIGIN'] = 'https://attacker.example';
$result = sinmei_validate_form_request($validPost, $crossOrigin, $now, $rateDir);
$assert(!$result['ok'] && $result['status'] === 403, 'cross-origin rejection');

$extraPost = $validPost;
$extraPost['unexpected'] = 'value';
$result = sinmei_validate_form_request($extraPost, $validServer, $now, $rateDir);
$assert(!$result['ok'] && $result['status'] === 400, 'unexpected field rejection');

$rateServer = $validServer;
$rateServer['REMOTE_ADDR'] = '192.0.2.2';
for ($i = 0; $i < 5; $i++) {
	$result = sinmei_validate_form_request($validPost, $rateServer, $now + $i, $rateDir);
	$assert($result['ok'], 'rate limit allowance ' . ($i + 1));
}
$result = sinmei_validate_form_request($validPost, $rateServer, $now + 5, $rateDir);
$assert(!$result['ok'] && $result['status'] === 429, 'sixth request rate-limited');

foreach (glob($rateDir . '/*') ?: array() as $file) unlink($file);
rmdir($rateDir);
