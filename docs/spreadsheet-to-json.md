# スプレッドシート → 予約JSON エクスポート（管理者ページ用）

予約管理スプレッドシートから、管理者ページにアップロードするJSON（1ファイル＝1施設1か月）を出力するGoogle Apps Scriptマクロです。

## スプレッドシートの前提
- 上部に「年：／月：／コート：」のラベルがあり、右隣のセルに値（例：2026 / 9 / 大沼公園グラウンド）。
- 見出し行に「日・曜日・コート・時間・ID・備考」列。
- データ行：1行＝1コマ。`ID` 列が入っていれば確保（〇）、空なら未確保（×）。
- コート列は「共用Ａ」「共用Ｂ」等（全角・接頭辞可）。`Ｄ`/`D` を含めれば Dコート（B面のマスに "D" として反映）。
- 施設名：「大沼」を含む→`onuma`、「立沼」を含む→`tatenuma`。

## マクロ
```js
function ExportCourtJson() {
  var ws = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  var data = ws.getDataRange().getValues();

  // 1) 見出し行（日付列の見出し "日"）と各列位置
  var hdr=-1, cD=-1,cW=-1,cC=-1,cT=-1,cI=-1,cN=-1;
  for (var r=0; r<data.length && hdr<0; r++)
    for (var c=0; c<data[r].length; c++)
      if (String(data[r][c]).trim()==='日'){ hdr=r; cD=c; break; }
  if (hdr<0){ SpreadsheetApp.getUi().alert('見出し「日」が見つかりません'); return; }
  data[hdr].forEach(function(h,c){ var t=String(h).trim();
    if(t==='曜日')cW=c; if(t==='コート')cC=c; if(t==='時間')cT=c; if(t==='ID')cI=c; if(t==='備考')cN=c; });
  if([cW,cC,cT,cI,cN].indexOf(-1)>=0){ SpreadsheetApp.getUi().alert('必要な見出し(曜日/コート/時間/ID/備考)が見つかりません'); return; }

  // 2) 上部ラベルから 年/月/施設名 を取得（ラベルの右隣セル）
  function firstNum(row,from){ for(var c=from+1;c<row.length;c++){ var v=parseInt(String(row[c]).replace(/[^0-9]/g,''),10); if(v)return v; } return 0; }
  function firstText(row,from){ for(var c=from+1;c<row.length;c++){ var v=String(row[c]).trim(); if(v)return v; } return ''; }
  var year=0, month=0, facility='';
  for (var r=0;r<hdr;r++) for (var c=0;c<data[r].length;c++){ var t=String(data[r][c]).trim();
    if(/^年/.test(t)&&!year) year=firstNum(data[r],c);
    if(/^月/.test(t)&&!month) month=firstNum(data[r],c);
    if(/^コート/.test(t)&&!facility) facility=firstText(data[r],c); }
  var location = facility.indexOf('大沼')>=0?'onuma':facility.indexOf('立沼')>=0?'tatenuma':'';
  if(!year||!month||!location){ SpreadsheetApp.getUi().alert('年/月/施設名(大沼 or 立沼)が読めません: '+year+'/'+month+' '+facility); return; }

  // 3) ヘルパ
  function normCourt(s){ s=String(s); if(/[ＡA]/.test(s))return 'A'; if(/[ＢB]/.test(s))return 'B'; if(/[ＤD]/.test(s))return 'D'; return ''; }
  function timeSlot(s){ var start=String(s).split(/[～~\-]/)[0]; var h=parseInt(start,10); return h===9?0:h===11?1:h===13?2:h===15?3:-1; }
  var DOW=['日','月','火','水','木','金','土'];

  // 4) 集計（日 × コート × 4スロット）
  var days={};
  for (var r=hdr+1;r<data.length;r++){ var row=data[r], dt=row[cD];
    if(!(dt instanceof Date)) continue;
    var court=normCourt(row[cC]); if(!court) continue;
    var idx=timeSlot(row[cT]); if(idx<0) continue;
    var booked=String(row[cI]).trim()!=='';
    var dn=dt.getDate();
    if(!days[dn]) days[dn]={day:dn, dow:(String(row[cW]).trim()||DOW[dt.getDay()]), courts:{}, memo:{}};
    if(!days[dn].courts[court]) days[dn].courts[court]=['×','×','×','×'];
    if(booked) days[dn].courts[court][idx]='〇';
    var note=String(row[cN]).trim().replace(/　/g,' '); if(note) days[dn].memo[note]=true;
  }

  // 5) a(A面)/b(B面) へ。A/B以外（Dコート等）は B面のマスに "D" として畳み込む
  var out=[];
  Object.keys(days).map(Number).sort(function(a,b){return a-b;}).forEach(function(dn){
    var d=days[dn];
    var a=(d.courts['A']||['×','×','×','×']).slice();
    var b=(d.courts['B']||['×','×','×','×']).slice();
    Object.keys(d.courts).forEach(function(cn){ if(cn==='A'||cn==='B')return;
      d.courts[cn].forEach(function(v,i){ if(v==='〇') b[i]=cn; }); });
    out.push({ day:d.day, dow:d.dow, a:a, b:b, memo:Object.keys(d.memo).join(' / ') });
  });

  var json={ year:year, month:month, location:location, days:out };
  var name = year + ('0'+month).slice(-2) + '_' + location + '.json';
  // 保存先：既存のCSV出力フォルダがあれば DriveApp の代わりに _getCsvFolder() に置き換え可
  DriveApp.createFile(Utilities.newBlob(JSON.stringify(json,null,2),'application/json',name));
  SpreadsheetApp.getUi().alert('JSON出力: '+name+'（'+out.length+'日分）');
}
```

## 使い方
1. スプレッドシートのApps Scriptにこの関数を貼り付け、`ExportCourtJson` を実行。
2. Googleドライブに `YYYYMM_onuma.json` 等が出力される。
3. 管理者ページ →「JSONアップロード」でそのファイルを取り込む（保存＆公開ページ生成）。

## Dコートについて
- 現状の運用ではスプシにDコート行を入れていないが、将来「コート」列に `Ｄ`/`D` を含む行を追加すれば、自動でJSONに反映され、**B面の該当マスに "D"** が入る（公開ページでD表示・中止連絡の対象にもなる）。
- 「B面ではなくA面に入れたい」等の希望があれば、手順5の畳み込み先を変更する。
