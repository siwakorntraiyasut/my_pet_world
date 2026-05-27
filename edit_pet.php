<?php
include 'connect.php';

// ต้องใช้ pet_id เพื่อระบุว่าจะแก้ไขตัวไหน
if (!isset($_POST['pet_id'])) {
    echo json_encode(['status' => 'fail', 'message' => 'Pet ID is required']);
    exit;
}

$pet_id = intval($_POST['pet_id']);

// ดึงข้อมูลเก่ามาเป็นค่าเริ่มต้น
$stmt = $conn->prepare("SELECT image FROM pets WHERE id = ?");
$stmt->bind_param("i", $pet_id);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows === 0) {
    echo json_encode(['status' => 'fail', 'message' => 'Pet not found']);
    exit;
}
$pet = $result->fetch_assoc();
$current_image = $pet['image'];

// รับข้อมูลใหม่
$name = isset($_POST['name']) ? $_POST['name'] : null;
$gender = isset($_POST['gender']) ? $_POST['gender'] : null;
$birthday = isset($_POST['birthday']) ? $_POST['birthday'] : null;
$age = isset($_POST['age']) ? $_POST['age'] : null;
$image_name = $current_image;

// อัปโหลดรูปใหม่ (ถ้ามี)
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $target_dir = "uploads/";
    $image_name = time() . "_" . basename($_FILES["image"]["name"]);
    $target_file = $target_dir . $image_name;
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        // ลบรูปเก่า (ถ้าไม่ใช่ default)
        if ($current_image != 'default_pet.png') {
            @unlink($target_dir . $current_image);
        }
    } else {
        $image_name = $current_image; // ใช้อันเดิมถ้าอัปโหลดไม่สำเร็จ
    }
}

// สร้าง SQL Query แบบ Dynamic
$sql = "UPDATE pets SET ";
$params = [];
$types = "";

if ($name !== null) { $sql .= "name = ?, "; $params[] = $name; $types .= "s"; }
if ($gender !== null) { $sql .= "gender = ?, "; $params[] = $gender; $types .= "s"; }
if ($birthday !== null) { $sql .= "birthday = ?, "; $params[] = $birthday; $types .= "s"; }
if ($age !== null) { $sql .= "age = ?, "; $params[] = $age; $types .= "s"; }

$sql .= "image = ? "; $params[] = $image_name; $types .= "s";

$sql .= "WHERE id = ?"; $params[] = $pet_id; $types .= "i";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Pet updated successfully']);
} else {
    echo json_encode(['status' => 'fail', 'message' => 'Failed to update pet']);
}

$stmt->close();
$conn->close();
?>