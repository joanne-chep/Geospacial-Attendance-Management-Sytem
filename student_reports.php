<?php
/**
 * student_reports.php
 * Generates and displays comprehensive attendance analytics for the authenticated student.
 * Utilises the Singleton database connection to retrieve historical session data.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Authentication gate ensuring only authorised students access their personal reports
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$conn = connectDB();
$studentId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') : 'Student User';
$records = [];
$error = "";

try {
    /**
     * Retrieval of attendance logs, associated session details, and course metadata.
     * Records are ordered chronologically by session date to provide a clear timeline.
     */
    $sql = "
        SELECT 
            c.course_code, 
            c.course_name,
            s.session_title, 
            s.session_date, 
            a.status, 
            a.attended_at
        FROM attendance a
        JOIN sessions s ON a.session_id = s.id
        JOIN courses c ON s.course_id = c.id
        WHERE a.user_id = ?
        ORDER BY s.session_date DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    $stmt->close();

} catch (Exception $e) {
    error_log("Report generation error: " . $e->getMessage());
    $error = "System error: Unable to retrieve attendance records.";
} finally {
    if (isset($conn)) $conn->close();
}

/**
 * Helper function to normalise status codes into descriptive labels.
 * * @param string $status The raw status character from the database.
 * @return string The human-readable status label.
 */
function get_status_label($status) {
    switch ($status) {
        case 'P': return 'Present';
        case 'L': return 'Late';
        case 'A': return 'Absent';
        default: return 'Pending';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Reports | Ashesi Attendance System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1000px; margin: 40px auto; padding: 25px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .report-header { border-bottom: 2px solid #7A0019; padding-bottom: 15px; margin-bottom: 25px; }
        .report-table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        .report-table th { background-color: #7A0019; color: white; padding: 15px; text-align: left; text-transform: uppercase; letter-spacing: 1px; font-size: 0.85rem; }
        .report-table td { padding: 15px; border-bottom: 1px solid #eee; color: #444; }
        .status-present { color: #28a745; font-weight: 600; }
        .status-late { color: #fd7e14; font-weight: 600; }
        .status-absent { color: #dc3545; font-weight: 600; }
        .back-link { display: inline-block; margin-top: 30px; color: #7A0019; text-decoration: none; font-weight: 500; transition: transform 0.2s; }
        .back-link:hover { transform: translateX(-5px); }
    </style>
</head>
<body style="background-color: #f8f9fa; font-family: 'Inter', sans-serif;">
    <div class="container">
        <div class="report-header">
            <h2 style="color: #7A0019; margin: 0;">Attendance Analytics</h2>
            <p style="color: #666; margin-top: 5px;">Student Record: <strong><?php echo $name; ?></strong></p>
        </div>
        
        <?php if ($error): ?>
            <p style="color: #dc3545;"><?php echo $error; ?></p>
        <?php elseif (empty($records)): ?>
            <div style="text-align: center; padding: 40px; color: #888;">
                <p>No historical attendance data identified for this account.</p>
            </div>
        <?php else: ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Course Module</th>
                        <th>Session Description</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($r['course_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['session_title']); ?></td>
                            <td><?php echo date('D, M d, Y', strtotime($r['session_date'])); ?></td>
                            <td>
                                <span class="status-<?php echo strtolower(get_status_label($r['status'])); ?>">
                                    <?php echo get_status_label($r['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('h:i A', strtotime($r['attended_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <a href="student-dashboard.php" class="back-link">← Return to Dashboard</a>
    </div>
</body>
</html>