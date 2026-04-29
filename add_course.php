<?php
/**
 * add_course.php
 * Facilitates the registration of new course modules by Faculty Interns.
 * Utilises an Adapter Pattern to decouple input sanitisation from core execution logic.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Authentication gate ensuring only authorised Faculty Interns manage course data
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'fi') {
    header("Location: login.php");
    exit();
}

/**
 * CourseInputAdapter
 * Implements the Adapter Pattern to standardise and validate incoming form data.
 */
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

    /**
     * Verifies that the mandatory course parameters are present.
     * @return bool
     */
    public function isValid(): bool {
        return !empty($this->courseCode) && !empty($this->courseName);
    }

    public function getValidationError(): string {
        return 'Both course code and course name are mandatory requirements.';
    }
}

/**
 * Initialisation of the centralised database connection utilising the Singleton instance.
 */
$conn = connectDB(); 

$fi_id   = $_SESSION['user_id'];
$name    = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') : 'Faculty Intern';
$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Utilising the Adapter pattern to process global POST data
    $input = new CourseInputAdapter($_POST);

    if (!$input->isValid()) {
        $error = $input->getValidationError();
    } else {
        $courseCode = $input->getCourseCode();
        $courseName = $input->getCourseName();

        try {
            /**
             * Uniqueness verification.
             * Checks the repository for existing course codes before initiating persistence.
             */
            $check = $conn->prepare("SELECT course_code FROM courses WHERE course_code = ?");
            $check->bind_param("s", $courseCode);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = 'A course module with this identifier is already registered.';
            } else {
                /**
                 * Data persistence using parameterised queries to ensure architectural security.
                 */
                $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, fi_id) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $courseCode, $courseName, $fi_id);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    header("Location: fi-dashboard.php?success=" . urlencode("Course '{$courseName}' successfully registered."));
                    exit();
                } else {
                    $error = 'Submission failed. Please verify the input and attempt again.';
                }
                $stmt->close();
            }
            $check->close();

        } catch (mysqli_sql_exception $ex) {
            error_log("Course Registration Exception: " . $ex->getMessage());
            $error = 'Database exception: Unable to complete course registration.';
        }
    }
}

// Managed closure of the connection following script execution
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Initialise Course | Ashesi Attendance System</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .form-container-course{max-width:500px;margin:40px auto;padding:2.5rem;background:white;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,0.05)}
        .form-container-course input{width:100%;padding:12px;margin:10px 0 25px 0;border:1.5px solid #eee;border-radius:8px;box-sizing:border-box;font-family: inherit;}
        .form-container-course button{width:100%;padding:14px;background-color:#7A0019;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;transition: background 0.2s}
        .form-container-course button:hover{background-color:#9e0022}
        .message-error{color:#dc3545;background-color:#f8d7da;padding:12px;border-radius:8px;margin-bottom:20px;font-size: 0.9rem;}
        label { font-size: 0.9rem; color: #444; }
    </style>
</head>
<body class="auth-body" style="background-color: #f8f9fa; font-family: 'Inter', sans-serif;">
    <div class="form-container-course">
        <h2 style="color:#7A0019;text-align:center;margin-top: 0;">Register New Course</h2>
        <p style="text-align:center;color:#666;font-size: 0.9rem;margin-bottom: 30px;">Associated FI: <strong><?php echo $name; ?></strong></p>

        <?php if ($error): ?>
            <p class="message-error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST" action="add_course.php">
            <label for="course_code" style="font-weight:500;">Course Identifier (e.g., CS 415)</label>
            <input type="text" id="course_code" name="course_code" placeholder="Enter course code" required maxlength="10">

            <label for="course_name" style="font-weight:500;">Module Name</label>
            <input type="text" id="course_name" name="course_name" placeholder="Enter full course title" required maxlength="100">

            <button type="submit">Register Course</button>
        </form>

        <p style="margin-top:25px;text-align:center;font-size: 0.9rem;">
            <a href="fi-dashboard.php" style="color:#7A0019;text-decoration:none;font-weight:500;">← Return to Dashboard</a>
        </p>
    </div>
</body>
</html>