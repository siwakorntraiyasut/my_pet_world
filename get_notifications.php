<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
require 'connect.php';

if (!isset($_GET['receiver_id'])) {
    echo json_encode(['status' => 'fail', 'message' => 'Missing receiver_id']);
    exit;
}

$receiver_id = intval($_GET['receiver_id']);

// ✅ [แก้ไข] เพิ่ม u.updated_at เข้ามาใน SELECT
$sql = "
    SELECT 
        n.id, n.receiver_id, n.sender_id,
        u.username AS sender_username,
        u.profile_image AS sender_profile_image,
        u.updated_at AS sender_updated_at, -- ดึงเวลาอัปเดตของผู้ส่ง
        n.type, n.content, n.post_id,
        p.image AS post_image,
        n.is_read, n.created_at
    FROM notifications n
    JOIN users u ON n.sender_id = u.id
    LEFT JOIN posts p ON n.post_id = p.id
    WHERE n.receiver_id = ?
    ORDER BY n.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $receiver_id);
$stmt->execute();
$result = $stmt->get_result();
$baseImageUrl = "http://192.168.1.52/my_pet_world_api/uploads/"; // ใส่ Base URL ของคุณ

$notifications = [];
while ($row = $result->fetch_assoc()) {
    // ✅ [แก้ไข] สร้าง URL แบบ Cache-Busting
    if (!empty($row['sender_profile_image']) && !preg_match('/^https?:\/\//', $row['sender_profile_image'])) {
        $version = strtotime($row['sender_updated_at']); // แปลงเวลาเป็นตัวเลข
        $row['sender_profile_image'] = $baseImageUrl . $row['sender_profile_image'] . '?v=' . $version;
    }
    $notifications[] = $row;
}

echo json_encode($notifications);
$conn->close();
?>