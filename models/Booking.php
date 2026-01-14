<?php
class Booking {

    private $conn;
    private $table_name = "bookings";

    public $id;
    public $user_id;
    public $vendor_id;
    public $booking_request_id;
    public $booking_reference;              // ✅ ADDED
    public $service_id;
    public $service_name;
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
    public $status;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {

        try {

            // ✅ Generate booking reference FIRST
            $this->booking_reference = "EVT-" . strtoupper(uniqid());

            $query = "
                INSERT INTO {$this->table_name}
                SET
                    booking_reference = :booking_reference,
                    user_id = :user_id,
                    vendor_id = :vendor_id,
                    service_id = :service_id,
                    service_name = :service_name,
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
                    status = 'pending'
            ";

            $stmt = $this->conn->prepare($query);

            // ✅ Bind parameters
            $stmt->bindParam(":booking_reference", $this->booking_reference);
            $stmt->bindParam(":user_id", $this->user_id, PDO::PARAM_INT);
            $stmt->bindParam(":vendor_id", $this->vendor_id, PDO::PARAM_INT);

            // ✅ CRITICAL FIX — NULL SAFE service_id
            if ($this->service_id === null) {
                $stmt->bindValue(":service_id", null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(":service_id", $this->service_id, PDO::PARAM_INT);
            }

            $stmt->bindParam(":service_name", $this->service_name);
            $stmt->bindParam(":vendor_name", $this->vendor_name);
            $stmt->bindParam(":event_type", $this->event_type);
            $stmt->bindParam(":event_name", $this->event_name);
            $stmt->bindParam(":event_date", $this->event_date);
            $stmt->bindParam(":event_time", $this->event_time);
            $stmt->bindParam(":duration", $this->duration);
            $stmt->bindParam(":venue", $this->venue);
            $stmt->bindParam(":guest_count", $this->guest_count, PDO::PARAM_INT);
            $stmt->bindParam(":customer_name", $this->customer_name);
            $stmt->bindParam(":customer_phone", $this->customer_phone);
            $stmt->bindParam(":customer_email", $this->customer_email);
            $stmt->bindParam(":alternate_phone", $this->alternate_phone);
            $stmt->bindParam(":preferred_contact_method", $this->preferred_contact_method);
            $stmt->bindParam(":special_requirements", $this->special_requirements);
            $stmt->bindParam(":estimated_price", $this->estimated_price);

            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return $this->booking_reference;
            }

            return false;

        } catch (PDOException $e) {

            // ✅ REAL ERROR LOGGING
            file_put_contents(
                __DIR__ . '/../debug.log',
                "BOOKING CREATE ERROR: " . $e->getMessage() . PHP_EOL,
                FILE_APPEND
            );

            return false;
        }
    }
}
