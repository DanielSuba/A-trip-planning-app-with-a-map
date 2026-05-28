<?php

declare(strict_types=1);

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function current_user(): ?array
{
    start_session();

    return $_SESSION['user'] ?? null;
}

function require_guest(): void
{
    if (current_user() !== null) {
        redirect('/dashboard.php');
    }
}

function require_auth(): void
{
    if (current_user() === null) {
        redirect('/login.php');
    }
}

function csrf_token(): string
{
    start_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    start_session();

    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function old(string $key, array $source): string
{
    $value = $source[$key] ?? '';

    return is_string($value) ? $value : '';
}

function flash(string $key, string $message): void
{
    start_session();
    $_SESSION['flash'][$key][] = $message;
}

function consume_flash(string $key): array
{
    start_session();
    $messages = $_SESSION['flash'][$key] ?? [];
    unset($_SESSION['flash'][$key]);

    return is_array($messages) ? $messages : [];
}
