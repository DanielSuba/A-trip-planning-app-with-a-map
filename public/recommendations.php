<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Adventure.php';

require_auth(); // Strona rekomendacji jest dostepna tylko dla zalogowanego uzytkownika.

$user = current_user(); // Pobiera aktualnego uzytkownika z sesji.
$adventureRepository = new Adventure(); // Tworzy repozytorium podrozy.
$adventureResult = $adventureRepository->listForUser((int) $user['id']); // Pobiera podroze uzytkownika do analizy pogody.
$adventures = $adventureResult['adventures'];
$errors = $adventureResult['errors'];

$recommendationAdventures = array_map(static function (array $adventure): array {
    return [
        'id' => (int) ($adventure['adventure_id'] ?? $adventure['id'] ?? 0),
        'title' => (string) ($adventure['title'] ?? ''),
        'destinationRegion' => (string) ($adventure['destination_region'] ?? ''),
        'startDate' => (string) ($adventure['start_date'] ?? ''),
        'endDate' => (string) ($adventure['end_date'] ?? ''),
        'latitude' => $adventure['latitude'] === null ? null : (float) $adventure['latitude'],
        'longitude' => $adventure['longitude'] === null ? null : (float) $adventure['longitude'],
    ];
}, $adventures);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Weather Recommendations | Trip Planner</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body class="dashboard-background">
    <header class="app-header">
        <div>
            <p class="eyebrow">Weather analysis</p>
            <h1>Weather Recommendations</h1>
        </div>
        <div class="header-actions">
            <a class="button-link secondary-link" href="/dashboard.php">Back to dashboard</a>
            <a class="button-link" href="/adventures/create.php">+ Create New Adventure</a>
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

        <section class="recommendations-panel" aria-labelledby="recommendations-form-title">
            <div>
                <h2 id="recommendations-form-title">Choose trip</h2>
                <p class="muted">Weather statistics use the saved trip dates and map location.</p>
            </div>

            <?php if ($recommendationAdventures === [] && $errors === []): ?>
                <div class="empty-state compact-empty">
                    <h2>No trips yet</h2>
                    <p class="muted">Create a trip with dates and a map point before opening recommendations.</p>
                </div>
            <?php else: ?>
                <label for="recommendationTripSelect">Trip</label>
                <select id="recommendationTripSelect" class="recommendation-select">
                    <?php foreach ($recommendationAdventures as $adventure): ?>
                        <option value="<?= e((string) $adventure['id']) ?>">
                            <?= e($adventure['title'] . ' - ' . $adventure['destinationRegion']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </section>

        <section id="recommendationsStatus" class="recommendations-status" aria-live="polite">
            Select Start Date and End Date to see weather recommendations.
        </section>

        <section id="recommendationsContent" class="recommendations-content" hidden>
            <section class="stats-grid" id="weatherStatsCards" aria-label="Weather statistics"></section>

            <section class="recommendations-section" aria-labelledby="daily-weather-title">
                <div>
                    <h2 id="daily-weather-title">Daily Weather</h2>
                    <p class="muted">Small daily forecast cards for the selected trip range.</p>
                </div>
                <div class="daily-weather-list" id="dailyWeatherList"></div>
            </section>

            <section class="charts-grid" aria-label="Weather charts">
                <article class="chart-panel">
                    <h2>Temperature Chart</h2>
                    <canvas id="temperatureChart" width="640" height="260"></canvas>
                </article>
                <article class="chart-panel">
                    <h2>Rain Chance Chart</h2>
                    <canvas id="rainChanceChart" width="640" height="260"></canvas>
                </article>
            </section>

            <section class="recommendations-section" id="weatherDescription"></section>
        </section>
    </main>

    <script>
        window.TRIP_RECOMMENDATION_ADVENTURES = <?= json_encode($recommendationAdventures, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    </script>
    <script src="/assets/js/weather-recommendations.js"></script>
</body>
</html>
