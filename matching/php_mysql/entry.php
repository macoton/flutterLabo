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
$password = $json->password;
$name = $json->name;
$tag = str_replace(['(', '!', ')', '*', '+'], '', $json->tag);
try {
	// DBを接続
	$pdo = new PDO($dbdsn, $dbuser, $dbpassword);
	$pdo->beginTransaction();
	// 入力確認
	if (!filter_var($mailaddress, FILTER_VALIDATE_EMAIL)) {
		throw new Exception('メールアドレスを確認してください。');
	}
	// 認証確認処理1
	$prepare = $pdo->prepare('SELECT 1 FROM matchingtb WHERE mailaddress=? and authenticate=1');
	$prepare->execute(array($mailaddress));
	$fetch = $prepare->fetchAll(PDO::FETCH_ASSOC);
	$count = count($fetch);
	// 認証無しの場合
	if ($count < 1) {
		throw new Exception('メールアドレスが認証されていません。');
	}
	// 認証確認処理2
	$prepare = $pdo->prepare('SELECT 1 FROM matchingtb WHERE mailaddress=? and authenticate=1 and password=?');
	$prepare->execute(array($mailaddress, $password));
	$fetch = $prepare->fetchAll(PDO::FETCH_ASSOC);
	$count = count($fetch);
	// 認証無しの場合
	if ($count < 1) {
		throw new Exception('パスワードが正しくありません。');
	}
	// 認証有りの場合
	// 削除処理
	$prepare = $pdo->prepare('DELETE FROM matchingtb WHERE mailaddress=?');
	$prepare->execute(array($mailaddress));
	// 登録処理
	$prepare = $pdo->prepare('INSERT INTO matchingtb SET mailaddress=?, authenticate=1, password=?, name=?, tag=?');
	$result = $prepare->execute(array($mailaddress, $password, $name, $tag));
	if (!$result) {
		throw new Exception('登録されませんでした。');
	}
	echo json_encode(array('message' => '登録されました。'));
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
