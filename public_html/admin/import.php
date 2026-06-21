<?php
/**
 * 移行ツール：既存の月別HTML → 正データ(JSON) を生成する（一度きりの初期取り込み）。
 * 既存HTMLは書き換えない（JSONのみ作成）。管理者ログイン必須。
 */
require __DIR__ . '/auth.php';
require __DIR__ . '/lib/reservation.php';
admin_require_login();

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$results = [];
$ran = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && admin_csrf_check($_POST['csrf'] ?? null)) {
    $ran = true;
    $overwrite = !empty($_POST['overwrite']);
    $base = dirname(__DIR__) . '/coatbokking/NEWFORMAT';
    $files = glob($base . '/*/coatbooking*.html') ?: [];
    sort($files);
    foreach ($files as $file) {
        if (!preg_match('#/(\d{4})/coatbooking(\d{2})-(onuma|tatenuma)\.html$#', str_replace('\\', '/', $file), $m)) {
            continue;
        }
        $year = (int)$m[1];
        $month = (int)$m[2];
        $loc = $m[3];
        $label = "{$year}/" . sprintf('%02d', $month) . "-{$loc}";

        if (!$overwrite && res_load($year, $month, $loc) !== null) {
            $results[] = ['label' => $label, 'status' => 'skip', 'msg' => '既にJSONあり'];
            continue;
        }
        $data = res_import_from_html($file, $year, $month, $loc);
        if ($data === null) {
            $results[] = ['label' => $label, 'status' => 'error', 'msg' => '解析できませんでした'];
            continue;
        }
        $save = res_save($data, false); // JSONのみ（HTMLは書き換えない）
        if (!empty($save['ok'])) {
            $results[] = ['label' => $label, 'status' => 'ok', 'msg' => count($data['days']) . '日分'];
        } else {
            $results[] = ['label' => $label, 'status' => 'error', 'msg' => '保存失敗:' . ($save['error'] ?? '')];
        }
    }
}

$csrf = admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>予約データ移行｜管理者</title>
<style>
  body { font-family: "遊ゴシック", sans-serif; margin: 20px; background:#f4f6f8; }
  .card { background:#fff; max-width:680px; margin:0 auto; padding:20px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  h1 { font-size:18px; }
  a { color:#0984e3; }
  button { padding:10px 16px; border:none; border-radius:8px; background:#0984e3; color:#fff; font-weight:bold; cursor:pointer; }
  table { border-collapse:collapse; width:100%; margin-top:14px; font-size:14px; }
  td, th { border:1px solid #e0e0e0; padding:6px 8px; text-align:left; }
  .ok { color:#1a7f1a; } .skip { color:#888; } .error { color:#c0392b; }
  .note { font-size:13px; color:#555; line-height:1.6; }
</style>
</head>
<body>
  <div class="card">
    <p><a href="index.php">← 管理トップ</a></p>
    <h1>予約データの移行（既存HTML → JSON）</h1>
    <p class="note">
      既存の月別HTMLを読み取り、編集の元になる正データ(JSON)を作成します。<br>
      既存HTMLは<strong>書き換えません</strong>（JSONを作るだけ）。最初の1回だけ実行してください。
    </p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <label style="font-size:14px;"><input type="checkbox" name="overwrite" value="1"> 既存のJSONも上書きする</label><br><br>
      <button type="submit">取り込みを実行</button>
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
