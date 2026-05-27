<?php
// === request_password_reset.php (ฉบับสมบูรณ์และถูกต้องที่สุด) ===

// เรียกใช้งาน PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// เรียกไฟล์ Autoload ของ Composer ที่ติดตั้ง PHPMailer ไว้
require 'vendor/autoload.php';
// เรียกไฟล์เชื่อมต่อฐานข้อมูล
include 'connect.php';

// รับข้อมูล JSON จาก Flutter
$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';

// ตรวจสอบว่ามีอีเมลส่งมาหรือไม่
if (empty($email)) {
    http_response_code(400);
    echo json_encode(['status' => 'fail', 'message' => 'กรุณากรอกอีเมล']);
    exit;
}

// ตรวจสอบว่าอีเมลนี้มีอยู่ในระบบหรือไม่
$stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // ตอบกลับแบบสำเร็จเสมอเพื่อป้องกันการเดาอีเมลในระบบ
    echo json_encode(['status' => 'success', 'message' => 'หากอีเมลนี้อยู่ในระบบ เราได้ส่งคำแนะนำในการรีเซ็ตรหัสผ่านไปให้แล้ว']);
    exit;
}
$user = $result->fetch_assoc();
$username = $user['username'];
$stmt->close();


$six_digit_token_for_verification = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
$hashed_verification_token = hash('sha256', $six_digit_token_for_verification);


// สร้าง object PHPMailer
$mail = new PHPMailer(true);

try {
    // --- 1. ตั้งค่า Server เป็นของ SendGrid ---
    $mail->isSMTP();
    $mail->Host       = 'smtp.sendgrid.net';  // ใช้ Host ของ SendGrid
    $mail->SMTPAuth   = true;
    $mail->Username   = 'apikey';             // Username คือคำว่า 'apikey'
    $mail->Password   = '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'; // Password คือ API Key ที่คุณสร้าง
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // --- 2. ตั้งค่าผู้ส่งและผู้รับ ---
    // ใช้อีเมลที่คุณยืนยันไว้กับ SendGrid
$mail->setFrom('earthsiwakon@gmail.com', 'Pet World App');

$mail->addAddress($email, $username);

    // --- 3. ตั้งค่าเนื้อหาอีเมล ---
    $mail->isHTML(true);
    $mail->CharSet = "UTF-8";
    $mail->Subject = 'คำขอรีเซ็ตรหัสผ่านสำหรับ Pet World';
    $mail->Body    = "
        สวัสดีคุณ $username,
        <br><br>
        เราได้รับคำขอรีเซ็ตรหัสผ่านสำหรับบัญชีของคุณ
        กรุณาคัดลอกรหัส 6 หลักด้านล่างนี้เพื่อนำไปใช้ยืนยันตัวตนในแอปพลิเคชัน:
        <br><br>
        <h3><b>" . $six_digit_token_for_verification . "</b></h3> 
        <br>
        รหัสนี้จะหมดอายุใน 10 นาที
        <br><br>
        หากคุณไม่ได้เป็นคนร้องขอ กรุณาเพิกเฉยต่ออีเมลนี้
        <br><br>
        ขอบคุณครับ,<br>
        ทีมงาน Pet World
    ";
    $mail->AltBody = 'รหัสสำหรับรีเซ็ตรหัสผ่านของคุณคือ: ' . $six_digit_token_for_verification;

    // --- 4. สั่งให้ส่งอีเมล ---
    $mail->send();

    // --- 5. บันทึก Hashed Token ลงฐานข้อมูล (ทำหลังจากส่งเมลสำเร็จ) ---
    $conn->begin_transaction();
    
    $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
    $delete_stmt->bind_param("s", $email);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    $insert_stmt = $conn->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
    $insert_stmt->bind_param("ss", $email, $hashed_verification_token);
    $insert_stmt->execute();
    $insert_stmt->close();

    $conn->commit();

    echo json_encode(['status' => 'success', 'message' => 'ส่งคำขอสำเร็จแล้ว กรุณาตรวจสอบอีเมลของคุณ']);

} catch (Exception $e) {
    $conn->rollback(); // ยกเลิกการเปลี่ยนแปลงใน DB ถ้าส่งเมลไม่สำเร็จ
    echo json_encode(['status' => 'fail', 'message' => "ไม่สามารถส่งอีเมลได้. Mailer Error: {$mail->ErrorInfo}"]);
}

$conn->close();
exit;

?>