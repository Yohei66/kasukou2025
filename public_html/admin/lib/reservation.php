<?php
/**
 * 予約データの中核ライブラリ
 *
 * 正データ = 公開フォルダの外に置くJSON（reservation_data/<年>/<月>-<場所>.json）。
 * 公開ページ = JSONから月別HTML（public_html/coatbokking/NEWFORMAT/<年>/coatbooking<月>-<場所>.html）を自動生成。
 *
 * - HTML→データ: 既存の手書きHTMLを取り込む移行用パーサ
 * - データ→HTML: 現行と同じ列構成＋中止overlayタグ込みで生成
 *
 * 中止機能(cancel.php)と同じく、データは公開領域の外を優先し、作れなければ
 * フォールバックで admin/ 配下に置く。
 */

const RES_TIME_SLOTS = ['9-11', '11-13', '13-15', '15-17'];
const RES_LOCATIONS = ['onuma' => '大沼', 'tatenuma' => '立沼'];

/** 場所キーの妥当性 */
function res_valid_location(string $loc): bool
{
    return array_key_exists($loc, RES_LOCATIONS);
}

function res_location_label(string $loc): string
{
    return RES_LOCATIONS[$loc] ?? $loc;
}

/** 正データ(JSON)の保存ルート。公開領域の外を優先、ダメなら admin/data */
function res_data_root(): string
{
    $candidates = [
        dirname(__DIR__, 3) . '/reservation_data', // public_html の1つ上
        dirname(__DIR__, 1) . '/data/reservation',  // admin/data/reservation（フォールバック）
    ];
    foreach ($candidates as $dir) {
        if (is_dir($dir)) {
            return $dir;
        }
    }
    // 先頭を作成、不可ならフォールバック
    if (@mkdir($candidates[0], 0700, true) || is_dir($candidates[0])) {
        return $candidates[0];
    }
    @mkdir($candidates[1], 0700, true);
    return $candidates[1];
}

function res_json_path(int $year, int $month, string $loc): string
{
    return res_data_root() . '/' . $year . '/' . sprintf('%02d', $month) . '-' . $loc . '.json';
}

/** 生成先の公開HTMLパス（既存の命名に合わせる） */
function res_html_path(int $year, int $month, string $loc): string
{
    return dirname(__DIR__, 2)
        . '/coatbokking/NEWFORMAT/' . $year
        . '/coatbooking' . sprintf('%02d', $month) . '-' . $loc . '.html';
}

/** JSON読み込み（無ければ null） */
function res_load(int $year, int $month, string $loc): ?array
{
    $path = res_json_path($year, $month, $loc);
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * JSONを保存し、月別HTMLを再生成する。
 * 排他ロックして書き込み、成功したら同じデータでHTMLを書き出す。
 * @return array{ok:bool,error?:string}
 */
function res_save(array $data, bool $regenHtml = true): array
{
    $year = (int)($data['year'] ?? 0);
    $month = (int)($data['month'] ?? 0);
    $loc = (string)($data['location'] ?? '');
    if ($year < 2000 || $month < 1 || $month > 12 || !res_valid_location($loc)) {
        return ['ok' => false, 'error' => 'invalid_target'];
    }
    $data = res_normalize($data);

    $jsonPath = res_json_path($year, $month, $loc);
    $dir = dirname($jsonPath);
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'mkdir_failed'];
    }

    $fp = @fopen($jsonPath, 'c+');
    if ($fp === false) {
        return ['ok' => false, 'error' => 'open_failed'];
    }
    try {
        if (!flock($fp, LOCK_EX)) {
            return ['ok' => false, 'error' => 'lock_failed'];
        }
        $out = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $out);
        fflush($fp);
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    if (!$regenHtml) {
        return ['ok' => true];
    }

    // 公開HTMLを再生成
    $html = res_render_html($data);
    $htmlPath = res_html_path($year, $month, $loc);
    $htmlDir = dirname($htmlPath);
    if (!is_dir($htmlDir) && !@mkdir($htmlDir, 0755, true) && !is_dir($htmlDir)) {
        return ['ok' => false, 'error' => 'html_dir_failed'];
    }
    if (@file_put_contents($htmlPath, $html, LOCK_EX) === false) {
        return ['ok' => false, 'error' => 'html_write_failed'];
    }
    return ['ok' => true];
}

