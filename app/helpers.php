<?php

declare(strict_types=1);

// Funkcja dla uruchamiania sesji tylko wtedy, gdy nie jest jeszcze aktywna.
function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start(); // Startuje sesje PHP dla logowania i komunikatow flash.
    }
}

// Funkcja dla bezpiecznego wyswietlania tekstu w HTML.
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); // Chroni przed wstrzyknieciem HTML/JS.
}

// Funkcja dla przekierowania uzytkownika na inna strone.
function redirect(string $path): never
{
    header('Location: ' . $path); // Ustawia naglowek HTTP Location.
    exit; // Konczy skrypt po przekierowaniu.
}

// Funkcja dla pobrania aktualnie zalogowanego uzytkownika z sesji.
function current_user(): ?array
{
    start_session();

    return $_SESSION['user'] ?? null;
}

// Funkcja dla blokowania stron logowania/rejestracji dla zalogowanych uzytkownikow.
function require_guest(): void
{
    if (current_user() !== null) {
        redirect('/dashboard.php');
    }
}

// Funkcja dla ochrony stron, ktore wymagaja logowania.
function require_auth(): void
{
    if (current_user() === null) {
        redirect('/login.php');
    }
}

// Funkcja dla wygenerowania tokenu CSRF dla formularzy.
function csrf_token(): string
{
    start_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Tworzy losowy token zabezpieczajacy formularze.
    }

    return $_SESSION['csrf_token'];
}

// Funkcja dla sprawdzenia poprawnosci tokenu CSRF.
function verify_csrf_token(?string $token): bool
{
    start_session();

    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// Funkcja dla przywracania poprzedniej wartosci pola formularza.
function old(string $key, array $source): string
{
    $value = $source[$key] ?? '';

    return is_string($value) ? $value : '';
}

// Funkcja dla zapisania jednorazowego komunikatu do sesji.
function flash(string $key, string $message): void
{
    start_session();
    $_SESSION['flash'][$key][] = $message; // Komunikat zostanie pokazany po przekierowaniu.
}

// Funkcja dla odczytania i usuniecia jednorazowych komunikatow.
function consume_flash(string $key): array
{
    start_session();
    $messages = $_SESSION['flash'][$key] ?? [];
    unset($_SESSION['flash'][$key]); // Usuwa komunikaty po pierwszym wyswietleniu.

    return is_array($messages) ? $messages : [];
}
