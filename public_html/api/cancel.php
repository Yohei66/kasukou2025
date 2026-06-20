<?php
/**
 * コート中止連絡 API
 *
 * 役割:
 *   GET  ... 当日（または指定日）の中止状況を JSON で返す（パスワード不要）
 *   POST action=cancel ... 1セル（場所×コート×時間帯）を中止登録（パスワード必須）
 *   POST action=clear  ... 中止の取り消し（パスワード必須）
 *
 * 設定とデータは「公開領域の外」に置く:
 *   - 設定 : <public_htmlの1つ上>/cancel_config.php       （合言葉。Web非公開・Git非同期）
 *   - 保存 : <public_htmlの1つ上>/cancel_data/cancellations.json
 *   無ければ api/ 直下の同名ファイルにフォールバックする。
 */

// 出力バッファ開始。設定ファイルがBOM付きや余分な空白で保存されても
// レスポンス本文に混入させないため、JSON出力直前にバッファを破棄する。
ob_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

/** これまでの出力（BOM等）を捨ててから JSON を返す */
function send_json($payload): void
{
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// ---- 設定ファイルの読み込み（公開領域の外を優先） -------------------------
$configCandidates = [
    dirname(__DIR__, 2) . '/cancel_config.php', // 推奨: public_html の1つ上
    __DIR__ . '/cancel_config.php',             // フォールバック: api/ 直下
];
$config = null;
foreach ($configCandidates as $path) {
    if (is_file($path)) {
        $config = require $path;
        break;
    }
}
if (!is_array($config) || empty($config['password'])) {
    http_response_code(500);
    send_json([
        'ok' => false,
        'error' => 'config_missing',
        'message' => '設定ファイル(cancel_config.php)が未設置です。cancel_config.sample.php を public_html の1つ上に cancel_config.php として置き、合言葉を設定してください。',
    ]);
}

// ---- データ保存先の決定 ---------------------------------------------------
$dataDirCandidates = [
    dirname(__DIR__, 2) . '/cancel_data', // 推奨: public_html の1つ上
    __DIR__ . '/cancel_data',             // フォールバック
];
$dataDir = $dataDirCandidates[0];
if (!is_dir($dataDir)) {
    // 公開領域の外に作れない環境ではフォールバックを使う
    if (!@mkdir($dataDir, 0700, true) && !is_dir($dataDir)) {
        $dataDir = $dataDirCandidates[1];
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0700, true);
        }
    }
}
$dataFile = $dataDir . '/cancellations.json';

// ---- ユーティリティ -------------------------------------------------------
function today_key(): string
{
    return (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d');
}

function load_data(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}

/** 排他ロックして読み→更新→書き込み。$mutator は配列を受け取り更新後配列を返す */
function update_data(string $file, callable $mutator)
{
    $fp = fopen($file, 'c+');
    if ($fp === false) {
        return false;
    }
    try {
        if (!flock($fp, LOCK_EX)) {
            return false;
        }
        $raw = stream_get_contents($fp);
        $data = ($raw !== '' && $raw !== false) ? (json_decode($raw, true) ?: []) : [];
        $data = $mutator($data);
        $out = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $out);
        fflush($fp);
        return $data;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/** その日のデータから「空・null」を整理して返す */
function prune_day(array $day): array
{
    foreach ($day as $loc => $courts) {
        if (!is_array($courts)) {
            unset($day[$loc]);
            continue;
        }
        foreach ($courts as $court => $slots) {
            if (!is_array($slots) || count($slots) === 0) {
                unset($day[$loc][$court]);
            }
        }
        if (count($day[$loc]) === 0) {
            unset($day[$loc]);
        }
    }
    return $day;
}

// 許可する値
$ALLOWED_LOC    = ['onuma', 'tatenuma'];
$ALLOWED_COURT  = ['A', 'B'];
$ALLOWED_SLOT   = ['0', '1', '2', '3'];
$ALLOWED_REASON = ['rain', 'heat', 'thunder', 'wind', 'other'];

function clean_text($v, int $max): string
{
    $v = is_string($v) ? $v : '';
    $v = trim($v);
    // 制御文字除去（/u を付けない＝バイト単位。不正UTF-8でも null を返さない）
    $stripped = preg_replace('/[\x00-\x1F\x7F]/', '', $v);
    if (is_string($stripped)) {
        $v = $stripped;
    }
    if (mb_strlen($v) > $max) {
        $v = mb_substr($v, 0, $max);
    }
    return $v;
}

// ---- ルーティング ---------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $date = isset($_GET['date']) ? clean_text($_GET['date'], 10) : today_key();
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = today_key();
    }
    $data = load_data($dataFile);
    $day = isset($data[$date]) && is_array($data[$date]) ? prune_day($data[$date]) : [];
    send_json(['ok' => true, 'date' => $date, 'cancellations' => (object)$day]);
}

