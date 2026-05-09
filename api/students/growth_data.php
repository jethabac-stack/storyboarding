<?php
// filepath: api/students/growth_data.php

// ── Bug 4 fix: CORS headers must appear before anything that can fail ──────
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../admin/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Optional: filter to a single student if student_id + section_id are provided
    $filterStudentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
    $filterSectionId = isset($_GET['section_id']) ? intval($_GET['section_id']) : null;

    // Build optional WHERE clause
    $where  = '';
    $params = [];
    if ($filterStudentId) {
        $where    = 'WHERE s.id = ?';
        $params[] = $filterStudentId;
    }

    // Main summary query — joins storyboard for accomplished_items + score
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.name,
            s.email,
            s.section_id,
            sec.name              AS section_name,
            s.grade,
            s.created_at,
            COALESCE(pt.post_test_score,    '') AS post_test_score,
            COALESCE(pt.time_consumed,      '') AS post_test_time,
            COALESCE(pre.pre_test_score,    '') AS pre_test_score,
            COALESCE(pre.time_consumed,     '') AS pre_test_time,
            COALESCE(sb.accomplished_items, '0/30') AS accomplished_items,
            COALESCE(sb.score,              '0/30') AS storyboard_score
        FROM students s
        LEFT JOIN sections   sec ON s.section_id = sec.id
        LEFT JOIN pre_test   pre ON s.id = pre.student_id
        LEFT JOIN post_test  pt  ON s.id = pt.student_id
        LEFT JOIN storyboard sb  ON s.id = sb.student_id
        $where
        ORDER BY s.id DESC
    ");
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse scores and build result array
    $result = array_map(function($student) {
        $preScore  = 0;
        $postScore = 0;

        if (!empty($student['pre_test_score']) && strpos($student['pre_test_score'], '/') !== false) {
            $parts    = explode('/', $student['pre_test_score']);
            $preScore = intval($parts[0]);
        }
        if (!empty($student['post_test_score']) && strpos($student['post_test_score'], '/') !== false) {
            $parts     = explode('/', $student['post_test_score']);
            $postScore = intval($parts[0]);
        }

        return [
            'id'                 => $student['id'],
            'name'               => $student['name'],
            'email'              => $student['email'],
            'section_id'         => $student['section_id'],
            'section_name'       => $student['section_name'] ?? '',
            'grade'              => $student['grade'],
            'pre_test_score'     => $student['pre_test_score'],
            'pre_test_time'      => $student['pre_test_time'],
            'post_test_score'    => $student['post_test_score'],
            'post_test_time'     => $student['post_test_time'],
            'pre_test_raw'       => $preScore,
            'post_test_raw'      => $postScore,
            'accomplished_items' => $student['accomplished_items'],
            'storyboard_score'   => $student['storyboard_score'],
            'created_at'         => $student['created_at']
        ];
    }, $students);

    // Per-question progress from storyboard_progress — only when student_id + section_id given
    $progressDetail = null;
    if ($filterStudentId && $filterSectionId) {
        $pStmt = $pdo->prepare("
            SELECT answers, step_images, submitted, scores,
                   current_step, unlocked_step, saved_at
            FROM storyboard_progress
            WHERE student_id = ? AND section_id = ?
            LIMIT 1
        ");
        $pStmt->execute([$filterStudentId, $filterSectionId]);
        $row = $pStmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            foreach (['answers', 'step_images', 'submitted', 'scores'] as $field) {
                if (!empty($row[$field])) {
                    $decoded     = json_decode($row[$field], true);
                    $row[$field] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : [];
                } else {
                    $row[$field] = [];
                }
            }
            $progressDetail = $row;
        }
    }

    echo json_encode([
        'success'  => true,
        'students' => $result,
        'progress' => $progressDetail  // null when not filtered; full detail when student_id given
    ]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}