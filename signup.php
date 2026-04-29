<?php
/**
 * signup.php
 * Facilitates user registration for the Ashesi Attendance System.
 * Utilises a Singleton database connection and prepared statements 
 * to ensure secure and efficient data persistence.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';

/**
 * Initialisation of the centralised database connection.
 * Utilising the Singleton pattern ensures resource optimisation across registration requests.
 */
$conn = connectDB();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Extraction and sanitisation of input parameters from the registration form
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $role = trim($_POST['role']);

        // Validation of mandatory fields to maintain data integrity
        if (empty($name) || empty($email) || empty($password) || empty($role)) {
            header("Location: signup.php?error=" . urlencode("All fields are mandatory."));
            exit();
        }

        /**
         * Verification of identity uniqueness.
         * Executes a prepared statement to prevent duplicate account creation for a single email address.
         */
        $check = $conn->prepare("SELECT email FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            header("Location: signup.php?error=" . urlencode("An account with this email already exists."));
            exit();
        }
        $check->close();

        /**
         * Secure password persistence utilising the PASSWORD_DEFAULT hashing algorithm.
         * Data insertion is handled via a parameterised query to mitigate SQL injection risks.
         */
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);
        $stmt->execute();
        $stmt->close();

        header("Location: login.php?success=" . urlencode("Account successfully created. Please authenticate to continue."));
        exit();
    }
} catch (mysqli_sql_exception $ex) {
    error_log("Database Exception: " . $ex->getMessage());
    header("Location: signup.php?error=" . urlencode("A database error occurred during registration."));
    exit();
} catch (Exception $ex) {
    error_log("General Application Exception: " . $ex->getMessage());
    header("Location: signup.php?error=" . urlencode("An unexpected system error occurred."));
    exit();
} finally {
    // Ensuring the connection instance is properly managed following execution
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up | Ashesi Attendance System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="signup-body">
<div class="signup-container">
    <h1>Create Account</h1>
    <?php if (isset($_GET['error'])): ?>
        <p style="color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px;">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </p>
    <?php elseif (isset($_GET['success'])): ?>
        <p style="color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px;">
            <?php echo htmlspecialchars($_GET['success']); ?>
        </p>
    <?php endif; ?>

    <form action="signup.php" method="POST" class="signup-form">
        <input type="text" name="name" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Institutional Email" required>
        <input type="password" name="password" placeholder="Secure Password" required>
        <select name="role" required>
            <option value="">Select Account Role</option>
            <option value="fi">Faculty Intern</option>
            <option value="student">Student</option>
        </select>
        <button type="submit">Sign Up</button>
    </form>
    <p>Already registered? <a href="login.php">Login here</a></p>
</div>
</body>
</html>
