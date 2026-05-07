<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    redirect('/dashboard.php');
}

$auth = new Auth();
$auth->logout();

redirect('/login.php');
