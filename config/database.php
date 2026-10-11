
<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

$mongoClient = new MongoDB\Client('mongodb://127.0.0.1:27017');

$database = $mongoClient->selectDatabase('student_information_system');

$studentsCollection = $database->selectCollection('students');
$usersCollection = $database->selectCollection('users');