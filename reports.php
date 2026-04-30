<?php
/**
 * reports.php
 * Generates comprehensive attendance analytics for Faculty Interns.
 * Utilises the Singleton database connection to aggregate data across course modules.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Authentication gate ensuring only authorised Faculty Interns access system reports.
 * Redirection is initiated if session credentials or roles are invalid.
 */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'fi') {
    header("Location: login.php");
    exit();
}

/**
 * Initialisation of the database connection utilising the centralised Singleton instance.
 * This resolves the 'null' object error by establishing a valid connection to the
 * 'geospacial_attendance_management' repository.
 */
$conn = connectDB();

$fi_id = $_SESSION['user_id'];
$report_type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : 'attendance';
$name = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') : 'Faculty Intern';

/**
 * SQL execution to retrieve attendance records, student identity data, and session identifiers.
 * Results are ordered chronologically and by course module for systematic review.
 */
$sql = "
    SELECT
        a.attended_at,
        a.status,
        u.name AS student_name,
        u.email AS student_email,
        s.session_title,
        s.session_date,
        s.start_time,
        c.course_code,
        c.course_name
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    JOIN sessions s ON a.session_id = s.id
    JOIN courses c ON s.course_id = c.id
    WHERE c.fi_id = ?
    ORDER BY s.session_date DESC, c.course_code ASC, u.name ASC
";

$records = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $fi_id);
    $stmt->execute();
    $result = $stmt->get_result();
   
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Report generation data failure: " . $e->getMessage());
    $error_message = "System error: Unable to load report analytics.";
} finally {
    /**
     * Ensuring resource release.
     * While the Singleton manages the lifecycle, explicit closure ensures immediate
     * availability for concurrent system requests.
     */
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

/**
 * Normalises raw status codes into professional human-readable descriptors.
 * @param string $status
 * @return string
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
    <title>Attendance Analytics | Ashesi Attendance System</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #333; }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .report-table th {
            background-color: #7A0019;
            color: white;
            padding: 15px;
            text-align: left;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        .status-present { color: #28a745; font-weight: 600; }
        .status-late { color: #fd7e14; font-weight: 600; }
        .status-absent { color: #dc3545; font-weight: 600; }
        .main-content-report { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .header-section { border-bottom: 2px solid #7A0019; padding-bottom: 15px; margin-bottom: 30px; }
        .back-btn { display: inline-block; margin-top: 30px; color: #7A0019; text-decoration: none; font-weight: 500; transition: transform 0.2s; }
        .back-btn:hover { transform: translateX(-5px); text-decoration: underline; }
    </style>
</head>
<body>
    <div class="main-content-report">
        <div class="header-section">
            <h2 style="color: #7A0019; margin: 0;">Attendance Analytics Report</h2>
            <p style="margin-top: 8px; color: #666;">Generated for: <strong><?php echo $name; ?></strong></p>
        </div>
       
        <?php if (isset($error_message)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px;"><?php echo $error_message; ?></div>
        <?php elseif (empty($records)): ?>
            <div style="background: #fff; padding: 40px; text-align: center; border-radius: 8px; color: #888;">
                No attendance records identified for associated course modules.
            </div>
        <?php else: ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Course Module</th>
                        <th>Session Title</th>
                        <th>Scheduled Date</th>
                        <th>Student Name</th>
                        <th>Status</th>
                        <th>Recorded At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $row): ?>
                        <?php $status_class = 'status-' . strtolower(get_status_label($row['status'])); ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['course_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['session_title']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['session_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                            <td><span class="<?php echo $status_class; ?>"><?php echo get_status_label($row['status']); ?></span></td>
                            <td><?php echo date('h:i A', strtotime($row['attended_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
       
        <a href="fi-dashboard.php" class="back-btn">← Return to Faculty Dashboard</a>
    </div>
</body>
</html>