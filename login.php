<?php
//login.php, responsible for user authentication
//Includes form handling and authentication logic

//This is for error identification in cases of issues
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();


//This includes the database connection file
include 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

//This block handles the form submission for user login
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { //Checks if the form is submitted via POST method so that data is not exposed in the URL
        if (empty($_POST['loginEmail']) || empty($_POST['loginPassword'])) {
            header("Location: login.php?error=" . urlencode("Please enter email and password"));//The user can login using their email and password
            exit();
        }
        //Ensuring that the email and password provided by the user are correct
        $email = trim($_POST['loginEmail']);
        $password = trim($_POST['loginPassword']);
        //Find the user's record based on the provided email, also using prepared statements to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            //If no user is found with the provided email, display an error message
            header("Location: login.php?error=" . urlencode("Invalid email or password!Try Again!"));
            exit();
        }
        //Fetch the user record
        $user = $result->fetch_assoc();
        $stmt->close();
        //Verify the provided password against the hashed password stored in the database
        if (!password_verify($password, $user['password'])) {
            header("Location: login.php?error=" . urlencode("Invalid email or password"));
            exit();
        }
        
        //If the user is found and the password is correct, start a new session
        //and store user details in the session, this helps prevent session fixation attacks
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name']; 
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        //Redirect the user to their respective dashboard based on their role
        if ($_SESSION['role'] === 'fi') {
            header("Location: fi-dashboard.php");
        } elseif ($_SESSION['role'] === 'student') {
            header("Location: student-dashboard.php");
        } else {
            header("Location: login.php?error=" . urlencode("Access denied. Dashboard for your role is not yet available."));//This is for other users whose dashboards have not been created
        }
        exit();
    }
    //Catch execeptions that may occur during the login process
} catch (mysqli_sql_exception $ex) {
    error_log("MySQL error: " . $ex->getMessage());
    header("Location: login.php?error=" . urlencode("A database error occurred."));
    exit();
} catch (Exception $ex) {
    error_log("General error: " . $ex->getMessage());
    header("Location: login.php?error=" . urlencode("An unexpected error occurred."));
    exit();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Ashesi University Attendance System</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-logo">
            <img src="Ashesi.jpeg" alt="Ashesi University" class="login-bg-logo">
            <h1>Ashesi University Attendance System</h1>
        </div>
        <div class="form-container">
            <form id="login-form" class="login-form" method="POST" action="">
                <h2>Login</h2>
                <?php if (isset($_GET['error'])): ?>
                    <p style="color:blue;"><?php echo htmlspecialchars($_GET['error']); ?></p>
                <?php elseif (isset($_GET['success'])): ?>
                    <p style="color:purple;"><?php echo htmlspecialchars($_GET['success']); ?></p>
                <?php endif; ?>
                <input type="email" name="loginEmail" placeholder="Email" required>
                <input type="password" name="loginPassword" placeholder="Password" required>
                <button type="submit">Login</button>
                <p>Don’t have an account? <a href="signup.php">Sign up</a></p>
            </form>
        </div>
    </div>
</body>
</html>
