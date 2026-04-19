<?php
//submit_attendance.php - Refactored using the Strategy Pattern

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';

// THE STRATEGY INTERFACE
// This defines the contract that any validation rule must follow
interface ValidationStrategy {
    public function validate($data): bool;
    public function getFeedback(): string;
    public function getStatus(): string;
}

// CONCRETE STRATEGY: IP VALIDATION
class IPValidationStrategy implements ValidationStrategy {
    private $authorised_prefix = "192.168.1.";
    private $client_ip;
    private $is_valid = false;

    public function __construct($ip) {
        $this->client_ip = $ip;
    }

    public function validate($data): bool {
        $this->is_valid = (strpos($this->client_ip, $this->authorised_prefix) !== false);
        return $this->is_valid;
    }

    public function getFeedback(): string {
        return $this->is_valid 
            ? "Success! You are on the classroom network." 
            : "Absent: You are not connected to the campus Wi-Fi. Detected IP: " . $this->client_ip;
    }

    public function getStatus(): string {
        return $this->is_valid ? 'P' : 'A';
    }
}

// This class uses the strategy. It doesn't care HOW the validation works.
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

//  MAIN EXECUTION 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    
    // If we wanted GPS later, we'd just swap this for 'new GPSValidationStrategy()'
    $strategy = new IPValidationStrategy($student_ip);
    $manager = new AttendanceManager($strategy);
    
    $result = $manager->process();
    
    $status_to_mark = $result['status'];
    $feedback_message = $result['message'];

    // Database operations using our Singleton
    $conn = connectDB();
    
    try {
        
        $redirect = ($status_to_mark === 'A') ? "error" : "message";
        header("Location: student-dashboard.php?$redirect=" . urlencode($feedback_message));
        exit();

    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        header("Location: student-dashboard.php?error=System anomaly.");
    }
}
?>