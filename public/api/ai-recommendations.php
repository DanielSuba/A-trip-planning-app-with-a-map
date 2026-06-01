<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/Adventure.php';
require_once __DIR__ . '/../../config/database.php';

require_auth(); // Opis AI moze byc generowany tylko dla zalogowanego uzytkownika.

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST requests are allowed.']);
    exit;
}

$config = require __DIR__ . '/../../config/config.php'; // Pobiera ustawienia LM Studio z .env.
$apiUrl = trim((string) ($config['lm_studio']['api_url'] ?? '')); // Adres lokalnego API LM Studio.
$apiKey = trim((string) ($config['lm_studio']['api_key'] ?? '')); // Opcjonalny klucz API LM Studio.
$model = trim((string) ($config['lm_studio']['model'] ?? 'local-model')); // Nazwa modelu wybranego w LM Studio.

if ($apiUrl === '') {
    http_response_code(500);
    echo json_encode(['error' => 'LM Studio API URL is not configured. Set LM_STUDIO_API_URL in .env.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput ?: '', true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload.']);
    exit;
}

$trip = is_array($input['trip'] ?? null) ? $input['trip'] : [];
$summary = is_array($input['summary'] ?? null) ? $input['summary'] : [];
$dailyStats = is_array($input['dailyStats'] ?? null) ? array_slice($input['dailyStats'], 0, 10) : [];
$tripId = filter_var($trip['id'] ?? null, FILTER_VALIDATE_INT);

if ($summary === [] || $dailyStats === []) {
    http_response_code(400);
    echo json_encode(['error' => 'Weather summary and daily stats are required.']);
    exit;
}

if (!$tripId) {
    http_response_code(400);
    echo json_encode(['error' => 'Trip id is required to save the description.']);
    exit;
}

$user = current_user(); // Pobiera uzytkownika do sprawdzenia wlasciciela podrozy.
$adventure = (new Adventure())->findForUser((int) $tripId, (int) $user['id']); // Sprawdza czy podroz nalezy do zalogowanego uzytkownika.

if ($adventure === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Trip was not found.']);
    exit;
}

$tripTitle = (string) ($trip['title'] ?? 'Selected trip');
$destination = (string) ($trip['destinationRegion'] ?? 'selected destination');
$startDate = (string) ($trip['startDate'] ?? '');
$endDate = (string) ($trip['endDate'] ?? '');

$dailyLines = array_map(static function (array $day): string {
    return sprintf(
        '- %s: avg temp %s C, rain %s%%, max rain %s%%, wind %s m/s, humidity %s%%, weather: %s',
        (string) ($day['date'] ?? 'unknown date'),
        (string) ($day['avgTemp'] ?? 'unknown'),
        (string) ($day['avgRain'] ?? 'unknown'),
        (string) ($day['maxRain'] ?? 'unknown'),
        (string) ($day['avgWind'] ?? 'unknown'),
        (string) ($day['avgHumidity'] ?? 'unknown'),
        (string) ($day['description'] ?? 'unknown')
    );
}, $dailyStats);

$prompt = sprintf(
    "Napisz krotki opis rekomendacyjny po polsku dla podrozy.\n" .
    "Powiedz czy warto urzadzac wycieczke w wybranych dniach, ktore dni sa najlepsze, ktore sa ryzykowne i co zabrac.\n" .
    "Nie wymyslaj danych spoza podanej prognozy. Pisz konkretnie, 2-4 krotkie akapity.\n\n" .
    "Podroz: %s\nMiejsce: %s\nStart: %s\nEnd: %s\n\n" .
    "Podsumowanie: srednia temperatura %s C, min %s C, max %s C, srednie ryzyko opadow %s%%, sredni wiatr %s m/s, wilgotnosc %s%%, dni z ryzykiem deszczu %s z %s.\n\n" .
    "Dni:\n%s",
    $tripTitle,
    $destination,
    $startDate,
    $endDate,
    (string) ($summary['avgTemp'] ?? 'unknown'),
    (string) ($summary['minTemp'] ?? 'unknown'),
    (string) ($summary['maxTemp'] ?? 'unknown'),
    (string) ($summary['avgRain'] ?? 'unknown'),
    (string) ($summary['avgWind'] ?? 'unknown'),
    (string) ($summary['avgHumidity'] ?? 'unknown'),
    (string) ($summary['rainyDays'] ?? 'unknown'),
    (string) ($summary['totalDays'] ?? 'unknown'),
    implode("\n", $dailyLines)
);

$payload = [
    'model' => $model,
    'messages' => [
        [
            'role' => 'system',
            'content' => 'Jestes pomocnym asystentem planowania podrozy. Odpowiadasz po polsku, jasno i praktycznie.',
        ],
        [
            'role' => 'user',
            'content' => $prompt,
        ],
    ],
    'temperature' => 0.7,
    'max_tokens' => 450,
    'stream' => false,
];

$headers = [
    'Content-Type: application/json',
];

if ($apiKey !== '') {
    $headers[] = 'Authorization: Bearer ' . $apiKey;
}

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => json_encode($payload),
        'timeout' => 45,
        'ignore_errors' => true,
    ],
]);

$response = @file_get_contents($apiUrl, false, $context); // Wysyla zapytanie do lokalnego LM Studio.
$statusLine = $http_response_header[0] ?? '';

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Could not connect to LM Studio. Start the local server and check LM_STUDIO_API_URL.']);
    exit;
}

if (!str_contains($statusLine, '200')) {
    $statusCode = preg_match('/\s(\d{3})\s/', $statusLine, $matches) ? (int) $matches[1] : 502;
    http_response_code($statusCode >= 400 ? $statusCode : 502);
    echo json_encode(['error' => 'LM Studio request failed. Check that a model is loaded and the local server is running.']);
    exit;
}

$decodedResponse = json_decode($response, true);
$description = trim((string) ($decodedResponse['choices'][0]['message']['content'] ?? ''));

if ($description === '') {
    http_response_code(502);
    echo json_encode(['error' => 'LM Studio returned an empty response.']);
    exit;
}

try {
    $database = db();
    $latestSnapshot = $database->prepare(
        'SELECT id FROM weather_snapshots WHERE adventure_id = :adventure_id ORDER BY created_at DESC, id DESC LIMIT 1'
    );
    $latestSnapshot->execute(['adventure_id' => $tripId]);
    $snapshotId = $latestSnapshot->fetchColumn();

    if ($snapshotId) {
        $statement = $database->prepare(
            'UPDATE weather_snapshots
             SET recommendation_description = :recommendation_description
             WHERE id = :id'
        );
        $statement->execute([
            'recommendation_description' => $description,
            'id' => $snapshotId,
        ]);
    } else {
        $statement = $database->prepare(
            'INSERT INTO weather_snapshots (adventure_id, recommendation_description, created_at)
             VALUES (:adventure_id, :recommendation_description, NOW())'
        );
        $statement->execute([
            'adventure_id' => $tripId,
            'recommendation_description' => $description,
        ]);
    }
} catch (Throwable $exception) {
    if (str_contains($exception->getMessage(), 'recommendation_description')) {
        http_response_code(500);
        echo json_encode(['error' => 'Database setup error: add recommendation_description to weather_snapshots. Import database/migrations/008_add_recommendation_description_to_weather_snapshots.sql.']);
        exit;
    }

    http_response_code(500);
    echo json_encode(['error' => 'Description was generated but could not be saved to weather_snapshots.']);
    exit;
}

echo json_encode(['description' => $description]);
