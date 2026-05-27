<?php
// =================================================================
// File: get_user_details.php (เวอร์ชันสมบูรณ์และเรียงลำดับถูกต้อง)
// =================================================================

header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');
require_once 'connect.php';

// --- ค่าคงที่และ Input ---
$baseImageUrl = "http://192.168.1.52/my_pet_world_api/uploads/";
$username = isset($_GET['username']) ? $_GET['username'] : '';
$current_user_id = isset($_GET['current_user_id']) ? intval($_GET['current_user_id']) : 0;

if (empty($username)) {
    http_response_code(400);
    echo json_encode(['status' => 'fail', 'message' => 'Username is required']);
    exit;
}

// --- 1. ดึงข้อมูลโปรไฟล์ผู้ใช้เป้าหมาย ---
$user_query = "
    SELECT 
        u.id, u.username, u.email, u.profile_image, u.updated_at,
        (SELECT COUNT(*) FROM follows WHERE following_id = u.id) as follower_count,
        (SELECT COUNT(*) FROM follows WHERE follower_id = u.id) as following_count
    FROM users u 
    WHERE u.username = ?
";
$stmt_user = $conn->prepare($user_query);
$stmt_user->bind_param("s", $username);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user->num_rows == 0) {
    http_response_code(404);
    echo json_encode(['status' => 'fail', 'message' => 'User not found']);
    exit;
}
$user_profile = $result_user->fetch_assoc();
$profile_user_id = intval($user_profile['id']);

// ปรับ path รูปโปรไฟล์ให้มีเวอร์ชัน (Cache Busting)
$profile_image = trim($user_profile['profile_image'] ?? '');
if (!empty($profile_image) && !preg_match('/^https?:\/\//', $profile_image)) {
    $version = strtotime($user_profile['updated_at']);
    $user_profile['profile_image'] = $baseImageUrl . $profile_image . '?v=' . $version;
} elseif (empty($profile_image)) {
    $user_profile['profile_image'] = $baseImageUrl . 'default.png';
}


// ✅✅✅ ย้ายส่วนที่ 2, 3, 4 มาไว้ตรงนี้ ก่อนที่จะ echo ✅✅✅

// --- 2. เช็คสถานะการติดตาม ---
$is_following = false;
if ($current_user_id > 0 && $current_user_id != $profile_user_id) {
    $follow_query = "SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ? LIMIT 1";
    $stmt_follow = $conn->prepare($follow_query);
    $stmt_follow->bind_param("ii", $current_user_id, $profile_user_id);
    $stmt_follow->execute();
    $is_following = $stmt_follow->get_result()->num_rows > 0;
    $stmt_follow->close();
}

// --- 3. ดึงโพสต์ทั้งหมดของ User คนนี้ (เรียงจากใหม่ไปเก่า) ---
$posts = [];
$posts_query = "
    SELECT 
        p.id, p.user_id, p.content AS subtitle, p.image AS imagePath, p.created_at,
        u.username, u.profile_image AS profileImagePath,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) AS likes,
        (EXISTS(SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?)) AS isLiked,
        (EXISTS(SELECT 1 FROM follows WHERE follower_id = ? AND following_id = p.user_id)) AS isFollowing
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
";
$stmt_posts = $conn->prepare($posts_query);
$stmt_posts->bind_param("iii", $current_user_id, $current_user_id, $profile_user_id);
$stmt_posts->execute();
$result_posts = $stmt_posts->get_result();

while ($row = $result_posts->fetch_assoc()) {
    $row['imagePath'] = trim($row['imagePath'] ?? '');
    $row['profileImagePath'] = trim($row['profileImagePath'] ?? '');
    
    if (!empty($row['imagePath']) && !preg_match('/^https?:\/\//', $row['imagePath'])) {
        $row['imagePath'] = $baseImageUrl . $row['imagePath'];
    }
    if (!empty($row['profileImagePath']) && !preg_match('/^https?:\/\//', $row['profileImagePath'])) {
        $row['profileImagePath'] = $baseImageUrl . $row['profileImagePath'];
    } elseif (empty($row['profileImagePath'])) {
        $row['profileImagePath'] = $baseImageUrl . 'default.png';
    }

    $row['isLiked'] = (bool) $row['isLiked'];
    $row['isFollowing'] = (bool) $row['isFollowing'];
    $row['likes'] = intval($row['likes']);
    $row['id'] = intval($row['id']);
    $row['user_id'] = intval($row['user_id']);
    $posts[] = $row;
}
$stmt_posts->close();

// --- 4. ดึงข้อมูลสัตว์เลี้ยงของ User นี้ ---
$pets = [];
$pets_query = "SELECT id, name, gender, age, birthday, image FROM pets WHERE user_id = ?";
$stmt_pets = $conn->prepare($pets_query);
$stmt_pets->bind_param("i", $profile_user_id);
$stmt_pets->execute();
$result_pets = $stmt_pets->get_result();

while ($row = $result_pets->fetch_assoc()) {
    $image = trim($row['image'] ?? '');
    if (!empty($image) && !preg_match('/^https?:\/\//', $image)) {
        $row['image'] = $baseImageUrl . $image;
    } elseif (empty($image)) {
        $row['image'] = $baseImageUrl . 'default_pet.png';
    }
    $pets[] = $row;
}
$stmt_pets->close();


// --- 5. ส่งกลับข้อมูลทั้งหมดเป็น JSON (หลังจากเตรียมข้อมูลครบแล้ว) ---
echo json_encode([
    'status' => 'success',
    'profile' => $user_profile,
    'is_following' => $is_following,
    'posts' => $posts,
    'pets' => $pets
], JSON_UNESCAPED_SLASHES);


// --- 6. ปิดการเชื่อมต่อ ---
$stmt_user->close();
$conn->close();
?>