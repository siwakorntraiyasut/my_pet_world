<?php
include 'connect.php';

$data = json_decode(file_get_contents("php://input"), true);

// --- 1. ตรวจสอบ Input ---
if (!isset($data['post_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'fail', 'message' => 'Post ID is required']);
    exit;
}

$post_id = intval($data['post_id']);

// --- 2. [ปรับปรุง] ดึงชื่อไฟล์รูปภาพอย่างปลอดภัย เพื่อลบออกจาก Server ---
$stmt = $conn->prepare("SELECT image FROM posts WHERE id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows > 0) {
    $post = $result->fetch_assoc();
    $image_to_delete = $post['image'];
    // ตรวจสอบว่ามีชื่อไฟล์และไม่ใช่ URL เต็ม
    if ($image_to_delete != null && !str_contains($image_to_delete, 'http')) {
        // ใช้ @ เพื่อป้องกัน Error หากไฟล์ไม่มีอยู่จริง
        @unlink('uploads/' . $image_to_delete);
    }
}
$stmt->close();


// --- 3. [ปรับปรุง] ใช้ Transaction และ Prepared Statements ในการลบข้อมูล ---
// เริ่ม Transaction เพื่อให้แน่ใจว่าทุกคำสั่งทำงานสำเร็จทั้งหมด หรือไม่ก็ยกเลิกทั้งหมด
$conn->begin_transaction();

try {
    // ลบข้อมูลจากตาราง likes
    $stmt_likes = $conn->prepare("DELETE FROM likes WHERE post_id = ?");
    $stmt_likes->bind_param("i", $post_id);
    $stmt_likes->execute();
    $stmt_likes->close();

    // (ถ้ามีตาราง comments ก็ลบด้วย Logic แบบเดียวกัน)
    // $stmt_comments = $conn->prepare("DELETE FROM comments WHERE post_id = ?");
    // $stmt_comments->bind_param("i", $post_id);
    // $stmt_comments->execute();
    // $stmt_comments->close();

    // ลบข้อมูลจากตาราง posts
    $stmt_posts = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $stmt_posts->bind_param("i", $post_id);
    $stmt_posts->execute();
    $stmt_posts->close();

    // ถ้าทุกอย่างสำเร็จ ให้ยืนยันการเปลี่ยนแปลง
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Post deleted successfully']);

} catch (mysqli_sql_exception $exception) {
    // หากมีข้อผิดพลาดเกิดขึ้น ให้ยกเลิกการเปลี่ยนแปลงทั้งหมด
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'fail', 'message' => 'Failed to delete post: ' . $exception->getMessage()]);
}


// --- 4. ปิดการเชื่อมต่อ ---
$conn->close();
?>