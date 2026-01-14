<?php
class PackageBooking {
    private $conn;
    private $table_name = "bookings"; // ✅ FIXED

    // Booking properties
    public $id;
    public $user_id;
    public $vendor_id;
    public $package_name;
    public $vendor_name;
    public $event_type;
    public $event_name;
    public $event_date;
    public $event_time;
    public $duration;
    public $venue;
    public $guest_count;
    public $customer_name;
    public $customer_phone;
    public $customer_email;
    public $alternate_phone;
    public $preferred_contact_method;
    public $special_requirements;
    public $estimated_price;
    public $booking_reference;
    public $status; // ✅ FIXED (was booking_status)
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create new package booking
    public function create() {

        // Generate unique booking reference
        $this->booking_reference = 'PKG-' . strtoupper(uniqid());
        $this->status = 'pending';

        // ✅ SAFETY DEFAULTS (PREVENT DB FAILURES)
        $this->event_name = !empty($this->event_name)
            ? $this->event_name
            : $this->package_name;

        $this->alternate_phone = !empty($this->alternate_phone)
            ? $this->alternate_phone
            : $this->customer_phone;

        $this->preferred_contact_method = !empty($this->preferred_contact_method)
            ? $this->preferred_contact_method
            : 'phone';

        $this->special_requirements = $this->special_requirements ?? '';

        $this->guest_count = max(1, (int)$this->guest_count);

        $query = "INSERT INTO " . $this->table_name . "
                SET
                    booking_reference = :booking_reference,
                    user_id = :user_id,
                    vendor_id = :vendor_id,
                    package_name = :package_name,
                    vendor_name = :vendor_name,
                    event_type = :event_type,
                    event_name = :event_name,
                    event_date = :event_date,
                    event_time = :event_time,
                    duration = :duration,
                    venue = :venue,
                    guest_count = :guest_count,
                    customer_name = :customer_name,
                    customer_phone = :customer_phone,
                    customer_email = :customer_email,
                    alternate_phone = :alternate_phone,
                    preferred_contact_method = :preferred_contact_method,
                    special_requirements = :special_requirements,
                    estimated_price = :estimated_price,
                    status = :status,
                    created_at = NOW()";

        try {
            $stmt = $this->conn->prepare($query);

            // Sanitize inputs
            $this->user_id = htmlspecialchars(strip_tags($this->user_id));
            $this->vendor_id = htmlspecialchars(strip_tags($this->vendor_id));
            $this->package_name = htmlspecialchars(strip_tags($this->package_name));
            $this->vendor_name = htmlspecialchars(strip_tags($this->vendor_name));
            $this->event_type = htmlspecialchars(strip_tags($this->event_type));
            $this->event_name = htmlspecialchars(strip_tags($this->event_name));
            $this->customer_name = htmlspecialchars(strip_tags($this->customer_name));
            $this->customer_phone = htmlspecialchars(strip_tags($this->customer_phone));
            $this->customer_email = htmlspecialchars(strip_tags($this->customer_email));
            $this->alternate_phone = htmlspecialchars(strip_tags($this->alternate_phone));
            $this->preferred_contact_method = htmlspecialchars(strip_tags($this->preferred_contact_method));
            $this->special_requirements = htmlspecialchars(strip_tags($this->special_requirements));
            $this->venue = htmlspecialchars(strip_tags($this->venue));
            $this->booking_reference = htmlspecialchars(strip_tags($this->booking_reference));

            // Bind values
            $stmt->bindParam(":booking_reference", $this->booking_reference);
            $stmt->bindParam(":user_id", $this->user_id);
            $stmt->bindParam(":vendor_id", $this->vendor_id);
            $stmt->bindParam(":package_name", $this->package_name);
            $stmt->bindParam(":vendor_name", $this->vendor_name);
            $stmt->bindParam(":event_type", $this->event_type);
            $stmt->bindParam(":event_name", $this->event_name);
            $stmt->bindParam(":event_date", $this->event_date);
            $stmt->bindParam(":event_time", $this->event_time);
            $stmt->bindParam(":duration", $this->duration);
            $stmt->bindParam(":venue", $this->venue);
            $stmt->bindParam(":guest_count", $this->guest_count);
            $stmt->bindParam(":customer_name", $this->customer_name);
            $stmt->bindParam(":customer_phone", $this->customer_phone);
            $stmt->bindParam(":customer_email", $this->customer_email);
            $stmt->bindParam(":alternate_phone", $this->alternate_phone);
            $stmt->bindParam(":preferred_contact_method", $this->preferred_contact_method);
            $stmt->bindParam(":special_requirements", $this->special_requirements);
            $stmt->bindParam(":estimated_price", $this->estimated_price);
            $stmt->bindParam(":status", $this->status);

            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return $this->booking_reference;
            }

        } catch (PDOException $e) {
            // 🔒 Log error safely
            file_put_contents(
                'debug_package.log',
                "DB ERROR: " . $e->getMessage() . "\n",
                FILE_APPEND
            );
        }

        return false;
    }

    // Read all bookings for a user
    public function readByUser() {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE user_id = :user_id
                  ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->execute();

        return $stmt;
    }

    // Read single booking
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE id = :id
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->user_id = $row['user_id'];
            $this->vendor_id = $row['vendor_id'];
            $this->package_name = $row['package_name'];
            $this->vendor_name = $row['vendor_name'];
            $this->event_type = $row['event_type'];
            $this->event_name = $row['event_name'];
            $this->event_date = $row['event_date'];
            $this->event_time = $row['event_time'];
            $this->duration = $row['duration'];
            $this->venue = $row['venue'];
            $this->guest_count = $row['guest_count'];
            $this->customer_name = $row['customer_name'];
            $this->customer_phone = $row['customer_phone'];
            $this->customer_email = $row['customer_email'];
            $this->alternate_phone = $row['alternate_phone'];
            $this->preferred_contact_method = $row['preferred_contact_method'];
            $this->special_requirements = $row['special_requirements'];
            $this->estimated_price = $row['estimated_price'];
            $this->booking_reference = $row['booking_reference'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            return true;
        }

        return false;
    }

    // Update booking status
    public function updateStatus() {
        $query = "UPDATE " . $this->table_name . "
                SET status = :status
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    // Delete booking
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }
}
?>
