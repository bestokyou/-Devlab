<?php
// logout.php
require_once 'config.php';
// ลบ remember token จากฐานข้อมูล (ต้องทำก่อนที่จะล้าง session)
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}
// ลบ remember token cookie
setcookie('remember_token', '', time() - 3600, '/');
// ล้างค่าทั้งหมดใน session
session_unset();
// ทำลาย session
session_destroy();
// ล้าง session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
// redirect กลับไปหน้า login
header('Location: login.php');
exit();
?>