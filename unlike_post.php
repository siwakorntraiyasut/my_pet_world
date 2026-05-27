<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
include 'connect.php';

$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'];
$post_id = $data['post_id'];

$sql = "DELETE FROM likes WHERE user_id = ? AND post_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $post_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "fail", "message" => $stmt->error]);
}
$conn->close();
?>
