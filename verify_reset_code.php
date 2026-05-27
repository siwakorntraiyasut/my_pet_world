<?php
// === verify_reset_code.php (ฉบับแก้ไข) ===

include 'connect.php';

// รับข้อมูล JSON จาก Flutter
$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$token = $data['token'] ?? ''; // นี่คือ token แบบ plain text ที่ผู้ใช้กรอก

// ตรวจสอบค่าว่างเบื้องต้น
if (empty($email) || empty($token)) {
    http_response_code(400);
    echo json_encode(['status' => 'fail', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
    exit;
}

// [จุดที่แก้ไข 1] -> ทำการ hash token ที่ผู้ใช้ส่งมาด้วย 'sha256' 
// ต้องเป็น algorithm เดียวกันกับตอนที่สร้างและบันทึก token
$hashed_user_token = hash('sha256', $token);

// เตรียมคำสั่ง SQL เพื่อค้นหา token ที่ถูก hash และยังไม่หมดอายุ (10 นาที)
$stmt = $conn->prepare(
    "SELECT * FROM password_resets 
     WHERE email = ? 
       AND token = ? 
       AND created_at >= NOW() - INTERVAL 10 MINUTE"
);

// [จุดที่แก้ไข 2] -> เปรียบเทียบกับ token ที่ถูก hash แล้วในฐานข้อมูล
$stmt->bind_param("ss", $email, $hashed_user_token);
$stmt->execute();

// ตรวจสอบผลลัพธ์
if ($stmt->get_result()->num_rows > 0) {
    // ถ้าพบ แสดงว่า token ถูกต้องและยังไม่หมดอายุ
    echo json_encode(['status' => 'success', 'message' => 'Token is valid']);
} else {
    // ถ้าไม่พบ แสดงว่า token ไม่ถูกต้อง หรือหมดอายุไปแล้ว
    echo json_encode(['status' => 'fail', 'message' => 'รหัสยืนยันไม่ถูกต้องหรือหมดอายุ']);
}

// ปิดการเชื่อมต่อ
$stmt->close();
$conn->close();

exit; // จบการทำงานของสคริปต์
?>