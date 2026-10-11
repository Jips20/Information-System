
<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/config/database.php';

$error = '';
$success = '';

if (empty($_SESSION['change_password_csrf'])) {
    $_SESSION['change_password_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['change_password_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($csrfToken, $token)
    ) {
        $error = 'Invalid form session. Please refresh and try again.';
    } elseif (
        !is_string($currentPassword) ||
        !is_string($newPassword) ||
        !is_string($confirmPassword)
    ) {
        $error = 'Invalid form information.';
    } elseif (
        $currentPassword === '' ||
        $newPassword === '' ||
        $confirmPassword === ''
    ) {
        $error = 'Please complete all password fields.';
    } elseif (strlen($newPassword) < 12) {
        $error = 'Your new password must contain at least 12 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'The new passwords do not match.';
    } elseif ($currentPassword === $newPassword) {
        $error = 'Your new password must be different from your current password.';
    } else {
        try {
            $email = strtolower(trim((string) ($_SESSION['email'] ?? '')));
            $role = $_SESSION['role'] ?? '';

            $user = $usersCollection->findOne([
                'email' => $email,
                'role' => $role
            ]);

            if (
                !$user ||
                !isset($user['password_hash']) ||
                !password_verify($currentPassword, $user['password_hash'])
            ) {
                $error = 'Your current password is incorrect.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

                $usersCollection->updateOne(
                    ['_id' => $user['_id']],
                    ['$set' => ['password_hash' => $newHash]]
                );

                $success = 'Your password has been changed successfully.';

                // Clear password fields after a successful update.
                $_POST = [];
            }
        } catch (Throwable $e) {
            error_log('Password change failed: ' . $e->getMessage());
            $error = 'Unable to change your password. Please try again.';
        }
    }
}

function escapePasswordPage($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$dashboardUrl = ($_SESSION['role'] ?? '') === 'admin'
    ? 'index.php'
    : 'student_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | SIS</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f3ff;
            color: #29243b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
        }

        .container {
            width: 100%;
            max-width: 480px;
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 35px rgba(40, 20, 80, 0.09);
        }

        h1 {
            color: #6d28d9;
            font-size: 27px;
            margin-bottom: 10px;
        }

        .description {
            color: #777087;
            line-height: 1.6;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .message {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
            line-height: 1.5;
        }

        .error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        label {
            display: block;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #ddd6fe;
            border-radius: 10px;
            font-size: 15px;
            margin-bottom: 19px;
            outline: none;
        }

        input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.12);
        }

        .submit-button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #6d28d9, #8b5cf6);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            font-size: 15px;
            cursor: pointer;
        }

        .submit-button:hover {
            opacity: 0.92;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #6d28d9;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .container {
                padding: 25px 20px;
            }
        }
    </style>
</head>

<body>
    <main class="container">
        <h1>Change Password</h1>
        <p class="description">
            Update your account password. Enter your current password
            and choose a new password with at least 12 characters.
        </p>

        <?php if ($error !== ''): ?>
            <div class="message error">
                <?= escapePasswordPage($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="message success">
                <?= escapePasswordPage($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="change_password.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapePasswordPage($csrfToken) ?>"
            >

            <label for="current_password">Current Password</label>
            <input
                type="password"
                id="current_password"
                name="current_password"
                autocomplete="current-password"
                required
            >

            <label for="new_password">New Password</label>
            <input
                type="password"
                id="new_password"
                name="new_password"
                minlength="12"
                autocomplete="new-password"
                required
            >

            <label for="confirm_password">Confirm New Password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="12"
                autocomplete="new-password"
                required
            >

            <button type="submit" class="submit-button">
                Update Password
            </button>
        </form>

        <a class="back-link" href="<?= escapePasswordPage($dashboardUrl) ?>">
            Back to Dashboard
        </a>
    </main>
</body>
</html>