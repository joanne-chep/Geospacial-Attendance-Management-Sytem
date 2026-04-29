<?php

//   1. SINGLETON  — DatabaseConnection ensures only one DB connection exists per request
//   2. COMPOSITE  — DashboardSection / DashboardRenderer build the page from uniform,
//                   swappable section components without the caller needing to know
//                   how each one renders itself

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


$conn = connectDB(); // Singleton already handled inside db.php

interface DashboardSection {
    public function render(): void;
}

// --- Leaf: Courses Section ---
class CoursesSection implements DashboardSection {
    private array $courses;

    public function __construct(array $courses) {
        $this->courses = $courses;
    }

    public function render(): void {
        ?>
        <div id="courses" class="section active">
            <div class="section-header">
                <h2>My courses</h2>
                <a href="add_course.php" class="btn-primary">+ Add new course</a>
            </div>
            <div class="card-list">
                <?php if (empty($this->courses)): ?>
                    <div class="empty-state">No courses assigned yet. Add your first course!</div>
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

// --- Leaf: Sessions Section ---
class SessionsSection implements DashboardSection {
    private array $sessions;

    public function __construct(array $sessions) {
        $this->sessions = $sessions;
    }

    public function render(): void {
        ?>
        <div id="sessions" class="section">
            <div class="section-header">
                <h2>Sessions</h2>
                <a href="add_session.php" class="btn-primary">+ Add new session</a>
            </div>
            <div class="card-list">
                <?php if (empty($this->sessions)): ?>
                    <div class="empty-state">No sessions found. Add a session to get started!</div>
                <?php else: ?>
                    <?php foreach ($this->sessions as $session): ?>
                        <div class="card-item">
                            <div class="item-main">
                                <div class="item-title">
                                    <?php echo $session['display_text']; ?>
                                    <span class="code-badge"><?php echo htmlspecialchars($session['attendance_code']); ?></span>
                                </div>
                                <div class="item-sub">
                                    <?php echo htmlspecialchars($session['session_date']); ?>
                                    &nbsp;·&nbsp;
                                    <?php echo htmlspecialchars(date('h:i A', strtotime($session['start_time']))); ?>
                                </div>
                            </div>
                            <a href="manage_attendance.php?session_id=<?php echo $session['session_id']; ?>" class="btn-blue">
                                Take attendance
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

// --- Leaf: Requests Section ---
class RequestsSection implements DashboardSection {
    private array $requests;

    public function __construct(array $requests) {
        $this->requests = $requests;
    }

    public function render(): void {
        ?>
        <div id="requests" class="section">
            <div class="section-header">
                <h2>Pending enrollment requests</h2>
            </div>
            <div class="card-list">
                <?php if (empty($this->requests)): ?>
                    <div class="empty-state" style="color:green;">No new enrollment requests pending.</div>
                <?php else: ?>
                    <?php foreach ($this->requests as $request): ?>
                        <div class="card-item request-accent">
                            <div class="item-main">
                                <div class="item-title"><?php echo htmlspecialchars($request['student_name']); ?></div>
                                <div class="item-sub">
                                    Wants to join: <?php echo htmlspecialchars("{$request['course_code']} — {$request['course_name']}"); ?>
                                </div>
                                <div class="item-sub" style="margin-top:3px">
                                    Requested: <?php echo date('M d, h:i A', strtotime($request['requested_at'])); ?>
                                </div>
                            </div>
                            <div style="display:flex;gap:6px">
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

// --- Leaf: Reports Section ---
class ReportsSection implements DashboardSection {
    public function render(): void {
        ?>
        <div id="reports" class="section">
            <div class="section-header">
                <h2>Reports</h2>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px">
                <a href="reports.php?type=attendance" class="report-link">
                    <span>Overall attendance report</span>
                    <span class="arrow">&#8594;</span>
                </a>
                <a href="reports.php?type=course_summary" class="report-link">
                    <span>Course summary reports</span>
                    <span class="arrow">&#8594;</span>
                </a>
            </div>
        </div>
        <?php
    }
}

class DashboardRenderer {
    private array $sections = [];

    public function addSection(DashboardSection $section): void {
        $this->sections[] = $section;
    }

    public function renderAll(): void {
        foreach ($this->sections as $section) {
            $section->render(); // every section looks the same to the renderer
        }
    }
}



// AUTH GUARD 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'fi') {
    header("Location: login.php");
    exit();
}


// ============================================================
// DATA FETCHING — uses the Singleton connection
// ============================================================
$db   = DatabaseConnection::getInstance(); // always returns the same instance
$conn = $db->getConnection();

$fi_id   = $_SESSION['user_id'];
$name    = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') : 'Faculty Intern';
$message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error   = isset($_GET['error'])   ? htmlspecialchars($_GET['error'])   : '';

$courses  = [];
$sessions = [];
$requests = [];

try {

    $stmt_courses = $conn->prepare("SELECT id, course_code, course_name FROM courses WHERE fi_id = ? ORDER BY course_code");
    $stmt_courses->bind_param("i", $fi_id);
    $stmt_courses->execute();
    $result_courses = $stmt_courses->get_result();
    while ($row = $result_courses->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt_courses->close();


    $sql_sessions = "
        SELECT 
            s.id, 
            s.session_title, 
            s.attendance_code,
            s.session_date, 
            s.start_time, 
            c.course_code, 
            c.course_name
        FROM sessions s JOIN courses c ON s.course_id = c.id
        WHERE c.fi_id = ? ORDER BY s.session_date DESC, s.start_time DESC
    ";
    $stmt_sessions = $conn->prepare($sql_sessions);
    $stmt_sessions->bind_param("i", $fi_id);
    $stmt_sessions->execute();
    $result_sessions = $stmt_sessions->get_result();
    while ($row = $result_sessions->fetch_assoc()) {
        $row['display_text'] = htmlspecialchars("{$row['course_code']} - {$row['session_title']} ({$row['session_date']})", ENT_QUOTES, 'UTF-8');
        $row['session_id']   = $row['id'];
        $sessions[] = $row;
    }
    $stmt_sessions->close();

   
    $sql_requests = "
        SELECT 
            er.id AS request_id, 
            u.name AS student_name, 
            c.course_code, 
            c.course_name,
            er.requested_at
        FROM requests er
        JOIN users u ON er.student_id = u.id
        JOIN courses c ON er.course_id = c.id
        WHERE c.fi_id = ? AND er.request_status = 'Pending'
        ORDER BY er.requested_at ASC
    ";
    $stmt_requests = $conn->prepare($sql_requests);
    $stmt_requests->bind_param("i", $fi_id);
    $stmt_requests->execute();
    $result_requests = $stmt_requests->get_result();
    while ($row = $result_requests->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt_requests->close();

} catch (Exception $e) {
    error_log("Database error fetching dashboard data: " . $e->getMessage());
    $error = "Database error fetching dashboard data.";
} finally {
    $db->close();
}


// ============================================================
// ASSEMBLE the dashboard using the Composite
// ============================================================
$dashboard = new DashboardRenderer();
$dashboard->addSection(new CoursesSection($courses));
$dashboard->addSection(new SessionsSection($sessions));
$dashboard->addSection(new RequestsSection($requests));
$dashboard->addSection(new ReportsSection());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ashesi Attendance System | Faculty Intern Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;font-size:15px;display:flex;min-height:100vh;background:#f5f5f5}
        .sidebar{width:220px;min-height:100vh;background:#7A0019;display:flex;flex-direction:column;flex-shrink:0}
        .sidebar-top{padding:20px 16px 16px;border-bottom:1px solid rgba(255,255,255,0.15)}
        .sidebar-logo{width:38px;height:38px;background:rgba(255,255,255,0.15);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:500;color:#fff;letter-spacing:0.5px}
        .sys-name{color:#fff;font-size:12px;font-weight:500;margin-top:8px;opacity:0.85;line-height:1.4}
        .profile-box{display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.15)}
        .avatar{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:500;color:#fff;flex-shrink:0}
        .pname{color:#fff;font-size:13px;font-weight:500;line-height:1.3}
        .prole{color:rgba(255,255,255,0.6);font-size:11px}
        nav{flex:1;padding:10px 0}
        nav a{display:flex;align-items:center;gap:8px;padding:10px 16px;color:rgba(255,255,255,0.8);text-decoration:none;font-size:13px;transition:background 0.15s;cursor:pointer}
        nav a:hover,nav a.active{background:rgba(255,255,255,0.15);color:#fff}
        .badge{background:rgba(255,255,255,0.25);color:#fff;font-size:10px;padding:1px 6px;border-radius:10px;margin-left:auto}
        .nav-icon{font-size:14px;width:16px;text-align:center}
        .logout-area{padding:10px 0 16px;border-top:1px solid rgba(255,255,255,0.15)}
        .logout-area a{display:flex;align-items:center;gap:8px;padding:10px 16px;color:rgba(255,255,255,0.7);font-size:13px;text-decoration:none}
        .main{flex:1;padding:24px;overflow:auto;min-width:0}
        .topbar{margin-bottom:20px}
        .topbar h1{font-size:18px;font-weight:500;color:#1a1a1a}
        .greeting{color:#666;font-size:13px;margin-top:2px}
        .toast{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
        .toast.success{background:#e0ffe0;color:#005000}
        .toast.error{background:#ffe0e0;color:#8b0000}
        .section{display:none}
        .section.active{display:block}
        .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
        .section-header h2{font-size:15px;font-weight:500;color:#1a1a1a}
        .btn-primary{background:#7A0019;color:#fff;border:none;padding:7px 14px;border-radius:6px;font-size:12px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:background 0.15s}
        .btn-primary:hover{background:#9e0022}
        .card-list{display:flex;flex-direction:column;gap:8px}
        .card-item{background:#fff;border:0.5px solid #e0e0e0;border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px}
        .item-main{flex:1;min-width:0}
        .item-title{font-size:13px;font-weight:500;color:#1a1a1a}
        .item-sub{font-size:12px;color:#666;margin-top:2px}
        .code-badge{background:#ffe6e6;color:#7A0019;font-size:11px;font-weight:500;padding:2px 7px;border-radius:4px;margin-left:6px;display:inline-block}
        .btn-blue{background:#007bff;color:#fff;border:none;padding:6px 12px;border-radius:5px;font-size:11px;cursor:pointer;text-decoration:none;white-space:nowrap;transition:background 0.15s}
        .btn-blue:hover{background:#0056b3}
        .btn-approve{background:#008000;color:#fff;border:none;padding:6px 11px;border-radius:5px;font-size:11px;cursor:pointer;transition:background 0.15s}
        .btn-approve:hover{background:#006400}
        .btn-reject{background:#cc0000;color:#fff;border:none;padding:6px 11px;border-radius:5px;font-size:11px;cursor:pointer;transition:background 0.15s}
        .btn-reject:hover{background:#a30000}
        .request-accent{border-left:3px solid #cc0000;border-radius:0 10px 10px 0}
        .empty-state{text-align:center;padding:32px 16px;color:#666;font-size:13px;background:#f9f9f9;border-radius:10px}
        .report-link{display:flex;align-items:center;justify-content:space-between;text-decoration:none;color:#1a1a1a;background:#fff;border:0.5px solid #e0e0e0;border-radius:10px;padding:12px 14px;font-size:13px;transition:border-color 0.15s}
        .report-link:hover{border-color:#7A0019;color:#7A0019}
        .arrow{color:#7A0019;font-size:14px}
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-top">
            <div class="sidebar-logo">AAS</div>
            <div class="sys-name">Ashesi Attendance System</div>
        </div>
        <div class="profile-box">
            <div class="avatar">
                <?php
                    // Generate initials from the FI's name for the avatar
                    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $name), 0, 2)));
                    echo htmlspecialchars($initials);
                ?>
            </div>
            <div>
                <div class="pname"><?php echo $name; ?></div>
                <div class="prole">Faculty Intern</div>
            </div>
        </div>
        <nav>
            <a onclick="showSection('courses')" id="nav-courses" class="active">
                <span class="nav-icon">&#9776;</span> Courses
            </a>
            <a onclick="showSection('sessions')" id="nav-sessions">
                <span class="nav-icon">&#128197;</span> Sessions
            </a>
            <a onclick="showSection('requests')" id="nav-requests">
                <span class="nav-icon">&#128233;</span> Requests
                <span class="badge"><?php echo count($requests); ?></span>
            </a>
            <a onclick="showSection('reports')" id="nav-reports">
                <span class="nav-icon">&#128202;</span> Reports
            </a>
        </nav>
        <div class="logout-area">
            <a href="logout.php">
                <span class="nav-icon">&#10148;</span> Logout
            </a>
        </div>
    </div>

    <div class="main">
        <div class="topbar">
            <h1 id="section-title">Courses</h1>
            <div class="greeting">Welcome back, <?php echo $name; ?> &#128075;</div>
        </div>

        <?php if ($message): ?>
            <div class="toast success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="toast error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php $dashboard->renderAll(); ?>
    </div>

    <script src="script.js"></script>
</body>
</html>