<?php
require_once 'config.php';
checkLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $_SESSION['user_id'];
    $language = $data['language'];
    $lesson_id = $data['lesson_id'];
    $draft_code = $data['code'];
    // Check if draft exists
    $stmt = $pdo->prepare("
        SELECT id FROM lesson_drafts 
        WHERE user_id = ? AND language = ? AND lesson_id = ?
    ");
    $stmt->execute([$user_id, $language, $lesson_id]);
    $existing = $stmt->fetch();
    if ($existing) {
        // Update existing draft
        $stmt = $pdo->prepare("
            UPDATE lesson_drafts 
            SET draft_code = ?, updated_at = NOW() 
            WHERE user_id = ? AND language = ? AND lesson_id = ?
        ");
        $stmt->execute([$draft_code, $user_id, $language, $lesson_id]);
    } else {
        // Create new draft
        $stmt = $pdo->prepare("
            INSERT INTO lesson_drafts (user_id, language, lesson_id, draft_code) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $language, $lesson_id, $draft_code]);
    }
    echo json_encode(['success' => true]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>