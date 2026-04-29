<?php
/**
 * db.php
 * Implements the Singleton Pattern to manage database connectivity.
 * This architecture ensures that a single database connection is maintained 
 * throughout the application lifecycle, preventing redundant resource consumption.
 */

class Database {
    /**
     * Static member to hold the unique instance of the Database class.
     */
    private static $instance = null;
    private $conn;

    /**
     * Private constructor to enforce the Singleton pattern by preventing 
     * external instantiation.
     */
    private function __construct() {
        $host = "localhost";
        $user = "root"; 
        $pass = ""; 
        $dbname = "geospacial_attendance_management";

        $this->conn = new mysqli($host, $user, $pass, $dbname);

        if ($this->conn->connect_error) {
            error_log("Database connection failure: " . $this->conn->connect_error);
            throw new Exception("Critical: Failed to establish a connection to the data repository.");
        }
    }

    /**
     * Controls access to the unique instance of the class.
     * * @return Database The single instance of the Database class.
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * Provides access to the established mysqli connection object.
     * * @return mysqli The active database connection.
     */
    public function getConnection() {
        return $this->conn;
    }
}

/**
 * Global accessor function for the database connection.
 * Facilitates integration across various modules while maintaining 
 * the integrity of the Singleton instance.
 * * @return mysqli The active database connection.
 */
function connectDB() {
    return Database::getInstance()->getConnection();
}
?>
