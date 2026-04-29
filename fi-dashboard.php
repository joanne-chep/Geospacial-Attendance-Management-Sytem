<?php
/**
 * fi-dashboard.php
 * Facilitates the Faculty Intern interface utilizing a Composite Pattern
 * to render modular dashboard sections for course and session management.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Authentication gate ensuring only users with the 'fi' role access this interface
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'fi') {
    header("Location: login.php");
    exit();
}

/**
 * Interface DashboardSection
 * Defines the contract for modular UI components within the FI dashboard.
 */
interface DashboardSection {
    public function render(): void;
}

/**
 * ScannerSection
 * Facilitates the QR scanning interface for processing student attendance tokens.
 */
class ScannerSection implements DashboardSection {
    public function render(): void {
        ?>
        <div id="scanner" class="section">
            <div class="section-header">
                <h2>Attendance Verification Scanner</h2>
            </div>
            <div class="scanner-container">
                <p class="instruction-text">Position the student's QR token within the camera frame to execute automated logging.</p>
                <div id="reader" class="scanner-window"></div>
                <div id="scanner-feedback" class="feedback-display"></div>
            </div>
        </div>
        <?php
    }
}

class CoursesSection implements DashboardSection {
    private array $courses;
    public function __construct(array $courses) { $this->courses = $courses; }
    public function render(): void {
        ?>
        <div id="courses" class="section active">
            <div class="section-header">
                <h2>Assigned Courses</h2>
                <a href="add_course.php" class="btn-primary">+ Create New Course</a>
            </div>
            <div class="card-list">
                <?php if (empty($this->courses)): ?>
                    <div class="empty-state">No courses identified in the current records.</div>
                <?php else: ?>
                    <?php foreach ($this->courses as $course): ?>
                        <div class="card-item">
                            <div class="item-main">
                                <div class="item-title">
                                    <?php echo htmlspecialchars("{$course['course_code']} — {$course['course_name']}"); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

class SessionsSection implements DashboardSection {
    private array $sessions;
    public function __construct(array $sessions) { $this->sessions = $sessions; }
    public function render(): void {
        ?>
        <div id="sessions" class="section">
            <div class="section-header">
                <h2>Active Sessions</h2>
                <a href="add_session.php" class="btn-primary">+ Initialise Session</a>
            </div>
            <div class="card-list">
                <?php if (empty($this->sessions)): ?>
                    <div class="empty-state">No active sessions identified.</div>
                <?php else: ?>
                    <?php foreach ($this->sessions as $session): ?>
                        <div class="card-item">
                            <div class="item-main">
                                <div class="item-title">
                                    <?php echo $session['display_text']; ?>
                                    <span class="code-badge"><?php echo htmlspecialchars($session['attendance_code']); ?></span>
                                </div>
                                <div class="item-sub">
                                    <?php echo htmlspecialchars($session['session_date']); ?> · <?php echo htmlspecialchars(date('h:i A', strtotime($session['start_time']))); ?>
                                </div>
                            </div>
                            <a href="manage_attendance.php?session_id=<?php echo $session['session_id']; ?>" class="btn-blue">Manage Logs</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

class RequestsSection implements DashboardSection {
    private array $requests;
    public function __construct(array $requests) { $this->requests = $requests; }
    public function render(): void {
        ?>
        <div id="requests" class="section">
            <div class="section-header">
                <h2>Pending Enrolment Requests</h2>
            </div>
            <div class="card-list">
                <?php if (empty($this->requests)): ?>
                    <div class="empty-state" style="color:green;">No pending requests identified.</div>
                <?php else: ?>
                    <?php foreach ($this->requests as $request): ?>
                        <div class="card-item request-accent">
                            <div class="item-main">
                                <div class="item-title"><?php echo htmlspecialchars($request['student_name']); ?></div>
                                <div class="item-sub">Module: <?php echo htmlspecialchars("{$request['course_code']} — {$request['course_name']}"); ?></div>
                            </div>
                            <div style="display:flex;gap:8px">
                                <form action="accept.php" method="POST" style="margin:0">
                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn-approve">Approve</button>
                                </form>
                                <form action="accept.php" method="POST" style="margin:0">
                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn-reject">Reject</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

class ReportsSection implements DashboardSection {
    public function render(): void {
        ?>
        <div id="reports" class="section">
            <div class="section-header"><h2>System Reports</h2></div>
            <div class="report-links-container">
                <a href="reports.php?type=attendance" class="report-link"><span>Consolidated Attendance Analytics</span><span class="arrow">&#8594;</span></a>
                <a href="reports.php?type=course_summary" class="report-link"><span>Course Module Summaries</span><span class="arrow">&#8594;</span></a>
            </div>
        </div>
        <?php
    }
}

class DashboardRenderer {
    private array $sections = [];
    public function addSection(DashboardSection $section): void { $this->sections[] = $section; }
    public function renderAll(): void { foreach ($this->sections as $section) { $section->render(); } }
}

$conn = connectDB();
$fi_id = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') : 'Faculty Intern';
$message = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$courses = []; $sessions = []; $requests = [];

try {
    $stmt_c = $conn->prepare("SELECT id, course_code, course_name FROM courses WHERE fi_id = ? ORDER BY course_code");
    $stmt_c->bind_param("i", $fi_id);
    $stmt_c->execute();
    $courses = $stmt_c->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_c->close();

    $stmt_s = $conn->prepare("SELECT s.id, s.session_title, s.attendance_code, s.session_date, s.start_time, c.course_code FROM sessions s JOIN courses c ON s.course_id = c.id WHERE c.fi_id = ? ORDER BY s.session_date DESC");
    $stmt_s->bind_param("i", $fi_id);
    $stmt_s->execute();
    $result_s = $stmt_s->get_result();
    while ($row = $result_s->fetch_assoc()) {
        $row['display_text'] = htmlspecialchars("{$row['course_code']} - {$row['session_title']}");
        $row['session_id'] = $row['id'];
        $sessions[] = $row;
    }
    $stmt_s->close();

    $stmt_r = $conn->prepare("SELECT er.id AS request_id, u.name AS student_name, c.course_code, c.course_name FROM requests er JOIN users u ON er.student_id = u.id JOIN courses c ON er.course_id = c.id WHERE c.fi_id = ? AND er.request_status = 'Pending'");
    $stmt_r->bind_param("i", $fi_id);
    $stmt_r->execute();
    $requests = $stmt_r->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_r->close();
} catch (Exception $e) {
    error_log("Data retrieval error: " . $e->getMessage());
}

$dashboard = new DashboardRenderer();
$dashboard->addSection(new CoursesSection($courses));
$dashboard->addSection(new SessionsSection($sessions));
$dashboard->addSection(new RequestsSection($requests));
$dashboard->addSection(new ReportsSection());
$dashboard->addSection(new ScannerSection()); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FI Dashboard | Ashesi Attendance System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: 'Inter', sans-serif; display: flex; min-height: 100vh; background: #f8f9fa; margin: 0; }
        
        /* Fixed Sidebar Alignment */
        .sidebar { 
            width: 260px; 
            background: #7A0019; 
            color: #fff; 
            display: flex; 
            flex-direction: column; 
            flex-shrink: 0; 
            position: fixed;
            height: 100vh;
        }

        .sidebar-logo { 
            padding: 30px 20px; 
            font-size: 1.2rem; 
            font-weight: 700; 
            border-bottom: 1px solid rgba(255,255,255,0.1); 
            text-align: center;
        }

        /* Profile Section */
        .profile-container {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .avatar-circle {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border: 2px solid #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 600;
            font-size: 1.2rem;
        }
        .profile-info h3 { font-size: 0.9rem; margin: 0; color: #fff; }
        .profile-info p { font-size: 0.75rem; margin: 5px 0 0; opacity: 0.7; }

        nav { padding: 10px 0; }
        nav a { padding: 12px 25px; display: block; color: rgba(255,255,255,0.8); text-decoration: none; cursor: pointer; transition: 0.3s; font-size: 0.9rem; }
        nav a:hover, nav a.active { background: rgba(255,255,255,0.15); color: #fff; border-left: 4px solid #fff; }
        
        /* Main Content Pushed for Sidebar */
        .main { 
            margin-left: 260px; 
            flex: 1; 
            padding: 40px; 
        }

        .dashboard-container { max-width: 1000px; margin: 0 auto; }
        .section { display: none; }
        .section.active { display: block; }
        
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .card-item { background: #fff; border-radius: 12px; padding: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 15px; }
        
        .btn-primary { background: #7A0019; color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        .btn-blue { background: #0056b3; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; }
        
        .scanner-container { background: #fff; border-radius: 12px; padding: 40px; text-align: center; }
        .scanner-window { max-width: 450px; margin: 0 auto; border: 4px solid #7A0019; border-radius: 10px; overflow: hidden; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">ASHESI ATTENDANCE</div>
        
        <div class="profile-container">
            <div class="avatar-circle">
                <?php 
                    $words = explode(' ', $name);
                    echo strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                ?>
            </div>
            <div class="profile-info">
                <h3><?php echo $name; ?></h3>
                <p>Faculty Intern</p>
            </div>
        </div>

        <nav>
            <a onclick="showSection('courses')" class="active" id="nav-courses">Course Modules</a>
            <a onclick="showSection('sessions')" id="nav-sessions">Active Sessions</a>
            <a onclick="showSection('scanner')" id="nav-scanner">QR Scanner</a>
            <a onclick="showSection('requests')" id="nav-requests">Requests (<?php echo count($requests); ?>)</a>
            <a onclick="showSection('reports')" id="nav-reports">System Analytics</a>
            <a href="logout.php" style="margin-top: 30px; opacity: 0.6;">Logout</a>
        </nav>
    </div>

    <div class="main">
        <div class="dashboard-container">
            <header style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                <h1 style="margin: 0; font-size: 1.8rem; color: #333;">System Dashboard</h1>
            </header>

            <?php if ($message): ?><div style="padding: 15px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 20px;"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 8px; margin-bottom: 20px;"><?php echo $error; ?></div><?php endif; ?>

            <?php $dashboard->renderAll(); ?>
        </div>
    </div>

    <script src="html5-qrcode.min.js"></script>
    <script>
        function showSection(id) {
            document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('nav a').forEach(a => a.classList.remove('active'));
            
            document.getElementById(id).classList.add('active');
            document.getElementById('nav-' + id).classList.add('active');
            
            if(id === 'scanner') { startScanner(); }
        }

        let html5QrcodeScanner;
        function startScanner() {
            if (html5QrcodeScanner) return; 
            html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 250 });
            html5QrcodeScanner.render(onScanSuccess);
        }

        function onScanSuccess(decodedText) {
            const feedback = document.getElementById('scanner-feedback');
            feedback.innerHTML = "Processing ID: " + decodedText;
            feedback.style.color = "#7A0019";

            fetch('process_qr_attendance.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'student_id=' + encodeURIComponent(decodedText)
            })
            .then(response => response.json())
            .then(data => {
                feedback.innerHTML = data.message;
                feedback.style.color = data.success ? "green" : "red";
            });
        }
    </script>
</body>
</html>