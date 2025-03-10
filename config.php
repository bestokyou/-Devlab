<?php
// config.php
$db_host = 'localhost';
$db_name = 'coding_tutorial';
$db_user = 'root';
$db_pass = '';
//$db_host = 'localhost';
//$db_name = 'u587676424_coding_tutoria';
//$db_user = 'u587676424_coding_tutoria';
//$db_pass = 'vP1jCC59:X2';
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
// แก้ไขในไฟล์ config.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
function checkRememberToken() {
    global $pdo;
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE remember_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            return true;
        }
    }
    return false;
}
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        if (!checkRememberToken()) {
            header('Location: login.php');
            exit();
        }
    }
}