<?php
/**
 * 中止データ（api/cancel.php と共通の cancellations.json）の読み書き。
 * 管理者ページから中止の確認・編集・取消を行うために使う。
 *
 * 保存先は api/cancel.php と同じ:
 *   推奨       : <public_htmlの1つ上>/cancel_data/cancellations.json
 *   フォールバック: public_html/api/cancel_data/cancellations.json
 */

const CXL_COURTS  = ['A', 'B'];
const CXL_SLOTS   = ['0', '1', '2', '3'];
const CXL_REASONS = ['rain', 'heat', 'thunder', 'wind', 'other'];

/** 中止データファイルのパス（api/cancel.php と同じ優先順） */
function cxl_data_file(): string
{
    $primary = dirname(__DIR__, 3) . '/cancel_data';
    $fallback = dirname(__DIR__, 2) . '/api/cancel_data';
    if (is_dir($primary)) {
        return $primary . '/cancellations.json';
    }
    if (is_dir($fallback)) {
        return $fallback . '/cancellations.json';
    }
    if (@mkdir($primary, 0700, true) || is_dir($primary)) {
        return $primary . '/cancellations.json';
    }
    @mkdir($fallback, 0700, true);
    return $fallback . '/cancellations.json';
}

function cxl_load(): array
{
    $file = cxl_data_file();
    if (!is_file($file)) {
        return [];
    }
    $json = json_decode((string)file_get_contents($file), true);
    return is_array($json) ? $json : [];
}

/** 指定月・場所の中止を「日(int) => [A|B => [slotN => entry]]」で返す */
function cxl_load_month(int $year, int $month, string $loc): array
{
    $prefix = sprintf('%04d-%02d-', $year, $month);
    $out = [];
    foreach (cxl_load() as $date => $day) {
        if (!is_string($date) || strpos($date, $prefix) !== 0 || !is_array($day)) {
            continue;
        }
        $byCourt = $day[$loc] ?? null;
        if (!is_array($byCourt)) {
            continue;
        }
        $courts = [];
        foreach (CXL_COURTS as $c) {
            if (!empty($byCourt[$c]) && is_array($byCourt[$c])) {
                $courts[$c] = $byCourt[$c];
            }
        }
        if ($courts) {
            $out[(int)substr($date, 8, 2)] = $courts;
        }
    }
    return $out;
}

/** 排他ロックして読み→更新→書き込み。$mutator は配列を受け取り更新後配列を返す */
function cxl_update(callable $mutator): bool
{
    $fp = fopen(cxl_data_file(), 'c+');
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
        return true;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/** 空になった場所・コート・日付を取り除く */
function cxl_prune(array $data, string $date): array
{
    if (!isset($data[$date]) || !is_array($data[$date])) {
        return $data;
    }
    foreach ($data[$date] as $loc => $courts) {
        if (is_array($courts)) {
            foreach ($courts as $court => $slots) {
                if (!is_array($slots) || count($slots) === 0) {
                    unset($data[$date][$loc][$court]);
                }
            }
        }
        if (!is_array($data[$date][$loc] ?? null) || count($data[$date][$loc]) === 0) {
            unset($data[$date][$loc]);
        }
    }
    if (count($data[$date]) === 0) {
        unset($data[$date]);
    }
    return $data;
}
