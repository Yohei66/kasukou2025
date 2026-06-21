<?php
/**
 * 予約編集画面。管理者ログイン必須。
 * 月を指定して読み込み、○×トグル＋備考を編集、日付行の追加・削除も可能。
 * 保存で save.php に送信 → JSON更新＆公開HTML再生成。
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
  .bar { max-width: 1040px; margin: 0 auto 10px; display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
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
  .delbtn { border:none; background:#fff; color:#c0392b; cursor:pointer; font-size:14px; border:1px solid #e0b4b4; border-radius:6px; padding:3px 8px; }
  .addbar { max-width:1040px; margin:0 auto 10px; background:#fff; padding:10px 14px; border-radius:10px; font-size:14px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .addbar input { width:60px; padding:6px; border:1px solid #ccc; border-radius:6px; }
  .addbar button { padding:7px 14px; border:none; border-radius:8px; background:#27ae60; color:#fff; font-weight:bold; cursor:pointer; }
  .msg { font-size:14px; }
  .msg.ok { color:#1a7f1a; } .msg.err { color:#c0392b; }
  .hint { max-width:1040px; margin:0 auto 10px; font-size:12px; color:#666; }
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
    各マスをクリックで <span style="color:#1a7f1a;font-weight:bold;">〇</span>（確保）⇄ <span style="color:#c0392b;">×</span>（未確保）を切替。備考は自由入力。日付の追加・削除も可能。保存で公開ページが自動更新されます。
  </div>

  <div class="addbar">
    <strong>日付を追加：</strong>
    <label><?= (int)$month ?>月 <input type="number" id="newday" min="1" max="31" placeholder="日"> 日</label>
    <button id="adddaybtn" type="button">＋ 追加</button>
    <span id="addmsg" style="color:#c0392b;"></span>
  </div>

  <div id="wrap" style="overflow-x:auto;"></div>

<script>
  const CSRF = <?= json_encode($csrf) ?>;
  const TARGET = { year: <?= (int)$year ?>, month: <?= (int)$month ?>, loc: <?= json_encode($loc) ?> };
  const DATA = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const SLOTS = ['9-11', '11-13', '13-15', '15-17'];
  const DOW = ['日','月','火','水','木','金','土'];

  // 作業用モデル（DATA.days のコピー）
  let model = DATA && Array.isArray(DATA.days) ? JSON.parse(JSON.stringify(DATA.days)) : [];

  function slotClass(v){ return (v==='〇'||v==='○') ? 'ok' : (v==='×'||v==='x'||v==='X') ? 'ng' : 'etc'; }
  function dowClass(d){ return d==='土' ? 'sat' : d==='日' ? 'sun' : ''; }
  function dowOf(day){ return DOW[new Date(TARGET.year, TARGET.month-1, day).getDay()]; }
  function daysInMonth(){ return new Date(TARGET.year, TARGET.month, 0).getDate(); }

  function makeToggle(val, court, slot){
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'toggle ' + slotClass(val);
    b.textContent = val || '×';
    b.dataset.val = val || '×';
    b.dataset.court = court; b.dataset.slot = slot;
    b.addEventListener('click', () => {
      const next = (b.dataset.val === '〇' || b.dataset.val === '○') ? '×' : '〇';
      b.dataset.val = next; b.textContent = next; b.className = 'toggle ' + slotClass(next);
    });
    return b;
  }

  // DOMの編集状態をモデルに反映（行は model と同順）
  function syncFromDom(){
    const rows = document.querySelectorAll('#wrap tbody tr');
    rows.forEach((tr, i) => {
      if (!model[i]) return;
      const a = ['×','×','×','×'], b = ['×','×','×','×'];
      tr.querySelectorAll('.toggle').forEach(t => {
        (t.dataset.court === 'a' ? a : b)[Number(t.dataset.slot)] = t.dataset.val;
      });
      model[i].a = a; model[i].b = b;
      model[i].memo = tr.querySelector('input[data-memo]').value;
    });
  }

  function renderRows(){
    const wrap = document.getElementById('wrap');
    wrap.innerHTML = '';
    const tbl = document.createElement('table');
    let hr = '<tr><th rowspan="2">日付</th><th rowspan="2">曜日</th><th colspan="4" class="grp-a">A面</th><th colspan="4" class="grp-b">B面</th><th rowspan="2">備考</th><th rowspan="2">操作</th></tr>';
    hr += '<tr>' + SLOTS.map(s=>`<th class="grp-a">${s}</th>`).join('') + SLOTS.map(s=>`<th class="grp-b">${s}</th>`).join('') + '</tr>';
    const thead = document.createElement('thead'); thead.innerHTML = hr; tbl.appendChild(thead);

    const tbody = document.createElement('tbody');
    model.forEach((d, idx) => {
      const tr = document.createElement('tr');
      const dc = dowClass(d.dow);
      tr.innerHTML = `<td class="${dc}">${TARGET.month}月${d.day}日</td><td class="${dc}">${d.dow||''}</td>`;
      ['a','b'].forEach(court => {
        for (let s=0; s<4; s++){
          const td = document.createElement('td');
          td.className = court==='a' ? 'grp-a' : 'grp-b';
          td.appendChild(makeToggle((d[court]||[])[s] || '×', court, s));
          tr.appendChild(td);
        }
      });
      const tdMemo = document.createElement('td');
      const inp = document.createElement('input');
      inp.className='memo'; inp.type='text'; inp.maxLength=100; inp.value=d.memo||''; inp.dataset.memo='1';
      tdMemo.appendChild(inp); tr.appendChild(tdMemo);
      const tdDel = document.createElement('td');
      const del = document.createElement('button');
      del.type='button'; del.className='delbtn'; del.textContent='削除';
      del.addEventListener('click', () => { if (confirm(`${TARGET.month}月${d.day}日 を削除しますか？`)) removeDay(idx); });
      tdDel.appendChild(del); tr.appendChild(tdDel);
      tbody.appendChild(tr);
    });
    tbl.appendChild(tbody);
    wrap.appendChild(tbl);
  }

  function addDay(){
    const am = document.getElementById('addmsg'); am.textContent='';
    const day = parseInt(document.getElementById('newday').value, 10);
    if (!day || day < 1 || day > daysInMonth()){ am.textContent = `1〜${daysInMonth()}の日付を入力してください`; return; }
    syncFromDom();
    if (model.some(d => Number(d.day) === day)){ am.textContent = `${day}日は既にあります`; return; }
    model.push({ day, dow: dowOf(day), a:['×','×','×','×'], b:['×','×','×','×'], memo:'' });
    model.sort((x,y) => x.day - y.day);
    renderRows();
    document.getElementById('newday').value = '';
  }

  function removeDay(idx){
    syncFromDom();
    model.splice(idx, 1);
    renderRows();
  }

  async function save(){
    const btn = document.getElementById('save'); const msg = document.getElementById('msg');
    syncFromDom();
    btn.disabled = true; msg.className='msg'; msg.textContent='保存中…';
    try {
      const resp = await fetch('save.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: CSRF, year: TARGET.year, month: TARGET.month, location: TARGET.loc, days: model })
      });
      const j = await resp.json().catch(()=>({}));
      if (resp.ok && j.ok){ msg.className='msg ok'; msg.textContent='保存しました（公開ページ更新済み）'; }
      else { msg.className='msg err'; msg.textContent = (j && j.message) || '保存に失敗しました'; }
    } catch(e){ msg.className='msg err'; msg.textContent='通信エラー'; }
    btn.disabled = false;
  }

  document.getElementById('title').textContent = DATA
    ? `${TARGET.year}年 ${TARGET.month}月 ${DATA.locationLabel || TARGET.loc} を編集`
    : `${TARGET.year}年 ${TARGET.month}月 ${TARGET.loc} を編集（データなし）`;
  document.getElementById('save').addEventListener('click', save);
  document.getElementById('adddaybtn').addEventListener('click', addDay);
  renderRows();
</script>
</body>
</html>