/** データを正規化（型・スロット数・場所ラベルを揃える） */
function res_normalize(array $data): array
{
    $loc = (string)($data['location'] ?? '');
    $days = [];
    foreach (($data['days'] ?? []) as $d) {
        if (!is_array($d)) {
            continue;
        }
        $day = (int)($d['day'] ?? 0);
        if ($day < 1 || $day > 31) {
            continue;
        }
        $days[] = [
            'day' => $day,
            'dow' => res_clean((string)($d['dow'] ?? ''), 2),
            'a' => res_norm_slots($d['a'] ?? []),
            'b' => res_norm_slots($d['b'] ?? []),
            'memo' => res_clean((string)($d['memo'] ?? ''), 100),
        ];
    }
    // 日付順に整列
    usort($days, fn($x, $y) => $x['day'] <=> $y['day']);
    return [
        'year' => (int)$data['year'],
        'month' => (int)$data['month'],
        'location' => $loc,
        'locationLabel' => res_location_label($loc),
        'days' => $days,
    ];
}

function res_norm_slots($slots): array
{
    $out = [];
    for ($i = 0; $i < 4; $i++) {
        $v = is_array($slots) && isset($slots[$i]) ? (string)$slots[$i] : '';
        $out[] = res_clean($v, 8);
    }
    return $out;
}

function res_clean(string $v, int $max): string
{
    $v = trim($v);
    $v = str_replace("\xc2\xa0", '', $v); // &nbsp;
    $stripped = preg_replace('/[\x00-\x1F\x7F]/', '', $v);
    if (is_string($stripped)) {
        $v = $stripped;
    }
    if (mb_strlen($v) > $max) {
        $v = mb_substr($v, 0, $max);
    }
    return trim($v);
}

/**
 * 既存の月別HTMLを解析してデータ配列にする（移行用）。
 * 失敗時は null。
 */
function res_import_from_html(string $htmlPath, int $year, int $month, string $loc): ?array
{
    if (!is_file($htmlPath)) {
        return null;
    }
    $html = file_get_contents($htmlPath);
    if ($html === false) {
        return null;
    }
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    // UTF-8として読む
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $xpath = new DOMXPath($doc);
    $tables = $xpath->query("//table[contains(@class,'auto-style27')]");
    if ($tables->length === 0) {
        return null;
    }
    $table = $tables->item(0);
    $trs = [];
    foreach ($table->getElementsByTagName('tr') as $tr) {
        $trs[] = $tr;
    }

    $days = [];
    $dateRe = '/(\d{1,2})月\s*(\d{1,2})日/u';
    for ($i = 0; $i < count($trs); $i++) {
        $tdsA = res_tr_texts($trs[$i]);
        if (count($tdsA) < 7) {
            continue;
        }
        if (!preg_match($dateRe, $tdsA[0], $m)) {
            continue;
        }
        $day = (int)$m[2];
        $dow = $tdsA[1];
        // A行: [日付,曜日,A,9-11,11-13,13-15,15-17,備考]
        $a = [$tdsA[3] ?? '', $tdsA[4] ?? '', $tdsA[5] ?? '', $tdsA[6] ?? ''];
        $memo = $tdsA[7] ?? '';
        // B行: [B,9-11,11-13,13-15,15-17]
        $b = ['', '', '', ''];
        if (isset($trs[$i + 1])) {
            $tdsB = res_tr_texts($trs[$i + 1]);
            if (count($tdsB) >= 5) {
                $b = [$tdsB[1], $tdsB[2], $tdsB[3], $tdsB[4]];
            }
        }
        $days[] = [
            'day' => $day,
            'dow' => res_clean($dow, 2),
            'a' => res_norm_slots($a),
            'b' => res_norm_slots($b),
            'memo' => res_clean($memo, 100),
        ];
    }

    return res_normalize([
        'year' => $year,
        'month' => $month,
        'location' => $loc,
        'days' => $days,
    ]);
}

/** tr内の各tdのテキストを配列で返す */
function res_tr_texts(DOMElement $tr): array
{
    $out = [];
    foreach ($tr->getElementsByTagName('td') as $td) {
        $out[] = res_clean($td->textContent, 100);
    }
    return $out;
}

/** スロット表示用のCSSクラス（○=確保, ×=未確保, その他） */
function res_slot_class(string $v): string
{
    if ($v === '〇' || $v === '○') {
        return 'ok';
    }
    if ($v === '×' || $v === 'x' || $v === 'X') {
        return 'ng';
    }
    return 'etc';
}

/** 曜日の色分けクラス */
function res_dow_class(string $dow): string
{
    if ($dow === '土') {
        return 'sat';
    }
    if ($dow === '日') {
        return 'sun';
    }
    return '';
}

/**
 * データから月別HTMLを生成（現行と同じ列構成＋中止overlay読み込み込み）。
 * table.auto-style27 を維持し、トップページ・中止overlayがそのまま動くようにする。
 */
