<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';

require_guest(); // Blokuje dostep do rejestracji dla zalogowanego uzytkownika.

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sprawdza token CSRF przed obsluga formularza rejestracji.
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $auth = new Auth(); // Tworzy obiekt obslugi autoryzacji.
        $result = $auth->register(
            trim((string) ($_POST['username'] ?? '')),
            trim((string) ($_POST['email'] ?? '')),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['confirm_password'] ?? '')
        );

        if ($result['ok']) {
            redirect('/dashboard.php'); // Po rejestracji przenosi uzytkownika na dashboard.
        }

        $errors = $result['errors'];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account | Trip Planner</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <main class="auth-shell">
        <section class="auth-panel" aria-labelledby="register-title">
            <p class="eyebrow">Start planning privately</p>
            <h1 id="register-title">Create your account</h1>

            <?php if ($errors !== []): ?>
                <div class="alert" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/register.php" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <label for="username">Username</label>
                <input id="username" name="username" type="text" autocomplete="username" required minlength="3" maxlength="30" value="<?= e(old('username', $_POST)) ?>">

                <label for="email">Email</label>
                <input id="email" name="email" type="email" autocomplete="email" required value="<?= e(old('email', $_POST)) ?>">

                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8">

                <label for="confirm_password">Confirm Password</label>
                <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required minlength="8">

                <button type="submit">Register</button>
            </form>

            <p class="switch-link">Already have an account? <a href="/login.php">Log in</a></p>
        </section>
    </main>
</body>
</html>
