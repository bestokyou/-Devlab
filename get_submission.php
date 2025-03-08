<?php
// get_submission.php - API endpoint to get submission details
require_once 'config.php';
checkLogin();

header('Content-Type: application/json');

// Check if ID is provided
if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Submission ID is required']);
    exit;
}

$submissionId = (int)$_GET['id'];

// Check if the user is a teacher
$teacherStmt = $pdo->prepare("
    SELECT t.id FROM teachers t
    WHERE t.user_id = ?
");
$teacherStmt->execute([$_SESSION['user_id']]);
$teacher = $teacherStmt->fetch();

// Get the submission details
$stmt = $pdo->prepare("
    SELECT ws.*, 
           w.teacher_id,
           u.username as student_name,
           DATE_FORMAT(ws.submitted_at, '%d/%m/%Y %H:%i') as formatted_submitted_at,
           DATE_FORMAT(ws.graded_at, '%d/%m/%Y %H:%i') as formatted_graded_at
    FROM worksheet_submissions ws
    JOIN worksheets w ON ws.worksheet_id = w.id
    JOIN users u ON ws.user_id = u.id
    WHERE ws.id = ?
");
$stmt->execute([$submissionId]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission) {
    echo json_encode(['success' => false, 'message' => 'Submission not found']);
    exit;
}

// Check if the user is authorized to view this submission
$isAuthorized = false;

// Teachers can view submissions for their worksheets
if ($teacher && $submission['teacher_id'] == $teacher['id']) {
    $isAuthorized = true;
}

// Students can view their own submissions
if ($submission['user_id'] == $_SESSION['user_id']) {
    $isAuthorized = true;
}

if (!$isAuthorized) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Prepare response data
$response = [
    'success' => true,
    'id' => $submission['id'],
    'student_name' => $submission['student_name'],
    'code' => htmlspecialchars($submission['code']),
    'status' => $submission['status'],
    'submitted_at' => $submission['formatted_submitted_at'],
    'score' => $submission['score']
];

// Add grading information if available
if ($submission['status'] === 'graded') {
    $response['graded_at'] = $submission['formatted_graded_at'];
    $response['feedback'] = htmlspecialchars($submission['feedback'] ?? '');
}

echo json_encode($response);
?>