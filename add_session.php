<?php
// add_session.php — responsible for addition of sessions with attendance codes
//builder pattern is applied to cleanly separate session construction and validation from the main script logic


error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Auth guard 
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'fi') {
    header("Location: login.php");
    exit();
}

class SessionBuilder {
    private ?int    $courseId    = null;
    private string  $title       = '';
    private string  $date        = '';
    private string  $startTime   = '';
    private ?string $endTime     = null;

    public function setCourseId(int $courseId): static {
        $this->courseId = $courseId;
        return $this;
    }

    public function setTitle(string $title): static {
        $this->title = trim($title);
        return $this;
    }

    public function setDate(string $date): static {
        $this->date = $date;
        return $this;
    }

    public function setStartTime(string $startTime): static {
        $this->startTime = $startTime;
        return $this;
    }

    public function setEndTime(?string $endTime): static {
        $this->endTime = empty($endTime) ? null : $endTime;
        return $this;
    }

    // build() is the payoff — validates all parts are present,
    // generates the attendance code, and returns the finished session.
    // Throws if required fields are missing so the caller can catch it.
    public function build(): array {
        if (empty($this->courseId) || empty($this->title) ||
            empty($this->date)     || empty($this->startTime)) {
            throw new InvalidArgumentException("Please fill in all required fields marked with *.");
        }

        return [
            'course_id'       => $this->courseId,
            'title'           => $this->title,
            'date'            => $this->date,
            'start_time'      => $this->startTime,
            'end_time'        => $this->endTime,
            // Attendance code generated here — one place, every time
            'attendance_code' => strtoupper(substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 4)),
        ];
    }
}


// ============================================================
// MAIN SCRIPT
// ============================================================
$conn           = connectDB(); 
$current_fi_id  = $_SESSION['user_id'];
$error_msg      = '';
$success_msg    = '';

// Load the FI's courses for the dropdown — unchanged logic
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Builder assembles and validates the session from POST data.
        // The main script hands off raw input; the Builder owns the rules.
        $session = (new SessionBuilder())
            ->setCourseId((int)$_POST['course_id'])
            ->setTitle($_POST['session_title'])
            ->setDate($_POST['session_date'])
            ->setStartTime($_POST['start_time'])
            ->setEndTime($_POST['end_time'] ?? null)
            ->build(); // throws InvalidArgumentException if invalid

        // Ownership check 
        // Verify the FI actually owns the selected course
        $check_stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND fi_id = ?");
        $check_stmt->bind_param("ii", $session['course_id'], $current_fi_id);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows === 0) {
            $error_msg = "You do not have permission to add sessions to that course.";
        } else {
            // Insert the new session into the database
            $insert_stmt = $conn->prepare(
                "INSERT INTO sessions (course_id, session_title, attendance_code, session_date, start_time, end_time)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $insert_stmt->bind_param(
                "isssss",
                $session['course_id'],
                $session['title'],
                $session['attendance_code'],
                $session['date'],
                $session['start_time'],
                $session['end_time']
            );
            $insert_stmt->execute();

            if ($insert_stmt->affected_rows > 0) {
                $success_msg = "Session created successfully! Code: " . $session['attendance_code'];
                header("refresh:2;url=fi-dashboard.php");
            }
            $insert_stmt->close();
        }
        $check_stmt->close();

    } catch (InvalidArgumentException $e) {
        // Validation failure from build()
        $error_msg = $e->getMessage();
    } catch (Exception $e) {
        $error_msg = "Database error: " . $e->getMessage();
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
        .session-form-box{max-width:500px;margin:30px auto;padding:2rem;background:white;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
        .form-input-group{margin-bottom:15px}
        .form-input-group label{display:block;margin-bottom:5px;font-weight:600}
        .form-input-group input,.form-input-group select{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box}
        .create-btn{width:100%;padding:10px;background-color:#7A0019;color:white;border:none;border-radius:4px;cursor:pointer;font-size:16px}
        .create-btn:hover{background-color:#9e0022}
    </style>
</head>
<body style="background-color:#f5f5f5;">
    <div class="session-form-box">
        <h2 style="color:#7A0019;text-align:center;">Create Class Session</h2>

        <?php if ($error_msg): ?>
            <p style="color:red;background:#ffe0e0;padding:10px;border-radius:4px;"><?php echo htmlspecialchars($error_msg); ?></p>
        <?php endif; ?>
        <?php if ($success_msg): ?>
            <p style="color:green;background:#e0ffe0;padding:10px;border-radius:4px;"><?php echo htmlspecialchars($success_msg); ?></p>
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

            <div style="display:flex;gap:10px;">
                <div class="form-input-group" style="flex:1;">
                    <label>Start Time *</label>
                    <input type="time" name="start_time" required>
                </div>
                <div class="form-input-group" style="flex:1;">
                    <label>End Time (Optional)</label>
                    <input type="time" name="end_time">
                </div>
            </div>

            <button type="submit" class="create-btn">Create Session</button>
        </form>
        <?php endif; ?>

        <p style="text-align:center;margin-top:20px;">
            <a href="fi-dashboard.php" style="color:#7A0019;text-decoration:none;">← Back to Dashboard</a>
        </p>
    </div>
</body>
</html>