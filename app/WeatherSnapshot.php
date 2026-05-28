<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class WeatherSnapshot
{
    public function saveForAdventure(int $adventureId, string $latitude, string $longitude): array
    {
        try {
            $forecast = $this->fetchNearestForecast($latitude, $longitude);

            if (!$forecast) {
                return ['ok' => false, 'errors' => ['No OpenWeatherMap forecast data was returned.']];
            }

            $statement = db()->prepare(
                'INSERT INTO weather_snapshots
                    (adventure_id, temperature, weather_main, weather_description, humidity, wind_speed, forecast_for, created_at)
                 VALUES
                    (:adventure_id, :temperature, :weather_main, :weather_description, :humidity, :wind_speed, :forecast_for, NOW())'
            );
            $statement->execute([
                'adventure_id' => $adventureId,
                'temperature' => $forecast['temperature'],
                'weather_main' => $forecast['weather_main'],
                'weather_description' => $forecast['weather_description'],
                'humidity' => $forecast['humidity'],
                'wind_speed' => $forecast['wind_speed'],
                'forecast_for' => $forecast['forecast_for'],
            ]);
        } catch (Throwable $exception) {
            return ['ok' => false, 'errors' => [$this->errorMessage($exception)]];
        }

        return ['ok' => true, 'errors' => []];
    }

    private function fetchNearestForecast(string $latitude, string $longitude): ?array
    {
        $config = require __DIR__ . '/../config/config.php';
        $apiKey = $config['openweather']['api_key'] ?? '';

        if ($apiKey === '' || $apiKey === 'PUT_API_KEY_HERE') {
            throw new RuntimeException('OpenWeatherMap API key is not configured.');
        }

        if (!extension_loaded('openssl')) {
            throw new RuntimeException('PHP openssl extension is not enabled.');
        }

        $query = http_build_query([
            'lat' => $latitude,
            'lon' => $longitude,
            'appid' => $apiKey,
            'units' => 'metric',
            'lang' => 'pl',
        ]);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents('https://api.openweathermap.org/data/2.5/forecast?' . $query, false, $context);
        $statusLine = $http_response_header[0] ?? '';

        if ($response === false) {
            throw new RuntimeException('Network error while requesting OpenWeatherMap forecast.');
        }

        if (!str_contains($statusLine, '200')) {
            throw new RuntimeException(str_contains($statusLine, '401') ? 'Invalid OpenWeatherMap API key.' : 'OpenWeatherMap forecast request failed.');
        }

        $data = json_decode($response, true);
        $forecast = is_array($data['list'] ?? null) ? ($data['list'][0] ?? null) : null;

        if (!is_array($forecast)) {
            return null;
        }

        return [
            'temperature' => isset($forecast['main']['temp']) ? (float) $forecast['main']['temp'] : null,
            'weather_main' => $forecast['weather'][0]['main'] ?? null,
            'weather_description' => $forecast['weather'][0]['description'] ?? null,
            'humidity' => isset($forecast['main']['humidity']) ? (int) $forecast['main']['humidity'] : null,
            'wind_speed' => isset($forecast['wind']['speed']) ? (float) $forecast['wind']['speed'] : null,
            'forecast_for' => $forecast['dt_txt'] ?? null,
        ];
    }

    private function errorMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, "doesn't exist") || str_contains($message, '[42S02]')) {
            return 'Weather snapshot was not saved: import database/migrations/007_create_weather_snapshots_table.sql.';
        }

        return 'Weather snapshot was not saved: ' . $message;
    }
}
