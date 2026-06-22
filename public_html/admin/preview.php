<?php
/**
 * 生成HTMLのプレビュー（ファイルに書き出さず、その場で表示するだけ）。
 * 管理者ログイン必須。生成器の見た目確認用。
 * 使い方: preview.php?year=2026&month=6&loc=onuma
 */
require __DIR__ . '/auth.php';
require __DIR__ . '/lib/reservation.php';
admin_require_login();

$year = (int)($_GET['year'] ?? 0);
$month = (int)($_GET['month'] ?? 0);
$loc = (string)($_GET['loc'] ?? '');

if ($year < 2000 || $month < 1 || $month > 12 || !res_valid_location($loc)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '対象が不正です（year/month/loc）。';
    exit;
}
$data = res_load($year, $month, $loc);
if ($data === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'この月の正データ(JSON)がありません。先に移行(import)してください。';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
if (ob_get_level() > 0) {
    ob_clean();
}
echo res_render_html($data);
