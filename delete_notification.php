<?php
include 'connect.php'; // ใช้ connect.php ที่มี CORS header อยู่แล้ว

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['notification_id'])) {
    echo json_encode(['status' => 'fail', 'message' => 'Notification ID is required']);
    exit;
}

$notification_id = intval($data['notification_id']);
// เพิ่มการตรวจสอบ user_id ได้ในอนาคตเพื่อความปลอดภัย
// $user_id = intval($data['user_id']); 
// "DELETE FROM notifications WHERE id = ? AND receiver_id = ?"

$stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
$stmt->bind_param("i", $notification_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Notification deleted successfully']);
    } else {
        echo json_encode(['status' => 'fail', 'message' => 'Notification not found or already deleted']);
    }
} else {
    echo json_encode(['status' => 'fail', 'message' => 'Failed to delete notification']);
}

$stmt->close();
$conn->close();
?>