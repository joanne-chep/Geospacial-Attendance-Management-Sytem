<?php
// accept.php — responsible for accepting or rejecting enrollment requests
// adapter design pattern used to cleanly separate request handling from business logic
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Auth guard — ensures only logged-in FIs can access this script
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'fi') {
    header("Location: login.php");
    exit();
}

class EnrollmentRequestAdapter {
    private int $requestId;
    private string $action;

    // Status and message map — one place to change if labels ever change
    private array $actionMap = [
        'approve' => [
            'status'  => 'Approved',
            'message' => 'Request approved and student enrolled successfully.'
        ],
        'reject' => [
            'status'  => 'Rejected',
            'message' => 'Request rejected successfully.'
        ],
    ];

    // Constructor validates and adapts the raw POST data
    public function __construct(array $postData) {
        if (!isset($postData['request_id']) || !isset($postData['action'])) {
            $this->redirectWithError("Invalid request parameters.");
        }

        $this->action    = $postData['action'];
        $this->requestId = (int)$postData['request_id'];

        if (!array_key_exists($this->action, $this->actionMap)) {
            $this->redirectWithError("Invalid action specified.");
        }
    }

    public function getRequestId(): int {
        return $this->requestId;
    }

    public function getAction(): string {
        return $this->action;
    }

    public function getNewStatus(): string {
        return $this->actionMap[$this->action]['status'];
    }

    public function getSuccessMessage(): string {
        return $this->actionMap[$this->action]['message'];
    }

    private function redirectWithError(string $message): void {
        header("Location: fi-dashboard.php?error=" . urlencode($message));
        exit();
    }
}


// ============================================================
// REQUEST GUARD — ensures this script is only accessed via POST with the expected parameters
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: fi-dashboard.php?error=" . urlencode("Invalid request parameters."));
    exit();
}

// Adapter translates $_POST into a clean object

$request = new EnrollmentRequestAdapter($_POST);

$conn      = connectDB();
$fiId      = $_SESSION['user_id'];
$requestId = $request->getRequestId();
$action    = $request->getAction();
$newStatus = $request->getNewStatus();

try {
    // Permission check 
    // Ensures the FI owns the course associated with the request
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

    if (!$requestData) {
        header("Location: fi-dashboard.php?error=" . urlencode("Error: Request not found or permission denied."));
        exit();
    }
    $checkStmt->close();

    // Transaction 
    $conn->begin_transaction();

    // Update request status
    $updateStmt = $conn->prepare("UPDATE requests SET request_status = ? WHERE id = ?");
    $updateStmt->bind_param("si", $newStatus, $requestId);
    $updateStmt->execute();
    $updateStmt->close();

    // Enroll student if approved 
    if ($action === 'approve') {
        $courseIdToEnroll  = $requestData['course_id'];
        $studentIdToEnroll = $requestData['student_id'];

        $enrollStmt = $conn->prepare("INSERT IGNORE INTO course_enrollments (course_id, student_id) VALUES (?, ?)");
        $enrollStmt->bind_param("ii", $courseIdToEnroll, $studentIdToEnroll);
        $enrollStmt->execute();
        $enrollStmt->close();
    }

    $conn->commit();

    header("Location: fi-dashboard.php?success=" . urlencode($request->getSuccessMessage()));
    exit();

} catch (Exception $e) {
    // Rollback on failure 
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("Database error during enrollment attempt: " . $e->getMessage());
    header("Location: fi-dashboard.php?error=" . urlencode("Database error processing request."));
    exit();

} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>