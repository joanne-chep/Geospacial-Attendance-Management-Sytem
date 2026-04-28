<?php
//add_session.php, responsible for addition of sessions with attendance codes

//This is for error identification in cases of issues
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

//Include database connection file
include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

//Checking if the user trying to add a session is an FI
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'fi') {
    header("Location: login.php");
    exit();
}

$conn = connectDB();
$current_fi_id = $_SESSION['user_id'];
$error_msg = '';
$success_msg = '';

//Get the courses that the FI ownns so they can add sessions for the selected course
$fi_courses = [];
try {
    $course_stmt = $conn->prepare("SELECT id, course_code, course_name FROM courses WHERE fi_id = ? ORDER BY course_code");
    $course_stmt->bind_param("i", $current_fi_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    while ($row = $course_result->fetch_assoc()) {
        $fi_courses[] = $row;
    }
    $course_stmt->close();
} catch (Exception $e) {
    $error_msg = "Could not load your courses.";
}

//Process the form when the button is clicked for addition
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //Get data from form
    $selected_course_id = $_POST['course_id'];
    $s_title = trim($_POST['session_title']);
    $s_date = $_POST['session_date'];
    $s_start = $_POST['start_time'];
    $s_end = empty($_POST['end_time']) ? null : $_POST['end_time'];

    //Check required fields are filled
    if (empty($selected_course_id) || empty($s_title) || empty($s_date) || empty($s_start)) {
        $error_msg = "Please fill in all required fields marked with *.";
    } else {
        try {
            //Verify the FI actually owns the course they selected before inserting
            $check_stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND fi_id = ?");
            $check_stmt->bind_param("ii", $selected_course_id, $current_fi_id);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows === 0) {
                $error_msg = "You do not have permission to add sessions to that course.";
            } else {
                //This generates a random 4 character code for student attendance
                $attendance_code = strtoupper(substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 4));

                //The FI owns the course, proceed with insert including the new code
                $insert_stmt = $conn->prepare("INSERT INTO sessions (course_id, session_title, attendance_code, session_date, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("isssss", $selected_course_id, $s_title, $attendance_code, $s_date, $s_start, $s_end);
                $insert_stmt->execute();
                //if successful display a message to the FI
                if ($insert_stmt->affected_rows > 0) {
                    $success_msg = "Session created successfully! Code: " . $attendance_code;
                    //Redirect the FI to their dashboard
                    header("refresh:2;url=fi-dashboard.php");
                }
                $insert_stmt->close();
            }
            $check_stmt->close();

        } catch (Exception $e) {
            $error_msg = "Database error: " . $e->getMessage();
        }
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Session</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .session-form-box { max-width: 500px; margin: 30px auto; padding: 2rem; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-input-group { margin-bottom: 15px; }
        .form-input-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-input-group input, .form-input-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .create-btn { width: 100%; padding: 10px; background-color: #7A0019; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .create-btn:hover { background-color: #9e0022; }
    </style>
</head>
<body style="background-color: #f5f5f5;">
    
    <div class="session-form-box">
        <h2 style="color: #7A0019; text-align: center;">Create Class Session</h2>
        
        <?php if ($error_msg): ?>
            <p style="color: red; background: #ffe0e0; padding: 10px; border-radius: 4px;"><?php echo htmlspecialchars($error_msg); ?></p>
        <?php endif; ?>
        <?php if ($success_msg): ?>
            <p style="color: green; background: #e0ffe0; padding: 10px; border-radius: 4px;"><?php echo htmlspecialchars($success_msg); ?></p>
        <?php else: ?>

        <form method="POST" action="add_session.php">
            <div class="form-input-group">
                <label>Select Course *</label>
                <select name="course_id" required>
                    <option value="">-- Choose Course --</option>
                    <?php foreach ($fi_courses as $c): ?>
                        <option value="<?php echo $c['id']; ?>">
                            <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-input-group">
                <label>Session Title *</label>
                <input type="text" name="session_title" required placeholder="e.g., Week 1 Lecture">
            </div>

            <div class="form-input-group">
                <label>Date *</label>
                <input type="date" name="session_date" required>
            </div>

            <div style="display: flex; gap: 10px;">
                <div class="form-input-group" style="flex: 1;">
                    <label>Start Time *</label>
                    <input type="time" name="start_time" required>
                </div>
                <div class="form-input-group" style="flex: 1;">
                    <label>End Time (Optional)</label>
                    <input type="time" name="end_time">
                </div>
            </div>

            <button type="submit" class="create-btn">Create Session</button>
        </form>
        <?php endif; ?>

        <p style="text-align: center; margin-top: 20px;">
            <a href="fi-dashboard.php" style="color: #7A0019; text-decoration: none;">← Back to Dashboard</a>
        </p>
    </div>
</body>
</html>