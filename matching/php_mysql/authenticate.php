<?php
// 初期処理
include 'var.php';
$method = $_SERVER['REQUEST_METHOD'];
if (!empty($_SERVER['HTTP_ORIGIN'])) {
	$origin = $_SERVER['HTTP_ORIGIN'];
} else {
	$origin = '';
}
$methodAllows = array('OPTIONS', 'POST');
if (count($originAllows) < 1) {
	error_log(date('Y/m/d H:i:s') . ": \$method/\$origin: $method/$origin\n", 3, '/var/tmp/debug.log');
} elseif (!in_array($method, $methodAllows) ||
	!in_array($origin, $originAllows)) {
	exit(0);
} elseif ($method == 'OPTIONS') {
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: OPTIONS');
	header('Access-Control-Allow-Headers: Content-Disposition, Content-Type, Content-Length, Accept-Encoding');
	header('Content-type: application/json');
	exit(0);
}
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Disposition, Content-Type, Content-Length, Accept-Encoding');
header('Content-type: application/json');
$json_string = file_get_contents('php://input');
$json = json_decode($json_string);
$mailaddress = $json->mailaddress;
try {
	// DBを接続
	$pdo = new PDO($dbdsn, $dbuser, $dbpassword);
	$pdo->beginTransaction();
	// 入力確認
	if (!filter_var($mailaddress, FILTER_VALIDATE_EMAIL)) {
		throw new Exception('メールアドレスを確認してください。');
	}
	// 認証確認処理
	$prepare = $pdo->prepare('SELECT 1 FROM matchingtb WHERE mailaddress=? and authenticate=1');
	$prepare->execute(array($mailaddress));
	$fetch = $prepare->fetchAll(PDO::FETCH_ASSOC);
	$count = count($fetch);
	// 認証有りの場合
	if ($count > 0) {
		throw new Exception('メールアドレスは認証されています。');
	}
	// 認証無しの場合
	// 未認証確認処理
	$prepare = $pdo->prepare('SELECT 1 FROM matchingtb WHERE mailaddress=? and authenticate=0');
	$prepare->execute(array($mailaddress));
	$fetch = $prepare->fetchAll(PDO::FETCH_ASSOC);
	$count = count($fetch);
	// 未認証有りの場合
	if ($count > 0) {
		throw new Exception('メールボックスを確認してください。');
	}
	// 未認証無しの場合
	// passwordを生成
	$length = 16;
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$password = '';
	for ($i = 0; $i < $length; $i++) {
		$password .= $characters[rand(0, strlen($characters) - 1)];
	}
	// 未認証処理1
	$prepare = $pdo->prepare('INSERT INTO matchingtb SET mailaddress=?, authenticate=0, tag="", password=?');
	$result = $prepare->execute(array($mailaddress, $password));
	if (!$result) {
		// 基本的にアプリバグか環境問題でしか起こりない
		throw new Exception('エラーが発生しました。');
	}
	// 未認証処理2
	mb_language("Japanese");
	mb_internal_encoding("UTF-8");
	$to = $mailaddress;
	$title = "matching";
    #$uri1 = "http://macoton.s500.xrea.com";
    $uri1 = "https://macoton.xrea.mydns.jp";
	$mailaddressencode = urlencode($mailaddress);
	$passwordencode = urlencode($password);
	$uri2 = "/matching/accept.php?mailaddress={$mailaddressencode}&password={$passwordencode}";
	$content = <<<EOT
matching home page に登録されました。
心当たりがありかつ認証する場合は以下をクリックしてください。
{$uri1}{$uri2}
また、アプリ画面のパスワードに以下を貼り付けてください。
{$password}
心当たりがなくまたは認証しない場合は本メールを無視してください。
EOT;
	$result = mb_send_mail($to, $title, $content);
	if (!$result) {
		// 基本的にアプリバグか環境問題でしか起こりない
		throw new Exception('エラーが発生しました。');
	}
	echo json_encode(array('message' => 'メールボックスを確認してください。'));
	$pdo->commit();
} catch (Exception $e) {
	// エラー発生
	echo json_encode(array('message' => $e->getMessage()));
	$pdo->rollBack();
} catch (PDOException $e) {
	// エラー発生
	echo json_encode(array('message' => $e->getMessage()));
	$pdo->rollBack();
} finally {
	// DBを切断
	$pdo = null;
}
?>
