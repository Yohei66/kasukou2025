<?php
/**
 * 全月いっせい再生成：正データ(JSON)から公開HTMLをまとめて生成し直す。
 * 「新しい見た目」に統一したいときに使う。管理者ログイン必須・CSRF必須。
 *
 * ※既存の公開HTMLを生成版で上書きします。データ(○×)はJSONのまま変わりません。
 */
require __DIR__ . '/auth.php';
require __DIR__ . '/lib/reservation.php';
admin_require_login();

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// 対象の年一覧（JSONのある年）
$root = res_data_root();
$years = [];
foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $d) {
    if (preg_match('#/(\d{4})$#', str_replace('\\', '/', $d), $m)) {
        $years[] = (int)$m[1];
    }
}
rsort($years);

$results = [];
$ran = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && admin_csrf_check($_POST['csrf'] ?? null)) {
    $ran = true;
    $targetYear = ($_POST['year'] ?? 'all') === 'all' ? null : (int)$_POST['year'];
    $pattern = $root . '/' . ($targetYear ? $targetYear : '*') . '/[0-1][0-9]-*.json';
    $files = glob($pattern) ?: [];
    sort($files);
    foreach ($files as $f) {
        if (!preg_match('#/(\d{4})/(\d{2})-(onuma|tatenuma)\.json$#', str_replace('\\', '/', $f), $m)) {
            continue;
        }
        $label = "{$m[1]}/{$m[2]}-{$m[3]}";
        $data = json_decode((string)file_get_contents($f), true);
        if (!is_array($data)) {
            $results[] = ['label' => $label, 'status' => 'error', 'msg' => 'JSON読込失敗'];
            continue;
        }
        $save = res_save($data, true);
        $results[] = empty($save['ok'])
            ? ['label' => $label, 'status' => 'error', 'msg' => $save['error'] ?? '']
            : ['label' => $label, 'status' => 'ok', 'msg' => '再生成'];
    }
}

$csrf = admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>全月いっせい再生成｜管理者</title>
<style>
  body { font-family: "遊ゴシック", sans-serif; margin: 20px; background:#f4f6f8; }
  .card { background:#fff; max-width:680px; margin:0 auto; padding:20px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  h1 { font-size:18px; }
  a { color:#0984e3; }
  button { padding:10px 16px; border:none; border-radius:8px; background:#e67e22; color:#fff; font-weight:bold; cursor:pointer; }
  select { padding:8px; border-radius:6px; border:1px solid #ccc; }
  table { border-collapse:collapse; width:100%; margin-top:14px; font-size:14px; }
  td, th { border:1px solid #e0e0e0; padding:6px 8px; text-align:left; }
  .ok { color:#1a7f1a; } .error { color:#c0392b; }
  .note { font-size:13px; color:#555; line-height:1.6; }
</style>
</head>
<body>
  <div class="card">
    <p><a href="index.php">← 管理トップ</a></p>
    <h1>全月いっせい再生成（新しい見た目に統一）</h1>
    <p class="note">
      正データ(JSON)から公開ページをまとめて作り直します。<br>
      予約データ(○×)は<strong>変わりません</strong>。見た目だけ統一されます。
    </p>
    <form method="post" onsubmit="return confirm('選択した範囲の公開ページを生成版で上書きします。よろしいですか？');">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <label>対象：
        <select name="year">
          <option value="all">すべての年</option>
          <?php foreach ($years as $y): ?>
            <option value="<?= $h($y) ?>"><?= $h($y) ?>年</option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit">再生成を実行</button>
    </form>

    <?php if ($ran): ?>
      <h2 style="font-size:16px;">結果（<?= count($results) ?>件）</h2>
      <table>
        <tr><th>対象</th><th>状態</th><th>内容</th></tr>
        <?php foreach ($results as $r): ?>
          <tr>
            <td><?= $h($r['label']) ?></td>
            <td class="<?= $h($r['status']) ?>"><?= $h($r['status']) ?></td>
            <td><?= $h($r['msg']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
