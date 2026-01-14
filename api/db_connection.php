<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'eventura_db_v2');
define('DB_USER', 'root');
define('DB_PASS', '');

// Database class
class Database {
    private $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }

            $this->conn->set_charset("utf8mb4");

        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }

        return $this->conn;
    }
}

/*
 CREATE a global $conn variable
 so existing APIs can directly use $conn
*/
$database = new Database();
$conn = $database->getConnection();
