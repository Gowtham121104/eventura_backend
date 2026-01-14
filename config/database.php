<?php
/**
 * Database Configuration
 * Eventura - Organizer Admin Panel
 */

class Database {
    private $host = "localhost";
    private $db_name = "eventura_db_v2";
    private $username = "root";
    private $password = "";
    private $conn;

    /**
     * Get database connection
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw $exception;
        }

        return $this->conn;
    }
}
?>