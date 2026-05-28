<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/Adventure.php';

require_auth();

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $adventureRepository = new Adventure();
        $result = $adventureRepository->create((int) $user['id'], $_POST);

        if ($result['ok']) {
            foreach ($result['warnings'] ?? [] as $warning) {
                flash('error', $warning);
            }

            redirect('/dashboard.php?status=created');
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
    <title>Create Adventure | Trip Planner</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <header class="app-header">
        <div>
            <p class="eyebrow">New Adventure</p>
            <h1>Create New Adventure</h1>
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

        <form method="post" action="/adventures/create.php" class="adventure-create-layout">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input id="latitude" name="latitude" type="hidden" value="<?= e(old('latitude', $_POST)) ?>">
            <input id="longitude" name="longitude" type="hidden" value="<?= e(old('longitude', $_POST)) ?>">

            <section class="adventure-form-panel" aria-labelledby="create-adventure-title">
                <h2 id="create-adventure-title">Trip information</h2>

                <div class="auth-form">
                    <label for="title">Trip Name</label>
                    <input id="title" name="title" type="text" required maxlength="120" value="<?= e(old('title', $_POST)) ?>">

                    <label for="destination_region">Destination Country / Region</label>
                    <input id="destination_region" name="destination_region" type="text" required maxlength="255" value="<?= e(old('destination_region', $_POST)) ?>">

                    <div class="date-row">
                        <div>
                            <label for="start_date">Start Date and Time</label>
                            <input id="start_date" name="start_date" type="datetime-local" required value="<?= e(old('start_date', $_POST)) ?>">
                        </div>
                        <div>
                            <label for="end_date">End Date and Time</label>
                            <input id="end_date" name="end_date" type="datetime-local" required value="<?= e(old('end_date', $_POST)) ?>">
                        </div>
                    </div>

                    <label for="description">Brief Description</label>
                    <textarea id="description" name="description" rows="6" required><?= e(old('description', $_POST)) ?></textarea>

                    <div class="coordinate-display" aria-live="polite">
                        <span>Latitude: <strong id="latitudeDisplay"><?= e(old('latitude', $_POST) ?: 'not selected') ?></strong></span>
                        <span>Longitude: <strong id="longitudeDisplay"><?= e(old('longitude', $_POST) ?: 'not selected') ?></strong></span>
                    </div>

                    <button type="submit">Save Adventure</button>
                </div>
            </section>

            <section class="map-panel" aria-labelledby="map-title">
                <div class="map-heading">
                    <h2 id="map-title">Choose location</h2>
                    <p class="muted">Click the map to set the trip point and check weather.</p>
                </div>
                <div id="adventureMap" class="leaflet-map"></div>
                <div id="selectedPointWeather" class="selected-point-weather" data-adventure-id="not saved yet" aria-live="polite"></div>
            </section>
        </form>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="/assets/js/adventure-map.js"></script>
</body>
</html>
