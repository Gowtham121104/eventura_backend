<?php
class Review {
    private $conn;
    private $table_name = "reviews";

    public $id;
    public $booking_type;
    public $booking_id;
    public $user_id;
    public $vendor_id;
    public $rating;
    public $comment;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    booking_type = :booking_type,
                    booking_id = :booking_id,
                    user_id = :user_id,
                    vendor_id = :vendor_id,
                    rating = :rating,
                    comment = :comment";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":booking_type", $this->booking_type);
        $stmt->bindParam(":booking_id", $this->booking_id);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":vendor_id", $this->vendor_id);
        $stmt->bindParam(":rating", $this->rating);
        $stmt->bindParam(":comment", $this->comment);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function getByBooking($booking_type, $booking_id) {
        $query = "SELECT * FROM " . $this->table_name . "
                WHERE booking_type = :booking_type 
                AND booking_id = :booking_id
                LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":booking_type", $booking_type);
        $stmt->bindParam(":booking_id", $booking_id);
        $stmt->execute();

        return $stmt;
    }
}
?>