<?php
// 検索処理
// 単純検索
function MakeSql1($tag, &$sql, &$arys) {
	$arys = array($tag);
	$sql = 'SELECT mailaddress, name, tag FROM matchingtb WHERE authenticate=1 AND CONCAT(",", tag, ",") LIKE CONCAT("%,", ?, ",%")' . ' ORDER BY mailaddress';
}
// AND検索
function MakeSql2($tag, &$sql, &$arys) {
	$arys = explode(',', $tag);
	$sql = 'SELECT mailaddress, name, tag FROM matchingtb WHERE authenticate=1' . str_repeat(' AND CONCAT(",", tag, ",") LIKE CONCAT("%,", ?, ",%")', count($arys)) . ' ORDER BY mailaddress';
}
// NOT/AND/OR検索
function MakeSql3($tag, &$sql, &$arys) {
	$con = 'AND';
	$splits = preg_split('/([\\*\\+,])/', $tag, -1, PREG_SPLIT_DELIM_CAPTURE);
	$val = array_shift($splits);
	preg_match('/^([\\(!]*)([^\\)]*)(.*)$/', $val, $matches);
	list(, $pre, $val, $suf) = $matches;
	$pre = str_replace(['!'], [' NOT '], $pre);
	$arys = [$val];
	$sql = "SELECT mailaddress, name, tag FROM matchingtb WHERE authenticate=1 AND $pre CONCAT(',', tag, ',') LIKE CONCAT('%,', ?, ',%') $suf";
	foreach (array_chunk($splits, 2) as $chunk) {
		$ope = str_replace(['*', '+', ','], [' AND ', ' OR ', " $con "], $chunk[0]);
		$val = $chunk[1];
		preg_match('/^([\\(!]*)([^\\)]*)(.*)$/', $val, $matches);
		list(, $pre, $val, $suf) = $matches;
		$pre = str_replace(['!'], [' NOT '], $pre);
		$arys[] = $val;
		$sql .= "$ope $pre CONCAT(',', tag, ',') LIKE CONCAT('%,', ?, ',%') $suf";
	}
	$sql .= ' ORDER BY mailaddress';
}
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
$tag = $json->tag;
try {
	// DBを接続
	$pdo = new PDO($dbdsn, $dbuser, $dbpassword);
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
	// 検索処理
	MakeSql3($tag, $sql, $arys);
	$prepare = $pdo->prepare($sql);
	$prepare->execute($arys);
	$fetch = $prepare->fetchAll(PDO::FETCH_ASSOC);
	echo json_encode(array('message' => '検索されました。', 'records' => $fetch));
} catch (Exception $e) {
	// エラー発生
	echo json_encode(array('message' => $e->getMessage(), 'records' => array()));
} catch (PDOException $e) {
	// エラー発生
	echo json_encode(array('message' => $e->getMessage(), 'records' => array()));
} finally {
	// DBを切断
	$pdo = null;
}
?>
