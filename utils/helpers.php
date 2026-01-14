<?php
/**
 * API Utility Classes
 * Eventura - Organizer Admin Panel
 */

/**
 * Standard API Response Handler
 */
class ApiResponse {
    
    /**
     * Send success response
     */
    public static function success($data = null, $message = "Success", $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ]);
        exit;
    }

    /**
     * Send error response
     */
    public static function error($message = "Error occurred", $statusCode = 400, $errors = null) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => time()
        ]);
        exit;
    }

    /**
     * Send validation error response
     */
    public static function validationError($errors) {
        self::error("Validation failed", 422, $errors);
    }

    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = "Unauthorized access") {
        self::error($message, 401);
    }

    /**
     * Send forbidden response
     */
    public static function forbidden($message = "Access forbidden") {
        self::error($message, 403);
    }

    /**
     * Send not found response
     */
    public static function notFound($message = "Resource not found") {
        self::error($message, 404);
    }
}

/**
 * Input Validator
 */
class Validator {
    
    private $errors = [];

    /**
     * Validate required field
     */
    public function required($value, $fieldName) {
        if (empty($value) && $value !== '0') {
            $this->errors[$fieldName] = "$fieldName is required";
        }
        return $this;
    }

    /**
     * Validate email
     */
    public function email($value, $fieldName) {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$fieldName] = "$fieldName must be a valid email address";
        }
        return $this;
    }

    /**
     * Validate minimum length
     */
    public function minLength($value, $min, $fieldName) {
        if (!empty($value) && strlen($value) < $min) {
            $this->errors[$fieldName] = "$fieldName must be at least $min characters";
        }
        return $this;
    }

    /**
     * Validate maximum length
     */
    public function maxLength($value, $max, $fieldName) {
        if (!empty($value) && strlen($value) > $max) {
            $this->errors[$fieldName] = "$fieldName must not exceed $max characters";
        }
        return $this;
    }

    /**
     * Validate numeric value
     */
    public function numeric($value, $fieldName) {
        if (!empty($value) && !is_numeric($value)) {
            $this->errors[$fieldName] = "$fieldName must be a number";
        }
        return $this;
    }

    /**
     * Validate phone number
     */
    public function phone($value, $fieldName) {
        if (!empty($value) && !preg_match('/^[0-9]{10,15}$/', $value)) {
            $this->errors[$fieldName] = "$fieldName must be a valid phone number";
        }
        return $this;
    }

    /**
     * Validate date format
     */
    public function date($value, $fieldName, $format = 'Y-m-d') {
        if (!empty($value)) {
            $d = DateTime::createFromFormat($format, $value);
            if (!$d || $d->format($format) !== $value) {
                $this->errors[$fieldName] = "$fieldName must be a valid date ($format)";
            }
        }
        return $this;
    }

    /**
     * Validate value is in array
     */
    public function in($value, array $allowed, $fieldName) {
        if (!empty($value) && !in_array($value, $allowed)) {
            $this->errors[$fieldName] = "$fieldName must be one of: " . implode(', ', $allowed);
        }
        return $this;
    }

    /**
     * Check if validation passed
     */
    public function isValid() {
        return empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function getErrors() {
        return $this->errors;
    }
}

/**
 * JWT Token Handler
 */
class JWT {
    
    private static $secret_key = "your-secret-key-change-in-production";
    private static $algorithm = 'HS256';

    /**
     * Generate JWT token
     */
    public static function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => self::$algorithm]);
        $payload = json_encode($payload);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret_key, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Decode and validate JWT token
     */
    public static function decode($jwt) {
        $tokenParts = explode('.', $jwt);

        if (count($tokenParts) != 3) {
            return false;
        }

        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret_key, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        if ($base64UrlSignature !== $signatureProvided) {
            return false;
        }

        $payload = json_decode($payload, true);

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }

        return $payload;
    }

    /**
     * Base64 URL encoding
     */
    private static function base64UrlEncode($text) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }

    /**
     * Get token from Authorization header
     */
    public static function getTokenFromHeader() {
        $headers = getallheaders();
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    /**
     * Verify and get user from token
     */
    public static function verifyToken() {
        $token = self::getTokenFromHeader();
        
        if (!$token) {
            ApiResponse::unauthorized("Token not provided");
        }

        $decoded = self::decode($token);
        
        if (!$decoded) {
            ApiResponse::unauthorized("Invalid or expired token");
        }

        return $decoded;
    }
}

/**
 * Request Helper
 */
class Request {
    
    /**
     * Get JSON input
     */
    public static function getJson() {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }

    /**
     * Get query parameter
     */
    public static function get($key, $default = null) {
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }

    /**
     * Get POST parameter
     */
    public static function post($key, $default = null) {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    /**
     * Sanitize input
     */
    public static function sanitize($value) {
        if (is_array($value)) {
            return array_map([self::class, 'sanitize'], $value);
        }
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get uploaded file
     */
    public static function file($key) {
        return isset($_FILES[$key]) ? $_FILES[$key] : null;
    }
}

/**
 * Pagination Helper
 */
class Paginator {
    
    public static function paginate($query, $db, $page = 1, $perPage = 10) {
        $page = max(1, (int)$page);
        $perPage = min(100, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        // Get total count
        $countQuery = preg_replace('/SELECT .+ FROM/i', 'SELECT COUNT(*) as total FROM', $query);
        $stmt = $db->prepare($countQuery);
        $stmt->execute();
        $total = $stmt->fetch()['total'];

        // Add limit and offset
        $query .= " LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll();

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => (int)$total,
                'total_pages' => ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total
            ]
        ];
    }
}

/**
 * File Upload Handler
 */
class FileUploader {
    
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
    private $maxSize = 5242880; // 5MB
    private $uploadDir = '../uploads/';

    public function __construct($uploadDir = null) {
        if ($uploadDir) {
            $this->uploadDir = $uploadDir;
        }
        
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    /**
     * Upload single file
     */
    public function upload($file, $subfolder = '') {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error");
        }

        // Validate file type
        if (!in_array($file['type'], $this->allowedTypes)) {
            throw new Exception("Invalid file type. Allowed: " . implode(', ', $this->allowedTypes));
        }

        // Validate file size
        if ($file['size'] > $this->maxSize) {
            throw new Exception("File too large. Maximum size: " . ($this->maxSize / 1024 / 1024) . "MB");
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;

        $targetDir = $this->uploadDir . $subfolder;
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $targetPath = $targetDir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return str_replace('../', '', $targetPath);
        }

        throw new Exception("Failed to upload file");
    }

    /**
     * Upload multiple files
     */
    public function uploadMultiple($files, $subfolder = '') {
        $uploaded = [];
        
        foreach ($files['tmp_name'] as $key => $tmp_name) {
            $file = [
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
                'tmp_name' => $tmp_name,
                'error' => $files['error'][$key],
                'size' => $files['size'][$key]
            ];
            
            try {
                $uploaded[] = $this->upload($file, $subfolder);
            } catch (Exception $e) {
                // Log error but continue with other files
                error_log("File upload failed: " . $e->getMessage());
            }
        }

        return $uploaded;
    }
}

?>