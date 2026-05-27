<?php
// ✅ รองรับ CORS Preflight Request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit();
}

// ✅ CORS Headers สำหรับทุก Request
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// เพิ่ม error logging
error_log("🔵 Login request received: " . $_SERVER['REQUEST_METHOD']);

include 'connect.php';

// ✅ รองรับทั้ง JSON และ Form Data
$raw_input = file_get_contents("php://input");
error_log("🔵 Raw input: " . $raw_input);

$data = json_decode($raw_input, true);

// ถ้าไม่ได้ส่ง JSON มา (เช่น Form-Data) ให้ fallback ไปใช้ $_POST
if (!$data) {
    $data = $_POST;
    error_log("🔵 Using POST data: " . print_r($_POST, true));
} else {
    error_log("🔵 Using JSON data: " . print_r($data, true));
}

// ✅ เช็กว่ามีข้อมูลที่ส่งมาครบไหม
if (!isset($data['login']) || !isset($data['password'])) {
    error_log("🔴 Missing login or password fields");
    echo json_encode(["status" => "fail", "message" => "Invalid input"]);
    exit();
}

$login = trim($data['login']);
$password = trim($data['password']);

error_log("🔵 Login attempt for: " . $login);

// เช็กค่าว่าง
if (empty($login) || empty($password)) {
    error_log("🔴 Empty login or password");
    echo json_encode(["status" => "fail", "message" => "Email/Username and Password are required"]);
    exit();
}

// ✅ เตรียม SQL ดึงจาก email หรือ username
$sql = "SELECT id, username, email, password, profile_image FROM users WHERE email = ? OR username = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("🔴 Database prepare error: " . $conn->error);
    echo json_encode(["status" => "fail", "message" => "Database error"]);
    exit();
}

$stmt->bind_param("ss", $login, $login);
$stmt->execute();
$result = $stmt->get_result();

error_log("🔵 Query executed, rows found: " . $result->num_rows);

// ✅ เช็กผลลัพธ์
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    error_log("🔵 User found: " . $user['username']);

    // ตรวจสอบรหัสผ่าน
    if (password_verify($password, $user['password'])) {
        unset($user['password']); // ไม่ส่ง password กลับ
        error_log("🟢 Password verified, login successful");
        echo json_encode([
            "status" => "success",
            "token" => bin2hex(random_bytes(16)), // สร้าง token จำลอง
            "user" => $user
        ]);
    } else {
        error_log("🔴 Password verification failed");
        echo json_encode(["status" => "fail", "message" => "Invalid password"]);
    }
} else {
    error_log("🔴 User not found for login: " . $login);
    echo json_encode(["status" => "fail", "message" => "User not found"]);
}

// ปิดการเชื่อมต่อ
$stmt->close();
$conn->close();
?>