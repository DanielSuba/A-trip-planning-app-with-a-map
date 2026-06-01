<?php

declare(strict_types=1);

$envPath = __DIR__ . '/../.env'; // Sciezka do pliku z lokalnymi zmiennymi srodowiskowymi.

if (is_file($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // Czyta plik .env linia po linii.

    foreach ($lines ?: [] as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'"); // Usuwa cudzyslowy z wartosci zmiennej.

        if (getenv($key) === false) {
            putenv($key . '=' . $value); // Dodaje zmienna do srodowiska PHP.
            $_ENV[$key] = $value; // Udostepnia zmienna takze w tablicy $_ENV.
        }
    }
}

// Glowne ustawienia aplikacji.
return [
    'app_name' => 'Trip Planner',
    'db' => [
        'driver' => getenv('DB_DRIVER') ?: 'mysql',
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'trip_planner',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
    'openweather' => [
        'api_key' => getenv('OPENWEATHER_API_KEY') ?: '',
    ],
    'lm_studio' => [
        'api_url' => getenv('LM_STUDIO_API_URL') ?: 'http://127.0.0.1:1234/v1/chat/completions',
        'api_key' => getenv('LM_STUDIO_API_KEY') ?: '',
        'model' => getenv('LM_STUDIO_MODEL') ?: 'local-model',
    ],
];
