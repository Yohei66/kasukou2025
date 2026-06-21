<?php
/**
 * 予約編集画面。管理者ログイン必須。
 * 月を指定して読み込み、○×トグル＋備考を編集 → 保存で save.php に送信。
 * 使い方: edit.php?year=2026&month=6&loc=onuma
 */
require __DIR__ . '/auth.php';
require __DIR__ . '/lib/reservation.php';
admin_require_login();

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$year = (int)($_GET['year'] ?? 0);
$month = (int)($_GET['month'] ?? 0);
$loc = (string)($_GET['loc'] ?? '');

$valid = ($year >= 2000 && $month >= 1 && $month <= 12 && res_valid_location($loc));
$data = $valid ? res_load($year, $month, $loc) : null;
$csrf = admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>予約編集｜管理者</title>
<style>
  body { font-family: "遊ゴシック", sans-serif; margin: 16px; background:#f4f6f8; }
  .bar { max-width: 980px; margin: 0 auto 12px; display:flex; align-items:center; gap:16px; }
  .bar h1 { font-size:18px; margin:0; }
  .bar .spacer { flex:1; }
  a { color:#0984e3; }
  table { border-collapse: collapse; margin: 0 auto; background:#fff; }
  th, td { border:1px solid #ccc; padding:3px 6px; text-align:center; font-size:14px; }
  thead th { background:#e8efe0; position:sticky; top:0; }
  .grp-a { background:#f3f8ff; } .grp-b { background:#fff7f0; }
  .toggle { width:34px; height:30px; border:1px solid #ccc; border-radius:6px; cursor:pointer; font-size:16px; background:#fff; }
  .toggle.ok { color:#1a7f1a; font-weight:bold; }
  .toggle.ng { color:#c0392b; }
  .toggle.etc { color:#8a6d00; font-size:13px; }
  .memo { width:150px; padding:4px; border:1px solid #ccc; border-radius:6px; }
  .sat { color:#1565c0; } .sun { color:#c0392b; }
  .savebtn { padding:9px 18px; border:none; border-radius:8px; background:#0984e3; color:#fff; font-weight:bold; cursor:pointer; }
  .savebtn:disabled { opacity:.6; cursor:not-allowed; }
  .msg { font-size:14px; }
  .msg.ok { color:#1a7f1a; } .msg.err { color:#c0392b; }
  .hint { max-width:980px; margin:0 auto 10px; font-size:12px; color:#666; }
</style>
</head>
<body>
  <div class="bar">
    <a href="index.php">← 管理トップ</a>
    <h1 id="title"></h1>
    <span class="spacer"></span>
    <span id="msg" class="msg"></span>
    <button id="save" class="savebtn">保存（公開ページに反映）</button>
  </div>
  <div class="hint">
    各マスをクリックで <span style="color:#1a7f1a;font-weight:bold;">〇</span>（確保）⇄ <span style="color:#c0392b;">×</span>（未確保）を切替。備考は自由入力。保存すると公開ページが自動更新されます。
  </div>

  <div id="wrap" style="overflow-x:auto;"></div>

<script>
  const CSRF = <?= json_encode($csrf) ?>;
  const TARGET = { year: <?= (int)$year ?>, month: <?= (int)$month ?>, loc: <?= json_encode($loc) ?> };
  const DATA = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const SLOTS = ['9-11', '11-13', '13-15', '15-17'];

  function slotClass(v){ return (v==='〇'||v==='○') ? 'ok' : (v==='×'||v==='x'||v==='X') ? 'ng' : 'etc'; }
  function dowClass(d){ return d==='土' ? 'sat' : d==='日' ? 'sun' : ''; }

  function makeToggle(val){
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'toggle ' + slotClass(val);
    b.textContent = val || '×';
    b.dataset.val = val || '×';
    b.addEventListener('click', () => {
      const cur = b.dataset.val;
      const next = (cur === '〇' || cur === '○') ? '×' : '〇';
      b.dataset.val = next;
      b.textContent = next;
      b.className = 'toggle ' + slotClass(next);
    });
    return b;
  }

  function render(){
    const title = document.getElementById('title');
    if (!DATA){ title.textContent = '対象データがありません'; document.getElementById('save').disabled = true; return; }
    title.textContent = `${TARGET.year}年 ${TARGET.month}月 ${DATA.locationLabel || TARGET.loc} を編集`;

    const tbl = document.createElement('table');
    const thead = document.createElement('thead');
    let hr = '<tr><th rowspan="2">日付</th><th rowspan="2">曜日</th><th colspan="4" class="grp-a">A面</th><th colspan="4" class="grp-b">B面</th><th rowspan="2">備考</th></tr>';
    hr += '<tr>' + SLOTS.map(s=>`<th class="grp-a">${s}</th>`).join('') + SLOTS.map(s=>`<th class="grp-b">${s}</th>`).join('') + '</tr>';
    thead.innerHTML = hr;
    tbl.appendChild(thead);

    const tbody = document.createElement('tbody');
    DATA.days.forEach((d, idx) => {
      const tr = document.createElement('tr');
      tr.dataset.idx = idx;
      const dc = dowClass(d.dow);
      tr.innerHTML = `<td class="${dc}">${TARGET.month}月${d.day}日</td><td class="${dc}">${d.dow||''}</td>`;
      ['a','b'].forEach(court => {
        for (let s=0; s<4; s++){
          const td = document.createElement('td');
          td.className = court==='a' ? 'grp-a' : 'grp-b';
          const tog = makeToggle((d[court]||[])[s] || '×');
          tog.dataset.court = court; tog.dataset.slot = s;
          td.appendChild(tog);
          tr.appendChild(td);
        }
      });
      const tdMemo = document.createElement('td');
      const inp = document.createElement('input');
      inp.className = 'memo'; inp.type = 'text'; inp.maxLength = 100;
      inp.value = d.memo || ''; inp.dataset.memo = '1';
      tdMemo.appendChild(inp);
      tr.appendChild(tdMemo);
      tbody.appendChild(tr);
    });
    tbl.appendChild(tbody);
    document.getElementById('wrap').appendChild(tbl);
  }

  function collect(){
    const rows = document.querySelectorAll('#wrap tbody tr');
    const days = [];
    rows.forEach(tr => {
      const idx = Number(tr.dataset.idx);
      const src = DATA.days[idx];
      const a = ['×','×','×','×'], b = ['×','×','×','×'];
      tr.querySelectorAll('.toggle').forEach(t => {
        const arr = t.dataset.court === 'a' ? a : b;
        arr[Number(t.dataset.slot)] = t.dataset.val;
      });
      const memo = tr.querySelector('input[data-memo]').value;
      days.push({ day: src.day, dow: src.dow, a, b, memo });
    });
    return days;
  }

  async function save(){
    const btn = document.getElementById('save');
    const msg = document.getElementById('msg');
    btn.disabled = true; msg.className = 'msg'; msg.textContent = '保存中…';
    try {
      const resp = await fetch('save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: CSRF, year: TARGET.year, month: TARGET.month, location: TARGET.loc, days: collect() })
      });
      const j = await resp.json().catch(()=>({}));
      if (resp.ok && j.ok){ msg.className='msg ok'; msg.textContent='保存しました（公開ページ更新済み）'; }
      else { msg.className='msg err'; msg.textContent = (j && j.message) || '保存に失敗しました'; }
    } catch(e){ msg.className='msg err'; msg.textContent='通信エラー'; }
    btn.disabled = false;
  }

  document.getElementById('save').addEventListener('click', save);
  render();
</script>
</body>
</html>
