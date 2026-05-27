<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require 'connect.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['follower_id']) || !isset($data['following_id'])) {
    echo json_encode(['status' => 'fail', 'message' => 'Missing parameters']);
    exit;
}

$follower_id = $data['follower_id'];
$following_id = $data['following_id'];

if ($follower_id == $following_id) {
    echo json_encode(['status' => 'fail', 'message' => 'Cannot follow yourself']);
    exit;
}

// เพิ่มการติดตาม
$stmt = $conn->prepare("INSERT IGNORE INTO follows (follower_id, following_id, created_at) VALUES (?, ?, NOW())");
$stmt->bind_param("ii", $follower_id, $following_id);

if ($stmt->execute()) {
    // ✅ เพิ่มการแจ้งเตือน
    $content = "ได้ติดตามคุณแล้ว";
    $type = "follow";
    $notify_stmt = $conn->prepare("INSERT INTO notifications (receiver_id, sender_id, type, content, is_read, created_at)
                                   VALUES (?, ?, ?, ?, 0, NOW())");
    $notify_stmt->bind_param("iiss", $following_id, $follower_id, $type, $content);
    $notify_stmt->execute();
    $notify_stmt->close();

    echo json_encode(['status' => 'success', 'message' => 'Followed successfully']);
} else {
    echo json_encode(['status' => 'fail', 'message' => 'Follow failed']);
}

$stmt->close();
$conn->close();
?>
