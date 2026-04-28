<?php
//fi-dashboard.php, responsible for displaying the Faculty Intern dashboard
//Includes course management, session management, and enrollment requests handling

//This is for error identification in cases of issues
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
//This includes the database connection file
include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


//This ensures that the user logged in is a Faculty Intern and redirects them to the login page if not
//This is very important as it ensures that not anyone is allowed to anyother users dashboard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'fi') {
    header("Location: login.php");
    exit();
}

$conn = connectDB();
//Get the FI's id to fetch their data
$fi_id = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') : 'Faculty Intern';
$message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
//These arrays will hold the data fetched from the database associated with the current FI
$courses = [];
$sessions = [];
$requests = []; 

try {
    //Get the courses that the FI creates
    $stmt_courses = $conn->prepare("SELECT id, course_code, course_name FROM courses WHERE fi_id = ? ORDER BY course_code");
    $stmt_courses->bind_param("i", $fi_id);
    $stmt_courses->execute();
    $result_courses = $stmt_courses->get_result();
    while ($row = $result_courses->fetch_assoc()) {
        $courses[] = $row; //Store the various courses in the array
    }
    $stmt_courses->close();
    //Once the FI creates sessions, this will get the sessions stored in the database
    //We also fetch the attendance code here to display it on the dashboard
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
        $row['session_id'] = $row['id'];
        $sessions[] = $row;
    }
    $stmt_sessions->close();
    //Get the pending requests by students to join the FI courses
    //Get them from the request, users and courses table
    //This only gets the pending courses
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
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
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
        body{font-family:var(--font-sans);font-size:15px;color:var(--color-text-primary);display:flex;min-height:600px;background:var(--color-background-tertiary)}
        .sidebar{width:220px;min-height:600px;background:#7A0019;display:flex;flex-direction:column;padding:0;flex-shrink:0}
        .sidebar-top{padding:20px 16px 16px;border-bottom:1px solid rgba(255,255,255,0.15)}
        .sidebar-top img.logo{width:40px;height:40px;border-radius:6px;object-fit:cover;background:#fff}
        .sidebar-top .sys-name{color:#fff;font-size:12px;font-weight:500;margin-top:8px;opacity:0.85;line-height:1.4}
        .profile-box{display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.15)}
        .avatar{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:500;color:#fff;flex-shrink:0}
        .profile-box .pname{color:#fff;font-size:13px;font-weight:500;line-height:1.3}
        .profile-box .prole{color:rgba(255,255,255,0.6);font-size:11px}
        nav{flex:1;padding:10px 0}
        nav a{display:flex;align-items:center;gap:8px;padding:10px 16px;color:rgba(255,255,255,0.8);text-decoration:none;font-size:13px;transition:background 0.15s;cursor:pointer;border:none;background:none;width:100%;text-align:left}
        nav a:hover,nav a.active{background:rgba(255,255,255,0.15);color:#fff}
        nav .badge{background:rgba(255,255,255,0.25);color:#fff;font-size:10px;padding:1px 6px;border-radius:10px;margin-left:auto}
        nav .nav-icon{font-size:14px;width:16px;text-align:center}
        .logout-area{padding:10px 0 16px;border-top:1px solid rgba(255,255,255,0.15)}
        .main{flex:1;padding:24px;overflow:auto;min-width:0}
        .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
        .topbar h1{font-size:18px;font-weight:500}
        .topbar .greeting{color:var(--color-text-secondary);font-size:13px;margin-top:2px}
        .toast{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
        .toast.success{background:var(--color-background-success);color:var(--color-text-success)}
        .toast.error{background:var(--color-background-danger);color:var(--color-text-danger)}
        .section{display:none}
        .section.active{display:block}
        .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
        .section-header h2{font-size:15px;font-weight:500}
        .btn-primary{background:#7A0019;color:#fff;border:none;padding:7px 14px;border-radius:6px;font-size:12px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:background 0.15s}
        .btn-primary:hover{background:#9e0022}
        .card-list{display:flex;flex-direction:column;gap:8px}
        .card-item{background:var(--color-background-primary);border:0.5px solid var(--color-border-tertiary);border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px}
        .card-item .item-main{flex:1;min-width:0}
        .item-title{font-size:13px;font-weight:500;color:var(--color-text-primary)}
        .item-sub{font-size:12px;color:var(--color-text-secondary);margin-top:2px}
        .code-badge{background:#ffe6e6;color:#7A0019;font-size:11px;font-weight:500;padding:2px 7px;border-radius:4px;margin-left:6px;display:inline-block}
        .btn-blue{background:#007bff;color:#fff;border:none;padding:6px 12px;border-radius:5px;font-size:11px;cursor:pointer;text-decoration:none;white-space:nowrap;transition:background 0.15s}
        .btn-blue:hover{background:#0056b3}
        .btn-approve{background:#008000;color:#fff;border:none;padding:6px 11px;border-radius:5px;font-size:11px;cursor:pointer;transition:background 0.15s}
        .btn-approve:hover{background:#006400}
        .btn-reject{background:#cc0000;color:#fff;border:none;padding:6px 11px;border-radius:5px;font-size:11px;cursor:pointer;transition:background 0.15s}
        .btn-reject:hover{background:#a30000}
        .request-accent{border-left:3px solid #cc0000;border-radius:0 10px 10px 0}
        .empty-state{text-align:center;padding:32px 16px;color:var(--color-text-secondary);font-size:13px;background:var(--color-background-secondary);border-radius:10px}
        .empty-state .empty-icon{font-size:24px;margin-bottom:8px}
        .report-link{display:flex;align-items:center;justify-content:space-between;text-decoration:none;color:var(--color-text-primary);background:var(--color-background-primary);border:0.5px solid var(--color-border-tertiary);border-radius:10px;padding:12px 14px;font-size:13px;transition:border-color 0.15s}
        .report-link:hover{border-color:#7A0019;color:#7A0019}
        .report-link .arrow{color:#7A0019;font-size:14px}
    </style>
</head>
<body>
    <div style="display:flex;min-height:600px">
    <div class="sidebar">
        <div class="sidebar-top">
        <div style="width:38px;height:38px;background:rgba(255,255,255,0.15);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:500;color:#fff;letter-spacing:0.5px">AAS</div>
        <div class="sys-name">Ashesi Attendance System</div>
        </div>
        <div class="profile-box">
        <div class="avatar">JC</div>
        <div>
            <div class="pname">Joanne Chepkoech</div>
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
            <span class="badge">3</span>
        </a>
        <a onclick="showSection('reports')" id="nav-reports">
            <span class="nav-icon">&#128202;</span> Reports
        </a>
        </nav>
        <div class="logout-area">
        <a href="logout.php" style="display:flex;align-items:center;gap:8px;padding:10px 16px;color:rgba(255,255,255,0.7);font-size:13px;text-decoration:none">
            <span class="nav-icon">&#10148;</span> Logout
        </a>
        </div>
    </div>

    <div class="main">
        <div class="topbar">
        <div>
            <h1 id="section-title">Courses</h1>
            <div class="greeting">Welcome back, Joanne 👋</div>
        </div>
        </div>

        <div id="courses" class="section active">
        <div class="section-header">
            <h2>My courses</h2>
            <a href="add_course.php" class="btn-primary">+ Add new course</a>
        </div>
        <div class="card-list">
            <div class="card-item">
            <div class="item-main">
                <div class="item-title">CS 101 — Introduction to Computer Science</div>
            </div>
            </div>
            <div class="card-item">
            <div class="item-main">
                <div class="item-title">CS 221 — Data Structures & Algorithms</div>
            </div>
            </div>
            <div class="card-item">
            <div class="item-main">
                <div class="item-title">MIS 310 — Information Systems Management</div>
            </div>
            </div>
        </div>
        </div>

        <div id="sessions" class="section">
        <div class="section-header">
            <h2>Sessions</h2>
            <a href="add_session.php" class="btn-primary">+ Add new session</a>
        </div>
        <div class="card-list">
            <div class="card-item">
            <div class="item-main">
                <div class="item-title">CS 101 — Week 4 Lecture <span class="code-badge">ATT-8821</span></div>
                <div class="item-sub">2025-04-25 &nbsp;·&nbsp; 08:00 AM</div>
            </div>
            <a href="manage_attendance.php?session_id=1" class="btn-blue">Take attendance</a>
            </div>
            <div class="card-item">
            <div class="item-main">
                <div class="item-title">CS 221 — Binary Trees <span class="code-badge">ATT-5540</span></div>
                <div class="item-sub">2025-04-23 &nbsp;·&nbsp; 10:00 AM</div>
            </div>
            <a href="manage_attendance.php?session_id=2" class="btn-blue">Take attendance</a>
            </div>
        </div>
        </div>

        <div id="requests" class="section">
        <div class="section-header">
            <h2>Pending enrollment requests</h2>
        </div>
        <div class="card-list">
            <div class="card-item request-accent">
            <div class="item-main">
                <div class="item-title">Kwame Asante</div>
                <div class="item-sub">Wants to join: CS 101 — Introduction to Computer Science</div>
                <div class="item-sub" style="margin-top:3px">Requested Apr 24, 09:15 AM</div>
            </div>
            <div style="display:flex;gap:6px">
                <form action="accept.php" method="POST"><input type="hidden" name="request_id" value="1"><input type="hidden" name="action" value="approve"><button class="btn-approve">Approve</button></form>
                <form action="accept.php" method="POST"><input type="hidden" name="request_id" value="1"><input type="hidden" name="action" value="reject"><button class="btn-reject">Reject</button></form>
            </div>
            </div>
            <div class="card-item request-accent">
            <div class="item-main">
                <div class="item-title">Abena Mensah</div>
                <div class="item-sub">Wants to join: MIS 310 — Information Systems Management</div>
                <div class="item-sub" style="margin-top:3px">Requested Apr 25, 11:30 AM</div>
            </div>
            <div style="display:flex;gap:6px">
                <form action="accept.php" method="POST"><input type="hidden" name="request_id" value="2"><input type="hidden" name="action" value="approve"><button class="btn-approve">Approve</button></form>
                <form action="accept.php" method="POST"><input type="hidden" name="request_id" value="2"><input type="hidden" name="action" value="reject"><button class="btn-reject">Reject</button></form>
            </div>
            </div>
            <div class="card-item request-accent">
            <div class="item-main">
                <div class="item-title">Kofi Boateng</div>
                <div class="item-sub">Wants to join: CS 221 — Data Structures & Algorithms</div>
                <div class="item-sub" style="margin-top:3px">Requested Apr 26, 02:00 PM</div>
            </div>
            <div style="display:flex;gap:6px">
                <form action="accept.php" method="POST"><input type="hidden" name="request_id" value="3"><input type="hidden" name="action" value="approve"><button class="btn-approve">Approve</button></form>
                <form action="accept.php" method="POST"><input type="hidden" name="request_id" value="3"><input type="hidden" name="action" value="reject"><button class="btn-reject">Reject</button></form>
            </div>
            </div>
        </div>
        </div>

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
    </div>
    </div>
    <script src="script.js"></script> 
</body>
</html>