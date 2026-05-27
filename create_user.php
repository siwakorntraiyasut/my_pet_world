<?php
// connect.php ควรมี header CORS อยู่แล้ว
include 'connect.php';

// รับข้อมูลเป็น JSON
$data = json_decode(file_get_contents("php://input"), true);

// --- 1. ตรวจสอบว่ามีข้อมูลที่จำเป็นส่งมาครบหรือไม่ ---
if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'fail', 'message' => 'Missing required fields']);
    exit;
}

$username = trim($data['username']);
$email = trim($data['email']);
$password = $data['password'];
$profile_image = isset($data['profile_image']) ? $data['profile_image'] : 'default.png';

// --- 2. [เพิ่มเข้ามา] ตรวจสอบ Username ซ้ำ ---
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // ถ้าเจอ username ซ้ำ
    http_response_code(409); // 409 Conflict
    echo json_encode(["status" => "fail", "message" => "ชื่อผู้ใช้นี้มีคนอื่นใช้แล้ว"]);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();


// --- 3. [เพิ่มเข้ามา] ตรวจสอบ Email ซ้ำ ---
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // ถ้าเจอ email ซ้ำ
    http_response_code(409); // 409 Conflict
    echo json_encode(["status" => "fail", "message" => "อีเมลนี้มีคนอื่นใช้แล้ว"]);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();


// --- 4. เข้ารหัสรหัสผ่าน และเตรียมข้อมูลเพื่อบันทึก ---
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// --- 5. บันทึกผู้ใช้ใหม่ลงฐานข้อมูล (ถ้าไม่ซ้ำ) ---
$sql = "INSERT INTO users (username, email, password, profile_image) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $username, $email, $hashed_password, $profile_image);

if ($stmt->execute()) {
    // ส่งผลลัพธ์ว่าสำเร็จกลับไป
    echo json_encode(["status" => "success", "message" => "Registration successful", "user_id" => $stmt->insert_id]);
} else {
    // ถ้าการบันทึกล้มเหลว
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "fail", "message" => "Database error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?> 