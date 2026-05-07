<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';

require_guest();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $auth = new Auth();
        $result = $auth->login(
            trim((string) ($_POST['email'] ?? '')),
            (string) ($_POST['password'] ?? '')
        );

        if ($result['ok']) {
            redirect('/dashboard.php');
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
    <title>Log in | Trip Planner</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <main class="auth-shell">
        <section class="auth-panel" aria-labelledby="login-title">
            <p class="eyebrow">Welcome back</p>
            <h1 id="login-title">Log in to Trip Planner</h1>

            <?php if ($errors !== []): ?>
                <div class="alert" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/login.php" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <label for="email">Email</label>
                <input id="email" name="email" type="email" autocomplete="email" required value="<?= e(old('email', $_POST)) ?>">

                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>

                <button type="submit">Log in</button>
            </form>

            <p class="switch-link">No account yet? <a href="/register.php">Create one</a></p>
        </section>
    </main>
</body>
</html>
