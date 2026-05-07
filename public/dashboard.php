<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

require_auth();

$user = current_user();
$adventures = [
    [
        'title' => 'Mountain Weekend',
        'date' => '2026-06-12',
        'location' => 'Zakopane, Poland',
        'description' => 'A short hiking escape with scenic trails, fresh air, and space to check mountain weather before departure.',
    ],
    [
        'title' => 'City Food Walk',
        'date' => '2026-07-03',
        'location' => 'Vilnius, Lithuania',
        'description' => 'A relaxed route through old town streets, local cafes, and evening viewpoints.',
    ],
    [
        'title' => 'Lake Sunrise Trip',
        'date' => '2026-08-19',
        'location' => 'Braslaw Lakes, Belarus',
        'description' => 'Early morning drive, lakeside breakfast, and a calm route planned around the forecast.',
    ],
];
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
        <form method="post" action="/logout.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="secondary header-button">Logout</button>
        </form>
    </header>

    <main class="dashboard-shell">
        <section class="dashboard-toolbar" aria-labelledby="adventures-title">
            <div>
                <h2 id="adventures-title">Planned adventures</h2>
                <p class="muted">Your saved trips will appear here with dates, places, and notes.</p>
            </div>
            <a class="button-link" href="/adventures/create.php">+ Create New Adventure</a>
        </section>

        <section class="adventure-grid" aria-label="Planned adventures list">
            <?php foreach ($adventures as $adventure): ?>
                <article class="adventure-card">
                    <div class="card-topline">
                        <span><?= e(date('M j, Y', strtotime($adventure['date']))) ?></span>
                        <span><?= e($adventure['location']) ?></span>
                    </div>
                    <h3><?= e($adventure['title']) ?></h3>
                    <p><?= e($adventure['description']) ?></p>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
