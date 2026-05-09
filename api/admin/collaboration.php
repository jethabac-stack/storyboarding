<?php
// filepath: api/admin/collaboration.php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get all games with player scores (collaboration data)
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'leaderboard') {
        // Get all games with their player scores
        // Sort by id DESC so newest game appears as Game 1
        $stmt = $pdo->query("
            SELECT 
                g.id as game_id,
                g.game_pin,
                g.status,
                g.current_question,
                g.started_at,
                q.id as quiz_id,
                q.title as quiz_title
            FROM games g
            LEFT JOIN quizzes q ON g.quiz_id = q.id
            ORDER BY g.id ASC
        ");
        $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get players for each game with total possible points
        foreach ($games as &$game) {
            // Get total possible points from questions for this quiz
            $game['total_points'] = 0;
            $game['total_questions'] = 0;
            
            if (!empty($game['quiz_id'])) {
                $stmt = $pdo->prepare("SELECT SUM(points) as total_points, COUNT(*) as total_questions FROM questions WHERE quiz_id = ?");
                $stmt->execute([$game['quiz_id']]);
                $pointsData = $stmt->fetch(PDO::FETCH_ASSOC);
                $game['total_points'] = $pointsData['total_points'] ?? 0;
                $game['total_questions'] = $pointsData['total_questions'] ?? 0;
            }
            
            // Get players (current_question is in games table, not players)
            $stmt = $pdo->prepare("
                SELECT id, nickname, score, joined_at 
                FROM players 
                WHERE game_id = ? 
                ORDER BY score DESC
            ");
            $stmt->execute([$game['game_id']]);
            $game['players'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode([
            'success' => true,
            'games' => $games
        ]);
        exit;
    }
    
    if ($action === 'by_quiz') {
        // Get collaboration data grouped by quiz
        $stmt = $pdo->query("
            SELECT 
                q.id as quiz_id,
                q.title as quiz_title,
                COUNT(DISTINCT g.id) as game_count,
                COUNT(DISTINCT p.id) as total_players,
                SUM(p.score) as total_score,
                AVG(p.score) as avg_score
            FROM quizzes q
            LEFT JOIN games g ON g.quiz_id = q.id
            LEFT JOIN players p ON p.game_id = g.id
            GROUP BY q.id
            ORDER BY q.id
        ");
        $quizStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'quiz_stats' => $quizStats
        ]);
        exit;
    }
    
    // Default: get all collaboration data
    $stmt = $pdo->query("
        SELECT 
            g.id as game_id,
            g.game_pin,
            g.status,
            g.started_at,
            g.ended_at,
            q.id as quiz_id,
            q.title as quiz_title
        FROM games g
        LEFT JOIN quizzes q ON g.quiz_id = q.id
        WHERE g.status = 'finished'
        ORDER BY g.ended_at DESC
    ");
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get players for each game
    foreach ($games as &$game) {
        $stmt = $pdo->prepare("
            SELECT id, nickname, score, joined_at 
            FROM players 
            WHERE game_id = ? 
            ORDER BY score DESC
        ");
        $stmt->execute([$game['game_id']]);
        $game['players'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'games' => $games
    ]);
    exit;
}

// If method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);