<?php
// save_progress.php
require_once 'config.php';
checkLogin();
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
try {
    // Log incoming request
    $rawData = file_get_contents('php://input');
    error_log("Received data: " . $rawData);
    // Get POST data
    $data = json_decode($rawData, true);
    if (!$data) {
        throw new Exception('Invalid input data: ' . json_last_error_msg());
    }
    // Validate required fields
    $required_fields = ['lesson_id', 'language', 'score'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    // Debug log
    error_log("Processing progress for user_id: " . $_SESSION['user_id'] . 
              ", lesson_id: " . $data['lesson_id'] . 
              ", language: " . $data['language']);
    // Check if progress already exists
    $checkStmt = $pdo->prepare("SELECT id FROM progress 
                               WHERE user_id = ? AND lesson_id = ? AND language = ?");
    $checkStmt->execute([
        $_SESSION['user_id'],
        $data['lesson_id'],
        $data['language']
    ]);
    $existingProgress = $checkStmt->fetch();
    try {
        if ($existingProgress) {
            // Update existing progress
            $stmt = $pdo->prepare("UPDATE progress 
                                  SET score = ?, completed = 1, completed_at = CURRENT_TIMESTAMP
                                  WHERE user_id = ? AND lesson_id = ? AND language = ?");
            $result = $stmt->execute([
                $data['score'],
                $_SESSION['user_id'],
                $data['lesson_id'],
                $data['language']
            ]);
            error_log("Updated existing progress record");
        } else {
            // Insert new progress
            $stmt = $pdo->prepare("INSERT INTO progress 
                                  (user_id, language, lesson_id, score, completed)
                                  VALUES (?, ?, ?, ?, 1)");
            $result = $stmt->execute([
                $_SESSION['user_id'],
                $data['language'],
                $data['lesson_id'],
                $data['score']
            ]);
            error_log("Inserted new progress record");
        }
        if (!$result) {
            throw new Exception('Database operation failed: ' . implode(" ", $stmt->errorInfo()));
        }
        echo json_encode([
            'success' => true,
            'message' => 'Progress saved successfully',
            'debug' => [
                'user_id' => $_SESSION['user_id'],
                'lesson_id' => $data['lesson_id'],
                'language' => $data['language'],
                'operation' => $existingProgress ? 'update' : 'insert'
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        throw new Exception('Database error: ' . $e->getMessage());
    }
} catch (Exception $e) {
    error_log("Error in save_progress.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => isset($data) ? $data : null
    ]);
}