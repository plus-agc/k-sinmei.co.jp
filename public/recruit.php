<?php header("Content-Type:text/html;charset=utf-8"); ?>
<?php
if (version_compare(PHP_VERSION, '5.1.0', '>=')) {
	date_default_timezone_set('Asia/Tokyo');
}

// ============================================================
// 基本設定
// ============================================================
$site_top = "https://www.k-sinmei.co.jp/";
$to = "mi-yu@k-sinmei.co.jp";
$from = "mi-yu@k-sinmei.co.jp";
$Email = "Email";

// ============================================================
// セキュリティ設定
// ============================================================
$Referer_check = 0;
$Referer_check_domain = "www.k-sinmei.co.jp";
$useToken = 1;

// ============================================================
// フォーム設定
// ============================================================
$BccMail = "";
$subject = "採用情報へのお問い合わせ";
$confirmDsp = 0;
$jumpPage = 0;
$thanksPage = "https://www.k-sinmei.co.jp/recruit-thanks/";
$requireCheck = 1;
$require = array('エントリー区分', '氏名', 'フリガナ', '生年月日', '年齢', '性別', 'Email', '学歴・職歴・資格等');

// ============================================================
// 自動返信メール設定
// ============================================================
$remail = 1;
$refrom_name = "株式会社SINMEI";
$re_subject = "送信ありがとうございました";
$dsp_name = 'お名前';
$remail_text = <<<TEXT

お問い合わせありがとうございました。
早急にご返信致しますので今しばらくお待ちください。

送信内容は以下になります。

TEXT;
$mailFooterDsp = 1;
$mailSignature = <<<FOOTER

──────────────────────
株式会社SINMEI
〒037-0091 青森県五所川原市大字太刀打字常盤83-2
TEL：0173-34-4320 　FAX：0173-33-4776
E-mail:mi-yu@k-sinmei.co.jp
URL: https://www.k-sinmei.co.jp
──────────────────────

FOOTER;

// ============================================================
// 変換設定
// ============================================================
$mail_check = 1;
$hankaku = 0;
$hankaku_array = array('電話番号', '金額');
$use_envelope = 0;
$replaceStr['before'] = array('①', '②', '③', '④', '⑤', '⑥', '⑦', '⑧', '⑨', '⑩', '№', '㈲', '㈱', '髙');
$replaceStr['after'] = array('(1)', '(2)', '(3)', '(4)', '(5)', '(6)', '(7)', '(8)', '(9)', '(10)', 'No.', '（有）', '（株）', '高');

// ============================================================
// 初期化処理
// ============================================================
if ($useToken == 1 && $confirmDsp == 1) {
	session_name('PHPMAILFORMSYSTEM');
	session_start();
}
$encode = "UTF-8";
if (isset($_GET))
	$_GET = sanitize($_GET);
if (isset($_POST))
	$_POST = sanitize($_POST);
if (isset($_COOKIE))
	$_COOKIE = sanitize($_COOKIE);
if ($encode == 'SJIS')
	$_POST = sjisReplace($_POST, $encode);
$funcRefererCheck = refererCheck($Referer_check, $Referer_check_domain);
$sendmail = 0;
$empty_flag = 0;
$post_mail = '';
$errm = '';
$header = '';

if ($requireCheck == 1) {
	$requireResArray = requireCheck($require);
	$errm = $requireResArray['errm'];
	$empty_flag = $requireResArray['empty_flag'];
}
if (empty($errm)) {
	foreach ($_POST as $key => $val) {
		if ($val == "confirm_submit")
			$sendmail = 1;
		if ($key == $Email)
			$post_mail = h($val);
		if ($key == $Email && $mail_check == 1 && !empty($val)) {
			if (!checkMail($val)) {
				$errm .= "<p class=\"error_messe\">【" . $key . "】はメールアドレスの形式が正しくありません。</p>\n";
				$empty_flag = 1;
			}
		}
	}
}

