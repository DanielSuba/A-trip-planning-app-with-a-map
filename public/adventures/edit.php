<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/Adventure.php';

require_auth(); // Edycja podrozy wymaga logowania.

$user = current_user(); // Pobiera aktualnego uzytkownika.
$adventureRepository = new Adventure(); // Tworzy obiekt zarzadzania podrozami.
$id = (int) ($_REQUEST['adventure_id'] ?? $_REQUEST['id'] ?? 0); // Pobiera ID podrozy z GET lub POST.
$errors = [];

if ($id <= 0) {
    flash('error', 'Trip id is missing. Refresh the dashboard and try again.');
    redirect('/dashboard.php');
}

try {
    $adventure = $id > 0 ? $adventureRepository->findForUser($id, (int) $user['id']) : null; // Laduje tylko podroz nalezaca do uzytkownika.
} catch (Throwable $exception) {
    $adventure = null;
    flash('error', $adventureRepository->databaseErrorMessage($exception));
    redirect('/dashboard.php');
}

if (!$adventure && $errors === []) {
    flash('error', 'Trip was not found.');
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sprawdza token CSRF przed aktualizacja podrozy.
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $result = $adventureRepository->update($id, (int) $user['id'], $_POST); // Aktualizuje podroz i zapisuje nowy snapshot pogody.

        if ($result['ok']) {
            foreach ($result['warnings'] ?? [] as $warning) {
                flash('error', $warning);
            }

            redirect('/dashboard.php'); // Po edycji wraca na dashboard.
        }

        $errors = $result['errors'];
        $adventure = array_merge($adventure ?? [], $_POST);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Adventure | Trip Planner</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <header class="app-header">
        <div>
            <p class="eyebrow">Edit Trip</p>
            <h1>Edit Adventure</h1>
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

        <form method="post" action="/adventures/edit.php" class="adventure-create-layout">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e((string) $id) ?>">
            <input type="hidden" name="adventure_id" value="<?= e((string) $id) ?>">
            <input id="latitude" name="latitude" type="hidden" value="<?= e((string) ($adventure['latitude'] ?? '')) ?>">
            <input id="longitude" name="longitude" type="hidden" value="<?= e((string) ($adventure['longitude'] ?? '')) ?>">

            <section class="adventure-form-panel" aria-labelledby="edit-adventure-title">
                <h2 id="edit-adventure-title">Trip information</h2>

                <div class="auth-form">
                    <label for="title">Trip Name</label>
                    <input id="title" name="title" type="text" required maxlength="120" value="<?= e((string) ($adventure['title'] ?? '')) ?>">

                    <label for="destination_region">Destination Country / Region</label>
                    <input id="destination_region" name="destination_region" type="text" required maxlength="255" value="<?= e((string) ($adventure['destination_region'] ?? '')) ?>">

                    <div class="date-row">
                        <div>
                            <label for="start_date">Start Date and Time</label>
                            <input id="start_date" name="start_date" type="datetime-local" required value="<?= e($adventureRepository->toDateTimeLocal((string) ($adventure['start_date'] ?? ''))) ?>">
                        </div>
                        <div>
                            <label for="end_date">End Date and Time</label>
                            <input id="end_date" name="end_date" type="datetime-local" required value="<?= e($adventureRepository->toDateTimeLocal((string) ($adventure['end_date'] ?? ''))) ?>">
                        </div>
                    </div>

                    <label for="description">Brief Description</label>
                    <textarea id="description" name="description" rows="6" required><?= e((string) ($adventure['description'] ?? '')) ?></textarea>

                    <div class="coordinate-display" aria-live="polite">
                        <span>Latitude: <strong id="latitudeDisplay"><?= e((string) (($adventure['latitude'] ?? '') ?: 'not selected')) ?></strong></span>
                        <span>Longitude: <strong id="longitudeDisplay"><?= e((string) (($adventure['longitude'] ?? '') ?: 'not selected')) ?></strong></span>
                    </div>

                    <button type="submit">Save Changes</button>
                </div>
            </section>

            <section class="map-panel" aria-labelledby="map-title">
                <div class="map-heading">
                    <h2 id="map-title">Choose location</h2>
                    <p class="muted">Click the map to update the trip point and check weather.</p>
                </div>
                <div id="adventureMap" class="leaflet-map"></div>
                <div id="selectedPointWeather" class="selected-point-weather" data-adventure-id="<?= e((string) $id) ?>" aria-live="polite"></div>
            </section>
        </form>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="/assets/js/adventure-map.js"></script>
</body>
</html>
