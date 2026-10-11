<?php
require_once __DIR__ . '/includes/auth.php';
requireRole('student');
require_once __DIR__ . '/config/database.php';

// Use only the student ID attached to the authenticated session.
$studentId = $_SESSION['student_id'] ?? null;
if (!is_string($studentId) || trim($studentId) === '') {
    http_response_code(403);
    exit('Student account is not properly linked to a student record.');
}

$student = $studentsCollection->findOne(['student_id' => $studentId]);
if (!$student) {
    http_response_code(404);
    exit('Your student record could not be found. Please contact the administrator.');
}

if (empty($_SESSION['logout_csrf'])) {
    $_SESSION['logout_csrf'] = bin2hex(random_bytes(32));
}

function escapeHtml($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function displayValue($value, string $fallback = 'Not provided'): string
{
    if ($value === null || trim((string) $value) === '') {
        return $fallback;
    }
    return escapeHtml($value);
}

$firstName = trim((string) ($student['first_name'] ?? ''));
$middleName = trim((string) ($student['middle_name'] ?? ''));
$lastName = trim((string) ($student['last_name'] ?? ''));
$suffix = trim((string) ($student['suffix'] ?? ''));
$fullName = trim(implode(' ', array_filter(
    [$firstName, $middleName, $lastName, $suffix],
    static fn($part) => $part !== ''
)));

$dateOfBirth = trim((string) ($student['date_of_birth'] ?? ''));
$age = '';
if ($dateOfBirth !== '') {
    try {
        $birthDate = new DateTimeImmutable($dateOfBirth);
        $today = new DateTimeImmutable('today');
        if ($birthDate <= $today) {
            $age = (string) $birthDate->diff($today)->y;
        }
    } catch (Exception $e) {
        $age = '';
    }
}

function infoCard(string $label, $value, string $fallback = 'Not provided'): void
{
    echo '<div class="info-card"><span>' . escapeHtml($label) . '</span><strong>' .
        displayValue($value, $fallback) . '</strong></div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Student Dashboard | SIS</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f3ff;
            color: #29243b;
            min-height: 100vh;
        }
        header {
            background: linear-gradient(135deg, #6d28d9, #8b5cf6);
            color: white;
            padding: 25px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            box-shadow: 0 5px 20px rgba(109, 40, 217, 0.15);
        }
        header h1 { font-size: 25px; margin-bottom: 7px; }
        header p { font-size: 14px; opacity: .9; }
        .header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .logout-button, .nav-button {
            display: inline-block;
            border: none;
            border-radius: 10px;
            padding: 11px 18px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: .2s ease;
        }
        .logout-button { background: white; color: #6d28d9; }
        .logout-button:hover, .nav-button:hover { background: #ede9fe; transform: translateY(-2px); }
        .nav-button { background: rgba(255,255,255,.15); color: white; border: 1px solid rgba(255,255,255,.45); }
        main { width: 86%; max-width: 1100px; margin: 35px auto; }
        .welcome-card {
            background: white; padding: 28px; border-radius: 18px;
            margin-bottom: 28px; box-shadow: 0 6px 25px rgba(40, 20, 80, .06);
        }
        .welcome-card h2 { color: #6d28d9; font-size: 25px; margin-bottom: 10px; }
        .welcome-card p { color: #716b80; line-height: 1.6; }
        .section { margin-bottom: 30px; }
        .section-heading {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 16px; color: #49208c; font-size: 20px;
        }
        .section-heading::before {
            content: ""; display: block; width: 5px; height: 24px;
            background: #8b5cf6; border-radius: 10px;
        }
        .info-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 15px; }
        .info-card {
            background: white; padding: 21px; border-radius: 15px;
            border: 1px solid #e9ddff; box-shadow: 0 5px 18px rgba(40, 20, 80, .04);
            overflow-wrap: anywhere; min-width: 0;
        }
        .info-card span { display: block; color: #817991; font-size: 13px; margin-bottom: 9px; }
        .info-card strong { color: #49208c; font-size: 16px; font-weight: 600; line-height: 1.5; }
        footer { text-align: center; color: #8b849b; font-size: 13px; padding: 25px 15px; }
        @media (max-width: 850px) { .info-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 600px) {
            header { padding: 22px 6%; }
            header h1 { font-size: 21px; }
            main { width: 90%; margin-top: 22px; }
            .info-grid { grid-template-columns: 1fr; }
            .welcome-card, .info-card { padding: 21px; }
            .header-actions { width: 100%; }
        }
    </style>
</head>
<body>
<header>
    <div>
        <h1>Student Information System</h1>
        <p>Student Dashboard</p>
    </div>
    <div class="header-actions">
        <a href="change_password.php" class="nav-button">Change Password</a>
        <form action="logout.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['logout_csrf']) ?>">
            <button class="logout-button" type="submit">Log Out</button>
        </form>
    </div>
</header>

<main>
    <section class="welcome-card">
        <h2>Welcome, <?= displayValue($firstName, 'Student') ?>!</h2>
        <p>You are logged in to your student account. Here is your student profile grouped into sections for easier viewing.</p>
    </section>

    <section class="section">
        <h3 class="section-heading">Personal Information</h3>
        <div class="info-grid">
            <?php
            infoCard('School ID', $student['student_id'] ?? null);
            infoCard('First Name', $student['first_name'] ?? null);
            infoCard('Middle Name', $student['middle_name'] ?? null);
            infoCard('Last Name', $student['last_name'] ?? null);
            infoCard('Name Suffix', $student['suffix'] ?? null);
            infoCard('Full Name', $fullName);
            infoCard('Email Address', $student['email'] ?? null);
            infoCard('Date of Birth', $dateOfBirth);
            infoCard('Age', $age, 'Not available');
            infoCard('Sex', $student['sex'] ?? null);
            infoCard('Contact Number', $student['contact_number'] ?? null);
            infoCard('Religion', $student['religion'] ?? null);
            infoCard('Civil Status', $student['civil_status'] ?? null);
            infoCard('Occupation', $student['occupation'] ?? null);
            ?>
        </div>
    </section>

    <section class="section">
        <h3 class="section-heading">Academic Information</h3>
        <div class="info-grid">
            <?php
            infoCard('Course', $student['course'] ?? null);
            infoCard('Year Level', $student['year_level'] ?? null);
            infoCard('Student Type', $student['student_type'] ?? null);
            infoCard('Academic Year', $student['academic_year'] ?? null);
            infoCard('Semester', $student['semester'] ?? null);
            infoCard('Last School Attended', $student['last_school_attended'] ?? null);
            infoCard('Last School Address', $student['last_school_address'] ?? null);
            infoCard('Nationality', $student['nationality'] ?? null);
            ?>
        </div>
    </section>

    <section class="section">
        <h3 class="section-heading">Address Information</h3>
        <div class="info-grid">
            <?php
            infoCard('Current Address', $student['current_address'] ?? null);
            infoCard('Provincial / Permanent Address', $student['provincial_address'] ?? null);
            ?>
        </div>
    </section>

    <section class="section">
        <h3 class="section-heading">Family Information</h3>
        <div class="info-grid">
            <?php
            infoCard("Mother's Full Name", $student['mother_name'] ?? null);
            infoCard("Mother's Contact Number", $student['mother_contact'] ?? null);
            infoCard("Father's Full Name", $student['father_name'] ?? null);
            infoCard("Father's Contact Number", $student['father_contact'] ?? null);
            infoCard("Guardian's Full Name", $student['guardian_name'] ?? null);
            infoCard("Guardian's Contact Number", $student['guardian_contact'] ?? null);
            ?>
        </div>
    </section>

    <section class="section">
        <h3 class="section-heading">Emergency Contact</h3>
        <div class="info-grid">
            <?php
            infoCard('Contact Person', $student['emergency_contact_name'] ?? null);
            infoCard('Relationship', $student['emergency_contact_relationship'] ?? null);
            infoCard('Contact Number', $student['emergency_contact_number'] ?? null);
            infoCard('Alternate Contact Number', $student['emergency_contact_alternate'] ?? null);
            ?>
        </div>
    </section>
</main>

<footer>
    Student Information System &copy; <?= date('Y') ?>
</footer>
</body>
</html>
