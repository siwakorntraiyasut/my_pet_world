<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
include 'connect.php';

$data = json_decode(file_get_contents("php://input"), true);

$notification_id = $data['notification_id'];

$sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $notification_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "fail", "message" => $stmt->error]);
}
$conn->close();
?>
