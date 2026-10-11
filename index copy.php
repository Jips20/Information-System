
<?php
require_once __DIR__ . '/vendor/autoload.php';

$client = new MongoDB\Client('mongodb://127.0.0.1:27017');
$database = $client->selectDatabase('student_information_system');
$students = $database->selectCollection('students');

$error = '';
$success = isset($_GET['added']) ? 'Student added successfully!' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = trim($_POST['student_id'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $yearLevel = trim($_POST['year_level'] ?? '');

    if (
        $studentId === '' || $firstName === '' ||
        $lastName === '' || $email === '' ||
        $course === '' || $yearLevel === ''
    ) {
        $error = 'Please complete all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($yearLevel, ['1st Year', '2nd Year', '3rd Year', '4th Year'], true)) {
        $error = 'Please select a valid year level.';
    } else {
        $existing = $students->findOne([
            'student_id' => $studentId
        ]);

        if ($existing) {
            $error = 'That Student ID already exists.';
        } else {
            $students->insertOne([
                'student_id' => $studentId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'course' => $course,
                'year_level' => $yearLevel,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);

            header('Location: index.php?added=1');
            exit;
        }
    }
}

$search = trim($_GET['search'] ?? '');
$filter = [];

if ($search !== '') {
    $pattern = preg_quote($search, '/');
    $filter = [
        '$or' => [
            ['student_id' => ['$regex' => $pattern, '$options' => 'i']],
            ['first_name' => ['$regex' => $pattern, '$options' => 'i']],
            ['last_name' => ['$regex' => $pattern, '$options' => 'i']],
            ['email' => ['$regex' => $pattern, '$options' => 'i']],
            ['course' => ['$regex' => $pattern, '$options' => 'i']]
        ]
    ];
}

$studentList = $students->find($filter, [
    'sort' => ['created_at' => -1]
]);

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Information System</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #25304a;
        }

        header {
            background: #4338ca;
            color: white;
            padding: 25px 6%;
        }

        header h1 { margin: 0 0 8px; }
        header p { margin: 0; opacity: .9; }

        main {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 18px #1725540d;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 11px;
            border: 1px solid #d5d9e5;
            border-radius: 7px;
            font: inherit;
        }

        button, .clear {
            display: inline-block;
            margin-top: 18px;
            padding: 11px 17px;
            border: 0;
            border-radius: 7px;
            background: #4338ca;
            color: white;
            cursor: pointer;
            text-decoration: none;
            font: inherit;
        }

        .search-form {
            display: flex;
            gap: 10px;
        }

        .search-form button { margin-top: 0; }
        .clear { margin-top: 0; background: #64748b; }

        .message {
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 18px;
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th, td {
            text-align: left;
            padding: 13px 10px;
            border-bottom: 1px solid #edf0f5;
        }

        th { background: #f8fafc; }

        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
            .search-form { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<header>
    <h1>Student Information System</h1>
    <p>Manage and search student records</p>
</header>

<main>
    <section class="card">
        <h2>Add New Student</h2>

        <?php if ($success): ?>
            <div class="message"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <div class="form-grid">
                <div>
                    <label for="student_id">Student ID</label>
                    <input id="student_id" name="student_id"
                           maxlength="30" required
                           value="<?= e($_POST['student_id'] ?? '') ?>">
                </div>

                <div>
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email"
                           maxlength="150" required
                           value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <div>
                    <label for="first_name">First Name</label>
                    <input id="first_name" name="first_name"
                           maxlength="80" required
                           value="<?= e($_POST['first_name'] ?? '') ?>">
                </div>

                <div>
                    <label for="last_name">Last Name</label>
                    <input id="last_name" name="last_name"
                           maxlength="80" required
                           value="<?= e($_POST['last_name'] ?? '') ?>">
                </div>

                <div>
                    <label for="course">Course</label>
                    <input id="course" name="course"
                           maxlength="100" required
                           placeholder="e.g. BS Computer Science"
                           value="<?= e($_POST['course'] ?? '') ?>">
                </div>

                <div>
                    <label for="year_level">Year Level</label>
                    <select id="year_level" name="year_level" required>
                        <option value="">Select year level</option>
                        <?php foreach (['1st Year', '2nd Year', '3rd Year', '4th Year'] as $year): ?>
                            <option value="<?= e($year) ?>"
                                <?= (($_POST['year_level'] ?? '') === $year) ? 'selected' : '' ?>>
                                <?= e($year) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit">Add Student</button>
        </form>
    </section>

    <section class="card">
        <h2>Student Records</h2>

        <form method="GET" action="index.php" class="search-form">
            <input name="search" placeholder="Search ID, name, email, or course..."
                   value="<?= e($search) ?>">
            <button type="submit">Search</button>
            <a class="clear" href="index.php">Clear</a>
        </form>

        <p>Records displayed: <?= $students->countDocuments($filter) ?></p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Year Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $hasRecords = false;
                    foreach ($studentList as $student):
                        $hasRecords = true;
                    ?>
                        <tr>
                            <td><?= e($student['student_id'] ?? '') ?></td>
                            <td><?= e(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) ?></td>
                            <td><?= e($student['email'] ?? '') ?></td>
                            <td><?= e($student['course'] ?? '') ?></td>
                            <td><?= e($student['year_level'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$hasRecords): ?>
                        <tr>
                            <td colspan="5">No student records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

</body>
</html>