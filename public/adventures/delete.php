<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/Adventure.php';

require_auth(); // Usuwanie podrozy wymaga logowania.

$user = current_user(); // Pobiera aktualnego uzytkownika.
$adventureRepository = new Adventure(); // Tworzy obiekt zarzadzania podrozami.
$id = (int) ($_REQUEST['adventure_id'] ?? $_REQUEST['id'] ?? 0); // Pobiera ID usuwanej podrozy.
$errors = [];

if ($id <= 0) {
    flash('error', 'Trip id is missing. Refresh the dashboard and try again.');
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sprawdza token CSRF przed usunieciem podrozy.
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your session expired. Please try again.');
        redirect('/dashboard.php');
    }

    $result = $adventureRepository->delete($id, (int) $user['id']); // Usuwa podroz tylko jesli nalezy do uzytkownika.

    if ($result['ok']) {
    redirect('/dashboard.php');
    }

    foreach ($result['errors'] as $error) {
        flash('error', $error);
    }

    redirect('/dashboard.php');
}

try {
    $adventure = $adventureRepository->findForUser($id, (int) $user['id']);
} catch (Throwable $exception) {
    flash('error', $adventureRepository->databaseErrorMessage($exception));
    redirect('/dashboard.php');
}

if (!$adventure) {
    flash('error', 'Trip was not found.');
    redirect('/dashboard.php');
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delete Adventure | Trip Planner</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <header class="app-header">
        <div>
            <p class="eyebrow">Delete Trip</p>
            <h1>Delete Adventure</h1>
        </div>
        <div class="header-actions">
            <a class="button-link secondary-link" href="/dashboard.php">Back to dashboard</a>
        </div>
    </header>

    <main class="dashboard-shell">
        <?php if ($errors !== []): ?>
            <div class="alert" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="delete-panel" aria-labelledby="delete-title">
            <h2 id="delete-title">Are you sure?</h2>
            <p class="muted">This will permanently delete this trip from your account.</p>

            <article class="delete-summary">
                <h3><?= e($adventure['title']) ?></h3>
                <p><?= e($adventure['destination_region']) ?></p>
                <p><?= e($adventureRepository->formatDateTime($adventure['start_date'])) ?> - <?= e($adventureRepository->formatDateTime($adventure['end_date'])) ?></p>
            </article>

            <form method="post" action="/adventures/delete.php?adventure_id=<?= e((string) $id) ?>" class="delete-actions">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e((string) $id) ?>">
                <input type="hidden" name="adventure_id" value="<?= e((string) $id) ?>">
                <a class="button-link secondary-link" href="/dashboard.php">Cancel</a>
                <button type="submit" class="danger-button">Delete Trip</button>
            </form>
        </section>
    </main>
</body>
</html>
