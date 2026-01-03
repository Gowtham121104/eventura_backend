<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->id) && !empty($data->organizer_id)) {
    try {
        $query = "UPDATE events SET 
                  title = :title,
                  description = :description,
                  event_date = :event_date,
                  start_time = :start_time,
                  end_time = :end_time,
                  location = :location,
                  venue = :venue,
                  category = :category,
                  price = :price,
                  max_attendees = :max_attendees,
                  image_url = :image_url,
                  status = :status
                  WHERE id = :id AND organizer_id = :organizer_id";
        
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':id', $data->id);
        $stmt->bindParam(':organizer_id', $data->organizer_id);
        $stmt->bindParam(':title', $data->title);
        $stmt->bindParam(':description', $data->description);
        $stmt->bindParam(':event_date', $data->event_date);
        $stmt->bindParam(':start_time', $data->start_time);
        $stmt->bindParam(':end_time', $data->end_time);
        $stmt->bindParam(':location', $data->location);
        $stmt->bindParam(':venue', $data->venue);
        $stmt->bindParam(':category', $data->category);
        $stmt->bindParam(':price', $data->price);
        $stmt->bindParam(':max_attendees', $data->max_attendees);
        $stmt->bindParam(':image_url', $data->image_url);
        $stmt->bindParam(':status', $data->status);
        
        if($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array(
                "success" => true,
                "message" => "Event updated successfully"
            ));
        } else {
            http_response_code(500);
            echo json_encode(array(
                "success" => false,
                "message" => "Failed to update event"
            ));
        }
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(array(
            "success" => false,
            "message" => "Error: " . $e->getMessage()
        ));
    }
} else {
    http_response_code(400);
    echo json_encode(array(
        "success" => false,
        "message" => "Incomplete data"
    ));
}
?>