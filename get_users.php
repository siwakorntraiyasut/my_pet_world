<?php
header("Content-Type: application/json; charset=UTF-8");
require_once 'connect.php';

$baseImageUrl = "http://192.168.1.52/my_pet_world_api/uploads/";

$sql = "SELECT id, username, email, profile_image, follower_count, following_count FROM users";
$result = $conn->query($sql);

$users = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $profileImage = trim($row['profile_image'] ?? '');

        if (!preg_match('/^https?:\/\//', $profileImage) && $profileImage !== '') {
            $profileImage = $baseImageUrl . $profileImage;
        } elseif ($profileImage === '') {
            $profileImage = $baseImageUrl . 'default.png';
        }

        $row['profile_image'] = $profileImage;
        $users[] = $row;
    }
}

echo json_encode($users, JSON_UNESCAPED_SLASHES);
$conn->close();
?>