if (($confirmDsp == 0 || $sendmail == 1) && $empty_flag != 1) {
	if ($useToken == 1 && $confirmDsp == 1) {
		if (empty($_SESSION['mailform_token']) || ($_SESSION['mailform_token'] !== $_POST['mailform_token'])) {
			exit('ページ遷移が不正です');
		}
		if (isset($_SESSION['mailform_token']))
			unset($_SESSION['mailform_token']);
		if (isset($_POST['mailform_token']))
			unset($_POST['mailform_token']);
	}
	if ($remail == 1) {
		$userBody = mailToUser($_POST, $dsp_name, $remail_text, $mailFooterDsp, $mailSignature, $encode);
		$reheader = userHeader($refrom_name, $from, $encode);
		$re_subject = "=?iso-2022-jp?B?" . base64_encode(mb_convert_encoding($re_subject, "JIS", $encode)) . "?=";
	}
	$adminBody = mailToAdmin($_POST, $subject, $mailFooterDsp, $mailSignature, $encode, $confirmDsp);
	$header = adminHeader($post_mail, $BccMail);
	$subject = "=?iso-2022-jp?B?" . base64_encode(mb_convert_encoding($subject, "JIS", $encode)) . "?=";
	if ($use_envelope == 0) {
		mail($to, $subject, $adminBody, $header);
		if ($remail == 1 && !empty($post_mail))
			mail($post_mail, $re_subject, $userBody, $reheader);
	} else {
		mail($to, $subject, $adminBody, $header, '-f' . $from);
		if ($remail == 1 && !empty($post_mail))
			mail($post_mail, $re_subject, $userBody, $reheader, '-f' . $from);
	}
} else if ($confirmDsp == 1) {


	?>
		<!DOCTYPE HTML>
		<html lang="ja">

		<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
			<meta name="format-detection" content="telephone=no">
			<title>確認画面</title>
			<style type="text/css">
				#formWrap {
					width: 700px;
					margin: 0 auto;
					color: #555;
					line-height: 120%;
					font-size: 90%;
				}

				table.formTable {
					width: 100%;
					margin: 0 auto;
					border-collapse: collapse;
				}

				table.formTable td,
				table.formTable th {
					border: 1px solid #ccc;
					padding: 10px;
				}

				table.formTable th {
					width: 30%;
					font-weight: normal;
					background: #efefef;
					text-align: left;
				}

				p.error_messe {
					margin: 5px 0;
					color: red;
				}


				@media screen and (max-width:572px) {
					#formWrap {
						width: 95%;
						margin: 0 auto;
					}

					table.formTable th,
					table.formTable td {
						width: auto;
						display: block;
					}

					table.formTable th {
						margin-top: 5px;
						border-bottom: 0;
					}

					form input[type="submit"],
					form input[type="reset"],
					form input[type="button"] {
						display: block;
						width: 100%;
						height: 40px;
					}
				}
			</style>
		</head>

		<body>

			<div id="formWrap">
			<?php if ($empty_flag == 1) { ?>
					<div align="center">
						<h4>入力にエラーがあります。下記をご確認の上「戻る」ボタンにて修正をお願い致します。</h4>
					<?php echo $errm; ?><br /><br /><input type="button" value=" 前画面に戻る " onClick="history.back()">
					</div>
			<?php } else { ?>
					<p align="center">以下の内容で間違いがなければ、「送信する」ボタンを押してください。</p>
					<form action="<?php echo h($_SERVER['SCRIPT_NAME']); ?>" method="POST">
						<table class="formTable">
						<?php echo confirmOutput($_POST);
						?>
						</table>
						<p align="center"><input type="hidden" name="mail_set" value="confirm_submit">
							<input type="hidden" name="httpReferer" value="<?php echo h($_SERVER['HTTP_REFERER']); ?>">
							<input type="submit" value="　送信する　">
							<input type="button" value="前画面に戻る" onClick="history.back()">
						</p>
					</form>
			<?php } ?>
			</div><!-- /formWrap -->

		</body>

		</html>
	<?php

}

