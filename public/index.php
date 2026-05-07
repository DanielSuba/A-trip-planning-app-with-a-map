<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

if (current_user() !== null) {
    redirect('/dashboard.php');
}

redirect('/login.php');
