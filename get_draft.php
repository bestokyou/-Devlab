<?php
require_once 'config.php';
checkLogin();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = $_SESSION['user_id'];
    $language = $_GET['language'];
    $lesson_id = (int)$_GET['lesson_id'];
    $stmt = $pdo->prepare("
        SELECT draft_code 
        FROM lesson_drafts 
        WHERE user_id = ? AND language = ? AND lesson_id = ?
    ");
    $stmt->execute([$user_id, $language, $lesson_id]);
    $result = $stmt->fetch();
    if ($result) {
        echo json_encode([
            'success' => true,
            'draft_code' => $result['draft_code']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No draft found'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>