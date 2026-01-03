<?php
class PackageBooking {
    private $conn;
    private $table_name = "package_bookings";

    // Booking properties
    public $id;
    public $user_id;
    public $vendor_id;
    public $package_id;
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
    public $booking_status;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create new package booking
    public function create() {
        // Generate unique booking reference
        $this->booking_reference = 'PKG-' . strtoupper(uniqid());
        $this->booking_status = 'pending';

        $query = "INSERT INTO " . $this->table_name . "
                SET
                    user_id = :user_id,
                    vendor_id = :vendor_id,
                    package_id = :package_id,
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
                    booking_reference = :booking_reference,
                    booking_status = :booking_status,
                    created_at = NOW()";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->vendor_id = htmlspecialchars(strip_tags($this->vendor_id));
        $this->package_name = htmlspecialchars(strip_tags($this->package_name));
        $this->vendor_name = htmlspecialchars(strip_tags($this->vendor_name));
        $this->event_type = htmlspecialchars(strip_tags($this->event_type));
        $this->customer_name = htmlspecialchars(strip_tags($this->customer_name));
        $this->customer_phone = htmlspecialchars(strip_tags($this->customer_phone));
        $this->customer_email = htmlspecialchars(strip_tags($this->customer_email));
        $this->venue = htmlspecialchars(strip_tags($this->venue));
        $this->booking_reference = htmlspecialchars(strip_tags($this->booking_reference));

        // Bind values
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":vendor_id", $this->vendor_id);
        $stmt->bindParam(":package_id", $this->package_id);
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
        $stmt->bindParam(":booking_reference", $this->booking_reference);
        $stmt->bindParam(":booking_status", $this->booking_status);

        // Execute query
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return $this->booking_reference;
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
            $this->package_id = $row['package_id'];
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
            $this->booking_status = $row['booking_status'];
            $this->created_at = $row['created_at'];
            return true;
        }

        return false;
    }

    // Update booking status
    public function updateStatus() {
        $query = "UPDATE " . $this->table_name . "
                SET booking_status = :booking_status
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->booking_status = htmlspecialchars(strip_tags($this->booking_status));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':booking_status', $this->booking_status);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    // Delete booking
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(":id", $this->id);

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }
}
?>
