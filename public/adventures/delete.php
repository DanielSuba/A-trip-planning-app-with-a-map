<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/Adventure.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    redirect('/dashboard.php');
}

$user = current_user();
$id = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    $adventureRepository = new Adventure();
    $adventureRepository->delete($id, (int) $user['id']);
}

redirect('/dashboard.php');
