<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json; charset=UTF-8");

include 'connect.php';

// ✅ ตรวจสอบว่าข้อมูลที่จำเป็นถูกส่งมาครบ
if (!isset($_POST['user_id']) || !isset($_POST['name']) || !isset($_POST['gender']) || !isset($_POST['birthday'])) {
    echo json_encode(['status' => 'fail', 'message' => 'Missing required fields']);
    exit;
}

$user_id   = intval($_POST['user_id']);
$name      = $_POST['name'];
$gender    = $_POST['gender'];
$birthday  = $_POST['birthday'];
$age       = isset($_POST['age']) ? $_POST['age'] : '';
$species   = isset($_POST['species']) ? $_POST['species'] : null;
$is_active = 1;

$image_name = 'default_pet.png'; // ค่าเริ่มต้น

// ✅ อัปโหลดรูปภาพถ้ามี
if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $target_dir  = "uploads/";
    $image_name  = time() . "_" . basename($_FILES["image"]["name"]);
    $target_file = $target_dir . $image_name;

    // ย้ายไฟล์ไปยังโฟลเดอร์
    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        echo json_encode(['status' => 'fail', 'message' => 'Failed to upload image']);
        exit;
    }
}

// ✅ เตรียมคำสั่ง SQL เพื่อบันทึก
$stmt = $conn->prepare("
    INSERT INTO pets 
        (user_id, name, species, gender, age, birthday, image, is_active, created_at) 
    VALUES 
        (?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param("issssssi", $user_id, $name, $species, $gender, $age, $birthday, $image_name, $is_active);

// ✅ ตอบกลับผลลัพธ์
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Pet added successfully']);
} else {
    echo json_encode(['status' => 'fail', 'message' => 'Failed to add pet to database']);
}

$stmt->close();
$conn->close();
?>
