<?php
/**
 * JSONアップロード：スプレッドシート等から生成した予約JSONを取り込む。
 * 1ファイル = 1施設1か月。アップロードで正データ(JSON)を保存し、公開ページを生成する。
 * 管理者ログイン必須・CSRF必須。複数ファイル同時可。
 */
require __DIR__ . '/auth.php';
require __DIR__ . '/lib/reservation.php';
admin_require_login();

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$results = [];
$ran = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ran = true;
    if (!admin_csrf_check($_POST['csrf'] ?? null)) {
        $results[] = ['name' => '-', 'status' => 'error', 'msg' => 'セッションが切れました。再読み込みしてください。'];
    } elseif (empty($_FILES['jsonfile']) || !is_array($_FILES['jsonfile']['name'])) {
        $results[] = ['name' => '-', 'status' => 'error', 'msg' => 'ファイルが選択されていません。'];
    } else {
        $files = $_FILES['jsonfile'];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $name = (string)$files['name'][$i];
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $results[] = ['name' => $name ?: '-', 'status' => 'error', 'msg' => 'アップロード失敗(code ' . $files['error'][$i] . ')'];
                continue;
            }
            $raw = file_get_contents($files['tmp_name'][$i]);
            if ($raw === false) {
                $results[] = ['name' => $name, 'status' => 'error', 'msg' => '読み込み失敗'];
                continue;
            }
            // BOM除去
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                $results[] = ['name' => $name, 'status' => 'error', 'msg' => 'JSONとして読めません'];
                continue;
            }
            $year = (int)($data['year'] ?? 0);
            $month = (int)($data['month'] ?? 0);
            $loc = (string)($data['location'] ?? '');
            if ($year < 2000 || $month < 1 || $month > 12 || !res_valid_location($loc)) {
                $results[] = ['name' => $name, 'status' => 'error', 'msg' => 'year/month/location が不正（location は onuma または tatenuma）'];
                continue;
            }
            $save = res_save($data, true);
            if (!empty($save['ok'])) {
                $label = "{$year}/" . sprintf('%02d', $month) . "-{$loc}";
                $days = is_array($data['days'] ?? null) ? count($data['days']) : 0;
                $results[] = ['name' => $name, 'status' => 'ok', 'msg' => "{$label}（{$days}日分）を保存・生成しました"];
            } else {
                $results[] = ['name' => $name, 'status' => 'error', 'msg' => '保存失敗:' . ($save['error'] ?? '')];
            }
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
<title>JSONアップロード｜管理者</title>
<style>
  body { font-family: "遊ゴシック", sans-serif; margin: 20px; background:#f4f6f8; }
  .card { background:#fff; max-width:680px; margin:0 auto; padding:20px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  h1 { font-size:18px; }
  a { color:#0984e3; }
  button { padding:10px 16px; border:none; border-radius:8px; background:#0984e3; color:#fff; font-weight:bold; cursor:pointer; }
  input[type=file] { margin:8px 0; }
  table { border-collapse:collapse; width:100%; margin-top:14px; font-size:14px; }
  td, th { border:1px solid #e0e0e0; padding:6px 8px; text-align:left; }
  .ok { color:#1a7f1a; } .error { color:#c0392b; }
  .note { font-size:13px; color:#555; line-height:1.7; }
  code { background:#f0f0f0; padding:1px 4px; border-radius:4px; }
</style>
</head>
<body>
  <div class="card">
    <p><a href="index.php">← 管理トップ</a></p>
    <h1>JSONアップロード（スプレッドシートから）</h1>
    <p class="note">
      スプレッドシートで生成した予約JSON（1ファイル＝1施設1か月）を取り込みます。<br>
      取り込むと正データ(JSON)が更新され、公開ページが自動生成されます。<br>
      JSONの形式：<code>{ "year":2026, "month":7, "location":"onuma", "days":[ {"day":3,"dow":"金","a":["〇","〇","×","×"],"b":["×","×","D","×"],"memo":""} ] }</code>
      （<code>location</code> は <code>onuma</code>＝大沼 / <code>tatenuma</code>＝立沼。各マスは 〇 / × / D）
    </p>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <input type="file" name="jsonfile[]" accept=".json,application/json" multiple required>
      <br>
      <button type="submit">アップロードして取り込み</button>
    </form>

    <?php if ($ran): ?>
      <h2 style="font-size:16px;">結果（<?= count($results) ?>件）</h2>
      <table>
        <tr><th>ファイル</th><th>状態</th><th>内容</th></tr>
        <?php foreach ($results as $r): ?>
          <tr>
            <td><?= $h($r['name']) ?></td>
            <td class="<?= $h($r['status']) ?>"><?= $h($r['status']) ?></td>
            <td><?= $h($r['msg']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
