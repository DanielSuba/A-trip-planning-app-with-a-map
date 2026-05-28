<?php

declare(strict_types=1);

header('Content-Type: application/json');

$config = require __DIR__ . '/../../config/config.php';
$apiKey = $config['openweather']['api_key'] ?? '';
$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);

if ($apiKey === '') {
    http_response_code(500);
    echo json_encode(['error' => 'OpenWeatherMap API key is not configured. Set OPENWEATHER_API_KEY.']);
    exit;
}

if ($apiKey === 'PUT_API_KEY_HERE') {
    http_response_code(500);
    echo json_encode(['error' => 'OpenWeatherMap API key still contains the placeholder value. Update OPENWEATHER_API_KEY in .env.']);
    exit;
}

if (!extension_loaded('openssl')) {
    http_response_code(500);
    echo json_encode(['error' => 'PHP openssl extension is not enabled. Enable extension=openssl in php.ini to call OpenWeatherMap over HTTPS.']);
    exit;
}

if ($lat === false || $lat === null || $lon === false || $lon === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid lat and lon query parameters are required.']);
    exit;
}

$query = http_build_query([
    'lat' => $lat,
    'lon' => $lon,
    'appid' => $apiKey,
    'units' => 'metric',
    'lang' => 'pl',
]);
$url = 'https://api.openweathermap.org/data/2.5/forecast?' . $query;
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 8,
        'ignore_errors' => true,
    ],
]);
$response = @file_get_contents($url, false, $context);
$statusLine = $http_response_header[0] ?? '';

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Network error while requesting OpenWeatherMap forecast.']);
    exit;
}

if (!str_contains($statusLine, '200')) {
    $statusCode = preg_match('/\s(\d{3})\s/', $statusLine, $matches) ? (int) $matches[1] : 502;
    http_response_code($statusCode === 401 ? 401 : 502);
    echo json_encode(['error' => $statusCode === 401 ? 'Invalid OpenWeatherMap API key.' : 'OpenWeatherMap forecast request failed.']);
    exit;
}

echo $response;
