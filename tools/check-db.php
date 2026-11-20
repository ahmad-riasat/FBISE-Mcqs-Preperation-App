<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../database/app.sqlite');
$names = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")
             ->fetchAll(PDO::FETCH_COLUMN);
print_r($names);
?>