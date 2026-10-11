
<?php

use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Exception\BulkWriteException;

require_once __DIR__ . '/includes/auth.php';

requireRole('admin');

require_once __DIR__ . '/config/database.php';

// Reuse the MongoDB connection from config/database.php.
$students = $studentsCollection;

if (empty($_SESSION['logout_csrf'])) {
    $_SESSION['logout_csrf'] = bin2hex(random_bytes(32));
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$error = '';
$success = '';

// Keep the rest of your existing CRUD code below this line.

$messages = [
    'added' => 'Student added successfully!',
    'updated' => 'Student information updated successfully!',
    'deleted' => 'Student deleted successfully!'
];

$success = $messages[$_GET['status'] ?? ''] ?? '';

$years = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
$civilStatuses = ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'];
$studentTypes = ['Regular', 'Irregular', 'Scholar', 'Other'];

$extraFields = [
    'middle_name', 'suffix', 'date_of_birth', 'sex', 'contact_number', 'religion', 'occupation',
    'civil_status', 'last_school_attended', 'last_school_address',
    'current_address', 'provincial_address', 'student_type',
    'academic_year', 'semester', 'nationality',
    'mother_name', 'mother_contact',
    'father_name', 'father_contact', 'guardian_name', 'guardian_contact',
    'emergency_contact_name', 'emergency_contact_relationship',
    'emergency_contact_number', 'alternate_emergency_number'
];
$editStudent = null;

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function validObjectId($id): bool {
    return is_string($id) && preg_match('/^[a-f0-9]{24}$/i', $id) === 1;
}

// Process add, update, and delete requests.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)) {
        $error = 'Invalid form session. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'delete') {
                $id = $_POST['id'] ?? '';

                if (!validObjectId($id)) {
                    throw new RuntimeException('Invalid student record ID.');
                }

                $result = $students->deleteOne([
                    '_id' => new ObjectId($id)
                ]);

                if ($result->getDeletedCount() === 0) {
                    throw new RuntimeException('Student record not found.');
                }

                header('Location: index.php?status=deleted');
                exit;
            }

            if ($action === 'save') {
                $id = trim($_POST['id'] ?? '');
                $studentId = trim($_POST['student_id'] ?? '');
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $email = strtolower(trim($_POST['email'] ?? ''));
                $course = trim($_POST['course'] ?? '');
                $yearLevel = trim($_POST['year_level'] ?? '');

                $extra = [];
                foreach ($extraFields as $field) {
                    $value = $_POST[$field] ?? '';
                    if (!is_string($value)) {
                        throw new RuntimeException('Invalid form information.');
                    }
                    $extra[$field] = trim($value);
                }

                if (
                    $studentId === '' || $firstName === '' ||
                    $lastName === '' || $email === '' ||
                    $course === '' || $yearLevel === ''
                ) {
                    throw new RuntimeException('Please complete all required fields.');
                }

                if (
                    strlen($studentId) > 30 ||
                    strlen($firstName) > 80 ||
                    strlen($lastName) > 80 ||
                    strlen($email) > 150 ||
                    strlen($course) > 100
                ) {
                    throw new RuntimeException('One or more fields exceed the allowed length.');
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Please enter a valid email address.');
                }

                if (!in_array($yearLevel, $years, true)) {
                    throw new RuntimeException('Please select a valid year level.');
                }

                if ($extra['date_of_birth'] !== '') {
                    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $extra['date_of_birth']);
                    $birthErrors = DateTimeImmutable::getLastErrors();
                    if (
                        !$birthDate ||
                        ($birthErrors !== false && ($birthErrors['warning_count'] > 0 || $birthErrors['error_count'] > 0)) ||
                        $birthDate->format('Y-m-d') !== $extra['date_of_birth'] ||
                        $birthDate > new DateTimeImmutable('today')
                    ) {
                        throw new RuntimeException('Please enter a valid date of birth that is not in the future.');
                    }
                }

                if ($extra['civil_status'] !== '' && !in_array($extra['civil_status'], $civilStatuses, true)) {
                    throw new RuntimeException('Please select a valid civil status.');
                }

                if ($extra['student_type'] !== '' && !in_array($extra['student_type'], $studentTypes, true)) {
                    throw new RuntimeException('Please select a valid student type.');
                }

                foreach ($extra as $field => $value) {
                    if (strlen($value) > 500) {
                        throw new RuntimeException('One or more additional fields are too long.');
                    }
                }

                $duplicateFilter = ['student_id' => $studentId];

                if ($id !== '') {
                    if (!validObjectId($id)) {
                        throw new RuntimeException('Invalid student record ID.');
                    }

                    $duplicateFilter['_id'] = ['$ne' => new ObjectId($id)];

                    $existingRecord = $students->findOne([
                        '_id' => new ObjectId($id)
                    ]);

                    if (!$existingRecord) {
                        throw new RuntimeException('Student record not found.');
                    }
                }

                if ($students->findOne($duplicateFilter)) {
                    throw new RuntimeException('That Student ID already exists.');
                }

                $data = [
                    'student_id' => $studentId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'course' => $course,
                    'year_level' => $yearLevel
                ];

                foreach ($extra as $field => $value) {
                    $data[$field] = $value;
                }

                if ($id === '') {
                    $data['created_at'] = new MongoDB\BSON\UTCDateTime();
                    $students->insertOne($data);

                    header('Location: index.php?status=added');
                } else {
                    $students->updateOne(
                        ['_id' => new ObjectId($id)],
                        ['$set' => $data]
                    );

                    header('Location: index.php?status=updated');
                }

                exit;
            }

            throw new RuntimeException('Invalid action.');
        } catch (BulkWriteException $ex) {
            $error = 'The operation could not be completed. Check for duplicate Student IDs.';
        } catch (Throwable $ex) {
            $error = $ex instanceof RuntimeException
                ? $ex->getMessage()
                : 'A database error occurred. Please check the server logs.';
        }
    }
}

