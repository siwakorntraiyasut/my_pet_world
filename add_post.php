<?php
// =================================================================
// File: add_post.php
// Description: รับข้อมูลโพสต์ใหม่จาก Flutter, อัปโหลดรูปภาพ, 
//              และบันทึกลงฐานข้อมูลอย่างปลอดภัย
// =================================================================

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'connect.php'; // ไฟล์เชื่อมต่อฐานข้อมูลของคุณ

// --- 1. ตรวจสอบ Input พื้นฐาน ---
// ตรวจสอบว่ามี user_id และ content ส่งมาใน 'fields' หรือไม่
if (!isset($_POST['user_id']) || !isset($_POST['content'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'fail', 'message' => 'Missing required fields: user_id or content.']);
    exit;
}
// ตรวจสอบว่ามีการอัปโหลดไฟล์รูปภาพมาหรือไม่
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'fail', 'message' => 'Image is required.']);
    exit;
}

$user_id = intval($_POST['user_id']);
$content = trim($_POST['content']);

// --- 2. จัดการการอัปโหลดไฟล์ ---
$upload_dir = 'uploads/'; // โฟลเดอร์สำหรับเก็บรูปภาพ
// ตรวจสอบว่ามีโฟลเดอร์นี้อยู่จริง ถ้าไม่มีให้สร้างขึ้นมา
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$image_file = $_FILES['image'];
$image_name = $image_file['name'];
$image_tmp_name = $image_file['tmp_name'];

// สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำกัน เพื่อป้องกันการเขียนทับ
// โดยใช้ timestamp + ชื่อไฟล์เดิม
$file_extension = pathinfo($image_name, PATHINFO_EXTENSION);
$unique_image_name = time() . '_' . uniqid() . '.' . $file_extension;
$target_path = $upload_dir . $unique_image_name;

// --- 3. ย้ายไฟล์ที่อัปโหลดไปยังโฟลเดอร์เป้าหมาย ---
if (!move_uploaded_file($image_tmp_name, $target_path)) {
    // ถ้าการย้ายไฟล์ล้มเหลว ให้หยุดการทำงานทันที
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'fail', 'message' => 'Failed to upload image.']);
    exit;
}

// --- 4. บันทึกข้อมูลลงฐานข้อมูล (ใช้ Prepared Statements) ---
// เราจะใช้ Transaction เพื่อให้แน่ใจว่าถ้าขั้นตอนใดล้มเหลว จะไม่เกิดการบันทึกข้อมูลครึ่งๆ กลางๆ
$conn->begin_transaction();

try {
    $sql = "INSERT INTO posts (user_id, content, image, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    
    // ส่ง path ของรูปภาพที่บันทึกสำเร็จแล้วลงฐานข้อมูล
    $stmt->bind_param("iss", $user_id, $content, $unique_image_name);

    // ถ้าการ execute สำเร็จ
    if ($stmt->execute()) {
        // ยืนยันการทำ Transaction ทั้งหมด
        $conn->commit();
        http_response_code(200); // OK
        echo json_encode(['status' => 'success', 'message' => 'Post created successfully.']);
    } else {
        // ถ้าการ execute ล้มเหลว ให้โยน Exception เพื่อไปเข้า catch block
        throw new Exception("Database insertion failed.");
    }

} catch (Exception $e) {
    // หากเกิดข้อผิดพลาดใดๆ ใน try block ให้ยกเลิกการเปลี่ยนแปลงทั้งหมด
    $conn->rollback();
    
    // ลบไฟล์รูปภาพที่อัปโหลดไปแล้ว เพื่อไม่ให้มีไฟล์ขยะในระบบ
    if (file_exists($target_path)) {
        unlink($target_path);
    }
    
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => 'fail', 
        'message' => 'Failed to create post: ' . $e->getMessage()
    ]);
} finally {
    // ปิด statement และ connection ไม่ว่าจะสำเร็จหรือล้มเหลว
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}
?>