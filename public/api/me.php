
<?php
require __DIR__ . '/../../src/auth.php';

require_method('GET');

respond(200, [
    'ok' => true,
    'user' => current_user(),
    'csrf_token' => csrf_token()
]);
