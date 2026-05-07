<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

require_auth();

$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account | Trip Planner</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <main class="auth-shell">
        <section class="auth-panel compact" aria-labelledby="account-title">
            <p class="eyebrow">Private account</p>
            <h1 id="account-title">Hello, <?= e($user['username']) ?></h1>
            <p class="muted">Authentication is ready. Trip planning, map, and weather features can be added behind this account gateway next.</p>
            <form method="post" action="/logout.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="secondary">Log out</button>
            </form>
        </section>
    </main>
</body>
</html>