function res_render_html(array $data): string
{
    $year = (int)$data['year'];
    $month = (int)$data['month'];
    $loc = (string)$data['location'];
    $locLabel = res_location_label($loc);
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    $rows = '';
    foreach ($data['days'] as $d) {
        $dowClass = res_dow_class($d['dow']);
        $dateText = $month . '月' . $d['day'] . '日';
        $rows .= "\t<tr>\n";
        $rows .= "\t\t<td class=\"res-date {$dowClass}\" rowspan=\"2\">" . $h($dateText) . "</td>\n";
        $rows .= "\t\t<td class=\"res-dow {$dowClass}\" rowspan=\"2\">" . $h($d['dow']) . "</td>\n";
        $rows .= "\t\t<td class=\"res-court\">A</td>\n";
        foreach ($d['a'] as $v) {
            $rows .= "\t\t<td class=\"res-slot " . res_slot_class($v) . "\">" . $h($v) . "</td>\n";
        }
        $rows .= "\t\t<td class=\"res-memo\" rowspan=\"2\">" . $h($d['memo']) . "</td>\n";
        $rows .= "\t</tr>\n";
        $rows .= "\t<tr>\n";
        $rows .= "\t\t<td class=\"res-court\">B</td>\n";
        foreach ($d['b'] as $v) {
            $rows .= "\t\t<td class=\"res-slot " . res_slot_class($v) . "\">" . $h($v) . "</td>\n";
        }
        $rows .= "\t</tr>\n";
    }

    $titleH = $h("■ 春日部硬式テニスクラブ ■ {$year}年 {$locLabel} コート予約状況");
    $locLabelH = $h($locLabel);

    // ナビゲーション（元ページと同じ：Home・施設切替・月切替）
    $pad = fn($n) => sprintf('%02d', $n);
    $otherLoc = $loc === 'onuma' ? 'tatenuma' : 'onuma';
    $otherLabelH = $h(res_location_label($otherLoc));
    $otherHrefH = $h('coatbooking' . $pad($month) . '-' . $otherLoc . '.html');
    $prevHrefH = $h($month > 1
        ? 'coatbooking' . $pad($month - 1) . '-' . $loc . '.html'
        : '../' . ($year - 1) . '/coatbooking12-' . $loc . '.html');
    $nextHrefH = $h($month < 12
        ? 'coatbooking' . $pad($month + 1) . '-' . $loc . '.html'
        : '../' . ($year + 1) . '/coatbooking01-' . $loc . '.html');

    $slotHead = '';
    foreach (RES_TIME_SLOTS as $s) {
        $slotHead .= "\t\t<th>" . $h($s) . "</th>\n";
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$titleH}</title>
<style>
  body { font-family: "遊ゴシック", sans-serif; margin: 16px; }
  h1 { font-size: 18px; text-align: center; }
  table.auto-style27 { width: 700px; max-width: 100%; margin: 0 auto; border-collapse: collapse; }
  table.auto-style27 th, table.auto-style27 td {
    border: 1px solid #999; text-align: center; padding: 4px 6px; font-size: 14px;
  }
  table.auto-style27 th { background: #e8efe0; }
  .res-date, .res-dow, .res-court { white-space: nowrap; }
  .res-slot.ok { color: #1a7f1a; }
  .res-slot.ng { color: #c0392b; }
  .res-memo { text-align: left; font-size: 12px; }
  .res-dow.sat, .res-date.sat { color: #1565c0; }
  .res-dow.sun, .res-date.sun { color: #c0392b; }
  .res-nav { text-align: center; margin-bottom: 10px; }
  .res-title { font-size: 26px; font-weight: bold; color: #d41a8a; }
  .res-title img { vertical-align: middle; margin-left: 8px; }
  .res-sub { font-size: 22px; color: #1a7f1a; margin-top: 4px; }
  .res-sub .res-loc-cur { font-weight: bold; }
  .res-sub .res-loc-other { margin-left: 6px; }
  .res-month { font-size: 18px; margin-top: 4px; }
  .res-month a { text-decoration: none; padding: 0 10px; font-weight: bold; }
  a { color: #0066cc; }
</style>
</head>
<body>
<div class="res-nav">
  <div class="res-title">■　春日部硬式テニスクラブ　■<a href="../../../index.html"><img src="../../../image/geo/pop2_home_g.gif" alt="home" height="24" width="102"></a></div>
  <div class="res-sub">{$year}年 コート予約状況　<span class="res-loc-cur">＜{$locLabelH}＞</span><a class="res-loc-other" href="{$otherHrefH}">＜{$otherLabelH}＞</a></div>
  <div class="res-month"><a href="{$prevHrefH}">←</a>　{$month}月　<a href="{$nextHrefH}">→</a></div>
</div>
<table class="auto-style27" align="center" cellpadding="0" cellspacing="0">
	<tr>
		<th>日付</th>
		<th>曜日</th>
		<th>コート</th>
{$slotHead}		<th>備考</th>
	</tr>
{$rows}</table>

<script src="../../../js/court-cancel-overlay.js" defer></script>
</body>
</html>

HTML;
}