// Load the record selected for editing.
$editId = $_GET['edit'] ?? '';

if ($editId !== '') {
    if (validObjectId($editId)) {
        $editStudent = $students->findOne([
            '_id' => new ObjectId($editId)
        ]);

        if (!$editStudent) {
            $error = 'Student record not found.';
        }
    } else {
        $error = 'Invalid student record ID.';
    }
}

// Search records.
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
            ['course' => ['$regex' => $pattern, '$options' => 'i']],
            ['contact_number' => ['$regex' => $pattern, '$options' => 'i']],
            ['current_address' => ['$regex' => $pattern, '$options' => 'i']],
            ['emergency_contact_name' => ['$regex' => $pattern, '$options' => 'i']]
        ]
    ];
}

$studentList = $students->find($filter, [
    'sort' => ['created_at' => -1]
]);

$count = $students->countDocuments($filter);

$form = [
    'id' => $editStudent ? (string) $editStudent['_id'] : '',
    'student_id' => $editStudent['student_id'] ?? '',
    'first_name' => $editStudent['first_name'] ?? '',
    'last_name' => $editStudent['last_name'] ?? '',
    'email' => $editStudent['email'] ?? '',
    'course' => $editStudent['course'] ?? '',
    'year_level' => $editStudent['year_level'] ?? ''
];

foreach ($extraFields as $field) {
    $form[$field] = $editStudent[$field] ?? '';
}

// Preserve submitted values if validation fails.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '' &&
    ($_POST['action'] ?? '') === 'save') {
    foreach ($form as $key => $value) {
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            $form[$key] = $_POST[$key];
        }
    }
}

$age = '';
if (!empty($form['date_of_birth'])) {
    try {
        $dob = new DateTimeImmutable($form['date_of_birth']);
        $today = new DateTimeImmutable('today');
        if ($dob <= $today) {
            $age = (string) $dob->diff($today)->y;
        }
    } catch (Throwable $e) {
        $age = '';
    }
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
    max-width: 1250px;
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

input, select, textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #d5d9e5;
    border-radius: 7px;
    font: inherit;
}

textarea {
    resize: vertical;
    min-height: 80px;
}

.form-section {
    grid-column: 1 / -1;
    margin-top: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e8eaf2;
    color: #4338ca;
    font-size: 17px;
}

.field-hint {
    display: block;
    color: #64748b;
    font-size: 12px;
    margin-top: 5px;
}

