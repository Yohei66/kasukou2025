<?php
require __DIR__ . '/auth.php';

if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_csrf_check($_POST['csrf'] ?? null)) {
        $error = 'セッションが切れました。もう一度お試しください。';
    } elseif (admin_config() === null) {
        $error = '管理者設定(admin_config.php)が未設置です。設置してください。';
    } elseif (admin_login((string)($_POST['user'] ?? ''), (string)($_POST['pass'] ?? ''))) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'IDまたはパスワードが違います。';
    }
}

$csrf = admin_csrf_token();
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理者ログイン｜春日部硬式テニスクラブ</title>
<style>
  body { font-family: "遊ゴシック", sans-serif; background: #dce6ef; display: flex; justify-content: center; }
  .box { background: #fff; margin-top: 12vh; padding: 28px 26px; border-radius: 14px; width: 320px; box-shadow: 0 8px 24px rgba(0,0,0,.15); }
  h1 { font-size: 18px; margin: 0 0 16px; text-align: center; }
  label { display: block; font-size: 13px; font-weight: bold; margin: 12px 0 4px; }
  input[type=text], input[type=password] { width: 100%; padding: 9px; border: 1px solid #ccc; border-radius: 8px; box-sizing: border-box; font-size: 16px; }
  button { width: 100%; margin-top: 18px; padding: 11px; border: none; border-radius: 8px; background: #0984e3; color: #fff; font-weight: bold; font-size: 15px; cursor: pointer; }
  .err { color: #c0392b; font-size: 13px; margin-top: 12px; min-height: 1.2em; }
</style>
</head>
<body>
  <form class="box" method="post" autocomplete="off">
    <h1>管理者ログイン</h1>
    <label for="user">管理者ID</label>
    <input type="text" id="user" name="user" autofocus>
    <label for="pass">パスワード</label>
    <input type="password" id="pass" name="pass">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
    <div class="err"><?= $h($error) ?></div>
    <button type="submit">ログイン</button>
  </form>
</body>
</html>
