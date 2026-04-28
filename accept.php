<?php
//accept.php, responsible for accepting or rejecting enrollment requests

//This is for error identification in cases of issues
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
//This ensures that the user logged in is a Faculty Intern and redirects them to the login page if not
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'fi') {
    header("Location: login.php");
    exit();
}
//If the form is submitted, process the data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['request_id']) || !isset($_POST['action'])) {
    header("Location: fi-dashboard.php?error=" . urlencode("Invalid request parameters."));
    exit();
}

$conn = connectDB();
$fiId = $_SESSION['user_id'];
$requestId = (int)$_POST['request_id'];
$action = $_POST['action'];
//Set the new status and success message based on the action
if ($action === 'approve') {
    $newStatus = 'Approved';
    $successMessage = 'Request approved and student enrolled successfully.';
} elseif ($action === 'reject') {
    $newStatus = 'Rejected';
    $successMessage = 'Request rejected successfully.';
} else {
    //Display an error message
    header("Location: fi-dashboard.php?error=" . urlencode("Invalid action specified."));
    exit();
}

try {
    //Check if the user has permission to accept or reject the request
    //Ensure the FI owns the course with the request
    $checkSql = "
        SELECT er.id, er.course_id, er.student_id
        FROM requests er
        JOIN courses c ON er.course_id = c.id
        WHERE er.id = ? AND c.fi_id = ?
    ";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ii", $requestId, $fiId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $requestData = $checkResult->fetch_assoc();

    if (!$requestData) {//If the request is not found or they are not allowed to take action
        header("Location: fi-dashboard.php?error=" . urlencode("Error: Request not found or permission denied."));
        exit();
    }
    $checkStmt->close();


    //Begin a transaction in the database
    //This will allow us to rollback if something goes wrong
    //If the transaction is successful, the changes will be committed
    $conn->begin_transaction();

    //Update the request status
    $updateSql = "UPDATE requests SET request_status = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("si", $newStatus, $requestId);
    $updateStmt->execute();
    $updateStmt->close();

    //Enroll the student in the course
    if ($action === 'approve') {
        $courseIdToEnroll = $requestData['course_id'];
        $studentIdToEnroll = $requestData['student_id'];

        //Insert the enrollment into the database
        //This will be used to check if the student has already enrolled in the course
        $enrollSql = "INSERT IGNORE INTO course_enrollments (course_id, student_id) VALUES (?, ?)";
        $enrollStmt = $conn->prepare($enrollSql);
        $enrollStmt->bind_param("ii", $courseIdToEnroll, $studentIdToEnroll);
        $enrollStmt->execute();
        $enrollStmt->close();
    }

    //Commit the transaction and save the changes in the database
    $conn->commit();

    header("Location: fi-dashboard.php?success=" . urlencode($successMessage));
    exit();

} catch (Exception $e) {
    //Rollback the transaction if something goes wrong
    //This will revert any changes made to the database
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("Database error during enrollment attempt: " . $e->getMessage());
    header("Location: fi-dashboard.php?error=" . urlencode("Database error processing request."));
    exit();
} finally {
    //Close the database connection
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>