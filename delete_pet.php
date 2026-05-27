<?php
include 'connect.php';

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['pet_id'])) {
    echo json_encode(['status' => 'fail', 'message' => 'Pet ID is required']);
    exit;
}

$pet_id = intval($data['pet_id']);

// (Optional) ดึงชื่อไฟล์รูปเพื่อลบไฟล์ออกจาก server ด้วย
$stmt = $conn->prepare("SELECT image FROM pets WHERE id = ?");
$stmt->bind_param("i", $pet_id);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows > 0) {
    $pet = $result->fetch_assoc();
    $image_to_delete = $pet['image'];
    if ($image_to_delete != 'default_pet.png') {
        @unlink('uploads/' . $image_to_delete);
    }
}

// ลบข้อมูลจากฐานข้อมูล
$delete_stmt = $conn->prepare("DELETE FROM pets WHERE id = ?");
$delete_stmt->bind_param("i", $pet_id);

if ($delete_stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Pet deleted successfully']);
} else {
    echo json_encode(['status' => 'fail', 'message' => 'Failed to delete pet']);
}

$delete_stmt->close();
$conn->close();
?>