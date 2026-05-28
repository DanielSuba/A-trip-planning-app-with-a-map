<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class Adventure
{
    public function listForUser(int $userId): array
    {
        try {
            $statement = db()->prepare(
                'SELECT id, title, destination_region, start_date, end_date, description, latitude, longitude
                 FROM adventures
                 WHERE user_id = :user_id
                 ORDER BY start_date ASC, created_at DESC'
            );
            $statement->execute(['user_id' => $userId]);

            return ['ok' => true, 'adventures' => $statement->fetchAll(), 'errors' => []];
        } catch (Throwable $exception) {
            return ['ok' => false, 'adventures' => [], 'errors' => [$this->databaseErrorMessage($exception)]];
        }
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $statement = db()->prepare(
            'SELECT id, title, destination_region, start_date, end_date, description, latitude, longitude
             FROM adventures
             WHERE id = :id AND user_id = :user_id
             LIMIT 1'
        );
        $statement->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);
        $adventure = $statement->fetch();

        return $adventure ?: null;
    }

    public function create(int $userId, array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $destinationRegion = trim((string) ($data['destination_region'] ?? ''));
        $startDate = trim((string) ($data['start_date'] ?? ''));
        $endDate = trim((string) ($data['end_date'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $latitude = trim((string) ($data['latitude'] ?? ''));
        $longitude = trim((string) ($data['longitude'] ?? ''));
        $errors = $this->validate($title, $destinationRegion, $startDate, $endDate, $description, $latitude, $longitude);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        try {
            $statement = db()->prepare(
                'INSERT INTO adventures (user_id, title, destination_region, start_date, end_date, description, latitude, longitude, created_at)
                 VALUES (:user_id, :title, :destination_region, :start_date, :end_date, :description, :latitude, :longitude, NOW())'
            );
            $statement->execute([
                'user_id' => $userId,
                'title' => $title,
                'destination_region' => $destinationRegion,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'description' => $description,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
        } catch (Throwable $exception) {
            return ['ok' => false, 'errors' => [$this->databaseErrorMessage($exception)]];
        }

        return ['ok' => true, 'errors' => []];
    }

    public function update(int $id, int $userId, array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $destinationRegion = trim((string) ($data['destination_region'] ?? ''));
        $startDate = trim((string) ($data['start_date'] ?? ''));
        $endDate = trim((string) ($data['end_date'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $latitude = trim((string) ($data['latitude'] ?? ''));
        $longitude = trim((string) ($data['longitude'] ?? ''));
        $errors = $this->validate($title, $destinationRegion, $startDate, $endDate, $description, $latitude, $longitude);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        try {
            $statement = db()->prepare(
                'UPDATE adventures
                 SET title = :title,
                     destination_region = :destination_region,
                     start_date = :start_date,
                     end_date = :end_date,
                     description = :description,
                     latitude = :latitude,
                     longitude = :longitude
                 WHERE id = :id AND user_id = :user_id'
            );
            $statement->execute([
                'title' => $title,
                'destination_region' => $destinationRegion,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'description' => $description,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'id' => $id,
                'user_id' => $userId,
            ]);

            if ($statement->rowCount() === 0 && $this->findForUser($id, $userId) === null) {
                return ['ok' => false, 'errors' => ['Adventure was not found.']];
            }
        } catch (Throwable $exception) {
            return ['ok' => false, 'errors' => [$this->databaseErrorMessage($exception)]];
        }

        return ['ok' => true, 'errors' => []];
    }

    public function delete(int $id, int $userId): array
    {
        try {
            $statement = db()->prepare('DELETE FROM adventures WHERE id = :id AND user_id = :user_id');
            $statement->execute([
                'id' => $id,
                'user_id' => $userId,
            ]);

            if ($statement->rowCount() === 0) {
                return ['ok' => false, 'errors' => ['Adventure was not found.']];
            }
        } catch (Throwable $exception) {
            return ['ok' => false, 'errors' => [$this->databaseErrorMessage($exception)]];
        }

        return ['ok' => true, 'errors' => []];
    }

    private function validate(string $title, string $destinationRegion, string $startDate, string $endDate, string $description, string $latitude, string $longitude): array
    {
        $errors = [];

        if ($title === '' || strlen($title) > 120) {
            $errors[] = 'Trip name is required and must be 120 characters or fewer.';
        }

        if ($destinationRegion === '' || strlen($destinationRegion) > 255) {
            $errors[] = 'Destination country or region is required and must be 255 characters or fewer.';
        }

        if (!$this->isDate($startDate)) {
            $errors[] = 'Start date is required.';
        }

        if (!$this->isDate($endDate)) {
            $errors[] = 'End date is required.';
        }

        if ($this->isDate($startDate) && $this->isDate($endDate) && $endDate < $startDate) {
            $errors[] = 'End date cannot be earlier than start date.';
        }

        if ($description === '') {
            $errors[] = 'Description is required.';
        }

        if (!is_numeric($latitude) || (float) $latitude < -90 || (float) $latitude > 90) {
            $errors[] = 'Choose a valid point on the map.';
        }

        if (!is_numeric($longitude) || (float) $longitude < -180 || (float) $longitude > 180) {
            $errors[] = 'Choose a valid point on the map.';
        }

        return array_values(array_unique($errors));
    }

    private function isDate(string $date): bool
    {
        return $date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
    }

    private function databaseErrorMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, "doesn't exist") || str_contains($message, '[42S02]')) {
            return 'Database setup error: the adventures table does not exist yet. Import database/migrations/002_create_adventures_table.sql.';
        }

        if (str_contains($message, 'Unknown column')) {
            return 'Database setup error: the adventures table needs the latest fields. Import database/migrations/003_update_adventures_trip_dates.sql.';
        }

        if (str_contains($message, 'Unknown database') || str_contains($message, '[1049]')) {
            return 'Database setup error: the trip_planner database does not exist yet. Import database/schema.sql first.';
        }

        if (str_contains($message, 'Access denied') || str_contains($message, '[1045]')) {
            return 'Database connection error: MySQL rejected the username or password.';
        }

        if (str_contains($message, 'actively refused') || str_contains($message, '[2002]')) {
            return 'Database connection error: MySQL is not running on the configured host and port.';
        }

        return 'Database connection error: ' . $message;
    }
}
