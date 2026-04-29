<?php
//logout.php, responsible for logging out users
//Includes session handling and redirection logic
session_start();
session_unset();
session_destroy();
header("Location: login.php");
?>