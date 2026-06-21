<?php
/**
 * 予約保存API（JSON）。管理者ログイン必須・CSRF必須。
 * 受け取った1か月分のデータでJSONを更新し、公開HTMLを再生成する。
 */
require __DIR__ . '/auth.php';
require __DIR__ . '/lib/reservation.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
if (ob_get_level() > 0) {
    ob_clean();
}

function save_json($payload): void
{
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!admin_is_logged_in()) {
    http_response_code(401);
    save_json(['ok' => false, 'error' => 'unauthorized', 'message' => 'ログインが必要です。']);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    save_json(['ok' => false, 'error' => 'method_not_allowed']);
}

$body = file_get_contents('php://input');
$in = json_decode($body, true);
if (!is_array($in)) {
    http_response_code(400);
    save_json(['ok' => false, 'error' => 'bad_json', 'message' => '入力が不正です。']);
}
if (!admin_csrf_check($in['csrf'] ?? null)) {
    http_response_code(403);
    save_json(['ok' => false, 'error' => 'bad_csrf', 'message' => 'セッションが切れました。ページを再読み込みしてください。']);
}

$year = (int)($in['year'] ?? 0);
$month = (int)($in['month'] ?? 0);
$loc = (string)($in['location'] ?? '');
if ($year < 2000 || $month < 1 || $month > 12 || !res_valid_location($loc)) {
    http_response_code(400);
    save_json(['ok' => false, 'error' => 'bad_target', 'message' => '対象（年・月・場所）が不正です。']);
}

$data = [
    'year' => $year,
    'month' => $month,
    'location' => $loc,
    'days' => is_array($in['days'] ?? null) ? $in['days'] : [],
];

$res = res_save($data, true); // JSON保存＋公開HTML再生成
if (empty($res['ok'])) {
    http_response_code(500);
    save_json(['ok' => false, 'error' => $res['error'] ?? 'save_failed', 'message' => '保存に失敗しました。']);
}
save_json(['ok' => true]);
