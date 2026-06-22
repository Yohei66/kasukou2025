<?php
/**
 * 新しい月のシートを作成する。管理者ログイン必須・CSRF必須。
 * 空の月（日付なし）を作り、編集画面へ誘導する（日付は編集画面で追加）。
 */
require __DIR__ . '/auth.php';
require __DIR__ . '/lib/reservation.php';
admin_require_login();

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_csrf_check($_POST['csrf'] ?? null)) {
        $error = 'セッションが切れました。再読み込みしてください。';
    } else {
        $year = (int)($_POST['year'] ?? 0);
        $month = (int)($_POST['month'] ?? 0);
        $loc = (string)($_POST['loc'] ?? '');
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12 || !res_valid_location($loc)) {
            $error = '年・月・場所を正しく指定してください。';
        } elseif (res_load($year, $month, $loc) !== null) {
            // 既にあれば編集へ
            header("Location: edit.php?year={$year}&month={$month}&loc={$loc}");
            exit;
        } else {
            $res = res_save(['year' => $year, 'month' => $month, 'location' => $loc, 'days' => []], true);
            if (!empty($res['ok'])) {
                header("Location: edit.php?year={$year}&month={$month}&loc={$loc}");
                exit;
            }
            $error = '作成に失敗しました：' . ($res['error'] ?? '');
        }
    }
}

$csrf = admin_csrf_token();
$thisYear = (int)date('Y');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>新しい月を作成｜管理者</title>
<style>
  body { font-family: "遊ゴシック", sans-serif; margin: 20px; background:#f4f6f8; }
  .card { background:#fff; max-width:520px; margin:0 auto; padding:20px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  h1 { font-size:18px; }
  a { color:#0984e3; }
  label { display:inline-block; margin:8px 12px 8px 0; font-size:14px; }
  select, input { padding:8px; border:1px solid #ccc; border-radius:6px; }
  button { padding:10px 18px; border:none; border-radius:8px; background:#27ae60; color:#fff; font-weight:bold; cursor:pointer; margin-top:8px; }
  .err { color:#c0392b; font-size:13px; min-height:1.2em; margin-top:8px; }
  .note { font-size:13px; color:#555; line-height:1.6; }
</style>
</head>
<body>
  <div class="card">
    <p><a href="index.php">← 管理トップ</a></p>
    <h1>新しい月を作成</h1>
    <p class="note">空のシートを作成します。作成後の編集画面で「日付を追加」して、確保状況(○×)を入力してください。</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <label>年：
        <input type="number" name="year" value="<?= $h($thisYear) ?>" min="2000" max="2100" style="width:90px">
      </label>
      <label>月：
        <select name="month">
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>"><?= $m ?>月</option>
          <?php endfor; ?>
        </select>
      </label>
      <label>場所：
        <select name="loc">
          <?php foreach (RES_LOCATIONS as $k => $v): ?>
            <option value="<?= $h($k) ?>"><?= $h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="err"><?= $h($error) ?></div>
      <button type="submit">作成して編集へ</button>
    </form>
  </div>
</body>
</html>