if (($jumpPage == 0 && $sendmail == 1) || ($jumpPage == 0 && ($confirmDsp == 0 && $sendmail == 0))) {


	?>
	<!DOCTYPE html>
	<html lang="ja">

	<head>
		<meta charset="UTF-8">
		<meta name="description"
			content="株式会社SINMEIは、設立以来培ってきた技術力により、お客様の幅広いニーズにお応えして鉄骨工事業を展開しております。 技術力、品質向上に努めて、お客様の満足度を高めるべく、邁進いたします。">
		<meta name="viewport" content="width=device-width">
		<link rel="icon" type="image/svg+xml" href="https:
		<link rel=" canonical" href="https:
		<meta name=" generator" content="Astro v4.3.1">
		<title>株式会社SINMEI</title>
		<link rel="preconnect" href="https:
		<link rel=" preconnect" href="https:
		<link
			href=" https: rel="stylesheet">
		<link rel="sitemap" href="./sitemap-index.xml">
		<!-- Google tag (gtag.js) -->
		<script async src="https:

		<style>
			path[data-astro-cid-j7pv25f6] {
				fill: none;
				stroke: #fff;
				stroke-width: 2px;
				stroke-dasharray: 2800px
			}
		</style>
		<link rel=" stylesheet" href="/_astro/_page_.gaP746Vk.css" />
		<style>
			h1[data-astro-cid-gjtny2mx] {
				line-height: 1.6
			}
		</style>
		<script type="module" src="/_astro/hoisted.Ai8IfR8G.js"></script>
	</head>

	<body>
		<div>
			<?php if ($empty_flag == 1) { ?>
				<h4>入力にエラーがあります。下記をご確認の上「戻る」ボタンにて修正をお願い致します。</h4>
				<div style="color:red">
					<?php echo $errm; ?>
				</div>
				<br /><br /><input type="button" value=" 前画面に戻る " onClick="history.back()">
			</div>
		</body>

		</html>
	<?php } else { ?>
		<header class="header">
			<nav class="gnav">
				<div class="gnav-inner">
					<div class="gnav-main">
						<a href="../">
							<figure>
								<img src="../_astro/sinmei_logo-icon.nz_Gxpml_xI4hy.svg" alt="株式会社SINMEI" width="100"
									height="100" loading="lazy" decoding="async">
							</figure>
						</a>
						<figure>
							<img src="../_astro/sinmei_name-logo.kShKqjBQ_Z2vxvjS.svg" alt="株式会社SINMEI" width="2000"
								height="394.2" loading="lazy" decoding="async">
						</figure>
						<button class="gnav-hamburger" aria-label="Menu">
							<span class="gnav-hamburger-line"></span>
							<span class="gnav-hamburger-line"></span>
							<span class="gnav-hamburger-line"></span>
						</button>
					</div>
					<ul class="gnav-list">
						<li class="gnav-dropdown">
							<a href="#" class="gnav-dropbtn" lang="en">company<span class="hidden">株式会社SINMEIについて</span>
							</a>
							<ul class="gnav-dropdown_content">
								<li>
									<a href="../company" lang="en">company overview <span>会社概要</span></a>
								</li>
								<li>
									<a href="../company" lang="en">history<span>沿革</span></a>
								</li>
							</ul>
						</li>
						<li>
							<a href="../works" lang="en">works<span class="hidden">施工実績</span></a>
						</li>
						<li class="gnav-dropdown">
							<a href="#" class="gnav-dropbtn">Our MISSION<span class="hidden">SINMEIの取組み</span></a>
							<ul class="gnav-dropdown_content">
								<li>
									<a href="../sdgs">SDGs <span>持続可能な開発目標</span></a>
								</li>
								<li>
									<a href="../partnership" lang="en">partner ship<span>パートナーシップ</span></a>
								</li>
							</ul>
						</li>
						<li>
							<a href="../recruit" lang="en">recruit<span class="hidden">採用情報</span></a>
						</li>
						<li>
							<a href="../news" lang="en">news<span class="hidden">お知らせ</span></a>
						</li>
						<li>
							<a href="../contact" lang="en">contact<span class="hidden">お問い合わせ</span></a>
							<a href="https:
								<figure>
									<img src=" ../_astro/instagram.MCce1YLL_ZkIztW.svg" alt="株式会社SINMEI Instagram" width="40" height="40"
								loading="lazy" decoding="async">
								</figure>
							</a>
						</li>
					</ul>
				</div>
			</nav>
		</header>

		<main>
			<section class="recruit-thanks">
				<div class="recruit-thanks-inner">
					<div class="recruit-thanks-header">
						<picture>
							<source srcset="../_astro/recruit_top.dftmNA05_2d6RUg.avif" type="image/avif">
							<source srcset="../_astro/recruit_top.dftmNA05_1cNFk1.webp" type="image/webp">
							<img src="../_astro/recruit_top.dftmNA05_Z1PMiqB.jpg" alt="採用情報 トップ画像" width="5464" height="3368"
								loading="lazy" decoding="async">
						</picture>
					</div>
					<div class="recruit-thanks-main">
						<div class="recruit-thanks-tit">
							<p lang="en">recruit</p>
							<h2>採用情報</h2>
						</div>
						<div class="recruit-thanks-text">
							<p>ご応募ありがとうございます！</p>
							<p>
								営業日3～4日以上経過してもお返事がない場合は、メール・お電話ともにお返事できない状況の可能性がございます。
								その際はお手数をおかけしますが、こちらのお電話に直接ご連絡いただくか、再度メールフォームから送信いただけますようお願い申し上げます。
							</p>
						</div>
					</div>
				</div>
			</section>
			<section class="cta">
				<div class="cta-inner">
					<div class="cta-main">
						<div class="cta-tit">
							<p lang="en">contact us</p>
							<h2>お問い合わせ</h2>
						</div>
						<div class="cta-text">
							<a href="../contact">フォームからのお問い合わせ
								<img src="../_astro/arrow.7p8vEpna_Z1D8eyM.svg" alt="矢印アイコン" width="57.787" height="57.787"
									loading="lazy" decoding="async">
							</a>
						</div>
					</div>
				</div>
			</section>
		</main>

		<div class="page-top">
			<img src="../_astro/page-top.x9rdHPW3_Z12787M.svg" alt="ページトップに戻る" width="128.695" height="65.867" loading="lazy"
				decoding="async">
		</div>
		<footer class="footer">
			<nav class="footer-nav">
				<div class="footer-inner">
					<ul class="footer-list">
						<li>
							COMPANY<span>株式会社SINMEIについて</span>
							<ul>
								<li>
									<a href="../company" lang="en">company overview<span>会社概要</span></a>
								</li>
								<li>
									<a href="../company" lang="en">history<span>沿革</span></a>
								</li>
							</ul>
						</li>
						<li>
							<a href="../works" lang="en">works<span>施工実績</span></a>
						</li>
						<li>
							Our MISSION<span>SINMEIの取組み</span>
							<ul>
								<li>
									<a href="../sdgs">SDGs <span>持続可能な開発目標</span></a>
								</li>
								<li>
									<a href="../partnership" lang="en">partner ship<span>パートナーシップ</span></a>
								</li>
							</ul>
						</li>
						<li>
							<a href="../recruit" lang="en">recruit<span>採用情報</span></a>
						</li>
						<li>
							<a href="../news" lang="en">news<span>お知らせ</span></a>
						</li>
						<li>
							<a href="../contact" lang="en">contact<span>お問い合わせ</span></a>
						</li>
						<li>
							<a href="../special-thanks" lang="en">special thanks</a>
						</li>
					</ul>
					<div class="footer-icon">
						<figure>
							<a href="https:
								<img src=" ../_astro/instagram.MCce1YLL_ZkIztW.svg" alt="株式会社SINMEI Instagram" width="40" height="40"
								loading="lazy" decoding="async">
							</a>
						</figure>
					</div>
					<div class="footer-text" lang="ja">
						<figure>
							<img src="../_astro/sinmei-logo.PblfwyqP_2aBzku.svg" alt="株式会社SINMEIロゴ" width="2000" height="450.3"
								loading="lazy" decoding="async">
						</figure>
						<p>
							<span>本 社</span>
							<span>青森県五所川原市大字太刀打字常盤83-2</span>
							<span>TEL 0173-34-4320</span>
							<span>FAX 0173-33-4776</span>
						</p>
					</div>
					<div class="footer-copy" lang="ja">
						<p>Copyright &copy;株式会社SINMEI</p>
					</div>
				</div>
			</nav>
		</footer>
		</body>

		</html>
		<?php

			}
} else if (($jumpPage == 1 && $sendmail == 1) || $confirmDsp == 0) {
	if ($empty_flag == 1) { ?>
			<div align="center">
				<h4>入力にエラーがあります。下記をご確認の上「戻る」ボタンにて修正をお願い致します。</h4>
				<div style="color:red">
				<?php echo $errm; ?>
				</div><br /><br /><input type="button" value=" 前画面に戻る " onClick="history.back()">
			</div>
		<?php
	} else {
		header("Location: " . $thanksPage);
	}
}
function checkMail($str)
{
	$mailaddress_array = explode('@', $str);
	if (preg_match("/^[\.!#%&\-_0-9a-zA-Z\?\/\+]+\@[!#%&\-_0-9a-zA-Z]+(\.[!#%&\-_0-9a-zA-Z]+)+$/", "$str") && count($mailaddress_array) == 2) {
		return true;
	} else {
		return false;
	}
}
function h($string)
{
	global $encode;
	return htmlspecialchars($string, ENT_QUOTES, $encode);
}
function sanitize($arr)
{
	if (is_array($arr)) {
		return array_map('sanitize', $arr);
	}
	return str_replace("\0", "", $arr);
}
function sjisReplace($arr, $encode)
{
	foreach ($arr as $key => $val) {
		$key = str_replace('＼', 'ー', $key);
		$resArray[$key] = $val;
	}
	return $resArray;
}
function postToMail($arr)
{
	global $hankaku, $hankaku_array;
	$resArray = '';
	foreach ($arr as $key => $val) {
		$out = '';
		if (is_array($val)) {
			foreach ($val as $key02 => $item) {
				if (is_array($item)) {
					$out .= connect2val($item);
				} else {
					$out .= $item . ', ';
				}
			}
			$out = rtrim($out, ', ');
		} else {
			$out = $val;
		}

		if (version_compare(PHP_VERSION, '5.1.0', '<=')) {
			if (get_magic_quotes_gpc()) {
				$out = stripslashes($out);
			}
		}
		if ($hankaku == 1) {
			$out = zenkaku2hankaku($key, $out, $hankaku_array);
		}
		if ($out != "confirm_submit" && $key != "httpReferer") {
			$resArray .= "【 " . h($key) . " 】 " . h($out) . "\n";
		}
	}
	return $resArray;
}
function confirmOutput($arr)
{
	global $hankaku, $hankaku_array, $useToken, $confirmDsp, $replaceStr;
	$html = '';
	foreach ($arr as $key => $val) {
		$out = '';
		if (is_array($val)) {
			foreach ($val as $key02 => $item) {
				if (is_array($item)) {
					$out .= connect2val($item);
				} else {
					$out .= $item . ', ';
				}
			}
			$out = rtrim($out, ', ');
		} else {
			$out = $val;
		}

		if (version_compare(PHP_VERSION, '5.1.0', '<=')) {
			if (get_magic_quotes_gpc()) {
				$out = stripslashes($out);
			}
		}
		if ($hankaku == 1) {
			$out = zenkaku2hankaku($key, $out, $hankaku_array);
		}

		$out = nl2br(h($out));
		$key = h($key);
		$out = str_replace($replaceStr['before'], $replaceStr['after'], $out);

		$html .= "<tr><th>" . $key . "</th><td>" . $out;
		$html .= '<input type="hidden" name="' . $key . '" value="' . str_replace(array("<br />", "<br>"), "", $out) . '" />';
		$html .= "</td></tr>\n";
	}
	if ($useToken == 1 && $confirmDsp == 1) {
		$token = sha1(uniqid(mt_rand(), true));
		$_SESSION['mailform_token'] = $token;
		$html .= '<input type="hidden" name="mailform_token" value="' . $token . '" />';
	}

	return $html;
}
function zenkaku2hankaku($key, $out, $hankaku_array)
{
	global $encode;
	if (is_array($hankaku_array) && function_exists('mb_convert_kana')) {
		foreach ($hankaku_array as $hankaku_array_val) {
			if ($key == $hankaku_array_val) {
				$out = mb_convert_kana($out, 'a', $encode);
			}
		}
	}
	return $out;
}
function connect2val($arr)
{
	$out = '';
	foreach ($arr as $key => $val) {
		if ($key === 0 || $val == '') {
			$key = '';
		} elseif (strpos($key, "円") !== false && $val != '' && preg_match("/^[0-9]+$/", $val)) {
			$val = number_format($val);
		}
		$out .= $val . $key;
	}
	return $out;
}
function adminHeader($post_mail, $BccMail)
{
	global $from;
	$header = "From: $from\n";
	if ($BccMail != '') {
		$header .= "Bcc: $BccMail\n";
	}
	if (!empty($post_mail)) {
		$header .= "Reply-To: " . $post_mail . "\n";
	}
	$header .= "Content-Type:text/plain;charset=iso-2022-jp\nX-Mailer: PHP/" . phpversion();
	return $header;
}
function mailToAdmin($arr, $subject, $mailFooterDsp, $mailSignature, $encode, $confirmDsp)
{
	$adminBody = "「" . $subject . "」からメールが届きました\n\n";
	$adminBody .= "＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝\n\n";
	$adminBody .= postToMail($arr);
	$adminBody .= "\n＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝\n";
	$adminBody .= "送信された日時：" . date("Y/m/d (D) H:i:s", time()) . "\n";
	$adminBody .= "送信者のIPアドレス：" . @$_SERVER["REMOTE_ADDR"] . "\n";
	$adminBody .= "送信者のホスト名：" . getHostByAddr(getenv('REMOTE_ADDR')) . "\n";
	if ($confirmDsp != 1) {
		$adminBody .= "問い合わせのページURL：" . @$_SERVER['HTTP_REFERER'] . "\n";
	} else {
		$adminBody .= "問い合わせのページURL：" . @$arr['httpReferer'] . "\n";
	}
	if ($mailFooterDsp == 1)
		$adminBody .= $mailSignature;
	return mb_convert_encoding($adminBody, "JIS", $encode);
}
function userHeader($refrom_name, $to, $encode)
{
	$reheader = "From: ";
	if (!empty($refrom_name)) {
		$default_internal_encode = mb_internal_encoding();
		if ($default_internal_encode != $encode) {
			mb_internal_encoding($encode);
		}
		$reheader .= mb_encode_mimeheader($refrom_name) . " <" . $to . ">\nReply-To: " . $to;
	} else {
		$reheader .= "$to\nReply-To: " . $to;
	}
	$reheader .= "\nContent-Type: text/plain;charset=iso-2022-jp\nX-Mailer: PHP/" . phpversion();
	return $reheader;
}
function mailToUser($arr, $dsp_name, $remail_text, $mailFooterDsp, $mailSignature, $encode)
{
	$userBody = '';
	if (isset($arr[$dsp_name]))
		$userBody = h($arr[$dsp_name]) . " 様\n";
	$userBody .= $remail_text;
	$userBody .= "\n＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝\n\n";
	$userBody .= postToMail($arr);
	$userBody .= "\n＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝\n\n";
	$userBody .= "送信日時：" . date("Y/m/d (D) H:i:s", time()) . "\n";
	if ($mailFooterDsp == 1)
		$userBody .= $mailSignature;
	return mb_convert_encoding($userBody, "JIS", $encode);
}
function requireCheck($require)
{
	$res['errm'] = '';
	$res['empty_flag'] = 0;
	foreach ($require as $requireVal) {
		$existsFalg = '';
		foreach ($_POST as $key => $val) {
			if ($key == $requireVal) {
				if (is_array($val)) {
					$connectEmpty = 0;
					foreach ($val as $kk => $vv) {
						if (is_array($vv)) {
							foreach ($vv as $kk02 => $vv02) {
								if ($vv02 == '') {
									$connectEmpty++;
								}
							}
						}
					}
					if ($connectEmpty > 0) {
						$res['errm'] .= "<p class=\"error_messe\">【" . h($key) . "】は必須項目です。</p>\n";
						$res['empty_flag'] = 1;
					}
				} elseif ($val == '') {
					$res['errm'] .= "<p class=\"error_messe\">【" . h($key) . "】は必須項目です。</p>\n";
					$res['empty_flag'] = 1;
				}

				$existsFalg = 1;
				break;
			}
		}
		if ($existsFalg != 1) {
			$res['errm'] .= "<p class=\"error_messe\">【" . $requireVal . "】が未選択です。</p>\n";
			$res['empty_flag'] = 1;
		}
	}

	return $res;
}
function refererCheck($Referer_check, $Referer_check_domain)
{
	if ($Referer_check == 1 && !empty($Referer_check_domain)) {
		if (strpos($_SERVER['HTTP_REFERER'], $Referer_check_domain) === false) {
			return exit('<p align="center">リファラチェックエラー。フォームページのドメインとこのファイルのドメインが一致しません</p>');
		}
	}
}

?>