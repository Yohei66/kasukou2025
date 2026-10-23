<?php
/**
 * 中止情報の編集・取消API（JSON）。管理者ログイン必須・CSRF必須。
 *   action=update ... 既存の中止の理由・キャンセル者・ひとことを書き換える
 *   action=clear  ... 中止を取り消す
 * 予約データ（〇×）には触れない。
 */
require __DIR__ . '/auth.php';
require __DIR__ . '/lib/reservation.php';
require __DIR__ . '/lib/cancellation.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function cxl_json($payload): void
{
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!admin_is_logged_in()) {
    http_response_code(401);
    cxl_json(['ok' => false, 'error' => 'unauthorized', 'message' => 'ログインが必要です。']);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    cxl_json(['ok' => false, 'error' => 'method_not_allowed']);
}

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) {
    http_response_code(400);
    cxl_json(['ok' => false, 'error' => 'bad_json', 'message' => '入力が不正です。']);
}
if (!admin_csrf_check($in['csrf'] ?? null)) {
    http_response_code(403);
    cxl_json(['ok' => false, 'error' => 'bad_csrf', 'message' => 'セッションが切れました。ページを再読み込みしてください。']);
}

$date = (string)($in['date'] ?? '');
$loc = (string)($in['location'] ?? '');
$court = strtoupper((string)($in['court'] ?? ''));
$slot = (string)($in['slot'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    || !res_valid_location($loc)
    || !in_array($court, CXL_COURTS, true)
    || !in_array($slot, CXL_SLOTS, true)) {
    http_response_code(400);
    cxl_json(['ok' => false, 'error' => 'bad_target', 'message' => '対象（日付・場所・コート・時間帯）が不正です。']);
}
$slotKey = 'slot' . $slot;
$action = (string)($in['action'] ?? '');

if ($action === 'update') {
    $reason = (string)($in['reason'] ?? '');
    if (!in_array($reason, CXL_REASONS, true)) {
        http_response_code(400);
        cxl_json(['ok' => false, 'error' => 'bad_reason', 'message' => '理由が不正です。']);
    }
    $name = res_clean((string)($in['name'] ?? ''), 30);
    if ($name === '') {
        http_response_code(400);
        cxl_json(['ok' => false, 'error' => 'name_required', 'message' => 'キャンセル者の名前を入力してください。']);
    }
    $comment = res_clean((string)($in['comment'] ?? ''), 100);
    $entry = null;
    $ok = cxl_update(function (array $data) use ($date, $loc, $court, $slotKey, $reason, $name, $comment, &$entry) {
        $cur = $data[$date][$loc][$court][$slotKey] ?? null;
        if (!is_array($cur)) {
            return $data; // 既に取り消されている
        }
        $cur['reason'] = $reason;
        $cur['name'] = $name;
        $cur['comment'] = $comment;
        $cur['edited_at'] = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d H:i');
        $data[$date][$loc][$court][$slotKey] = $cur;
        $entry = $cur;
        return $data;
    });
    if (!$ok) {
        http_response_code(500);
        cxl_json(['ok' => false, 'error' => 'write_failed', 'message' => '保存に失敗しました。']);
    }
    if ($entry === null) {
        http_response_code(404);
        cxl_json(['ok' => false, 'error' => 'not_found', 'message' => 'この中止は既に取り消されています。ページを再読み込みしてください。']);
    }
    cxl_json(['ok' => true, 'entry' => $entry]);
}

if ($action === 'clear') {
    $ok = cxl_update(function (array $data) use ($date, $loc, $court, $slotKey) {
        unset($data[$date][$loc][$court][$slotKey]);
        return cxl_prune($data, $date);
    });
    if (!$ok) {
        http_response_code(500);
        cxl_json(['ok' => false, 'error' => 'write_failed', 'message' => '保存に失敗しました。']);
    }
    cxl_json(['ok' => true]);
}

http_response_code(400);
cxl_json(['ok' => false, 'error' => 'bad_action']);
