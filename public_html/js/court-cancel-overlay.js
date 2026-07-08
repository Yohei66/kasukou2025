/**
 * 月別コート予約状況ページに「中止」を重ねて表示する。
 *
 * - このページのURL（.../NEWFORMAT/<年>/coatbooking<月>-<場所>.html）から
 *   年・月・場所を判定する。
 * - api/cancel.php?month=YYYY-MM から「その月の全日分」の中止情報を取得し、
 *   中止があった各日の行の該当マス（A面/B面×時間帯）を「中止」（赤）に置き換える。
 * - 当日だけでなく過去の中止もそのまま残るので、予約状況ページが履歴として使える。
 *
 * 既存の予約HTMLには手を加えず、後付けで重ねるだけ。読み込みや通信に失敗しても
 * ページ表示には影響しない（黙って何もしない）。
 */
(function () {
  var m = location.pathname.match(/\/NEWFORMAT\/(\d{4})\/coatbooking(\d{2})-(onuma|tatenuma)\.html/i);
  if (!m) return;
  var pageYear = parseInt(m[1], 10);
  var pageMonth = parseInt(m[2], 10);
  var loc = m[3].toLowerCase();
  var pad = function (n) { return ('0' + n).slice(-2); };

  // このページの月の中止を「全日分」取得（過去分も含めて予約状況ページに残す）
  fetch('../../../api/cancel.php?month=' + pageYear + '-' + pad(pageMonth), { cache: 'no-store' })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (j) {
      if (!j || !j.ok || !j.cancellations) return;
      var table = document.querySelector('table.auto-style27');
      if (!table) return;
      var trs = Array.prototype.slice.call(table.querySelectorAll('tr'));

      Object.keys(j.cancellations).forEach(function (dateKey) {
        var parts = dateKey.split('-');
        if (parts.length !== 3) return;
        if (parseInt(parts[0], 10) !== pageYear || parseInt(parts[1], 10) !== pageMonth) return;
        var day = parseInt(parts[2], 10);
        var byCourt = (j.cancellations[dateKey] || {})[loc];
        if (byCourt) applyOverlay(trs, pageMonth, day, byCourt);
      });
    })
    .catch(function () { /* 通信失敗時は何もしない */ });

  function reasonJP(k) {
    return ({ rain: '雨', heat: '熱中症警戒', thunder: '雷', wind: '強風', other: 'その他' })[k] || k;
  }

  function applyOverlay(trs, month, day, byCourt) {
    if (!byCourt || (!byCourt.A && !byCourt.B)) return;
    var dateRe = new RegExp(month + '月\\s*' + day + '日');

    for (var i = 0; i < trs.length; i++) {
      var tds = trs[i].querySelectorAll('td');
      if (!tds.length) continue;
      var matched = false;
      for (var k = 0; k < tds.length; k++) {
        if (dateRe.test(tds[k].textContent)) { matched = true; break; }
      }
      if (!matched) continue;

      // 対象日の行（A面）と次の行（B面）。トップページの解析と同じ列構成。
      var tdsA = trs[i].querySelectorAll('td');            // [日付,曜日,A,9-11,11-13,13-15,15-17,メモ]
      var tdsB = trs[i + 1] ? trs[i + 1].querySelectorAll('td') : []; // [B,9-11,11-13,13-15,15-17]
      markCourt(byCourt.A, [tdsA[3], tdsA[4], tdsA[5], tdsA[6]]);
      markCourt(byCourt.B, [tdsB[1], tdsB[2], tdsB[3], tdsB[4]]);
      return;
    }
  }

  function markCourt(courtData, cells) {
    if (!courtData) return;
    for (var s = 0; s < 4; s++) {
      var entry = courtData['slot' + s];
      var td = cells[s];
      if (!entry || !td) continue;
      td.textContent = '中止';
      td.style.color = '#fff';
      td.style.backgroundColor = '#d63031';
      td.style.fontWeight = 'bold';
      td.title = '中止（' + reasonJP(entry.reason) + '） キャンセル者: ' + (entry.name || '');
    }
  }
})();
