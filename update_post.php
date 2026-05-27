<?php
include 'connect.php';

if (!isset($_POST['post_id'])) {
    echo json_encode(['status' => 'fail', 'message' => 'Post ID is required']);
    exit;
}

$post_id = intval($_POST['post_id']);
$new_content = isset($_POST['content']) ? $_POST['content'] : null;

// ดึงข้อมูลรูปภาพเก่า
$stmt = $conn->prepare("SELECT image FROM posts WHERE id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();
$current_image = $post['image'];

// จัดการรูปภาพใหม่ (ถ้ามี)
$image_name_to_update = $current_image;
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $target_dir = "uploads/";
    $image_name_to_update = time() . "_" . basename($_FILES["image"]["name"]);
    $target_file = $target_dir . $image_name_to_update;
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        // ลบรูปเก่าถ้าไม่ใช่ default
        if ($current_image != null && !str_contains($current_image, 'http')) {
            @unlink($target_dir . $current_image);
        }
    } else {
        $image_name_to_update = $current_image;
    }
}

// อัปเดตฐานข้อมูล
$stmt = $conn->prepare("UPDATE posts SET content = ?, image = ? WHERE id = ?");
$stmt->bind_param("ssi", $new_content, $image_name_to_update, $post_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Post updated']);
} else {
    echo json_encode(['status' => 'fail', 'message' => 'Update failed']);
}

$stmt->close();
$conn->close();
?>