if ($method !== 'POST') {
    http_response_code(405);
    send_json(['ok' => false, 'error' => 'method_not_allowed']);
}

// POST 本文（フォーム or JSON）
$input = $_POST;
if (empty($input)) {
    $body = file_get_contents('php://input');
    $j = json_decode($body, true);
    if (is_array($j)) {
        $input = $j;
    }
}

// パスワード照合（タイミング攻撃に配慮して hash_equals）
$pw = is_string($input['password'] ?? null) ? $input['password'] : '';
if (!hash_equals((string)$config['password'], $pw)) {
    usleep(300000); // わずかに遅延
    http_response_code(401);
    send_json(['ok' => false, 'error' => 'bad_password', 'message' => '合言葉が違います。']);
}

$action = $input['action'] ?? '';
$date   = clean_text($input['date'] ?? '', 10);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = today_key();
}
$loc   = (string)($input['location'] ?? '');
$court = strtoupper((string)($input['court'] ?? ''));
$slot  = (string)($input['slot'] ?? '');

if (!in_array($loc, $ALLOWED_LOC, true)
    || !in_array($court, $ALLOWED_COURT, true)
    || !in_array($slot, $ALLOWED_SLOT, true)) {
    http_response_code(400);
    send_json(['ok' => false, 'error' => 'bad_target', 'message' => '対象（場所・コート・時間帯）が不正です。']);
}
// PHPは数値文字列キー("0")を整数添字に変換しJSON配列になってしまうため、
// 非数値のキー("slot0"等)にして常にJSONオブジェクトとして保存する。
$slotKey = 'slot' . $slot;

if ($action === 'cancel') {
    $reason = (string)($input['reason'] ?? '');
    if (!in_array($reason, $ALLOWED_REASON, true)) {
        http_response_code(400);
        send_json(['ok' => false, 'error' => 'bad_reason', 'message' => '理由が不正です。']);
    }
    $name = clean_text($input['name'] ?? '', 30);
    if ($name === '') {
        http_response_code(400);
        send_json(['ok' => false, 'error' => 'name_required', 'message' => 'キャンセル者の名前を入力してください。']);
    }
    $comment = clean_text($input['comment'] ?? '', 100);
    $entry = [
        'reason'  => $reason,
        'name'    => $name,
        'comment' => $comment,
        'at'      => (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d H:i'),
    ];
    $result = update_data($dataFile, function (array $data) use ($date, $loc, $court, $slotKey, $entry) {
        if (!isset($data[$date]) || !is_array($data[$date])) {
            $data[$date] = [];
        }
        if (!isset($data[$date][$loc]) || !is_array($data[$date][$loc])) {
            $data[$date][$loc] = [];
        }
        if (!isset($data[$date][$loc][$court]) || !is_array($data[$date][$loc][$court])) {
            $data[$date][$loc][$court] = [];
        }
        $data[$date][$loc][$court][$slotKey] = $entry;
        return $data;
    });
    if ($result === false) {
        http_response_code(500);
        send_json(['ok' => false, 'error' => 'write_failed', 'message' => '保存に失敗しました。']);
    }
    send_json(['ok' => true, 'entry' => $entry]);
}

if ($action === 'clear') {
    $result = update_data($dataFile, function (array $data) use ($date, $loc, $court, $slotKey) {
        if (isset($data[$date][$loc][$court][$slotKey])) {
            unset($data[$date][$loc][$court][$slotKey]);
        }
        if (isset($data[$date])) {
            $data[$date] = prune_day($data[$date]);
            if (count($data[$date]) === 0) {
                unset($data[$date]);
            }
        }
        return $data;
    });
    if ($result === false) {
        http_response_code(500);
        send_json(['ok' => false, 'error' => 'write_failed', 'message' => '保存に失敗しました。']);
    }
    send_json(['ok' => true]);
}

http_response_code(400);
send_json(['ok' => false, 'error' => 'bad_action']);
