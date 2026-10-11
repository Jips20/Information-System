
<?php

require_once __DIR__ . '/vendor/autoload.php';

try {
    $client = new MongoDB\Client('mongodb://127.0.0.1:27017');

    $database = $client->selectDatabase('student_information_system');

    $result = $database->command(['ping' => 1]);

    echo "SUCCESS! PHP is connected to MongoDB.";

} catch (Throwable $e) {
    echo "Connection failed: " . $e->getMessage();
}