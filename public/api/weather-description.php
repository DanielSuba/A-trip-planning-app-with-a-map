<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/Adventure.php';
require_once __DIR__ . '/../../config/database.php';

require_auth(); // Odczyt opisu jest dostepny tylko dla zalogowanego uzytkownika.

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Only GET requests are allowed.']);
    exit;
}

$tripId = filter_input(INPUT_GET, 'adventure_id', FILTER_VALIDATE_INT); // Pobiera ID podrozy z URL.

if (!$tripId) {
    http_response_code(400);
    echo json_encode(['error' => 'Trip id is required.']);
    exit;
}

$user = current_user(); // Pobiera aktualnego uzytkownika z sesji.
$adventure = (new Adventure())->findForUser((int) $tripId, (int) $user['id']); // Sprawdza czy podroz nalezy do uzytkownika.

if ($adventure === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Trip was not found.']);
    exit;
}

try {
    $statement = db()->prepare(
        'SELECT recommendation_description
         FROM weather_snapshots
         WHERE adventure_id = :adventure_id
           AND recommendation_description IS NOT NULL
           AND recommendation_description <> ""
         ORDER BY created_at DESC, id DESC
         LIMIT 1'
    );
    $statement->execute(['adventure_id' => $tripId]);
    $description = $statement->fetchColumn();
} catch (Throwable $exception) {
    if (str_contains($exception->getMessage(), 'recommendation_description')) {
        http_response_code(500);
        echo json_encode(['error' => 'Saved description is not available yet.']);
        exit;
    }

    http_response_code(500);
    echo json_encode(['error' => 'Saved description could not be loaded.']);
    exit;
}

echo json_encode([
    'description' => $description ? (string) $description : '',
]);
