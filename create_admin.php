
<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$error = '';
$success = false;

// This setup page must only be used before the first admin is created.
if ($usersCollection->countDocuments(['role' => 'admin']) > 0) {
    http_response_code(403);
    exit(
        'An admin account already exists. ' .
        'Delete create_admin.php from your project now.'
    );
}

if (empty($_SESSION['setup_csrf'])) {
    $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['setup_csrf'], $token)
    ) {
        $error = 'Invalid session. Refresh the page and try again.';
    } elseif (
        !is_string($email) ||
        !is_string($password) ||
        !is_string($confirmPassword)
    ) {
        $error = 'Please enter valid information.';
    } else {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 12) {
            $error = 'Use a password with at least 12 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'The passwords do not match.';
        } else {
            try {
                // Enforce one account per email address.
                $usersCollection->createIndex(
                    ['email' => 1],
                    ['unique' => true]
                );

                // Recheck before creating the first administrator.
                if ($usersCollection->countDocuments(['role' => 'admin']) > 0) {
                    $error = 'An admin account already exists.';
                } else {
                    $usersCollection->insertOne([
                        'email' => $email,
                        'password_hash' => password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        ),
                        'role' => 'admin',
                        'student_id' => null,
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                    ]);

                    $success = true;
                    unset($_SESSION['setup_csrf']);
                }
            } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
                $error = 'That email may already be registered. Try another email.';
            } catch (Throwable $e) {
                error_log('Admin setup failed: ' . $e->getMessage());
                $error = 'Unable to create the account. Check your PHP error log.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create First Admin | SIS</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f5f0ff;
            color: #302442;
        }
        main {
            width: 100%;
            max-width: 440px;
            padding: 30px;
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 35px #39205b18;
        }
        h1 { font-size: 24px; }
        p { line-height: 1.5; color: #746783; }
        label {
            display: block;
            margin: 16px 0 7px;
            font-weight: bold;
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d8cde8;
            border-radius: 9px;
            font-size: 15px;
        }
        button, .link {
            display: block;
            width: 100%;
            margin-top: 20px;
            padding: 13px;
            border: 0;
            border-radius: 9px;
            background: #7138b8;
            color: white;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
        }
        .error {
            padding: 10px;
            color: #9d2020;
            background: #fff0f0;
            border-radius: 8px;
        }
        .success {
            padding: 12px;
            background: #edf9f0;
            color: #176b32;
            border-radius: 8px;
        }
        small { color: #746783; }
    </style>
</head>
<body>
<main>
    <h1>Create First Admin</h1>
    <p>Set up the administrator account for your Student Information System.</p>

    <?php if ($success): ?>
        <p class="success">Admin account created successfully!</p>
        <p><strong>Important:</strong> Delete <code>create_admin.php</code>
        from your project before continuing.</p>
        <a class="link" href="login.php">Go to Login</a>
    <?php else: ?>

        <?php if ($error !== ''): ?>
            <p class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['setup_csrf'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>">

            <label for="email">Admin Email</label>
            <input type="email" id="email" name="email"
                maxlength="254" autocomplete="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                minlength="12" autocomplete="new-password" required>
            <small>Use at least 12 characters.</small>

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password"
                name="confirm_password" minlength="12"
                autocomplete="new-password" required>

            <button type="submit">Create Admin Account</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
