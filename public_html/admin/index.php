<?php
/**
 * 管理トップ（ダッシュボード）。ログイン必須。
 * Phase1時点では、ログイン確認・移行への導線・現在の正データ(JSON)一覧を表示する。
 * 編集UIは Phase2 で追加。
 */
require __DIR__ . '/auth.php';
require __DIR__ . '/lib/reservation.php';
admin_require_login();

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// 既存の正データ(JSON)を一覧化
$root = res_data_root();
$items = [];
foreach (glob($root . '/*/[0-1][0-9]-*.json') ?: [] as $f) {
    if (preg_match('#/(\d{4})/(\d{2})-(onuma|tatenuma)\.json$#', str_replace('\\', '/', $f), $m)) {
        $data = json_decode((string)file_get_contents($f), true);
        $items[] = [
            'year' => (int)$m[1],
            'month' => (int)$m[2],
            'loc' => $m[3],
            'days' => is_array($data['days'] ?? null) ? count($data['days']) : 0,
        ];
    }
}
usort($items, fn($a, $b) => [$b['year'], $b['month'], $a['loc']] <=> [$a['year'], $a['month'], $b['loc']]);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理トップ｜春日部硬式テニスクラブ</title>
<style>
  body { font-family: "遊ゴシック", sans-serif; margin: 20px; background:#f4f6f8; }
  .card { background:#fff; max-width:760px; margin:0 auto 16px; padding:20px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  h1 { font-size:18px; margin:0; }
  .top { display:flex; justify-content:space-between; align-items:center; }
  a { color:#0984e3; }
  table { border-collapse:collapse; width:100%; margin-top:8px; font-size:14px; }
  td, th { border:1px solid #e0e0e0; padding:6px 8px; text-align:left; }
  .muted { color:#777; font-size:13px; }
  .btnrow a { display:inline-block; margin-right:12px; }
</style>
</head>
<body>
  <div class="card">
    <div class="top">
      <h1>管理トップ</h1>
      <span class="muted"><?= $h($_SESSION['admin_user'] ?? '') ?> さん｜<a href="logout.php">ログアウト</a></span>
    </div>
    <p class="btnrow" style="margin-top:14px;">
      <a href="import.php">▶ 予約データの移行（既存HTML→JSON）</a>
      <a href="regen.php">▶ 全月いっせい再生成（新見た目に統一）</a>
    </p>
  </div>

  <div class="card">
    <h1>正データ(JSON)一覧</h1>
    <?php if (!$items): ?>
      <p class="muted">まだJSONがありません。まず「予約データの移行」を実行してください。</p>
    <?php else: ?>
      <table>
        <tr><th>年</th><th>月</th><th>場所</th><th>登録日数</th><th>編集</th><th>現在の公開ページ</th><th>生成プレビュー</th></tr>
        <?php foreach ($items as $it):
          $htmlRel = '../coatbokking/NEWFORMAT/' . $it['year'] . '/coatbooking' . sprintf('%02d', $it['month']) . '-' . $it['loc'] . '.html';
          $q = 'year=' . $it['year'] . '&month=' . $it['month'] . '&loc=' . $it['loc']; ?>
          <tr>
            <td><?= $h($it['year']) ?></td>
            <td><?= $h($it['month']) ?>月</td>
            <td><?= $h(res_location_label($it['loc'])) ?></td>
            <td><?= $h($it['days']) ?>日</td>
            <td><a href="edit.php?<?= $h($q) ?>"><strong>編集</strong></a></td>
            <td><a href="<?= $h($htmlRel) ?>" target="_blank">表示</a></td>
            <td><a href="preview.php?<?= $h($q) ?>" target="_blank">プレビュー</a></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
