<?php
declare(strict_types=1);
require __DIR__ . '/../../src/auth.php';

require_method('POST');
require_csrf();
logout_user();

respond(200, ['ok' => true]);