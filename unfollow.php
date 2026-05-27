<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require 'connect.php';

header('Content-Type: application/json; charset=UTF-8');

// รับข้อมูล JSON
$data = json_decode(file_get_contents('php://input'), true);

// ตรวจสอบข้อมูล
if (!isset($data['follower_id']) || !isset($data['following_id'])) {
    echo json_encode(['status' => 'fail', 'message' => 'Missing parameters']);
    exit;
}

$follower_id = $data['follower_id'];
$following_id = $data['following_id'];

// เช็กว่า follower_id และ following_id มีใน users หรือไม่
function userExists($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($result->num_rows > 0);
}

if (!userExists($conn, $follower_id) || !userExists($conn, $following_id)) {
    echo json_encode(['status' => 'fail', 'message' => 'User not found']);
    exit;
}

// ลบการติดตาม
$stmt = $conn->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
$stmt->bind_param("ii", $follower_id, $following_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Unfollowed successfully']);
    } else {
        echo json_encode(['status' => 'fail', 'message' => 'Not following']);
    }
} else {
    echo json_encode(['status' => 'fail', 'message' => 'Unfollow failed']);
}

$stmt->close();
$conn->close();
?>
