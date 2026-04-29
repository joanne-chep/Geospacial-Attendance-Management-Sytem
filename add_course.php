<?php
// add_course.php — responsible for adding courses into the system (FIs only)
// Adapter pattern is applied to cleanly separate input handling and validation from the main script logic


error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Auth guard — unchanged
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'fi') {
    header("Location: login.php");
    exit();
}

class CourseInputAdapter {
    private string $courseCode;
    private string $courseName;

    public function __construct(array $postData) {
        
        $this->courseCode = trim($postData['course_code'] ?? '');
        $this->courseName = trim($postData['course_name'] ?? '');
    }

    public function getCourseCode(): string {
        return $this->courseCode;
    }

    public function getCourseName(): string {
        return $this->courseName;
    }

    // Single place that defines what "valid input" means for a course
    public function isValid(): bool {
        return !empty($this->courseCode) && !empty($this->courseName);
    }

    public function getValidationError(): string {
        return 'Both course code and course name are required.';
    }
}


// ============================================================
// MAIN SCRIPT
// ============================================================
$conn    = connectDB(); 
$fi_id   = $_SESSION['user_id'];
$name    = $_SESSION['name'];
$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Adapter translates $_POST — everything below uses the adapter only
    $input = new CourseInputAdapter($_POST);

    if (!$input->isValid()) {
        $error = $input->getValidationError();
    } else {
        $courseCode = $input->getCourseCode();
        $courseName = $input->getCourseName();

        try {
            // Duplicate course code check 
            $check = $conn->prepare("SELECT course_code FROM courses WHERE course_code = ?");
            $check->bind_param("s", $courseCode);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = 'A course with this code already exists.';
            } else {
                // Insert the new course into the database
                $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, fi_id) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $courseCode, $courseName, $fi_id);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    header("Location: fi-dashboard.php?success=" . urlencode("Course '{$courseName}' added successfully!"));
                    exit();
                } else {
                    $error = 'Course was not added! Try again.';
                }
                $stmt->close();
            }
            $check->close();

        } catch (mysqli_sql_exception $ex) {
            $error = 'Database error! Could not insert the course.';
        }
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Course</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .form-container-course{max-width:500px;margin:40px auto;padding:2rem;background:white;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.1)}
        .form-container-course input{width:100%;padding:12px;margin:10px 0 20px 0;border:1px solid #ddd;border-radius:6px;box-sizing:border-box}
        .form-container-course button{width:100%;padding:12px;background-color:#7A0019;color:white;border:none;border-radius:6px;cursor:pointer;transition:background 0.3s}
        .form-container-course button:hover{background-color:#9e0022}
        .message-error{color:red;background-color:#ffe0e0;padding:10px;border-radius:6px;margin-bottom:15px}
    </style>
</head>
<body class="auth-body">
    <div class="form-container-course">
        <h2 style="color:#7A0019;text-align:center;">Enroll a New Course</h2>
        <p style="text-align:center;color:#555;">Assigned to: <?php echo htmlspecialchars($name); ?></p>

        <?php if ($error): ?>
            <p class="message-error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST" action="add_course.php">
            <label for="course_code" style="font-weight:600;display:block;">Course Code (e.g., CS 304)</label>
            <input type="text" id="course_code" name="course_code" required maxlength="10">

            <label for="course_name" style="font-weight:600;display:block;">Course Name</label>
            <input type="text" id="course_name" name="course_name" required maxlength="100">

            <button type="submit">Add Course</button>
        </form>

        <p style="margin-top:20px;text-align:center;">
            <a href="fi-dashboard.php" style="color:#7A0019;text-decoration:none;">← Back to Dashboard</a>
        </p>
    </div>
</body>
</html>