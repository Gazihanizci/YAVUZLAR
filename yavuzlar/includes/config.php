<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_PATH', __DIR__ . '/../database/app.sqlite');
define('CANCEL_LIMIT_HOURS', 2);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        $target = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
        redirect('login.php?redirect=' . urlencode($target));
    }
}

function require_role(array $allowedRoles): void
{
    $user = current_user();
    if (!$user || !in_array($user['role'], $allowedRoles, true)) {
        redirect('index.php');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

?>
