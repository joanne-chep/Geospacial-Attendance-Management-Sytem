<?php
//manage_attendance.php, responsible for managing attendance records

//This is for error identification in cases of issues
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

//Ensure that the user is a Faculty Intern
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'fi') {
    header("Location: login.php");
    exit();
}
if (!isset($_GET['session_id'])) {
    die("Error: Missing session ID.");
}

$conn = connectDB();
$current_fi_id = $_SESSION['user_id'];
$target_session_id = (int)$_GET['session_id'];
$page_message = '';
$page_error = '';

try {
    //Get Session Details and verify FI owns the sessions
    $info_sql = "
        SELECT s.session_title, s.session_date, s.start_time, c.id AS course_id, c.course_code
        FROM sessions s JOIN courses c ON s.course_id = c.id
        WHERE s.id = ? AND c.fi_id = ?
    ";
    $info_stmt = $conn->prepare($info_sql);
    $info_stmt->bind_param("ii", $target_session_id, $current_fi_id);
    $info_stmt->execute();
    $session_data = $info_stmt->get_result()->fetch_assoc();
    $info_stmt->close();

    if (!$session_data) {
        die("Session not found or access denied.");
    }
    $course_id_of_session = $session_data['course_id'];


    //Handle form submission for attendance
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_list'])) {
        //This getting the submitted data
        $submitted_attendance = $_POST['attendance_list'];
        $saved_count = 0;

        //Loop through every student submitted in the form
        foreach ($submitted_attendance as $student_id_key => $status_val) {
            $student_id_int = (int)$student_id_key;

            //Ensure status is valid before saving
            if (in_array($status_val, ['P', 'A', 'L'])) {

                //Check if a record already exists for this student in this session
                //We select 'status' instead of 'id' to avoid errors if the table has no ID column
                $check_existing = $conn->prepare("SELECT status FROM attendance WHERE session_id = ? AND user_id = ?");
                $check_existing->bind_param("ii", $target_session_id, $student_id_int);
                $check_existing->execute();
                $exists_result = $check_existing->get_result();
                $check_existing->close();

                if ($exists_result->num_rows > 0) {
                    //If the record exists we update it
                    $update_stmt = $conn->prepare("UPDATE attendance SET status = ?, attended_at = NOW() WHERE session_id = ? AND user_id = ?");
                    $update_stmt->bind_param("sii", $status_val, $target_session_id, $student_id_int);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else {
                    //If the record does not exist, we insert a new one
                    $insert_stmt = $conn->prepare("INSERT INTO attendance (session_id, user_id, status, attended_at) VALUES (?, ?, ?, NOW())");
                    $insert_stmt->bind_param("iis", $target_session_id, $student_id_int, $status_val);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                }
                $saved_count++;
            }
        }
        $page_message = "Saved attendance for $saved_count students.";
    }


    //Fetch students to display in the table
    //Getting enrolled students and their current marks for this session if they exist
    $stud_sql = "
        SELECT
            u.id AS s_id, u.name AS s_name, u.email AS s_email,
            a.status AS saved_status -- This will be NULL if not marked yet
        FROM course_enrollments ce
        JOIN users u ON ce.student_id = u.id
        LEFT JOIN attendance a ON u.id = a.user_id AND a.session_id = ?
        WHERE ce.course_id = ?
        ORDER BY u.name ASC
    ";
    $stud_stmt = $conn->prepare($stud_sql);
    $stud_stmt->bind_param("ii", $target_session_id, $course_id_of_session);
    $stud_stmt->execute();
    $stud_result = $stud_stmt->get_result();
    $enrolled_students = [];
    while ($row = $stud_result->fetch_assoc()) {
        $enrolled_students[] = $row;
    }
    $stud_stmt->close();

} catch (Exception $e) {
    $page_error = "Error: " . $e->getMessage();
} finally {
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Take Attendance</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .att-box { max-width: 800px; margin: 30px auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .info-header { border-bottom: 2px solid #f0f0f0; margin-bottom: 20px; padding-bottom: 10px; }
        .info-header h2 { color: #7A0019; margin: 0; }
        .att-table { width: 100%; border-collapse: collapse; }
        .att-table th, .att-table td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        .att-table th { background: #f8f9fa; }
        .radio-opt { margin-right: 15px; cursor: pointer; font-weight: 500; }
        .radio-p { color: green; } .radio-l { color: orange; } .radio-a { color: red; }
        .save-btn { background-color: #7A0019; color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; float: right; margin-top: 15px;}
        .save-btn:hover { background-color: #9e0022; }
    </style>
</head>
<body style="background-color: #f5f5f5;">

<div class="att-box">
    <p><a href="fi-dashboard.php" style="color: #7A0019; text-decoration: none;">← Back to Dashboard</a></p>

    <?php if ($page_message): ?><p style="color: green; background: #e0ffe0; padding: 10px; border-radius: 4px;"><?php echo $page_message; ?></p><?php endif; ?>
    <?php if ($page_error): ?><p style="color: red; background: #ffe0e0; padding: 10px; border-radius: 4px;"><?php echo $page_error; ?></p><?php endif; ?>

    <div class="info-header">
        <h2>Attendance: <?php echo htmlspecialchars($session_data['course_code']); ?></h2>
        <p>Session: <?php echo htmlspecialchars($session_data['session_title']); ?> on <?php echo date('d M Y', strtotime($session_data['session_date'])); ?></p>
    </div>

    <?php if (empty($enrolled_students)): ?>
        <p>No students are enrolled in this course yet. Students must request to join the course first.</p>
    <?php else: ?>
        <form method="POST" action="">
            <table class="att-table">
                <thead>
                    <tr><th>Student</th><th>Email</th><th>Mark Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($enrolled_students as $stud): ?>
                        <?php $db_status = $stud['saved_status']; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($stud['s_name']); ?></td>
                            <td><?php echo htmlspecialchars($stud['s_email']); ?></td>
                            <td>
                                <label class="radio-opt radio-p">
                                    <input type="radio" name="attendance_list[<?php echo $stud['s_id']; ?>]" value="P" <?php echo ($db_status === 'P') ? 'checked' : ''; ?> required> P
                                </label>
                                <label class="radio-opt radio-l">
                                    <input type="radio" name="attendance_list[<?php echo $stud['s_id']; ?>]" value="L" <?php echo ($db_status === 'L') ? 'checked' : ''; ?>> L
                                </label>
                                <label class="radio-opt radio-a">
                                    <input type="radio" name="attendance_list[<?php echo $stud['s_id']; ?>]" value="A" <?php echo ($db_status === 'A') ? 'checked' : ''; ?>> A
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="save-btn">Save Attendance</button>
            <div style="clear: both;"></div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>