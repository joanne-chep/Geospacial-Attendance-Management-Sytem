<?php
//student-dashboard.php, responsible for rendering the primary student interface

//This configuration enforces comprehensive error reporting for server-side monitoring
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

//This validation mechanism securely prohibits unauthorised access to the student interface
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$conn = connectDB();
$studentId = $_SESSION['user_id'];
$name = htmlspecialchars($_SESSION['name'] ?? 'Student User', ENT_QUOTES, 'UTF-8');
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

$enrolledCourses = [];
$pendingRequests = [];
$availableCourses = [];

try {
    //This query fetches courses where the student has an active, approved enrolment
    $stmtEnrolled = $conn->prepare("
        SELECT c.id, c.course_code, c.course_name, u.name AS fi_name
        FROM course_enrollments ce
        JOIN courses c ON ce.course_id = c.id
        JOIN users u ON c.fi_id = u.id
        WHERE ce.student_id = ?
        ORDER BY c.course_code
    ");
    $stmtEnrolled->bind_param("i", $studentId);
    $stmtEnrolled->execute();
    $enrolledCourses = $stmtEnrolled->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtEnrolled->close();

    //This unified query retrieves all courses not yet enrolled, identifying those with pending requests
    $stmtAvailable = $conn->prepare("
        SELECT c.id, c.course_code, c.course_name, u.name AS fi_name, r.request_status
        FROM courses c
        JOIN users u ON c.fi_id = u.id
        LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.student_id = ?
        LEFT JOIN requests r ON c.id = r.course_id AND r.student_id = ?
        WHERE ce.id IS NULL
        ORDER BY c.course_code
    ");
    $stmtAvailable->bind_param("ii", $studentId, $studentId); 
    $stmtAvailable->execute();
    $result = $stmtAvailable->get_result();

    while ($row = $result->fetch_assoc()) {
        if ($row['request_status'] === 'Pending') {
            $pendingRequests[] = $row;
        } else {
            $availableCourses[] = $row;
        }
    }
    $stmtAvailable->close();

} catch (Exception $e) {
    error_log("Database error in student dashboard: " . $e->getMessage());
    $error = "Database error: Could not fetch course data.";
} finally {
    if (isset($conn)) { $conn->close(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .attendance-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 30px; border-left: 5px solid #7A0019; }
        .code-form { display: flex; gap: 10px; margin-top: 15px; }
        .code-input { padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 1.2rem; width: 200px; text-align: center; }
        .submit-btn { padding: 12px 24px; background-color: #7A0019; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .profile-photo { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #7A0019; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="Ashesi.jpeg" alt="Ashesi University Logo" class="ashesi-image">
            <h2 class="system-name">Ashesi Attendance</h2>
        </div>
        <div class="profile-section">
            <?php 
                //This determines the profile image source; it defaults to the user's specific image but provides a resilient fallback to the system default
                $profile_img = "Joanne--Chepkoech.jpg"; 
            ?>
            <img src="<?php echo $profile_img; ?>" alt="Profile Photo" class="profile-photo" onerror="this.src='default-profile.png'">
            <p class="profile-name"><?php echo $name; ?></p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="#" onclick="showSection('dashboard_home')">Home</a></li>
            <li><a href="#" onclick="showSection('available')">Request Courses</a></li>
            <li><a href="student_reports.php">My Reports</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <h2>WELCOME! <?php echo $name; ?> (Student) 👋</h2>

        <?php if ($message): ?>
            <p style="color:green; background-color:#e0ffe0; padding:15px; border-radius:6px; border: 1px solid green;"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p style="color:red; background-color:#ffe0e0; padding:15px; border-radius:6px; border: 1px solid red;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <section id="dashboard_home" class="content-section active">
            <div class="attendance-card">
                <h3>Mark Attendance</h3>
                <p>Input the 4-character code provided by your instructor for the current session.</p>
                <form action="submit_attendance.php" method="POST" class="code-form">
                    <input type="text" name="attendance_code" class="code-input" placeholder="CODE" required maxlength="4">
                    <button type="submit" class="submit-btn">Submit Attendance</button>
                </form>
            </div>

            <h3>My Enrolled Courses</h3>
            <ul id="enrolledList">
                <?php if (empty($enrolledCourses)): ?>
                    <li>You are not currently enrolled in any courses.</li>
                <?php else: ?>
                    <?php foreach ($enrolledCourses as $course): ?>
                        <li style="border-left: 4px solid #008000; background-color: #f0fff0; margin-bottom: 10px; padding: 10px;">
                            <strong><?php echo htmlspecialchars($course['course_code']); ?></strong> - 
                            <?php echo htmlspecialchars($course['course_name']); ?> 
                            <br><small>Instructor: <?php echo htmlspecialchars($course['fi_name']); ?></small>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>

        <section id="available" class="content-section" style="display:none;">
            <h3>Course Requests</h3>
            <ul id="availableList">
                <?php foreach ($pendingRequests as $course): ?>
                    <li style="background-color: #fffac2; border-left: 4px solid #ffc107; margin-bottom: 10px; padding: 10px;">
                        <?php echo htmlspecialchars($course['course_code']); ?> - Pending Approval
                    </li>
                <?php endforeach; ?>

                <?php foreach ($availableCourses as $course): ?>
                    <li style="display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #ddd;">
                        <span><strong><?php echo htmlspecialchars($course['course_code']); ?></strong> - <?php echo htmlspecialchars($course['course_name']); ?></span>
                        <form action="request.php" method="POST" style="margin: 0;">
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            <button type="submit" style="background-color: #7A0019; color: white; border: none; padding: 5px 10px; cursor: pointer;">Join</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </main>
    <script src="script.js"></script>
</body>
</html>