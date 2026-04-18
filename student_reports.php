<?php
//student_reports.php, responsible for displaying comprehensive attendance logs to students

//This configuration enforces comprehensive error reporting to facilitate accurate server-side monitoring
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

//This validation mechanism securely prohibits unauthorised actors from accessing the reporting infrastructure
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$conn = connectDB();
$studentId = $_SESSION['user_id'];
$name = htmlspecialchars($_SESSION['name']);
$records = [];

try {
    //This query meticulously aggregates data from attendance, sessions, and courses to provide a detailed chronological report
    $sql = "
        SELECT c.course_code, c.course_name, s.session_title, s.session_date, a.status, a.attended_at
        FROM attendance a
        JOIN sessions s ON a.session_id = s.id
        JOIN courses c ON s.course_id = c.id
        WHERE a.user_id = ?
        ORDER BY s.session_date DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    error_log("Error fetching student report: " . $e->getMessage());
    $error = "The system encountered an anomaly whilst loading your records.";
} finally {
    if (isset($conn)) $conn->close();
}

//This helper function maps internal status codes to human-readable labels for the user interface
function resolve_status($status) {
    $map = ['P' => 'Present', 'L' => 'Late', 'A' => 'Absent'];
    return $map[$status] ?? 'Unknown';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Attendance Reports</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .report-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
        .report-table th, .report-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .report-table th { background-color: #7A0019; color: white; }
        .status-present { color: #28a745; font-weight: bold; }
        .status-absent { color: #dc3545; font-weight: bold; }
        .container { max-width: 1000px; margin: 40px auto; padding: 20px; }
    </style>
</head>
<body style="background-color: #f8f9fa;">
    <div class="container">
        <h2 style="color: #7A0019;">Academic Attendance Summary</h2>
        <p>Authenticated Student: <strong><?php echo $name; ?></strong></p>
        
        <?php if (empty($records)): ?>
            <div style="padding: 20px; background: white; border-radius: 8px;">No attendance manifestations detected within the database.</div>
        <?php else: ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Course Module</th>
                        <th>Session Title</th>
                        <th>Calendar Date</th>
                        <th>Status</th>
                        <th>Log Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <?php $readableStatus = resolve_status($r['status']); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($r['session_title']); ?></td>
                            <td><?php echo date('D, M d, Y', strtotime($r['session_date'])); ?></td>
                            <td>
                                <span class="status-<?php echo strtolower($readableStatus); ?>">
                                    <?php echo $readableStatus; ?>
                                </span>
                            </td>
                            <td><?php echo date('h:i A', strtotime($r['attended_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p style="margin-top: 30px;"><a href="student-dashboard.php" style="color: #7A0019; font-weight: 600;">← Return to Dashboard</a></p>
    </div>
</body>
</html>