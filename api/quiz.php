<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Create quiz
if ($method === 'POST' && $action === 'create_quiz') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['title']) || !isset($data['user_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    $title = trim($data['title']);
    $description = $data['description'] ?? '';
    $user_id = $data['user_id'];
    $time_limit = $data['time_limit'] ?? 20;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO quizzes (title, description, user_id, time_limit) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $description, $user_id, $time_limit]);
        
        echo json_encode(['message' => 'Quiz created', 'quiz_id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create quiz']);
    }
    exit;
}

// Get user's quizzes
if ($method === 'GET' && $action === 'get_quizzes') {
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($quizzes);
    exit;
}

// Get single quiz with questions
if ($method === 'GET' && $action === 'get_quiz') {
    $quiz_id = $_GET['quiz_id'] ?? null;
    
    if (!$quiz_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Quiz ID required']);
        exit;
    }
    
    // Get quiz
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quiz) {
        http_response_code(404);
        echo json_encode(['error' => 'Quiz not found']);
        exit;
    }
    
    // Get questions with answers
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($questions as &$question) {
        $stmt = $pdo->prepare("SELECT * FROM answers WHERE question_id = ?");
        $stmt->execute([$question['id']]);
        $question['answers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $quiz['questions'] = $questions;
    
    echo json_encode($quiz);
    exit;
}

// Update quiz
if ($method === 'PUT' && $action === 'update_quiz') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['quiz_id']) || !isset($data['title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    $quiz_id = $data['quiz_id'];
    $title = trim($data['title']);
    $description = $data['description'] ?? '';
    $time_limit = $data['time_limit'] ?? 20;
    $is_published = $data['is_published'] ?? 0;
    
    $stmt = $pdo->prepare("UPDATE quizzes SET title = ?, description = ?, time_limit = ?, is_published = ? WHERE id = ?");
    $stmt->execute([$title, $description, $time_limit, $is_published, $quiz_id]);
    
    echo json_encode(['message' => 'Quiz updated']);
    exit;
}

// Delete quiz
if ($method === 'DELETE' && $action === 'delete_quiz') {
    $quiz_id = $_GET['quiz_id'] ?? null;
    
    if (!$quiz_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Quiz ID required']);
        exit;
    }
    
    $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
    $stmt->execute([$quiz_id]);
    
    echo json_encode(['message' => 'Quiz deleted']);
    exit;
}

// Add question to quiz
if ($method === 'POST' && $action === 'add_question') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['quiz_id']) || !isset($data['question_text']) || !isset($data['answers'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    $quiz_id = $data['quiz_id'];
    $question_text = $data['question_text'];
    $question_image = $data['question_image'] ?? null;
    $time_limit = $data['time_limit'] ?? 20;
    $points = $data['points'] ?? 1000;
    $answers = $data['answers'];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, question_image, time_limit, points) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$quiz_id, $question_text, $question_image, $time_limit, $points]);
        $question_id = $pdo->lastInsertId();
        
        foreach ($answers as $answer) {
            $stmt = $pdo->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
            $stmt->execute([$question_id, $answer['text'], $answer['is_correct'] ? 1 : 0]);
        }
        
        $pdo->commit();
        
        echo json_encode(['message' => 'Question added', 'question_id' => $question_id]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add question']);
    }
    exit;
}

// Update question
if ($method === 'PUT' && $action === 'update_question') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['question_id']) || !isset($data['question_text'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    $question_id = $data['question_id'];
    $question_text = $data['question_text'];
    $question_image = $data['question_image'] ?? null;
    $time_limit = $data['time_limit'] ?? 20;
    $points = $data['points'] ?? 1000;
    $answers = $data['answers'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE questions SET question_text = ?, question_image = ?, time_limit = ?, points = ? WHERE id = ?");
        $stmt->execute([$question_text, $question_image, $time_limit, $points, $question_id]);
        
        // Delete old answers and add new ones
        $stmt = $pdo->prepare("DELETE FROM answers WHERE question_id = ?");
        $stmt->execute([$question_id]);
        
        foreach ($answers as $answer) {
            $stmt = $pdo->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
            $stmt->execute([$question_id, $answer['text'], $answer['is_correct'] ? 1 : 0]);
        }
        
        $pdo->commit();
        
        echo json_encode(['message' => 'Question updated']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update question']);
    }
    exit;
}

// Delete question
if ($method === 'DELETE' && $action === 'delete_question') {
    $question_id = $_GET['question_id'] ?? null;
    
    if (!$question_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Question ID required']);
        exit;
    }
    
    $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    
    echo json_encode(['message' => 'Question deleted']);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);