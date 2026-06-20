<?php
/**
 * コート中止連絡 API の設定（テンプレート）
 *
 * ■ 使い方（FTPで1回だけ作業）
 *   1. このファイルをコピーし、ファイル名を「cancel_config.php」に変える
 *   2. 置き場所は public_html の「1つ上」のフォルダが推奨（Web非公開・GitHub非同期）
 *        例) /kasukou2025.stars.ne.jp/cancel_config.php
 *      もし1つ上に置けない場合は public_html/api/cancel_config.php でも動きます
 *   3. 下の 'password' を、担当者だけが知る合言葉に書き換える
 *
 * ※ このサンプル(.sample)ファイルは合言葉ではないので、そのままでは動きません。
 * ※ 合言葉はこのファイルに平文で書きますが、PHPファイルは中身がWebに出ないため安全です。
 */

return [
    // 中止登録／取消に必要な合言葉（半角英数推奨・必ず変更してください）
    'password' => 'CHANGE_ME',
];
