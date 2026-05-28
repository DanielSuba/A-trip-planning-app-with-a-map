<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

final class Auth
{
    private const AVATAR_UPLOAD_DIR = __DIR__ . '/../public/uploads/avatars';
    private const AVATAR_PUBLIC_DIR = '/uploads/avatars';
    private const MAX_AVATAR_SIZE = 2097152;

    // Funkcja dla rejestracji nowego uzytkownika.
    public function register(string $username, string $email, string $password, string $confirmPassword): array
    {
        $errors = $this->validateRegistration($username, $email, $password, $confirmPassword); // Sprawdza dane formularza rejestracji.

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        try {
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

            $passwordHash = password_hash($password, PASSWORD_DEFAULT); // Haszuje haslo przed zapisem do bazy.

            $statement = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash, created_at) VALUES (:username, :email, :password_hash, NOW())'
            );
            $statement->execute([
                'username' => $username,
                'email' => $email,
                'password_hash' => $passwordHash,
            ]);

            $this->loginSession((int) $pdo->lastInsertId(), $username, $email);
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'errors' => [$this->databaseErrorMessage($exception)],
            ];
        }

        return ['ok' => true, 'errors' => []];
    }

    // Funkcja dla logowania uzytkownika.
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

        try {
            $statement = db()->prepare('SELECT id, username, email, password_hash, avatar_path FROM users WHERE email = :email LIMIT 1');
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'errors' => [$this->databaseErrorMessage($exception)],
            ];
        }

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return [
                'ok' => false,
                'errors' => ['The email or password is incorrect.'],
            ];
        }

        $this->loginSession((int) $user['id'], $user['username'], $user['email'], $user['avatar_path'] ?? null); // Zapisuje dane uzytkownika w sesji.

        return ['ok' => true, 'errors' => []];
    }

    // Funkcja dla zmiany hasla zalogowanego uzytkownika.
    public function changePassword(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): array
    {
        $errors = [];

        if ($currentPassword === '') {
            $errors[] = 'Current password is required.';
        }

        if (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New password confirmation does not match.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        try {
            $user = $this->findUserById($userId);

            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return ['ok' => false, 'errors' => ['Current password is incorrect.']];
            }

            $statement = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $statement->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), // Zapisuje nowe haslo jako hash.
                'id' => $userId,
            ]);
        } catch (Throwable $exception) {
            return ['ok' => false, 'errors' => [$this->databaseErrorMessage($exception)]];
        }

        return ['ok' => true, 'errors' => []];
    }

    // Funkcja dla aktualizacji avatara uzytkownika.
    public function updateAvatar(int $userId, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'errors' => ['Please choose an avatar image.']];
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'errors' => ['Avatar upload failed. Please try again.']];
        }

        if (($file['size'] ?? 0) > self::MAX_AVATAR_SIZE) {
            return ['ok' => false, 'errors' => ['Avatar must be 2 MB or smaller.']];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $imageInfo = $tmpName !== '' ? @getimagesize($tmpName) : false;
        $mimeType = is_array($imageInfo) ? ($imageInfo['mime'] ?? '') : '';
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!isset($extensions[$mimeType])) {
            return ['ok' => false, 'errors' => ['Avatar must be a JPG, PNG, WebP, or GIF image.']];
        }

        if (!is_dir(self::AVATAR_UPLOAD_DIR) && !mkdir(self::AVATAR_UPLOAD_DIR, 0755, true)) {
            return ['ok' => false, 'errors' => ['Avatar folder could not be created.']];
        }

        $filename = sprintf('user-%d-%s.%s', $userId, bin2hex(random_bytes(8)), $extensions[$mimeType]);
        $targetPath = self::AVATAR_UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename; // Sciezka zapisu pliku na dysku.
        $publicPath = self::AVATAR_PUBLIC_DIR . '/' . $filename; // Sciezka dostepna dla przegladarki.

        if (!move_uploaded_file($tmpName, $targetPath)) {
            return ['ok' => false, 'errors' => ['Avatar could not be saved.']];
        }

        try {
            $user = $this->findUserById($userId);
            $statement = db()->prepare('UPDATE users SET avatar_path = :avatar_path WHERE id = :id');
            $statement->execute([
                'avatar_path' => $publicPath,
                'id' => $userId,
            ]);

            $this->deleteAvatarFile($user['avatar_path'] ?? null);
            $this->refreshSessionUser($userId);
        } catch (Throwable $exception) {
            $this->deleteAvatarFile($publicPath);

            return ['ok' => false, 'errors' => [$this->databaseErrorMessage($exception)]];
        }

        return ['ok' => true, 'errors' => []];
    }

    // Funkcja dla usuniecia konta uzytkownika.
    public function deleteAccount(int $userId, string $currentPassword): array
    {
        if ($currentPassword === '') {
            return ['ok' => false, 'errors' => ['Current password is required to delete your account.']];
        }

        try {
            $user = $this->findUserById($userId);

            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return ['ok' => false, 'errors' => ['Current password is incorrect.']];
            }

            $statement = db()->prepare('DELETE FROM users WHERE id = :id');
            $statement->execute(['id' => $userId]);
            $this->deleteAvatarFile($user['avatar_path'] ?? null); // Usuwa plik avatara po usunieciu konta.
            $this->logout(); // Czysci sesje po usunieciu konta.
        } catch (Throwable $exception) {
            return ['ok' => false, 'errors' => [$this->databaseErrorMessage($exception)]];
        }

        return ['ok' => true, 'errors' => []];
    }

    // Funkcja dla wylogowania uzytkownika.
    public function logout(): void
    {
        start_session();
        $_SESSION = []; // Czysci dane sesji.

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    // Funkcja dla walidacji danych rejestracji.
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

    // Funkcja dla odswiezenia danych uzytkownika zapisanych w sesji.
    public function refreshSessionUser(int $userId): void
    {
        $user = $this->findUserById($userId);

        if ($user) {
            $this->loginSession((int) $user['id'], $user['username'], $user['email'], $user['avatar_path'] ?? null);
        }
    }

    // Funkcja dla znalezienia uzytkownika po ID.
    private function findUserById(int $userId): ?array
    {
        $statement = db()->prepare('SELECT id, username, email, password_hash, avatar_path FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    // Funkcja dla zapisania danych logowania w sesji.
    private function loginSession(int $id, string $username, string $email, ?string $avatarPath = null): void
    {
        start_session();
        session_regenerate_id(true); // Chroni przed session fixation po logowaniu.

        $_SESSION['user'] = [
            'id' => $id,
            'username' => $username,
            'email' => $email,
            'avatar_path' => $avatarPath,
        ];
    }

    // Funkcja dla usuniecia pliku avatara z katalogu publicznego.
    private function deleteAvatarFile(?string $avatarPath): void
    {
        if (!$avatarPath || !str_starts_with($avatarPath, self::AVATAR_PUBLIC_DIR . '/')) {
            return;
        }

        $fullPath = __DIR__ . '/../public' . $avatarPath;

        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    // Funkcja dla zamiany wyjatkow bazy danych na czytelne komunikaty.
    private function databaseErrorMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'pdo_mysql')) {
            return 'Database setup error: PHP PDO MySQL is not enabled. Create or edit your php.ini file and enable extension=pdo_mysql.';
        }

        if (str_contains($message, 'actively refused') || str_contains($message, '[2002]')) {
            return 'Database connection error: MySQL is not running on the configured host and port. Start MySQL or update DB_HOST and DB_PORT in your environment.';
        }

        if (str_contains($message, 'Access denied') || str_contains($message, '[1045]')) {
            return 'Database connection error: MySQL rejected the username or password. Update DB_USERNAME and DB_PASSWORD.';
        }

        if (str_contains($message, 'Unknown database') || str_contains($message, '[1049]')) {
            return 'Database connection error: the trip_planner database does not exist yet. Import database/schema.sql first.';
        }

        if (str_contains($message, "doesn't exist") || str_contains($message, '[42S02]')) {
            return 'Database connection error: the users table does not exist yet. Import database/schema.sql first.';
        }

        if (str_contains($message, 'Unknown column') && str_contains($message, 'avatar_path')) {
            return 'Database setup error: the users table is missing avatar_path. Import database/migrations/001_add_avatar_path_to_users.sql.';
        }

        return 'Database connection error: ' . $message;
    }
}
