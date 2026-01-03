<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../models/Review.php';

$database = new Database();
$db = $database->getConnection();

$review = new Review($db);

$data = json_decode(file_get_contents("php://input"));

if (
    !empty($data->booking_type) &&
    !empty($data->booking_id) &&
    !empty($data->user_id) &&
    isset($data->rating)
) {
    $review->booking_type = $data->booking_type;
    $review->booking_id = $data->booking_id;
    $review->user_id = $data->user_id;
    $review->vendor_id = isset($data->vendor_id) ? $data->vendor_id : null;
    $review->rating = $data->rating;
    $review->comment = isset($data->comment) ? $data->comment : null;

    if ($review->create()) {
        http_response_code(201);
        echo json_encode(array(
            "success" => true,
            "message" => "Review submitted successfully."
        ));
    } else {
        http_response_code(503);
        echo json_encode(array(
            "success" => false,
            "message" => "Unable to submit review."
        ));
    }
} else {
    http_response_code(400);
    echo json_encode(array(
        "success" => false,
        "message" => "Unable to submit review. Data is incomplete."
    ));
}
?>