button, .button {
    display: inline-block;
    padding: 10px 14px;
    border: 0;
    border-radius: 7px;
    background: #4338ca;
    color: white;
    cursor: pointer;
    text-decoration: none;
    font: inherit;
}


.nav-button {
    display: inline-block;
    background: white;
    color: #6d28d9;
    padding: 11px 18px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
    transition: 0.2s ease;
}

.nav-button:hover {
    background: #ede9fe;
    transform: translateY(-2px);
}

.form-button { margin-top: 18px; }
.secondary { background: #64748b; }
.edit { background: #2563eb; }
.delete { background: #dc2626; }

.search-form {
    display: flex;
    gap: 10px;
    margin-bottom: 18px;
}

.search-form button, .search-form .button {
    white-space: nowrap;
}

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
    min-width: 950px;
}

th, td {
    text-align: left;
    padding: 13px 10px;
    border-bottom: 1px solid #edf0f5;
}

th { background: #f8fafc; }

.actions {
    display: flex;
    gap: 7px;
    align-items: center;
}

.actions form { margin: 0; }

.count { color: #64748b; }

@media (max-width: 600px) {
    .form-grid { grid-template-columns: 1fr; }
    .search-form { flex-wrap: wrap; }
}
</style>
</head>
<body>


<header>
    <h1>Student Information System</h1>
    <p>Manage, search, edit, and delete student records</p>

    <!-- ADD THIS LINK HERE -->
    <a href="student_accounts.php" class="nav-button">
        Manage Student Accounts
    </a>

    
    <a href="change_password.php" class="nav-button">
        Change Password
    </a>

    <form action="logout.php" method="POST">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $_SESSION['logout_csrf'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >
        <button type="submit">Logout</button>
    </form>
</header>

<main>
<section class="card">
    <h2><?= $form['id'] !== '' ? 'Edit Student' : 'Add New Student' ?></h2>

    <?php if ($success !== ''): ?>
        <div class="message"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="message error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= e($form['id']) ?>">

        <div class="form-grid">
            <h3 class="form-section">A. Personal Information</h3>

            <div>
                <label for="student_id">School ID *</label>
                <input id="student_id" name="student_id" maxlength="30"
                    required value="<?= e($form['student_id']) ?>">
            </div>

            <div>
                <label for="email">Email Address *</label>
                <input id="email" name="email" type="email" maxlength="150"
                    required value="<?= e($form['email']) ?>">
            </div>

            <div>
                <label for="first_name">First Name *</label>
                <input id="first_name" name="first_name" maxlength="80"
                    required value="<?= e($form['first_name']) ?>">
            </div>

            <div>
                <label for="last_name">Last Name *</label>
                <input id="last_name" name="last_name" maxlength="80"
                    required value="<?= e($form['last_name']) ?>">
            </div>

            <div>
                <label for="middle_name">Middle Name (optional)</label>
                <input id="middle_name" name="middle_name" maxlength="80"
                    value="<?= e($form['middle_name']) ?>">
            </div>

            <div>
                <label for="suffix">Name Suffix (optional)</label>
                <select id="suffix" name="suffix">
                    <option value="">None</option>
                    <?php foreach (['Jr.', 'Sr.', 'II', 'III', 'IV', 'V', 'Other'] as $suffixOption): ?>
                        <option value="<?= e($suffixOption) ?>" <?= $form['suffix'] === $suffixOption ? 'selected' : '' ?>>
                            <?= e($suffixOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="date_of_birth">Date of Birth</label>
                <input id="date_of_birth" name="date_of_birth" type="date"
                    max="<?= date('Y-m-d') ?>" value="<?= e($form['date_of_birth']) ?>"
                    oninput="calculateAge()">
            </div>

            <div>
                <label for="age_display">Age</label>
                <input id="age_display" value="<?= e($age) ?>" readonly
                    placeholder="Calculated from date of birth">
                <small class="field-hint">Automatically calculated; not stored separately.</small>
            </div>

            <div>
                <label for="sex">Sex (optional)</label>
                <select id="sex" name="sex">
                    <option value="">Prefer not to specify</option>
                    <?php foreach (['Female', 'Male', 'Other'] as $sexOption): ?>
                        <option value="<?= e($sexOption) ?>" <?= $form['sex'] === $sexOption ? 'selected' : '' ?>>
                            <?= e($sexOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="contact_number">Contact Number</label>
                <input id="contact_number" name="contact_number" type="tel"
                    maxlength="30" value="<?= e($form['contact_number']) ?>"
                    placeholder="e.g. 09XXXXXXXXX">
            </div>

            <div>
                <label for="religion">Religion (optional)</label>
                <input id="religion" name="religion" maxlength="100"
                    value="<?= e($form['religion']) ?>">
            </div>

            <div>
                <label for="civil_status">Civil Status</label>
                <select id="civil_status" name="civil_status">
                    <option value="">Select civil status</option>
                    <?php foreach ($civilStatuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= $form['civil_status'] === $status ? 'selected' : '' ?>>
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="occupation">Occupation (if applicable)</label>
                <input id="occupation" name="occupation" maxlength="150"
                    value="<?= e($form['occupation']) ?>">
            </div>

            <h3 class="form-section">B. Academic Information</h3>

            <div>
                <label for="course">Course / Program *</label>
                <input id="course" name="course" maxlength="100"
                    required placeholder="e.g. BS Computer Science"
                    value="<?= e($form['course']) ?>">
            </div>

            <div>
                <label for="year_level">Year Level *</label>
                <select id="year_level" name="year_level" required>
                    <option value="">Select year level</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= e($year) ?>" <?= $form['year_level'] === $year ? 'selected' : '' ?>>
                            <?= e($year) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="student_type">Student Type</label>
                <select id="student_type" name="student_type">
                    <option value="">Select student type</option>
                    <?php foreach ($studentTypes as $type): ?>
                        <option value="<?= e($type) ?>" <?= $form['student_type'] === $type ? 'selected' : '' ?>>
                            <?= e($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>


            <div>
                <label for="academic_year">Academic Year</label>
                <input id="academic_year" name="academic_year" maxlength="20"
                    placeholder="e.g. 2026-2027" value="<?= e($form['academic_year']) ?>">
            </div>

            <div>
                <label for="semester">Semester</label>
                <select id="semester" name="semester">
                    <option value="">Select semester</option>
                    <?php foreach (['1st Semester', '2nd Semester', 'Summer'] as $semesterOption): ?>
                        <option value="<?= e($semesterOption) ?>" <?= $form['semester'] === $semesterOption ? 'selected' : '' ?>>
                            <?= e($semesterOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="last_school_attended">Last School Attended</label>
                <input id="last_school_attended" name="last_school_attended" maxlength="200"
                    value="<?= e($form['last_school_attended']) ?>">
            </div>

            <div>
                <label for="last_school_address">Last School Address</label>
                <input id="last_school_address" name="last_school_address" maxlength="300"
                    value="<?= e($form['last_school_address']) ?>">
            </div>

            <div>
                <label for="nationality">Nationality (optional)</label>
                <input id="nationality" name="nationality" maxlength="100"
                    value="<?= e($form['nationality']) ?>">
            </div>


            <h3 class="form-section">C. Address Information</h3>

            <div>
                <label for="current_address">Current Address</label>
                <textarea id="current_address" name="current_address" maxlength="500"><?= e($form['current_address']) ?></textarea>
            </div>

            <div>
                <label for="provincial_address">Provincial / Permanent Address</label>
                <textarea id="provincial_address" name="provincial_address" maxlength="500"><?= e($form['provincial_address']) ?></textarea>
            </div>

            <h3 class="form-section">D. Family Information</h3>

            <div>
                <label for="mother_name">Mother's Full Name</label>
                <input id="mother_name" name="mother_name" maxlength="200"
                    value="<?= e($form['mother_name']) ?>">
            </div>

            <div>
                <label for="mother_contact">Mother's Contact Number</label>
                <input id="mother_contact" name="mother_contact" type="tel" maxlength="30"
                    value="<?= e($form['mother_contact']) ?>">
            </div>

            <div>
                <label for="father_name">Father's Full Name</label>
                <input id="father_name" name="father_name" maxlength="200"
                    value="<?= e($form['father_name']) ?>">
            </div>

            <div>
                <label for="father_contact">Father's Contact Number</label>
                <input id="father_contact" name="father_contact" type="tel" maxlength="30"
                    value="<?= e($form['father_contact']) ?>">
            </div>

            <div>
                <label for="guardian_name">Guardian's Full Name (if applicable)</label>
                <input id="guardian_name" name="guardian_name" maxlength="200"
                    value="<?= e($form['guardian_name']) ?>">
            </div>

            <div>
                <label for="guardian_contact">Guardian's Contact Number</label>
                <input id="guardian_contact" name="guardian_contact" type="tel" maxlength="30"
                    value="<?= e($form['guardian_contact']) ?>">
            </div>

            <h3 class="form-section">E. Emergency Contact</h3>

            <div>
                <label for="emergency_contact_name">Contact Person's Full Name</label>
                <input id="emergency_contact_name" name="emergency_contact_name" maxlength="200"
                    value="<?= e($form['emergency_contact_name']) ?>">
            </div>

            <div>
                <label for="emergency_contact_relationship">Relationship to Student</label>
                <input id="emergency_contact_relationship" name="emergency_contact_relationship"
                    maxlength="100" placeholder="e.g. Mother, Father, Sibling"
                    value="<?= e($form['emergency_contact_relationship']) ?>">
            </div>

            <div>
                <label for="emergency_contact_number">Emergency Contact Number</label>
                <input id="emergency_contact_number" name="emergency_contact_number" type="tel"
                    maxlength="30" value="<?= e($form['emergency_contact_number']) ?>">
            </div>

            <div>
                <label for="alternate_emergency_number">Alternate Emergency Number (optional)</label>
                <input id="alternate_emergency_number" name="alternate_emergency_number" type="tel"
                    maxlength="30" value="<?= e($form['alternate_emergency_number']) ?>">
            </div>
        </div>

        <button class="form-button" type="submit">
            <?= $form['id'] !== '' ? 'Save Changes' : 'Add Student' ?>
        </button>

        <?php if ($form['id'] !== ''): ?>
            <a class="button secondary" href="index.php">Cancel Edit</a>
        <?php endif; ?>
    </form>
</section>

<section class="card">
    <h2>Student Records</h2>

    <form method="GET" action="index.php" class="search-form">
        <input name="search"
            placeholder="Search ID, name, email, or course..."
            value="<?= e($search) ?>">
        <button type="submit">Search</button>
        <a class="button secondary" href="index.php">Clear</a>
    </form>

    <p class="count">Records displayed: <?= $count ?></p>

    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Student ID</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Course</th>
            <th>Year Level</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php $hasRecords = false; ?>

        <?php foreach ($studentList as $student): ?>
            <?php $hasRecords = true; ?>
            <tr>
                <td><?= e($student['student_id'] ?? '') ?></td>
                <td><?= e(trim(
                    ($student['first_name'] ?? '') . ' ' .
                    ($student['middle_name'] ?? '') . ' ' .
                    ($student['last_name'] ?? '') . ' ' .
                    ($student['suffix'] ?? '')
                )) ?></td>
                <td><?= e($student['email'] ?? '') ?></td>
                <td><?= e($student['course'] ?? '') ?></td>
                <td><?= e($student['year_level'] ?? '') ?></td>
                <td>
                    <div class="actions">
                        <a class="button edit"
                           href="index.php?edit=<?= e((string) $student['_id']) ?>">
                            Edit
                        </a>

                        <form method="POST" action="index.php"
                            onsubmit="return confirm('Are you sure you want to delete this student?');">
                            <input type="hidden" name="csrf_token"
                                value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id"
                                value="<?= e((string) $student['_id']) ?>">
                            <button class="delete" type="submit">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$hasRecords): ?>
            <tr>
                <td colspan="6">No student records found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>
</main>

<script>
function calculateAge() {
    const dobInput = document.getElementById('date_of_birth');
    const ageInput = document.getElementById('age_display');

    if (!dobInput || !dobInput.value) {
        ageInput.value = '';
        return;
    }

    const dob = new Date(dobInput.value + 'T00:00:00');
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const monthDifference = today.getMonth() - dob.getMonth();

    if (monthDifference < 0 ||
        (monthDifference === 0 && today.getDate() < dob.getDate())) {
        age--;
    }

    ageInput.value = age >= 0 ? age : '';
}

document.addEventListener('DOMContentLoaded', calculateAge);
</script>
</body>
</html>