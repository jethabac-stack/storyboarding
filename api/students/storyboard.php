<?php
// ── Bug 5 fix: CORS headers before require_once so they fire even on DB error ─
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../admin/db.php';

// ── TABLE 1: storyboard — summary row per student+section ─────────────────
// (accomplished_items + score only — no progress_data column, avoids schema conflicts)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS storyboard (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        student_id         INT          NOT NULL,
        section_id         INT          NOT NULL,
        accomplished_items VARCHAR(20)  NOT NULL DEFAULT '0/30',
        score              VARCHAR(20)  NOT NULL DEFAULT '0/30',
        image              VARCHAR(255) NULL,
        created_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_storyboard (student_id, section_id),
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'storyboard table error: ' . $e->getMessage()]);
    exit;
}

// ── TABLE 2: storyboard_progress — full JSON progress per student+section ──
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS storyboard_progress (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        student_id        INT      NOT NULL,
        section_id        INT      NOT NULL,
        answers           LONGTEXT NULL,
        step_images       LONGTEXT NULL,
        submitted         LONGTEXT NULL,
        scores            LONGTEXT NULL,
        saved_step_inputs LONGTEXT NULL,
        saved_step_images LONGTEXT NULL,
        current_step      INT      NOT NULL DEFAULT 0,
        unlocked_step     INT      NOT NULL DEFAULT 0,
        saved_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_progress (student_id, section_id),
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migration: add new columns to existing tables
    foreach (['saved_step_inputs', 'saved_step_images'] as $col) {
        $exists = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'storyboard_progress'
            AND COLUMN_NAME = '$col'")->fetchColumn();
        if (!$exists) {
            $pdo->exec("ALTER TABLE storyboard_progress ADD COLUMN $col LONGTEXT NULL");
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'storyboard_progress table error: ' . $e->getMessage()]);
    exit;
}

// ── GET — fetch summary + full progress for a student ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    $sectionId = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

    if (!$studentId || !$sectionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing student_id or section_id']);
        exit;
    }

    try {
        // Summary row
        // Bug 1 fix: actual column is `name` on both students and sections tables
        $stmt = $pdo->prepare(
            "SELECT s.id, st.name AS student_name, sec.name AS section_name,
                    s.accomplished_items, s.score, s.updated_at
             FROM storyboard s
             JOIN students  st  ON st.id  = s.student_id
             JOIN sections  sec ON sec.id = s.section_id
             WHERE s.student_id = ? AND s.section_id = ?
             LIMIT 1"
        );
        $stmt->execute([$studentId, $sectionId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        // Detailed progress row
        $stmt2 = $pdo->prepare(
            "SELECT answers, step_images, submitted, scores,
                    saved_step_inputs, saved_step_images,
                    current_step, unlocked_step, saved_at
             FROM storyboard_progress
             WHERE student_id = ? AND section_id = ?
             LIMIT 1"
        );
        $stmt2->execute([$studentId, $sectionId]);
        $progress = $stmt2->fetch(PDO::FETCH_ASSOC);

        // Decode JSON fields
        if ($progress) {
            foreach (['answers', 'step_images', 'submitted', 'scores', 'saved_step_inputs', 'saved_step_images'] as $field) {
                if (!empty($progress[$field])) {
                    $decoded = json_decode($progress[$field], true);
                    $progress[$field] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : [];
                } else {
                    $progress[$field] = [];
                }
            }
        }

        echo json_encode([
            'success'  => true,
            'record'   => $record   ?: null,
            'progress' => $progress ?: null
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── POST — upsert both tables inside a transaction ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body         = json_decode(file_get_contents('php://input'), true);
    $studentId    = isset($body['student_id'])       ? intval($body['student_id'])  : 0;
    $sectionId    = isset($body['section_id'])       ? intval($body['section_id'])  : 0;
    $accomplished = trim($body['accomplished_items'] ?? '');
    $score        = trim($body['score']              ?? '');
    $pd           = $body['progress_data']           ?? null;

    if (!$studentId || !$sectionId || $accomplished === '' || $score === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields: student_id, section_id, accomplished_items, score']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Upsert summary into storyboard
        $stmt = $pdo->prepare(
            "INSERT INTO storyboard (student_id, section_id, accomplished_items, score)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                accomplished_items = VALUES(accomplished_items),
                score              = VALUES(score),
                updated_at         = NOW()"
        );
        $stmt->execute([$studentId, $sectionId, $accomplished, $score]);

        // 2. Upsert detailed progress into storyboard_progress
        if ($pd !== null) {
            $answers         = json_encode($pd['answers']          ?? []);
            $stepImages      = json_encode($pd['stepImages']       ?? []);
            $submitted       = json_encode($pd['submittedAnswers'] ?? []);
            $scores          = json_encode($pd['questionScores']   ?? []);
            $savedStepInputs = json_encode($pd['savedStepInputs']  ?? []);
            $savedStepImages = json_encode($pd['savedStepImages']  ?? []);
            $currentStep     = intval($pd['currentStep']           ?? 0);
            $unlockedStep    = intval($pd['currentUnlockedStep']   ?? 0);

            $stmt2 = $pdo->prepare(
                "INSERT INTO storyboard_progress
                    (student_id, section_id, answers, step_images, submitted, scores,
                     saved_step_inputs, saved_step_images, current_step, unlocked_step)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    answers           = VALUES(answers),
                    step_images       = VALUES(step_images),
                    submitted         = VALUES(submitted),
                    scores            = VALUES(scores),
                    saved_step_inputs = VALUES(saved_step_inputs),
                    saved_step_images = VALUES(saved_step_images),
                    current_step      = VALUES(current_step),
                    unlocked_step     = VALUES(unlocked_step),
                    saved_at          = NOW()"
            );
            $stmt2->execute([
                $studentId, $sectionId,
                $answers, $stepImages, $submitted, $scores,
                $savedStepInputs, $savedStepImages,
                $currentStep, $unlockedStep
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Storyboard saved successfully']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── Anything else ─────────────────────────────────────────────────────────
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);