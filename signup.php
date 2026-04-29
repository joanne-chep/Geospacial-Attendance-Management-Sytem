<?php
//signup.php, responsible for user registration
//Includes form handling and database insertion logic

//This is for error identification in cases of issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

//This starts a session, where users can provide their information to create an account
session_start();
include 'db.php'; //The database connection file
$conn = connectDB();
//This reports MySQLi errors as exceptions for better error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


//This block handles the form submission for user registration
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {   //Checks if the form is submitted via POST method so that data is not exposed in the URL

        //This ensures that all required fields are provided by the user during registration
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $role = trim($_POST['role']);


        //Ensures that a user leaves no required fields empty, this is for validation purposes
        if (empty($name) || empty($email) || empty($password) || empty($role)) {
            header("Location: signup.php?error=" . urlencode("All fields are required"));
            exit();
        }
        //Checking if the email provided by the user already exists in the database to prevent duplicate accounts
        $check = $conn->prepare("SELECT email FROM users WHERE email = ?");//Making use of prepared statements to prevent SQL injection(?)
        //Converting the email to a string so that it can be bound to the prepared statement
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            //If email already exists show an error message
            header("Location: signup.php?error=" . urlencode("Sorry!Email already exists!"));
            exit();
        }

        $check->close();
        //Hashing the password for security before storing it in the database
        //Also using prepared statements to insert the new user data into the database
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);
        $stmt->execute();
        $stmt->close();
        //Display success message and redirect to login page after successful registration
        header("Location: login.php?success=" . urlencode("Account created! Please log in"));
        exit();
    }
    //Catching exceptions that may occur during the registration process
} catch (mysqli_sql_exception $ex) {
    error_log("MySQL error: " . $ex->getMessage());
    header("Location: signup.php?error=" . urlencode("Database error occurred"));//Redirecting to the signup page with an error message
    exit();
    //Catching any other execptions that may occur
} catch (Exception $ex) {
    error_log("General error: " . $ex->getMessage());
    header("Location: signup.php?error=" . urlencode("An unexpected error occurred"));
    exit();
    //Ensuring the database connection is closed after completing the commands
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
<script>alert("hello");</script>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Ashesi University Attendance System | Sign Up</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="signup-body">
<div class="signup-container">
    <h1>Create Account</h1>
    <?php if (isset($_GET['error'])): ?>
        <p style="color:blue;"><?php echo htmlspecialchars($_GET['error']); ?></p>
    <?php elseif (isset($_GET['success'])): ?>
        <p style="color:purple;"><?php echo htmlspecialchars($_GET['success']); ?></p>
    <?php endif; ?>
    <form action="signup.php" method="POST" class="signup-form">
        <input type="text" name="name" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <select name="role" required>
            <option value="">Select Role</option>
            <option value="fi">Faculty Intern</option>
            <option value="student">Student</option>
            <option value="other">Other Role</option>
        </select>
        <button type="submit">Sign Up</button>
    </form>
    <p>Do you have an account? <a href="login.php">Login</a></p>
</div>
</body>
</html>
