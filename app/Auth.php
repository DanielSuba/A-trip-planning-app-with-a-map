<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

final class Auth
{
    public function register(string $username, string $email, string $password, string $confirmPassword): array
    {
        $errors = $this->validateRegistration($username, $email, $password, $confirmPassword);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $pdo = db();

        $exists = $pdo->prepare('SELECT id FROM users WHERE email = :email OR username = :username LIMIT 1');
        $exists->execute([
            'email' => $email,
            'username' => $username,
        ]);

        if ($exists->fetch()) {
            return [
                'ok' => false,
                'errors' => ['An account with this email or username already exists.'],
            ];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $statement = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, created_at) VALUES (:username, :email, :password_hash, NOW())'
        );
        $statement->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);

        $this->loginSession((int) $pdo->lastInsertId(), $username, $email);

        return ['ok' => true, 'errors' => []];
    }

    public function login(string $email, string $password): array
    {
        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $statement = db()->prepare('SELECT id, username, email, password_hash FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return [
                'ok' => false,
                'errors' => ['The email or password is incorrect.'],
            ];
        }

        $this->loginSession((int) $user['id'], $user['username'], $user['email']);

        return ['ok' => true, 'errors' => []];
    }

    public function logout(): void
    {
        start_session();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    private function validateRegistration(string $username, string $email, string $password, string $confirmPassword): array
    {
        $errors = [];

        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            $errors[] = 'Username must be 3-30 characters and use only letters, numbers, or underscores.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }

        return $errors;
    }

    private function loginSession(int $id, string $username, string $email): void
    {
        start_session();
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => $id,
            'username' => $username,
            'email' => $email,
        ];
    }
}
