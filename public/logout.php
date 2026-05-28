<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';

require_auth(); // Wylogowanie jest dostepne tylko dla zalogowanego uzytkownika.

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    redirect('/dashboard.php'); // Niepoprawne wylogowanie wraca na dashboard.
}

$auth = new Auth(); // Tworzy obiekt autoryzacji.
$auth->logout(); // Czysci sesje uzytkownika.

redirect('/login.php');
