<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Adventure.php';

require_auth(); // Dashboard jest dostepny tylko po zalogowaniu.

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$user = current_user(); // Pobiera aktualnego uzytkownika z sesji.
$adventureRepository = new Adventure(); // Tworzy obiekt do pracy z podrozami.
$adventureResult = $adventureRepository->listForUser((int) $user['id']); // Pobiera podroze tylko tego uzytkownika.
$adventures = $adventureResult['adventures'];
$errors = $adventureResult['errors'];
$now = date('Y-m-d H:i:s');
$plannedAdventures = array_values(array_filter($adventures, static fn (array $adventure): bool => $adventure['end_date'] >= $now)); // Oddziela przyszle/aktywne podroze.
$pastAdventures = array_values(array_filter($adventures, static fn (array $adventure): bool => $adventure['end_date'] < $now)); // Oddziela zakonczone podroze.
$flashErrors = consume_flash('error'); // Pobiera jednorazowe bledy po przekierowaniu.
$flashSuccess = consume_flash('success'); // Pobiera jednorazowe komunikaty sukcesu.
$notice = match ($_GET['status'] ?? '') {
    'created' => 'Trip was created successfully.',
    'updated' => 'Trip was updated successfully.',
    'deleted' => 'Trip was deleted successfully.',
    default => '',
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account | Trip Planner</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body class="dashboard-background">
    <header class="app-header">
        <div>
            <p class="eyebrow">Trip Planner Dashboard</p>
            <h1>Welcome back, <?= e($user['username']) ?>!</h1>
        </div>
        <div class="header-actions">
            <form method="post" action="/logout.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="secondary header-button">Logout</button>
            </form>
            <a class="avatar-link" href="/profile.php" title="Open profile" aria-label="Open profile">
                <?php if (!empty($user['avatar_path'])): ?>
                    <img src="<?= e($user['avatar_path']) ?>" alt="">
                <?php else: ?>
                    <span class="default-avatar" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M12 12c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4Zm0 2c-3.3 0-6 2.1-6 4.7 0 .7.6 1.3 1.3 1.3h9.4c.7 0 1.3-.6 1.3-1.3 0-2.6-2.7-4.7-6-4.7Z"/>
                        </svg>
                    </span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <main class="dashboard-shell">
        <?php foreach ($flashSuccess as $message): ?>
            <div class="notice" role="status"><?= e((string) $message) ?></div>
        <?php endforeach; ?>

        <?php if ($notice !== ''): ?>
            <div class="notice" role="status"><?= e($notice) ?></div>
        <?php endif; ?>

        <section class="dashboard-toolbar" aria-labelledby="adventures-title">
            <div>
                <h2 id="adventures-title">Trips</h2>
                <p class="muted">All planned trips in your account.</p>
            </div>
            <a class="button-link" href="/adventures/create.php">+ Create New Adventure</a>
        </section>

        <?php if ($errors !== [] || $flashErrors !== []): ?>
            <div class="alert dashboard-alert" role="alert">
                <?php foreach ($flashErrors as $error): ?>
                    <p><?= e((string) $error) ?></p>
                <?php endforeach; ?>
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($adventures === [] && $errors === []): ?>
            <section class="empty-state" aria-label="No planned adventures">
                <h2>No trips yet</h2>
                <p class="muted">Create your first trip and it will appear here.</p>
            </section>
        <?php else: ?>
            <section class="trip-section" aria-labelledby="planned-trips-title">
                <h2 id="planned-trips-title">Planned trips</h2>
                <?php if ($plannedAdventures === []): ?>
                    <p class="muted">No planned trips yet.</p>
                <?php endif; ?>
                <div class="adventure-grid" aria-label="Planned trips list">
                <?php foreach ($plannedAdventures as $adventure): ?>
                    <?php $adventureId = (string) ($adventure['adventure_id'] ?? $adventure['id'] ?? ''); ?>
                    <article class="adventure-card clickable-card">
                        <a class="card-edit-link" href="/adventures/edit.php?adventure_id=<?= e($adventureId) ?>" aria-label="Edit <?= e($adventure['title']) ?>">
                            <div class="card-topline">
                                <span><?= e($adventureRepository->formatDateTime($adventure['start_date'])) ?> - <?= e($adventureRepository->formatDateTime($adventure['end_date'])) ?></span>
                                <span><?= e($adventure['destination_region']) ?></span>
                            </div>
                            <h3><?= e($adventure['title']) ?></h3>
                            <p><?= e(strlen($adventure['description']) > 150 ? substr($adventure['description'], 0, 147) . '...' : $adventure['description']) ?></p>
                        </a>
                        <div class="card-actions">
                            <form method="post" action="/adventures/delete.php" class="inline-action-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="adventure_id" value="<?= e($adventureId) ?>">
                                <button type="submit" class="small-danger-button" onclick="return confirm('Delete this trip?')">Delete</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            </section>

            <section class="trip-section" aria-labelledby="past-trips-title">
                <h2 id="past-trips-title">Past trips</h2>
                <?php if ($pastAdventures === []): ?>
                    <p class="muted">No past trips yet.</p>
                <?php endif; ?>
                <div class="adventure-grid" aria-label="Past trips list">
                <?php foreach ($pastAdventures as $adventure): ?>
                    <?php $adventureId = (string) ($adventure['adventure_id'] ?? $adventure['id'] ?? ''); ?>
                    <article class="adventure-card clickable-card">
                        <a class="card-edit-link" href="/adventures/edit.php?adventure_id=<?= e($adventureId) ?>" aria-label="Edit <?= e($adventure['title']) ?>">
                            <div class="card-topline">
                                <span><?= e($adventureRepository->formatDateTime($adventure['start_date'])) ?> - <?= e($adventureRepository->formatDateTime($adventure['end_date'])) ?></span>
                                <span><?= e($adventure['destination_region']) ?></span>
                            </div>
                            <h3><?= e($adventure['title']) ?></h3>
                            <p><?= e(strlen($adventure['description']) > 150 ? substr($adventure['description'], 0, 147) . '...' : $adventure['description']) ?></p>
                        </a>
                        <div class="card-actions">
                            <form method="post" action="/adventures/delete.php" class="inline-action-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="adventure_id" value="<?= e($adventureId) ?>">
                                <button type="submit" class="small-danger-button" onclick="return confirm('Delete this trip?')">Delete</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
