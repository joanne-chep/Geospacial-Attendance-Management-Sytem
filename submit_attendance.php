<?php
/**
 * submit_attendance.php
 * Implements a Strategy Pattern to handle student attendance verification.
 * This architecture decouples validation logic from the main execution flow,
 * allowing for interchangeable verification rules.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';

/**
 * ValidationStrategy Interface
 * Defines the contract for attendance verification mechanisms.
 */
interface ValidationStrategy {
    public function validate($data): bool;
    public function getFeedback(): string;
    public function getStatus(): string;
}

/**
 * IPValidationStrategy
 * Concrete implementation of ValidationStrategy that verifies attendance
 * based on the student's network infrastructure.
 */
class IPValidationStrategy implements ValidationStrategy {
    private $authorised_prefix = "192.168.1.";
    private $client_ip;
    private $is_valid = false;

    public function __construct($ip) {
        $this->client_ip = $ip;
    }

    public function validate($data): bool {
        // Verification of the client IP against the authorised network prefix
        $this->is_valid = (strpos($this->client_ip, $this->authorised_prefix) !== false);
        return $this->is_valid;
    }

    public function getFeedback(): string {
        return $this->is_valid 
            ? "Success! Presence verified on the classroom network." 
            : "Verification Failed: Device not connected to the authorised campus Wi-Fi. Detected IP: " . $this->client_ip;
    }

    public function getStatus(): string {
        // Returns 'P' for Present or 'A' for Absent based on validation outcome
        return $this->is_valid ? 'P' : 'A';
    }
}

/**
 * AttendanceManager
 * Context class that executes a selected ValidationStrategy.
 */
class AttendanceManager {
    private $strategy;

    public function __construct(ValidationStrategy $strategy) {
        $this->strategy = $strategy;
    }

    public function process() {
        $this->strategy->validate(null);
        return [
            'status' => $this->strategy->getStatus(),
            'message' => $this->strategy->getFeedback()
        ];
    }
}

// Logic execution for POST requests initiated from the student dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    
    // Initialisation of the IP-based validation strategy
    $strategy = new IPValidationStrategy($student_ip);
    $manager = new AttendanceManager($strategy);
    
    $result = $manager->process();
    
    $status_to_mark = $result['status'];
    $feedback_message = $result['message'];

    // Database interaction utilising the established Singleton connection
    $conn = connectDB();
    
    try {
        // Redirection with the appropriate status message based on validation results
        $redirect = ($status_to_mark === 'A') ? "error" : "message";
        header("Location: student-dashboard.php?$redirect=" . urlencode($feedback_message));
        exit();

    } catch (Exception $e) {
        error_log("Attendance processing error: " . $e->getMessage());
        header("Location: student-dashboard.php?error=System error during verification.");
    }
}
?>