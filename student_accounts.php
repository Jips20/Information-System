
<?php

use MongoDB\BSON\UTCDateTime;

require_once __DIR__ . '/includes/auth.php';
requireRole('admin');

require_once __DIR__ . '/config/database.php';

$error = '';
$success = '';

if (empty($_SESSION['student_account_csrf'])) {
    $_SESSION['student_account_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['student_account_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $studentId = $_POST['student_id'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($csrfToken, $token)
    ) {
        $error = 'Invalid form session. Please refresh and try again.';
    } elseif (
        !is_string($studentId) ||
        !is_string($password) ||
        !is_string($confirmPassword)
    ) {
        $error = 'Invalid form information.';
    } elseif (strlen($password) < 12) {
        $error = 'The password must contain at least 12 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'The passwords do not match.';
    } else {
        try {
            // Find the existing student record.
            $student = $studentsCollection->findOne([
                'student_id' => trim($studentId),
            ]);

            if (!$student) {
                $error = 'The selected student record was not found.';
            } else {
                $email = strtolower(trim((string) ($student['email'] ?? '')));

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'This student has no valid email address. Update the student record first.';
                } elseif (
                    $usersCollection->findOne([
                        'student_id' => $student['student_id'],
                        'role' => 'student',
                    ])
                ) {
                    $error = 'This student already has a login account.';
                } elseif ($usersCollection->findOne([
                    'email' => $email,
                ])) {
                    $error = 'This email is already used by another account.';
                } else {
                    $usersCollection->insertOne([
                        'email' => $email,
                        'password_hash' => password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        ),
                        'role' => 'student',
                        'student_id' => $student['student_id'],
                        'created_at' => new UTCDateTime(),
                    ]);

                    $success = 'Student account created successfully.';
                }
            }
        } catch (Throwable $e) {
            error_log('Student account creation failed: ' . $e->getMessage());
            $error = 'Unable to create the account. Check the PHP error log.';
        }
    }
}

// Load all student records for the account-creation dropdown.
$students = $studentsCollection->find([], [
    'sort' => ['student_id' => 1],
]);

// Load existing student accounts.
$studentAccounts = $usersCollection->find([
    'role' => 'student',
], [
    'sort' => ['email' => 1],
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Accounts | SIS</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f5f0ff;
            color: #302442;
        }

        main {
            max-width: 950px;
            margin: 0 auto;
        }

        header, section {
            background: white;
            padding: 24px;
            margin-bottom: 22px;
            border-radius: 15px;
            box-shadow: 0 8px 25px #39205b10;
        }

        h1, h2 { margin-top: 0; }

        .muted { color: #776b87; }

        label {
            display: block;
            margin: 16px 0 7px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d8cde8;
            border-radius: 9px;
            font-size: 15px;
        }

        button, .button {
            display: inline-block;
            margin-top: 18px;
            padding: 12px 16px;
            border: 0;
            border-radius: 9px;
            background: #7138b8;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .success {
            background: #edf9f0;
            color: #176b32;
        }

        .error {
            background: #fff0f0;
            color: #a32121;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 450px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #eee8f5;
            text-align: left;
        }

        th { background: #f8f5ff; }

        @media (max-width: 600px) {
            body { padding: 12px; }
        }
    </style>
</head>
<body>
<main>
    <header>
        <h1>Student Account Management</h1>
        <p class="muted">
            Create login accounts for students already registered in the system.
        </p>
        <a class="button" href="index.php">Back to Student Records</a>
    </header>

    <section>
        <h2>Create Student Account</h2>

        <p class="muted">
            The account email will come from the selected student's existing record.
        </p>

        <?php if ($success !== ''): ?>
            <div class="message success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="message error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="student_accounts.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
            >

            <label for="student_id">Select Student</label>
            <select id="student_id" name="student_id" required>
                <option value="">Choose a student</option>

                <?php foreach ($students as $student): ?>
                    <option value="<?= htmlspecialchars(
                        (string) $student['student_id'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">
                        <?= htmlspecialchars(
                            (string) $student['student_id'] . ' - ' .
                            (string) $student['first_name'] . ' ' .
                            (string) $student['last_name'] . ' (' .
                            (string) $student['email'] . ')',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="password">Initial Password</label>
            <input
                type="password"
                id="password"
                name="password"
                minlength="12"
                autocomplete="new-password"
                required
            >

            <label for="confirm_password">Confirm Password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="12"
                autocomplete="new-password"
                required
            >

            <button type="submit">Create Student Account</button>
        </form>
    </section>

    <section>
        <h2>Existing Student Accounts</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Email</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $hasAccounts = false; ?>

                    <?php foreach ($studentAccounts as $account): ?>
                        <?php $hasAccounts = true; ?>
                        <tr>
                            <td><?= htmlspecialchars(
                                (string) ($account['student_id'] ?? ''),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></td>
                            <td><?= htmlspecialchars(
                                (string) $account['email'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></td>
                            <td>Student</td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$hasAccounts): ?>
                        <tr>
                            <td colspan="3">No student accounts created yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>