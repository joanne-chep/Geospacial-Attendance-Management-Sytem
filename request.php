<?php
/**
 * request.php
 * Facilitates the submission of course enrolment requests by students.
 * Utilises the Singleton database connection to manage request persistence.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Authentication gate ensuring only authorised students can initiate requests.
 */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['course_id'])) {
    header("Location: student-dashboard.php?error=" . urlencode("Invalid request protocol."));
    exit();
}

/**
 * Initialisation of the database connection utilising the centralised Singleton instance.
 */
$conn = connectDB();
$studentId = $_SESSION['user_id'];
$courseId = (int)$_POST['course_id'];

try {
    /**
     * Prepared statement to securely insert a new enrolment request into the database, preventing SQL injection and ensuring data integrity.
     */
    $stmt = $conn->prepare("INSERT INTO requests (course_id, student_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $courseId, $studentId);
    $stmt->execute();
    $stmt->close();

    header("Location: student-dashboard.php?message=" . urlencode("Enrolment request submitted successfully. Awaiting Faculty Intern verification."));
    exit();

} catch (mysqli_sql_exception $ex) {
    $errorMessage = "Submission failure: ";
    if ($ex->getCode() === 1062) {
        $errorMessage .= "A pending request already exists for this module.";
    } else {
        $errorMessage .= "A database exception occurred during processing.";
    }
    header("Location: student-dashboard.php?error=" . urlencode($errorMessage));
    exit();
} catch (Exception $ex) {
    error_log("General Request Exception: " . $ex->getMessage());
    header("Location: student-dashboard.php?error=" . urlencode("An unexpected system error occurred."));
    exit();
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>