<?php
include 'connect.php'; // ใช้ connect.php ที่มี CORS header แล้ว

// --- 1. รับข้อมูล Input และตรวจสอบเบื้องต้น ---
if (!isset($_POST['user_id'])) {
    echo json_encode(['status' => 'fail', 'message' => 'User ID is required']);
    exit;
}
$user_id = intval($_POST['user_id']);

// --- 2. ดึงข้อมูล user เดิมจากฐานข้อมูล ---
$stmt = $conn->prepare("SELECT username, email, profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['status' => 'fail', 'message' => 'User not found']);
    exit;
}
$user = $result->fetch_assoc();
$stmt->close();

// --- 3. เตรียมข้อมูลใหม่ และตรวจสอบความถูกต้อง ---
// ใช้ trim() เพื่อตัดช่องว่างหน้า-หลัง และตรวจสอบค่าว่างด้วย !empty()
$new_username = !empty(trim($_POST['username'])) ? trim($_POST['username']) : $user['username'];
$new_email = !empty(trim($_POST['email'])) ? trim($_POST['email']) : $user['email'];
$new_profile_image = $user['profile_image']; // ใช้รูปเดิมเป็นค่าตั้งต้น

// ✅✅✅ 4. ตรวจสอบการซ้ำ (Username & Email) ก่อนทำการอัปเดต ✅✅✅
// ตรวจสอบ Username ซ้ำ (เฉพาะในกรณีที่มีการเปลี่ยนชื่อ)
if ($new_username !== $user['username']) {
    // ค้นหาว่ามี username นี้ที่ ID อื่นใช้อยู่หรือไม่
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt_check->bind_param("si", $new_username, $user_id);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'fail', 'message' => 'ชื่อผู้ใช้นี้มีคนอื่นใช้แล้ว']);
        $stmt_check->close();
        $conn->close();
        exit;
    }
    $stmt_check->close();
}

// ตรวจสอบ Email ซ้ำ (เฉพาะในกรณีที่มีการเปลี่ยนอีเมล)
if ($new_email !== $user['email']) {
    // ค้นหาว่ามี email นี้ที่ ID อื่นใช้อยู่หรือไม่
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt_check->bind_param("si", $new_email, $user_id);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'fail', 'message' => 'อีเมลนี้มีคนอื่นใช้แล้ว']);
        $stmt_check->close();
        $conn->close();
        exit;
    }
    $stmt_check->close();
}


// --- 5. จัดการการอัปโหลดรูปภาพ (ถ้ามี) ---
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
    $target_dir = "uploads/";
    // สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำกัน
    $image_name = time() . "_" . uniqid() . "_" . basename($_FILES["profile_image"]["name"]);
    $target_file = $target_dir . $image_name;

    if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
        // ถ้าอัปโหลดสำเร็จ ให้ใช้ชื่อไฟล์ใหม่ และลบไฟล์เก่า (ถ้าไม่ใช่ default)
        if ($user['profile_image'] != 'default.png' && !empty($user['profile_image'])) {
             @unlink($target_dir . $user['profile_image']);
        }
        $new_profile_image = $image_name;
    }
}

// --- 6. อัปเดตข้อมูลลงฐานข้อมูล ---
$stmt_update = $conn->prepare("UPDATE users SET username = ?, email = ?, profile_image = ? WHERE id = ?");
$stmt_update->bind_param("sssi", $new_username, $new_email, $new_profile_image, $user_id);

if ($stmt_update->execute()) {
    // --- 7. ดึงข้อมูลล่าสุดทั้งหมดกลับไปให้แอป ---
    $stmt_final = $conn->prepare("SELECT id, username, email, profile_image FROM users WHERE id = ?");
    $stmt_final->bind_param("i", $user_id);
    $stmt_final->execute();
    $updated_user = $stmt_final->get_result()->fetch_assoc();
    
    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully', 'user' => $updated_user]);

    $stmt_final->close();
} else {
    echo json_encode(['status' => 'fail', 'message' => 'Failed to update profile']);
}

$stmt_update->close();
$conn->close();
?>