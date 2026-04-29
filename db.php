<?php
//db.php - Refactored to use the Singleton Pattern for resource management

class Database {
    // This static variable holds our single instance
    private static $instance = null;
    private $conn;

    // A private constructor prevents creating new instances via 'new Database()'
    private function __construct() {
        $host = "localhost";
        $user = "root"; 
        $pass = ""; 
        $dbname = "webtech_2025a_joanne_chepkoech";

        $this->conn = new mysqli($host, $user, $pass, $dbname);

        if ($this->conn->connect_error) {
            throw new Exception("Connection failed: " . $this->conn->connect_error);
        }
    }

    // The static method that controls access to the instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}

// Global helper function to maintain compatibility with your other files
function connectDB() {
    return Database::getInstance()->getConnection();
}
?>