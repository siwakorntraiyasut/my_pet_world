<?php
// connect.php ควรจะมี header CORS อยู่แล้ว
include 'connect.php';

// รับข้อมูลเป็น JSON
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_id']) || !isset($data['current_password']) || !isset($data['new_password'])) {
    echo json_encode(['status' => 'fail', 'message' => 'Missing required fields']);
    exit;
}

$user_id = $data['user_id'];
$current_password = $data['current_password'];
$new_password = $data['new_password'];

// --- 1. ดึงรหัสผ่านที่เข้ารหัสไว้ (hashed password) ของผู้ใช้ปัจจุบันออกมา ---
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'fail', 'message' => 'User not found']);
    exit;
}

$user = $result->fetch_assoc();
$hashed_password_from_db = $user['password'];

// --- 2. ตรวจสอบว่ารหัสผ่านปัจจุบันที่ผู้ใช้กรอกมา ตรงกับในฐานข้อมูลหรือไม่ ---
if (password_verify($current_password, $hashed_password_from_db)) {
    // ถ้าตรงกัน ให้เข้ารหัสรหัสผ่านใหม่
    $new_hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    // --- 3. อัปเดตรหัสผ่านใหม่ลงในฐานข้อมูล ---
    $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_hashed_password, $user_id);

    if ($update_stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Password updated successfully']);
    } else {
        echo json_encode(['status' => 'fail', 'message' => 'Failed to update password']);
    }
    $update_stmt->close();
} else {
    // ถ้ารหัสผ่านปัจจุบันไม่ถูกต้อง
    echo json_encode(['status' => 'fail', 'message' => 'Incorrect current password']);
}

$stmt->close();
$conn->close();
?>