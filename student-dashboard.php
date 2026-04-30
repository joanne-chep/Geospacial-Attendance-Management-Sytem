<?php
/**
 * student-dashboard.php
 * Facilitates the primary interface for students to manage course enrolments 
 * and generate attendance credentials for verification.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Authentication gate to ensure only users with the student role access this interface
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$conn = connectDB();
$studentId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') : 'Student User';
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

$enrolledCourses = [];
$pendingRequests = [];
$availableCourses = [];

try {
    // Retrieval of courses where the student has established an approved enrolment record
    $sqlEnrolled = "
        SELECT 
            c.id, 
            c.course_code, 
            c.course_name,
            u.name AS fi_name
        FROM course_enrollments ce
        JOIN courses c ON ce.course_id = c.id
        JOIN users u ON c.fi_id = u.id
        WHERE ce.student_id = ?
        ORDER BY c.course_code
    ";
    $stmtEnrolled = $conn->prepare($sqlEnrolled);
    $stmtEnrolled->bind_param("i", $studentId);
    $stmtEnrolled->execute();
    $resultEnrolled = $stmtEnrolled->get_result();
    while ($row = $resultEnrolled->fetch_assoc()) {
        $enrolledCourses[] = $row;
    }
    $stmtEnrolled->close();

    // Retrieval of available courses and active join requests
    $sqlAvailable = "
        SELECT 
            c.id, 
            c.course_code, 
            c.course_name,
            u.name AS fi_name,
            r.request_status
        FROM courses c
        JOIN users u ON c.fi_id = u.id
        LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.student_id = ?
        LEFT JOIN requests r ON c.id = r.course_id AND r.student_id = ?
        WHERE ce.id IS NULL
        ORDER BY c.course_code
    ";
    
    $stmtAvailable = $conn->prepare($sqlAvailable);
    $stmtAvailable->bind_param("ii", $studentId, $studentId); 
    $stmtAvailable->execute();
    $resultAvailable = $stmtAvailable->get_result();

    while ($row = $resultAvailable->fetch_assoc()) {
        if ($row['request_status'] === 'Pending') {
            $pendingRequests[] = $row;
        } else {
            $availableCourses[] = $row;
        }
    }
    $stmtAvailable->close();

} catch (Exception $e) {
    error_log("Database execution error: " . $e->getMessage());
    $error = "System error: Failed to retrieve course data.";
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
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
        .qr-section { text-align: center; padding: 20px; background: #f9f9f9; border-radius: 8px; margin-top: 15px; display: none; }
        .toggle-btn { margin-bottom: 15px; padding: 10px 15px; background: #7A0019; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .toggle-btn:hover { background: #9e0022; }
        #qrcode img { margin: 0 auto; border: 10px solid white; }
        
        /* Dynamic Avatar Styling for multiple users profile*/
        .avatar-circle {
            width: 80px;
            height: 80px;
            background-color: rgba(255, 255, 255, 0.2);
            border: 2px solid #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px auto;
            font-size: 24px;
            font-weight: 600;
            color: #fff;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="Ashesi.jpeg" alt="Ashesi University Logo" class="ashesi-image">
            <h2 class="system-name">Ashesi Attendance System</h2>
        </div>
        <div class="profile-section">
            <div class="avatar-circle">
                <?php 
                    // Extraction of initials from the student name
                    $words = explode(' ', $name);
                    $initials = (count($words) >= 2) 
                        ? strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1)) 
                        : strtoupper(substr($words[0], 0, 1));
                    echo $initials;
                ?>
            </div>
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
        <h2 id="welcomeMessage">WELCOME! <?php echo $name; ?> (Student)</h2>

        <?php if ($message): ?>
            <p style="color:green; background-color:#e0ffe0; padding:15px; border-radius:6px; margin-bottom: 20px; border: 1px solid green;"><?php echo $message; ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p style="color:red; background-color:#ffe0e0; padding:15px; border-radius:6px; margin-bottom: 20px; border: 1px solid red;"><?php echo $error; ?></p>
        <?php endif; ?>

        <section id="dashboard_home" class="content-section active">
            <div class="attendance-card">
                <h3>Submit Attendance</h3>
                <button class="toggle-btn" onclick="toggleAttendanceMethod()">Switch to QR Token</button>

                <div id="manualEntry">
                    <p>Enter the 4-character code provided by the instructor for the current session.</p>
                    <form action="submit_attendance.php" method="POST" class="code-form">
                        <input type="text" name="attendance_code" class="code-input" placeholder="CODE" required maxlength="4">
                        <button type="submit" class="submit-btn">Submit Attendance</button>
                    </form>
                </div>

                <div id="qrEntry" class="qr-section">
                    <p>Present this unique identifier to the instructor for scanning.</p>
                    <div id="qrcode"></div>
                    <p style="margin-top:10px; font-weight:bold; color:#7A0019;">ID: <?php echo $studentId; ?></p>
                </div>
            </div>

            <h3>Enrolled Courses</h3>
            <ul id="enrolledList">
                <?php if (empty($enrolledCourses)): ?>
                    <li>No active enrolments identified. Use the <strong>Request Courses</strong> module to join a session.</li>
                <?php else: ?>
                    <?php foreach ($enrolledCourses as $course): ?>
                        <li style="border-left: 4px solid #008000; background-color: #f0fff0; margin-bottom: 10px; padding: 10px; border-radius: 0 4px 4px 0;">
                            <strong><?php echo htmlspecialchars($course['course_code']); ?></strong> - 
                            <?php echo htmlspecialchars($course['course_name']); ?> 
                            <br>
                            <span style="font-size: 0.9em; color: #666;">Instructor: <?php echo htmlspecialchars($course['fi_name']); ?></span>
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
                        <strong><?php echo htmlspecialchars($course['course_code']); ?></strong> - <?php echo htmlspecialchars($course['course_name']); ?>
                        <span style="float:right; font-style:italic; color: #997404;">Pending Approval</span>
                    </li>
                <?php endforeach; ?>

                <?php foreach ($availableCourses as $course): ?>
                    <li style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #ddd;">
                        <span>
                            <strong><?php echo htmlspecialchars($course['course_code']); ?></strong> - <?php echo htmlspecialchars($course['course_name']); ?>
                        </span>
                        <form action="request.php" method="POST" style="margin: 0;">
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            <button type="submit" style="padding: 6px 12px; background-color: #7A0019; color: white; border: none; border-radius: 4px; cursor: pointer;">Join Course</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </main>

    <script src="qrcode.min.js"></script>
    <script>
        function toggleAttendanceMethod() {
            const manual = document.getElementById('manualEntry');
            const qr = document.getElementById('qrEntry');
            const btn = document.querySelector('.toggle-btn');

            if (manual.style.display === 'none') {
                manual.style.display = 'block';
                qr.style.display = 'none';
                btn.innerText = 'Switch to QR Token';
            } else {
                manual.style.display = 'none';
                qr.style.display = 'block';
                btn.innerText = 'Switch to Manual Entry';
                generateStudentQR();
            }
        }

        function generateStudentQR() {
            const qrContainer = document.getElementById("qrcode");
            qrContainer.innerHTML = ""; 
            new QRCode(qrContainer, {
                text: "<?php echo $studentId; ?>",
                width: 180,
                height: 180,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        }

        function showSection(sectionId) {
            document.querySelectorAll('.content-section').forEach(s => s.style.display = 'none');
            document.getElementById(sectionId).style.display = 'block';
        }
    </script>
</body>
</html>