<?php
/**
 * process_qr_attendance.php
 * Handles asynchronous attendance logging initiated by a Faculty Intern
 * scanning a student's QR token.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';

header('Content-Type: application/json');

// Validation of session and role to ensure only authorised FIs execute this script
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'fi') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $studentId = intval($_POST['student_id']);
    $fi_id = $_SESSION['user_id'];
    $conn = connectDB();

    try {
        /**
         * Identification of the most recent active session managed by this FI.
         * Assumes the FI is scanning for the session currently in progress.
         */
        $sessionSql = "
            SELECT s.id, s.course_id 
            FROM sessions s
            JOIN courses c ON s.course_id = c.id
            WHERE c.fi_id = ? 
            ORDER BY s.session_date DESC, s.start_time DESC 
            LIMIT 1
        ";
        $stmtSession = $conn->prepare($sessionSql);
        $stmtSession->bind_param("i", $fi_id);
        $stmtSession->execute();
        $sessionResult = $stmtSession->get_result()->fetch_assoc();
        $stmtSession->close();

        if (!$sessionResult) {
            echo json_encode(['success' => false, 'message' => 'No active session identified.']);
            exit();
        }

        $sessionId = $sessionResult['id'];
        $courseId = $sessionResult['course_id'];

        // Verification of the student's enrolment status in the specific course
        $enrollSql = "SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ?";
        $stmtEnroll = $conn->prepare($enrollSql);
        $stmtEnroll->bind_param("ii", $courseId, $studentId);
        $stmtEnroll->execute();
        if ($stmtEnroll->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Student is not enrolled in this course.']);
            $stmtEnroll->close();
            exit();
        }
        $stmtEnroll->close();

        /**
         * Execution of an 'Upsert' operation to log attendance.
         * If a record already exists, the status is updated to 'P' (Present).
         */
        $upsertSql = "
            INSERT INTO attendance (session_id, user_id, status, attended_at, ip_address) 
            VALUES (?, ?, 'P', NOW(), 'QR_SCAN') 
            ON DUPLICATE KEY UPDATE status = 'P', attended_at = NOW(), ip_address = 'QR_SCAN'
        ";
        $stmtUpsert = $conn->prepare($upsertSql);
        $stmtUpsert->bind_param("ii", $sessionId, $studentId);
        
        if ($stmtUpsert->execute()) {
            echo json_encode(['success' => true, 'message' => 'Attendance logged successfully for Student ID: ' . $studentId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database update failed.']);
        }
        $stmtUpsert->close();

    } catch (Exception $e) {
        error_log("QR Attendance Processing Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Internal server error occurred.']);
    } finally {
        if (isset($conn)) {
            $conn->close();
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request payload.']);
}