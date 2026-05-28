<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Adventure.php';

require_auth();

$user = current_user();
$adventureRepository = new Adventure();
$adventureResult = $adventureRepository->listForUser((int) $user['id']);
$adventures = $adventureResult['adventures'];
$errors = $adventureResult['errors'];
$today = date('Y-m-d');
$plannedAdventures = array_values(array_filter($adventures, static fn (array $adventure): bool => $adventure['end_date'] >= $today));
$pastAdventures = array_values(array_filter($adventures, static fn (array $adventure): bool => $adventure['end_date'] < $today));
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
        <section class="dashboard-toolbar" aria-labelledby="adventures-title">
            <div>
                <h2 id="adventures-title">Trips</h2>
                <p class="muted">All planned and past trips saved in your account.</p>
            </div>
            <a class="button-link" href="/adventures/create.php">+ Create New Adventure</a>
        </section>

        <?php if ($errors !== []): ?>
            <div class="alert dashboard-alert" role="alert">
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
                    <article class="adventure-card">
                        <div class="card-topline">
                            <span><?= e(date('M j, Y', strtotime($adventure['start_date']))) ?> - <?= e(date('M j, Y', strtotime($adventure['end_date']))) ?></span>
                            <span><?= e($adventure['destination_region']) ?></span>
                        </div>
                        <h3><?= e($adventure['title']) ?></h3>
                        <p><?= e(strlen($adventure['description']) > 150 ? substr($adventure['description'], 0, 147) . '...' : $adventure['description']) ?></p>
                        <div class="card-actions">
                            <a class="small-link-button" href="/adventures/edit.php?id=<?= e((string) $adventure['id']) ?>">Edit</a>
                            <form method="post" action="/adventures/delete.php">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= e((string) $adventure['id']) ?>">
                                <button type="submit" class="small-danger-button">Delete</button>
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
                    <article class="adventure-card">
                        <div class="card-topline">
                            <span><?= e(date('M j, Y', strtotime($adventure['start_date']))) ?> - <?= e(date('M j, Y', strtotime($adventure['end_date']))) ?></span>
                            <span><?= e($adventure['destination_region']) ?></span>
                        </div>
                        <h3><?= e($adventure['title']) ?></h3>
                        <p><?= e(strlen($adventure['description']) > 150 ? substr($adventure['description'], 0, 147) . '...' : $adventure['description']) ?></p>
                        <div class="card-actions">
                            <a class="small-link-button" href="/adventures/edit.php?id=<?= e((string) $adventure['id']) ?>">Edit</a>
                            <form method="post" action="/adventures/delete.php">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= e((string) $adventure['id']) ?>">
                                <button type="submit" class="small-danger-button">Delete</button>
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
