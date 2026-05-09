<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Create new game (generate game PIN)
if ($method === 'POST' && $action === 'create_game') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['quiz_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Quiz ID required']);
        exit;
    }
    
    $quiz_id = $data['quiz_id'];
    
    // Generate unique 6-digit game PIN
    $game_pin = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Check if PIN exists, regenerate if needed
    $stmt = $pdo->prepare("SELECT id FROM games WHERE game_pin = ?");
    $stmt->execute([$game_pin]);
    while ($stmt->fetch()) {
        $game_pin = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $stmt->execute([$game_pin]);
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO games (quiz_id, game_pin, status) VALUES (?, ?, 'waiting')");
        $stmt->execute([$quiz_id, $game_pin]);
        
        $game_id = $pdo->lastInsertId();
        
        echo json_encode([
            'message' => 'Game created',
            'game_id' => $game_id,
            'game_pin' => $game_pin
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create game']);
    }
    exit;
}

// Join game (player enters nickname)
if ($method === 'POST' && $action === 'join_game') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['game_pin']) || !isset($data['nickname'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Game PIN and nickname required']);
        exit;
    }
    
    $game_pin = $data['game_pin'];
    $nickname = trim($data['nickname']);
    
    // Find game by PIN
    $stmt = $pdo->prepare("SELECT * FROM games WHERE game_pin = ? AND status != 'finished'");
    $stmt->execute([$game_pin]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        http_response_code(404);
        echo json_encode(['error' => 'Game not found or already finished']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO players (game_id, nickname) VALUES (?, ?)");
        $stmt->execute([$game['id'], $nickname]);
        
        $player_id = $pdo->lastInsertId();
        
        echo json_encode([
            'message' => 'Joined game',
            'player_id' => $player_id,
            'game_id' => $game['id'],
            'nickname' => $nickname
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to join game']);
    }
    exit;
}

// Get game status (for polling)
if ($method === 'GET' && $action === 'game_status') {
    $game_id = $_GET['game_id'] ?? null;
    
    if (!$game_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Game ID required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        http_response_code(404);
        echo json_encode(['error' => 'Game not found']);
        exit;
    }
    
    // Get players
    $stmt = $pdo->prepare("SELECT * FROM players WHERE game_id = ? ORDER BY score DESC");
    $stmt->execute([$game_id]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get quiz info
    $stmt = $pdo->prepare("SELECT id, title FROM quizzes WHERE id = ?");
    $stmt->execute([$game['quiz_id']]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current question with correct answer if answer is shown
    $current_question_data = null;
    if ($game['status'] === 'active' && $game['current_question'] > 0) {
        $stmt = $pdo->prepare("SELECT quiz_id FROM games WHERE id = ?");
        $stmt->execute([$game_id]);
        $game_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id LIMIT 1 OFFSET " . (int)($game['current_question'] - 1));
        $stmt->execute([$game_data['quiz_id']]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($question) {
            // Always include correct answer for players to check after submission
            $stmt = $pdo->prepare("SELECT id, answer_text, is_correct FROM answers WHERE question_id = ?");
            $stmt->execute([$question['id']]);
            $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $question['answers'] = $answers;
            $current_question_data = $question;
        }
    }
    
    echo json_encode([
        'game' => $game,
        'players' => $players,
        'quiz' => $quiz,
        'current_question' => $current_question_data
    ]);
    exit;
}

// Start game (host starts)
if ($method === 'POST' && $action === 'start_game') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['game_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Game ID required']);
        exit;
    }
    
    $game_id = $data['game_id'];
    
    $stmt = $pdo->prepare("UPDATE games SET status = 'active', started_at = NOW(), current_question = 1 WHERE id = ?");
    $stmt->execute([$game_id]);
    
    echo json_encode(['message' => 'Game started']);
    exit;
}

// Get current question
if ($method === 'GET' && $action === 'current_question') {
    $game_id = $_GET['game_id'] ?? null;
    
    if (!$game_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Game ID required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT current_question FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        http_response_code(404);
        echo json_encode(['error' => 'Game not found']);
        exit;
    }
    
    // Get quiz ID
    $stmt = $pdo->prepare("SELECT quiz_id FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get question
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id LIMIT 1 OFFSET " . (int)($game['current_question'] - 1));
    $stmt->execute([$game_data['quiz_id']]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$question) {
        echo json_encode(['finished' => true]);
        exit;
    }
    
    // Get answers (include is_correct for host, hide for players)
    $include_correct = $_GET['include_correct'] ?? 'false';
    if ($include_correct === 'true') {
        $stmt = $pdo->prepare("SELECT id, answer_text, is_correct FROM answers WHERE question_id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT id, answer_text FROM answers WHERE question_id = ?");
    }
    $stmt->execute([$question['id']]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $question['answers'] = $answers;
    
    echo json_encode([
        'question' => $question,
        'question_number' => $game['current_question']
    ]);
    exit;
}

// Submit answer
if ($method === 'POST' && $action === 'submit_answer') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['game_id']) || !isset($data['player_id']) || !isset($data['answer_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    $game_id = $data['game_id'];
    $player_id = $data['player_id'];
    $answer_id = $data['answer_id'];
    $points_earned = isset($data['points_earned']) ? (int)$data['points_earned'] : 0;
    
    // Get current question
    $stmt = $pdo->prepare("SELECT current_question FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT quiz_id FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id LIMIT 1 OFFSET " . (int)($game['current_question'] - 1));
    $stmt->execute([$game_data['quiz_id']]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$question) {
        http_response_code(400);
        echo json_encode(['error' => 'No active question']);
        exit;
    }
    
    // Check if answer is correct
    $stmt = $pdo->prepare("SELECT is_correct FROM answers WHERE id = ? AND question_id = ?");
    $stmt->execute([$answer_id, $question['id']]);
    $answer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $is_correct = $answer ? $answer['is_correct'] : 0;
    
    // Use points from frontend (time-based calculation) if answer is correct
    // If answer is wrong or timeout, points_earned will be 0 or the minimum
    $final_points = ($is_correct && $points_earned > 0) ? $points_earned : 0;
    
    // Save player answer
    $stmt = $pdo->prepare("INSERT INTO player_answers (game_id, player_id, question_id, answer_id, is_correct, points_earned) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$game_id, $player_id, $question['id'], $answer_id, $is_correct, $final_points]);
    
    // Update player score
    if ($final_points > 0) {
        $stmt = $pdo->prepare("UPDATE players SET score = score + ? WHERE id = ?");
        $stmt->execute([$final_points, $player_id]);
    }
    
    echo json_encode([
        'is_correct' => $is_correct,
        'points_earned' => $final_points
    ]);
    exit;
}

// Next question (host advances)
if ($method === 'POST' && $action === 'next_question') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['game_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Game ID required']);
        exit;
    }
    
    $game_id = $data['game_id'];
    
    // Get total questions
    $stmt = $pdo->prepare("SELECT quiz_id FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM questions WHERE quiz_id = ?");
    $stmt->execute([$game['quiz_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT current_question FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $next_question = $game_data['current_question'] + 1;
    
    if ($next_question > $result['total']) {
        // Game finished
        $stmt = $pdo->prepare("UPDATE games SET status = 'finished', ended_at = NOW() WHERE id = ?");
        $stmt->execute([$game_id]);
        
        echo json_encode(['finished' => true]);
    } else {
        $stmt = $pdo->prepare("UPDATE games SET current_question = ?, answer_shown = 0 WHERE id = ?");
        $stmt->execute([$next_question, $game_id]);
        
        echo json_encode(['finished' => false, 'next_question' => $next_question]);
    }
    exit;
}

// Get answer distribution (for host to see live results)
if ($method === 'GET' && $action === 'answer_distribution') {
    $game_id = $_GET['game_id'] ?? null;
    
    if (!$game_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Game ID required']);
        exit;
    }
    
    // Get current question number
    $stmt = $pdo->prepare("SELECT current_question, quiz_id FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        http_response_code(404);
        echo json_encode(['error' => 'Game not found']);
        exit;
    }
    
    // Get current question
    $offset = (int)($game['current_question'] - 1);
    $stmt = $pdo->prepare("SELECT id FROM questions WHERE quiz_id = ? ORDER BY id LIMIT 1 OFFSET " . $offset);
    $stmt->execute([$game['quiz_id']]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$question) {
        echo json_encode(['distribution' => []]);
        exit;
    }
    
    // Get all answers for this question
    $stmt = $pdo->prepare("SELECT id, answer_text FROM answers WHERE question_id = ?");
    $stmt->execute([$question['id']]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get answer counts
    $distribution = [];
    $total_answered = 0;
    
    foreach ($answers as $answer) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM player_answers WHERE game_id = ? AND question_id = ? AND answer_id = ?");
        $stmt->execute([$game_id, $question['id'], $answer['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $distribution[] = [
            'answer_id' => $answer['id'],
            'answer_text' => $answer['answer_text'],
            'count' => (int)$result['count']
        ];
        $total_answered += (int)$result['count'];
    }
    
    // Get total players in game
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM players WHERE game_id = ?");
    $stmt->execute([$game_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_players = (int)$result['total'];
    
    echo json_encode([
        'distribution' => $distribution,
        'total_answered' => $total_answered,
        'total_players' => $total_players
    ]);
    exit;
}

// Get leaderboard
if ($method === 'GET' && $action === 'leaderboard') {
    $game_id = $_GET['game_id'] ?? null;
    
    if (!$game_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Game ID required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT nickname, score FROM players WHERE game_id = ? ORDER BY score DESC");
    $stmt->execute([$game_id]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($players);
    exit;
}

// Show answer (host reveals answer to all players)
if ($method === 'POST' && $action === 'show_answer') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['game_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Game ID required']);
        exit;
    }
    
    $game_id = $data['game_id'];
    
    $stmt = $pdo->prepare("UPDATE games SET answer_shown = 1 WHERE id = ?");
    $stmt->execute([$game_id]);
    
    echo json_encode(['message' => 'Answer shown']);
    exit;
}

// Get game results
if ($method === 'GET' && $action === 'game_results') {
    $game_id = $_GET['game_id'] ?? null;
    
    if (!$game_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Game ID required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT nickname, score FROM players WHERE game_id = ? ORDER BY score DESC");
    $stmt->execute([$game_id]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'game' => $game,
        'players' => $players
    ]);
    exit;
}

// End game (host exits)
if ($method === 'POST' && $action === 'end_game') {
    $data = json_decode(file_get_contents('php://input'), true);
    $game_id = $data['game_id'] ?? null;
    
    if (!$game_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Game ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE games SET status = 'finished', ended_at = NOW() WHERE id = ?");
        $stmt->execute([$game_id]);
        
        echo json_encode(['message' => 'Game ended']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to end game']);
    }
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);