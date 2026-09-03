<?php

/**
 * 公開フォーム共通の送信前検証。
 *
 * @return array{ok: bool, status: int, message: string, post: array<string, string>}
 */
function sinmei_validate_form_request(array $post, array $server, int $now, ?string $rateDir = null): array
{
	$reject = static function (int $status, string $message): array {
		return array('ok' => false, 'status' => $status, 'message' => $message, 'post' => array());
	};

	if (($server['REQUEST_METHOD'] ?? '') !== 'POST') {
		return $reject(405, 'このページから直接送信することはできません。');
	}

	if ((int)($server['CONTENT_LENGTH'] ?? 0) > 65536) {
		return $reject(413, '送信内容が大きすぎます。');
	}

	$source = trim((string)($server['HTTP_ORIGIN'] ?? ''));
	if ($source === '') {
		$source = trim((string)($server['HTTP_REFERER'] ?? ''));
	}
	$sourceParts = $source !== '' ? parse_url($source) : false;
	$allowedHosts = array('www.k-sinmei.co.jp', 'k-sinmei.co.jp');
	if (!is_array($sourceParts)
		|| strtolower((string)($sourceParts['scheme'] ?? '')) !== 'https'
		|| !in_array(strtolower((string)($sourceParts['host'] ?? '')), $allowedHosts, true)) {
		return $reject(403, '送信元を確認できませんでした。フォーム画面から再度お試しください。');
	}

	if (trim((string)($post['_company_website'] ?? '')) !== '') {
		return $reject(400, '送信内容を確認できませんでした。');
	}

	$startedAt = filter_var($post['_form_started_at'] ?? null, FILTER_VALIDATE_INT);
	if ($startedAt === false) {
		return $reject(400, 'フォームの有効期限を確認できませんでした。');
	}
	if ($startedAt > 2000000000) {
		$startedAt = intdiv($startedAt, 1000);
	}
	$elapsed = $now - $startedAt;
	if ($elapsed < 3 || $elapsed > 7200) {
		return $reject(400, 'フォームの有効期限が切れています。ページを再読み込みしてください。');
	}

	$fields = array(
		'お名前' => 100,
		'所属名' => 150,
		'Email' => 254,
		'tel' => 30,
		'お問い合わせ内容' => 5000,
	);
	$guardFields = array('_company_website', '_form_started_at');
	if (array_diff(array_keys($post), array_merge(array_keys($fields), $guardFields))) {
		return $reject(400, '許可されていない送信項目が含まれています。');
	}

	$clean = array();
	foreach ($fields as $name => $maxLength) {
		$value = $post[$name] ?? '';
		if (!is_string($value) || mb_strlen($value, 'UTF-8') > $maxLength) {
			return $reject(400, '入力内容が長すぎる項目があります。');
		}
		$clean[$name] = trim($value);
	}

	foreach (array('お名前', '所属名', 'Email', 'お問い合わせ内容') as $required) {
		if ($clean[$required] === '') {
			return $reject(400, '必須項目を入力してください。');
		}
	}
	if (!filter_var($clean['Email'], FILTER_VALIDATE_EMAIL)
		|| preg_match('/[\r\n]/', $clean['Email'])) {
		return $reject(400, 'メールアドレスの形式を確認してください。');
	}

	$ip = trim((string)($server['REMOTE_ADDR'] ?? ''));
	if ($ip === '') {
		return $reject(400, '送信元を確認できませんでした。');
	}
	$rateDir = $rateDir ?? sys_get_temp_dir();
	$rateFile = rtrim($rateDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
		. 'sinmei-contact-' . hash('sha256', $ip) . '.rate';
	$handle = @fopen($rateFile, 'c+');
	if ($handle === false || !flock($handle, LOCK_EX)) {
		if (is_resource($handle)) fclose($handle);
		return $reject(503, 'ただいま送信できません。時間をおいて再度お試しください。');
	}
	$stored = stream_get_contents($handle);
	$timestamps = array_filter(array_map('intval', preg_split('/\R/', (string)$stored)));
	$timestamps = array_values(array_filter($timestamps, static fn (int $time): bool => $time > $now - 900));
	if (count($timestamps) >= 5) {
		flock($handle, LOCK_UN);
		fclose($handle);
		return $reject(429, '短時間に送信できる回数を超えました。15分ほどお待ちください。');
	}
	$timestamps[] = $now;
	rewind($handle);
	ftruncate($handle, 0);
	$written = fwrite($handle, implode("\n", $timestamps) . "\n");
	$flushed = $written !== false && fflush($handle);
	$unlocked = flock($handle, LOCK_UN);
	$closed = fclose($handle);
	if (!$flushed || !$unlocked || !$closed) {
		return $reject(503, 'ただいま送信できません。時間をおいて再度お試しください。');
	}

	return array('ok' => true, 'status' => 200, 'message' => '', 'post' => $clean);
}

function sinmei_enforce_contact_form_security(): void
{
	$result = sinmei_validate_form_request($_POST, $_SERVER, time());
	if ($result['ok']) {
		$_POST = $result['post'];
		return;
	}

	http_response_code($result['status']);
	if ($result['status'] === 429) {
		header('Retry-After: 900');
	}
	echo '<!doctype html><html lang="ja"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<title>送信できませんでした｜株式会社SINMEI</title></head><body>'
		. '<main><h1>送信できませんでした</h1><p>'
		. htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8')
		. '</p><p><a href="/contact/">お問い合わせフォームへ戻る</a></p></main></body></html>';
	exit;
}
