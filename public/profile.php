<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';

require_auth(); // Profil jest dostepny tylko dla zalogowanego uzytkownika.

$auth = new Auth(); // Tworzy obiekt obslugi konta uzytkownika.
$user = current_user(); // Pobiera dane uzytkownika z sesji.
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sprawdza token CSRF przed zmiana profilu.
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? ''); // Okresla, ktora akcja profilu ma byc wykonana.

        if ($action === 'change_password') {
            $result = $auth->changePassword(
                (int) $user['id'],
                (string) ($_POST['current_password'] ?? ''),
                (string) ($_POST['new_password'] ?? ''),
                (string) ($_POST['confirm_password'] ?? '')
            );
            $success = $result['ok'] ? 'Password changed successfully.' : '';
            $errors = $result['errors'];
        } elseif ($action === 'update_avatar') {
            $result = $auth->updateAvatar((int) $user['id'], $_FILES['avatar'] ?? []);
            $success = $result['ok'] ? 'Avatar updated successfully.' : '';
            $errors = $result['errors'];
            $user = current_user();
        } elseif ($action === 'delete_account') {
            $result = $auth->deleteAccount((int) $user['id'], (string) ($_POST['delete_password'] ?? ''));

            if ($result['ok']) {
                redirect('/register.php');
            }

            $errors = $result['errors'];
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile | Trip Planner</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <header class="app-header">
        <div>
            <p class="eyebrow">User Profile</p>
            <h1><?= e($user['username']) ?></h1>
        </div>
        <div class="header-actions">
            <a class="button-link secondary-link" href="/dashboard.php">Back to dashboard</a>
            <form method="post" action="/logout.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="secondary header-button">Logout</button>
            </form>
        </div>
    </header>

    <main class="dashboard-shell">
        <?php if ($success !== ''): ?>
            <div class="notice" role="status"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="alert" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="profile-grid" aria-label="Profile settings">
            <article class="profile-panel">
                <h2>Avatar</h2>
                <div class="profile-avatar-preview">
                    <?php if (!empty($user['avatar_path'])): ?>
                        <img src="<?= e($user['avatar_path']) ?>" alt="">
                    <?php else: ?>
                        <span class="default-avatar large" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M12 12c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4Zm0 2c-3.3 0-6 2.1-6 4.7 0 .7.6 1.3 1.3 1.3h9.4c.7 0 1.3-.6 1.3-1.3 0-2.6-2.7-4.7-6-4.7Z"/>
                            </svg>
                        </span>
                    <?php endif; ?>
                </div>
                <form method="post" action="/profile.php" enctype="multipart/form-data" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_avatar">

                    <label for="avatar">Choose avatar image</label>
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp,image/gif" required>

                    <button type="submit">Change Avatar</button>
                </form>
            </article>

            <article class="profile-panel">
                <h2>Change password</h2>
                <form method="post" action="/profile.php" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="change_password">

                    <label for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>

                    <label for="new_password">New password</label>
                    <input id="new_password" name="new_password" type="password" autocomplete="new-password" required minlength="8">

                    <label for="confirm_password">Confirm new password</label>
                    <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required minlength="8">

                    <button type="submit">Update Password</button>
                </form>
            </article>

            <article class="profile-panel danger-panel">
                <h2>Delete account</h2>
                <p class="muted">This permanently removes your account. Enter your current password to confirm.</p>
                <form method="post" action="/profile.php" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_account">

                    <label for="delete_password">Current password</label>
                    <input id="delete_password" name="delete_password" type="password" autocomplete="current-password" required>

                    <button type="submit" class="danger-button">Delete Account</button>
                </form>
            </article>
        </section>
    </main>
</body>
</html>
