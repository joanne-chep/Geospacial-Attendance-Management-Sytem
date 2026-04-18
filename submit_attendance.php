<?php
//submit_attendance.php, responsible for processing student attendance codes whilst enforcing classroom-specific IP address restrictions to counteract fraudulent submissions

//This configuration enforces comprehensive error reporting to facilitate accurate server-side monitoring and debugging during the development lifecycle
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

//This validation mechanism securely prohibits unauthorised actors from accessing the submission infrastructure
//It explicitly verifies that an active session exists and that the authenticated user holds the 'student' role designation
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

//This condition ensures that the script only processes data when an explicit POST request is transmitted from the student dashboard form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['attendance_code']);
    $studentId = $_SESSION['user_id'];

    //This validates that the payload is not empty before committing to intensive database operations
    if (empty($code)) {
        header("Location: student-dashboard.php?error=" . urlencode("Please provide a valid 4-character attendance code."));
        exit();
    }

    //This section meticulously isolates the student's actual network IP address from the incoming request headers
    //It evaluates various server variables because complex university network topologies frequently route traffic through proxies, load balancers, or firewalls
    $student_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

    //This variable defines the authorised IP address or subnet designating the specific physical classroom environment
    //You must alter this string to match the precise network prefix broadcasted by the lecture theatre's Wi-Fi router
    $authorised_classroom_ip = "192.168.1."; 
    
    //These variables initialise the default parameters for the database insertion
    $status_to_mark = (strpos($student_ip, $authorised_classroom_ip) !== false) ? 'P' : 'A';
    $feedback_message = ($status_to_mark === 'P') 
        ? "Success! You have been marked Present." 
        : "Attendance marked as Absent. You must be connected to the designated classroom network. Your current recorded IP is " . $student_ip;

    $conn = connectDB();

    try {
        //This query validates the provided attendance code against all active and historical sessions currently stored within the database
        $stmt = $conn->prepare("SELECT id, course_id FROM sessions WHERE attendance_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $session = $result->fetch_assoc();
        $stmt->close();

        //If the query yields zero results, it strictly implies the code is either inherently invalid, mistyped, or pertains to an obsolete session
        if (!$session) {
            header("Location: student-dashboard.php?error=" . urlencode("Invalid attendance code. Please verify the code and attempt submission again."));
            exit();
        }

        $sessionId = $session['id'];
        $courseId = $session['course_id'];

        //This block verifies the student's official formal enrolment status for the specific academic course associated with the scanned code
        $checkEnroll = $conn->prepare("SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ?");
        $checkEnroll->bind_param("ii", $courseId, $studentId);
        $checkEnroll->execute();
        if ($checkEnroll->get_result()->num_rows === 0) {
            $checkEnroll->close();
            header("Location: student-dashboard.php?error=" . urlencode("Access denied: You lack the requisite enrolment permissions for this course."));
            exit();
        }
        $checkEnroll->close();

        //This atomic operation handles both new entries and updates to ensure the database maintains a singular source of truth for each student's session status
        $upsertSql = "INSERT INTO attendance (session_id, user_id, status, attended_at, ip_address) 
                    VALUES (?, ?, ?, NOW(), ?) 
                    ON DUPLICATE KEY UPDATE status = VALUES(status), attended_at = NOW(), ip_address = VALUES(ip_address)";
        
        $upsertStmt = $conn->prepare($upsertSql);
        $upsertStmt->bind_param("iiss", $sessionId, $studentId, $status_to_mark, $student_ip);
        $upsertStmt->execute();
        $upsertStmt->close();

        //This conditional branching dictates whether the resulting feedback message is rendered as a success notification or a red error alert on the dashboard
        $redirectType = ($status_to_mark === 'A') ? "error" : "message";
        header("Location: student-dashboard.php?$redirectType=" . urlencode($feedback_message));
        exit();

    } catch (Exception $e) {
        //This captures exact system anomalies and records them to the server log whilst displaying a sanitised, non-technical notification to the end-user
        error_log("Attendance processing error: " . $e->getMessage());
        header("Location: student-dashboard.php?error=" . urlencode("An internal database anomaly occurred whilst attempting to process your attendance submission."));
    } finally {
        //This ensures the database connection is cleanly severed to prevent resource exhaustion on the server architecture
        if (isset($conn)) { $conn->close(); }
    }
} else {
    //This firmly redirects any users attempting to access the processing script directly via the browser URL bar
    header("Location: student-dashboard.php");
    exit();
}
?>