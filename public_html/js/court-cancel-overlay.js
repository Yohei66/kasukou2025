/**
 * 月別コート予約状況ページに「本日の中止」を重ねて表示する。
 *
 * - このページのURL（.../NEWFORMAT/<年>/coatbooking<月>-<場所>.html）から
 *   年・月・場所を判定する。
 * - 中止は当日分のみ意味を持つため、表示中のページが「今月」のときだけ動作する。
 * - api/cancel.php から本日の中止情報を取得し、本日の行の該当マス（A面/B面×時間帯）を
 *   「中止」（赤）に置き換える。
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

  var now = new Date();
  // 表示中のページが今月でなければ、本日の中止が載ることはないので何もしない
  if (now.getFullYear() !== pageYear || (now.getMonth() + 1) !== pageMonth) return;

  var month = now.getMonth() + 1;
  var day = now.getDate();
  var dateKey = pageYear + '-' + ('0' + pageMonth).slice(-2) + '-' + ('0' + day).slice(-2);

  // このページは /coatbokking/NEWFORMAT/<年>/ にあるので、公開ルートまで3つ上がる
  fetch('../../../api/cancel.php?date=' + dateKey, { cache: 'no-store' })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (j) {
      if (!j || !j.ok || !j.cancellations) return;
      applyOverlay(j.cancellations[loc] || {});
    })
    .catch(function () { /* 通信失敗時は何もしない */ });

  function reasonJP(k) {
    return ({ rain: '雨', heat: '熱中症警戒', thunder: '雷', wind: '強風', other: 'その他' })[k] || k;
  }

  function applyOverlay(byCourt) {
    if (!byCourt || (!byCourt.A && !byCourt.B)) return;
    var table = document.querySelector('table.auto-style27');
    if (!table) return;

    var trs = Array.prototype.slice.call(table.querySelectorAll('tr'));
    var dateRe = new RegExp(month + '月\\s*' + day + '日');

    for (var i = 0; i < trs.length; i++) {
      var tds = trs[i].querySelectorAll('td');
      if (!tds.length) continue;
      var matched = false;
      for (var k = 0; k < tds.length; k++) {
        if (dateRe.test(tds[k].textContent)) { matched = true; break; }
      }
      if (!matched) continue;

      // 本日の行（A面）と次の行（B面）。トップページの解析と同じ列構成。
      var tdsA = trs[i].querySelectorAll('td');            // [日付,曜日,A,9-11,11-13,13-15,15-17,メモ]
      var tdsB = trs[i + 1] ? trs[i + 1].querySelectorAll('td') : []; // [B,9-11,11-13,13-15,15-17]
      markCourt(byCourt.A, [tdsA[3], tdsA[4], tdsA[5], tdsA[6]]);
      markCourt(byCourt.B, [tdsB[1], tdsB[2], tdsB[3], tdsB[4]]);
      break;
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
