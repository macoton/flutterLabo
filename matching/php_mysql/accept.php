<?php
// 初期処理
include 'var.php';
header('Content-type: text/plain');
$mailaddress = urldecode($_GET['mailaddress']);
$password = urldecode($_GET['password']);
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
	$prepare = $pdo->prepare('SELECT 1 FROM matchingtb WHERE mailaddress=? and authenticate=0 and password=?');
	$prepare->execute(array($mailaddress, $password));
	$fetch = $prepare->fetchAll(PDO::FETCH_ASSOC);
	$count = count($fetch);
	// 未認証無しの場合
	if ($count < 1) {
		throw new Exception('メールアドレスは認証されませんでした。');
	}
	// 未認証有りの場合
	// 削除処理
	$prepare = $pdo->prepare('DELETE FROM matchingtb WHERE mailaddress=?');
	$prepare->execute(array($mailaddress));
	// 登録処理
	$prepare = $pdo->prepare('INSERT INTO matchingtb SET mailaddress=?, authenticate=1, name="", tag="", password=?');
	$result = $prepare->execute(array($mailaddress, $password));
	if (!$result) {
		throw new Exception('認証されませんでした。');
	}
	echo '認証されました。';
	$pdo->commit();
} catch (Exception $e) {
	// エラー発生
	echo $e->getMessage();
	$pdo->rollBack();
} catch (PDOException $e) {
	// エラー発生
	echo $e->getMessage();
	$pdo->rollBack();
} finally {
	// DBを切断
	$pdo = null;
}
?>
