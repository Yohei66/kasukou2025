<?php
/**
 * 管理者認証の共通処理（セッション）
 *
 * - 設定(admin_config.php)は公開フォルダの外を優先して読み込む
 * - ログイン状態の確認・要求、ログイン/ログアウト、CSRFトークンを提供
 *
 * 各管理ページの先頭で require し、保護したいページでは admin_require_login() を呼ぶ。
 */

// 設定ファイルのBOM/空白がヘッダ送出やリダイレクトを壊さないようにバッファ
if (function_exists('ob_get_level') && ob_get_level() === 0) {
    ob_start();
}

// セッション（HTTPS前提のセキュアCookie）
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
    ]);
    session_name('KASUKOU_ADMIN');
    session_start();
}

/** 管理者設定を読み込む（公開領域の外を優先） */
function admin_config(): ?array
{
    static $cfg = false;
    if ($cfg !== false) {
        return $cfg;
    }
    $candidates = [
        dirname(__DIR__, 2) . '/admin_config.php', // public_html の1つ上
        __DIR__ . '/admin_config.php',             // admin/ 直下（フォールバック）
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            $c = require $path;
            if (is_array($c) && !empty($c['admin_user'])) {
                $cfg = $c;
                return $cfg;
            }
        }
    }
    $cfg = null;
    return $cfg;
}

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

/** ログイン必須ページの先頭で呼ぶ。未ログインならログイン画面へ */
function admin_require_login(): void
{
    if (!admin_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/** 認証情報を検証してログイン。成功でtrue */
function admin_login(string $user, string $pass): bool
{
    $cfg = admin_config();
    if (!$cfg) {
        return false;
    }
    if (!hash_equals((string)$cfg['admin_user'], $user)) {
        usleep(300000);
        return false;
    }
    $ok = false;
    if (!empty($cfg['admin_pass_hash'])) {
        $ok = password_verify($pass, (string)$cfg['admin_pass_hash']);
    } elseif (isset($cfg['admin_pass'])) {
        $ok = hash_equals((string)$cfg['admin_pass'], $pass);
    }
    if (!$ok) {
        usleep(300000);
        return false;
    }
    // セッション固定化対策
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = $cfg['admin_user'];
    return true;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** CSRFトークン（フォーム/APIのPOST保護） */
function admin_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function admin_csrf_check(?string $token): bool
{
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}
