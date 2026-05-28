<?php

declare(strict_types=1);

// Funkcja dla utworzenia i ponownego uzycia polaczenia PDO z baza danych.
function db(): PDO
{
    static $pdo = null; // Przechowuje jedno polaczenie w czasie jednego requestu.

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php'; // Pobiera ustawienia aplikacji i bazy.
    $db = $config['db']; // Wydziela sekcje konfiguracji bazy danych.

    if ($db['driver'] !== 'mysql') {
        throw new RuntimeException('Only MySQL is configured for this first auth module.');
    }

    if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('Database setup error: PDO MySQL is not enabled. Enable the pdo_mysql extension in your PHP configuration.');
    }

    // Tworzy DSN potrzebny do polaczenia PDO z MySQL.
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['database'],
        $db['charset']
    );

    // Tworzy bezpieczne polaczenie PDO z obsluga wyjatkow.
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
