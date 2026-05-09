<?php
// filepath: api/admin/dashboard.php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get dashboard stats
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'stats') {
        try {
            // Get sections count
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM sections");
            $sectionsCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get admin users count
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin");
            $usersCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get students count from the students table
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
            $studentsCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get storyboarding count (from quizzes table - published quizzes as storyboards)
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM quizzes WHERE is_published = 1");
            $storyboardingCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get recent activity from games table with players
            $stmt = $pdo->query("
                SELECT g.id, g.game_pin, g.status, g.started_at, g.ended_at, g.current_question, 
                       q.title as quiz_title,
                       (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) as total_questions,
                       GROUP_CONCAT(p.nickname ORDER BY p.score DESC SEPARATOR ', ') as players
                FROM games g
                JOIN quizzes q ON g.quiz_id = q.id
                LEFT JOIN players p ON p.game_id = g.id
                GROUP BY g.id
                ORDER BY g.started_at DESC
                LIMIT 10
            ");
            $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'storyboarding' => (int)$storyboardingCount,
                    'students' => (int)$studentsCount,
                    'sections' => (int)$sectionsCount,
                    'users' => (int)$usersCount
                ],
                'recent_activity' => $recentActivity
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    if ($action === 'growth_tracking') {
        try {
            // Get growth tracking data: pre-test and post-test scores for each student
            $stmt = $pdo->query("
                SELECT 
                    s.name as student_name,
                    pt.pre_test_score,
                    pot.post_test_score
                FROM students s
                LEFT JOIN pre_test pt ON s.id = pt.student_id
                LEFT JOIN post_test pot ON s.id = pot.student_id
                WHERE pt.pre_test_score IS NOT NULL OR pot.post_test_score IS NOT NULL
                ORDER BY s.name
            ");
            $growthData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'growth_data' => $growthData
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    if ($action === 'storyboards') {
        try {
            $stmt = $pdo->query(
                "SELECT
                    s.id AS storyboard_id,
                    st.id AS student_id,
                    st.name AS student_name,
                    s.section_id,
                    sec.name AS section_name,
                    s.accomplished_items,
                    s.score,
                    s.created_at AS updated_at
                FROM storyboard s
                JOIN students st ON st.id = s.student_id
                JOIN sections sec ON sec.id = s.section_id
                ORDER BY CAST(SUBSTRING_INDEX(s.accomplished_items, '/', 1) AS UNSIGNED) DESC,
                         CAST(SUBSTRING_INDEX(s.score, '/', 1) AS UNSIGNED) DESC"
            );
            $storyboards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'storyboards' => $storyboards
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    // ── NEW: Full per-question detail for the gallery modal ──────────────
    if ($action === 'storyboard_detail') {
        $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
        $sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

        if (!$studentId || !$sectionId) {
            echo json_encode(['success' => false, 'error' => 'student_id and section_id are required']);
            exit;
        }

        try {
            // Summary row from storyboard
            $stmt = $pdo->prepare(
                "SELECT s.accomplished_items, s.score, s.image, s.updated_at,
                        st.name AS student_name, sec.name AS section_name
                 FROM storyboard s
                 JOIN students  st  ON st.id  = s.student_id
                 JOIN sections  sec ON sec.id = s.section_id
                 WHERE s.student_id = ? AND s.section_id = ?
                 LIMIT 1"
            );
            $stmt->execute([$studentId, $sectionId]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$summary) {
                echo json_encode(['success' => false, 'error' => 'No storyboard found for this student/section']);
                exit;
            }

            // Progress detail from storyboard_progress
            $stmt2 = $pdo->prepare(
                "SELECT answers, step_images, submitted, scores, current_step, unlocked_step, saved_at
                 FROM storyboard_progress
                 WHERE student_id = ? AND section_id = ?
                 LIMIT 1"
            );
            $stmt2->execute([$studentId, $sectionId]);
            $progress = $stmt2->fetch(PDO::FETCH_ASSOC);

            // Decode JSON columns safely
            $decodeCol = function($raw) {
                if (!$raw) return [];
                $decoded = json_decode($raw, true);
                return is_array($decoded) ? $decoded : [];
            };

            echo json_encode([
                'success'      => true,
                'summary'      => $summary,
                'answers'      => $decodeCol($progress['answers']     ?? null),
                'step_images'  => $decodeCol($progress['step_images'] ?? null),
                'submitted'    => $decodeCol($progress['submitted']   ?? null),
                'scores'       => $decodeCol($progress['scores']      ?? null),
                'current_step' => (int)($progress['current_step']  ?? 0),
                'unlocked_step'=> (int)($progress['unlocked_step'] ?? 0),
                'saved_at'     => $progress['saved_at'] ?? null
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'post_test_results') {
        try {
            // Get post-test results with student and section information
            $stmt = $pdo->query("
                SELECT 
                    s.name as student_name,
                    sec.name as section_name,
                    pt.post_test_score,
                    pt.time_consumed,
                    pt.created_at
                FROM post_test pt
                JOIN students s ON pt.student_id = s.id
                JOIN sections sec ON pt.section_id = sec.id
                ORDER BY CAST(SUBSTRING_INDEX(pt.post_test_score, '/', 1) AS UNSIGNED) DESC,
                         TIME_TO_SEC(STR_TO_DATE(pt.time_consumed, '%i:%s')) ASC, pt.created_at ASC
            ");
            $postTestData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'post_test_results' => $postTestData
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    if ($action === 'pre_test_results') {
        try {
            // Get pre-test results with student and section information
            $stmt = $pdo->query("
                SELECT 
                    s.name as student_name,
                    sec.name as section_name,
                    pt.pre_test_score,
                    pt.time_consumed,
                    pt.created_at
                FROM pre_test pt
                JOIN students s ON pt.student_id = s.id
                JOIN sections sec ON pt.section_id = sec.id
                ORDER BY CAST(SUBSTRING_INDEX(pt.pre_test_score, '/', 1) AS UNSIGNED) DESC,
                         TIME_TO_SEC(STR_TO_DATE(pt.time_consumed, '%i:%s')) ASC, pt.created_at ASC
            ");
            $preTestData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'pre_test_results' => $preTestData
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    // Default: return stats
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}