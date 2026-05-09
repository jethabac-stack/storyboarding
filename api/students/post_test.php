<?php
// filepath: api/students/post_test.php
require_once '../admin/db.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = isset($input['student_id']) ? intval($input['student_id']) : 0;
    $sectionId = isset($input['section_id']) ? intval($input['section_id']) : 0;
    $score = $input['score'] ?? '';
    $timeConsumed = $input['time_consumed'] ?? '';

    // Debug log
    error_log("Post-test save: student_id=$studentId, section_id=$sectionId, score=$score, time=$timeConsumed");

    // Validation
    if (empty($studentId) || empty($sectionId) || empty($score) || empty($timeConsumed)) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required', 'debug' => [
            'student_id' => $studentId,
            'section_id' => $sectionId,
            'score' => $score,
            'time_consumed' => $timeConsumed
        ]]);
        exit;
    }

    // Check if student already has a post-test record
    $checkStmt = $pdo->prepare("SELECT id FROM post_test WHERE student_id = ?");
    $checkStmt->execute([$studentId]);
    $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingRecord) {
        // Update existing record
        $stmt = $pdo->prepare("UPDATE post_test SET post_test_score = ?, time_consumed = ? WHERE student_id = ?");
        $stmt->execute([$score, $timeConsumed, $studentId]);
    } else {
        // Insert new record
        try {
            $stmt = $pdo->prepare("INSERT INTO post_test (student_id, section_id, post_test_score, time_consumed) VALUES (?, ?, ?, ?)");
            $stmt->execute([$studentId, $sectionId, $score, $timeConsumed]);
        } catch (PDOException $e) {
            error_log("Post-test insert error: " . $e->getMessage());
            http_response_code(400);
            echo json_encode(['error' => 'Failed to save post-test. Please verify student and section exist.']);
            exit;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Post-test results saved successfully'
    ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}