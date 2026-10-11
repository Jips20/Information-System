
<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    header('Location: ' .
        ($_SESSION['role'] === 'admin'
            ? 'index.php'
            : 'student_dashboard.php'));
    exit;
}

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['login_csrf'], $token)
    ) {
        $error = 'Your session expired. Please refresh and try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (!is_string($email) || !is_string($password)) {
            $error = 'Invalid email or password.';
        } else {
            $user = $usersCollection->findOne([
                'email' => $email,
            ]);

            if (
                $user &&
                isset($user->password_hash, $user->role) &&
                password_verify($password, $user->password_hash) &&
                in_array($user->role, ['admin', 'student'], true)
            ) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = (string) $user->_id;
                $_SESSION['role'] = $user->role;
                $_SESSION['email'] = $user->email;
                $_SESSION['student_id'] = $user->student_id ?? null;

                unset($_SESSION['login_csrf']);

                header('Location: ' .
                    ($user->role === 'admin'
                        ? 'index.php'
                        : 'student_dashboard.php'));
                exit;
            }

            // Use the same message for unknown email and wrong password.
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Student Information System</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Arial, sans-serif;
            color: #29213b;
            background: linear-gradient(135deg, #f8f5ff, #eee5ff);
        }

        .login-card {
            width: 100%;
            max-width: 410px;
            padding: 36px;
            background: #fff;
            border: 1px solid #eee5ff;
            border-radius: 20px;
            box-shadow: 0 18px 50px rgba(74, 39, 130, .12);
        }

        .logo {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            margin: 0 auto 18px;
            border-radius: 17px;
            background: #efe5ff;
            color: #7138b8;
            font-size: 27px;
            font-weight: bold;
        }

        h1 {
            margin: 0;
            text-align: center;
            font-size: 25px;
        }

        .subtitle {
            margin: 10px 0 26px;
            color: #776e88;
            text-align: center;
            line-height: 1.5;
        }

        label {
            display: block;
            margin: 16px 0 7px;
            font-size: 14px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #ddd5eb;
            border-radius: 10px;
            font-size: 15px;
        }

        input:focus {
            outline: 2px solid #c5a5ff;
            border-color: #8b5cf6;
        }

        button {
            width: 100%;
            margin-top: 23px;
            padding: 14px;
            border: 0;
            border-radius: 10px;
            background: #7138b8;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #5c2d98;
        }

        .error {
            padding: 12px;
            border-radius: 9px;
            background: #fff0f0;
            color: #a32121;
            font-size: 14px;
        }

        .footer {
            margin-top: 22px;
            color: #8a8197;
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>
<body>
    <main class="login-card">
        <div class="logo">SIS</div>
        <h1>Welcome Back</h1>
        <p class="subtitle">
            Sign in to your Student Information System account.
        </p>

        <?php if ($error !== ''): ?>
            <p class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['login_csrf'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <label for="email">Email address</label>
            <input
                type="email"
                id="email"
                name="email"
                autocomplete="username"
                maxlength="254"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

            <button type="submit">Sign In</button>
        </form>

        <p class="footer">
            Student Information System &copy; <?= date('Y') ?>
        </p>
    </main>
</body>
</html>