<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
include 'connect.php';

$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'];
$post_id = $data['post_id'];

// 🔍 หาผู้สร้างโพสต์
$owner_stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
$owner_stmt->bind_param("i", $post_id);
$owner_stmt->execute();
$owner_result = $owner_stmt->get_result();

if ($owner_row = $owner_result->fetch_assoc()) {
    $owner_id = $owner_row['user_id'];

    // ✅ เพิ่มไลค์
    $stmt = $conn->prepare("INSERT IGNORE INTO likes (user_id, post_id, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $user_id, $post_id);

    if ($stmt->execute()) {
        // ✅ เพิ่มการแจ้งเตือน
        if ($user_id != $owner_id) { // ไม่แจ้งเตือนตัวเอง
            $content = "ถูกใจโพสต์ของคุณ";
            $type = "like";
            
            // [แก้ไข] เพิ่ม post_id เข้าไปในการแจ้งเตือน
            $notify_stmt = $conn->prepare("INSERT INTO notifications (receiver_id, sender_id, type, content, post_id, is_read, created_at)
                                           VALUES (?, ?, ?, ?, ?, 0, NOW())");
            // [แก้ไข] เพิ่ม "i" สำหรับ post_id ใน bind_param
            $notify_stmt->bind_param("iissi", $owner_id, $user_id, $type, $content, $post_id);
            $notify_stmt->execute();
            $notify_stmt->close();
        }

        echo json_encode(["status" => "success", "message" => "Post liked"]);
    } else {
        echo json_encode(["status" => "fail", "message" => "Like failed"]);
    }

    $stmt->close();
} else {
    echo json_encode(["status" => "fail", "message" => "Post not found"]);
}

$owner_stmt->close();
$conn->close();
?>