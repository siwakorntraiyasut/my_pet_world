<?php
require_once 'connect.php';

// รับ Input จาก Flutter
$input = json_decode(file_get_contents('php://input'), true);
$current_user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;

if ($current_user_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'fail', 'message' => 'Missing or invalid user_id']);
    exit;
}

// ✅✅✅ SQL Query ที่ปรับปรุงใหม่ ✅✅✅
$sql = "
    SELECT 
        p.id,
        p.user_id,
        p.content AS subtitle,
        p.image AS imagePath,
        p.created_at,
        u.username,
        u.profile_image AS profileImagePath,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) AS likes,

        -- ใช้วิธี EXISTS ทั้ง 2 ส่วนเพื่อความแม่นยำ --
        (EXISTS(SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?)) AS isLiked,
        (EXISTS(SELECT 1 FROM follows WHERE follower_id = ? AND following_id = p.user_id)) AS isFollowing

    FROM 
        posts p
    JOIN 
        users u ON p.user_id = u.id
    ORDER BY 
        p.created_at DESC
";

// การใช้ Prepared Statement
$stmt = $conn->prepare($sql);
// ส่ง current_user_id เข้าไปใน ? ทั้งสองตัว
$stmt->bind_param("ii", $current_user_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$posts = [];
// คุณสามารถเปลี่ยน IP นี้ให้ตรงกับเครื่องของคุณได้
$baseImageUrl = "http://192.168.1.52/my_pet_world_api/uploads/"; 

while ($row = $result->fetch_assoc()) {
    // จัดการเรื่อง Path ของรูปภาพ (เหมือนเดิม)
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

    // แปลงชนิดข้อมูลให้ถูกต้อง (สำคัญมาก)
    $row['isLiked'] = boolval($row['isLiked']);
    $row['isFollowing'] = boolval($row['isFollowing']);
    $row['likes'] = intval($row['likes']);
    $row['id'] = intval($row['id']);
    $row['user_id'] = intval($row['user_id']);

    $posts[] = $row;
}

// ส่งผลลัพธ์กลับ
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'posts' => $posts
], JSON_UNESCAPED_SLASHES);

// ปิดการเชื่อมต่อ
$stmt->close();
$conn->close();
?>