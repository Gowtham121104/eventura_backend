<?php
class User {
    private $conn;
    private $table_name = "users";

    // Object properties
    public $id;
    public $name;
    public $email;
    public $phone;
    public $password;
    public $role;
    public $created_at;
    public $updated_at;

    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }

    // Check if email already exists
    public function emailExists() {
        $query = "SELECT id, name, email 
                  FROM " . $this->table_name . "
                  WHERE email = :email
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->name = $row['name'];
            return true;
        }
        return false;
    }

    // Register new user
    public function register() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET name = :name,
                      email = :email,
                      phone = :phone,
                      password = :password,
                      role = :role,
                      created_at = NOW()";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->name  = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));

        // Hash password
        $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);

        // Bind
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':role', $this->role);

        return $stmt->execute();
    }

    // Login user
    public function login() {
        $query = "SELECT id, name, email, phone, password, role, created_at
                  FROM " . $this->table_name . "
                  WHERE email = :email
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();

        return $stmt;
    }

    // Get user by ID
    public function getUserById() {
        $query = "SELECT id, name, email, phone, role, created_at
                  FROM " . $this->table_name . "
                  WHERE id = :id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->name = $row['name'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->role = $row['role'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    // Update user profile
    public function updateProfile() {
        $query = "UPDATE " . $this->table_name . "
                  SET name = :name,
                      phone = :phone,
                      updated_at = NOW()
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->name  = htmlspecialchars(strip_tags($this->name));
        $this->phone = htmlspecialchars(strip_tags($this->phone));

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    // Update password
    public function updatePassword($new_password) {
        $query = "UPDATE " . $this->table_name . "
                  SET password = :password,
                      updated_at = NOW()
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    // Delete user
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }
}
?>
