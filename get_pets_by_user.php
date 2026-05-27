<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
require_once 'connect.php';

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($userId <= 0) {
    echo json_encode(['status' => 'fail', 'message' => 'Missing or invalid user_id']);
    exit;
}

$baseImageUrl = "http://192.168.1.52/my_pet_world_api/";

$sql = "SELECT id, name, species, image, gender, age, birthday FROM pets WHERE user_id = $userId AND is_active = 1";
$result = $conn->query($sql);

$pets = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pets[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'type' => $row['species'],
            'image' => (strpos($row['image'], 'http') === 0) ? $row['image'] : $baseImageUrl . $row['image'],
            'gender' => $row['gender'],
            'age' => $row['age'],
            'birthday' => $row['birthday'],
        ];
    }
}

echo json_encode(['status' => 'success', 'pets' => $pets], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
