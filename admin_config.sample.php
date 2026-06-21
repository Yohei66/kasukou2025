<?php
/**
 * 管理者ページの設定（テンプレート）
 *
 * ■ 使い方（FTPで1回だけ）
 *   1. このファイルをコピーして「admin_config.php」にリネーム
 *   2. 置き場所は public_html の「1つ上」（cancel_config.php と同じ階層）が推奨
 *        例) /kasukou2025.stars.ne.jp/admin_config.php
 *      置けない場合は public_html/admin/admin_config.php でも動きます
 *   3. admin_user と合言葉を設定する
 *
 * ■ パスワードの設定方法（どちらか）
 *   (A) ハッシュ推奨: 下の 'admin_pass_hash' に password_hash() の結果を貼る
 *       生成例) PHPが使える環境で:
 *         php -r "echo password_hash('実際のパスワード', PASSWORD_DEFAULT), PHP_EOL;"
 *       生成された "$2y$..." を 'admin_pass_hash' に設定し、'admin_pass' は消す
 *   (B) 手軽: 'admin_pass' に平文を書く（PHPファイルなのでWebには出ません）
 *       ※(A)が用意できないときの暫定。落ち着いたら(A)に移行推奨
 */

return [
    'admin_user' => 'admin',

    // (A) ハッシュ（推奨）。使う場合はこの行を有効にして 'admin_pass' を削除
    // 'admin_pass_hash' => '$2y$10$ここにpassword_hashの結果',

    // (B) 平文（暫定）。必ず変更してください
    'admin_pass' => 'CHANGE_ME',
];
