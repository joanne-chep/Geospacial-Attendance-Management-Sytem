<?php
//request.php, responsible for handling course requests by students

//This is for error identification in cases of issues
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
//This ensure that requests can only be sent by students
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}
//If the form is submitted, process the data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['course_id'])) {
    header("Location: student-dashboard.php?error=" . urlencode("Invalid request method."));
    exit();
}

$conn = $conn;
$studentId = $_SESSION['user_id'];
//Get the course ID from the form
$courseId = (int)$_POST['course_id'];

try {
    //Insert the request into the database
    //This will be used to check if the student has already requested the course
    $stmt = $conn->prepare("INSERT INTO requests (course_id, student_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $courseId, $studentId);
    $stmt->execute();
    $stmt->close();
    //Redirect to the student dashboard with a display of a success meassage
    header("Location: student-dashboard.php?message=" . urlencode("Request sent successfully! Awaiting faculty Intern approval."));
    exit();

} catch (mysqli_sql_exception $ex) {
    $errorMessage = "Request failed: ";
    //This will be used to check if the student has already requested the course
    //No duplicate requests accepted
    if ($ex->getCode() === 1062) {
        $errorMessage .= "You have already requested this course.";
    } else {
        $errorMessage .= "Database error occurred.";
    }
    header("Location: student-dashboard.php?error=" . urlencode($errorMessage));
    exit();
} catch (Exception $ex) {
    header("Location: student-dashboard.php?error=" . urlencode("An unexpected error occurred."));
    exit();
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>