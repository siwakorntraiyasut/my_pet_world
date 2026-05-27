<?php
// === set_new_password.php (ฉบับแก้ไข) ===

include 'connect.php';

// รับข้อมูล JSON
$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$token = $data['token'] ?? ''; // Token 6 หลักที่ผู้ใช้กรอก
$new_password = $data['new_password'] ?? '';

// ตรวจสอบข้อมูลเบื้องต้น
if (empty($email) || empty($token) || empty($new_password)) {
    http_response_code(400);
    echo json_encode(['status' => 'fail', 'message' => 'ข้อมูลที่ส่งมาไม่ครบถ้วน']);
    exit;
}

// [จุดที่แก้ไข 1] -> ทำการ Hash Token ที่ผู้ใช้ส่งมาด้วย Algorithm เดียวกัน
$hashed_token = hash('sha256', $token);

// [จุดที่แก้ไข 2] -> ตรวจสอบ Token ที่ถูก Hash และยังไม่หมดอายุในฐานข้อมูล
$stmt = $conn->prepare("
    SELECT * FROM password_resets 
    WHERE email = ? AND token = ? AND created_at >= NOW() - INTERVAL 10 MINUTE
");
$stmt->bind_param("ss", $email, $hashed_token);
$stmt->execute();

// ถ้าตรวจสอบ Token ผ่าน (มีแถวข้อมูลคืนมา)
if ($stmt->get_result()->num_rows > 0) {
    // 1. เข้ารหัสรหัสผ่านใหม่
    $new_hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    
    // 2. อัปเดตรหัสผ่านใหม่ลงในตาราง users
    $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $update_stmt->bind_param("ss", $new_hashed_password, $email);
    $update_stmt->execute();
    $update_stmt->close();
    
    // 3. ลบข้อมูล Token ที่ใช้แล้วออกจากตาราง password_resets
    $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
    $delete_stmt->bind_param("s", $email);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    echo json_encode(['status' => 'success', 'message' => 'เปลี่ยนรหัสผ่านสำเร็จแล้ว']);
} else {
    // ถ้า Token ไม่ถูกต้อง หรือหมดอายุ
    echo json_encode(['status' => 'fail', 'message' => 'รหัสยืนยันไม่ถูกต้องหรือหมดอายุ']);
}

$stmt->close();
$conn->close();
exit;
?>