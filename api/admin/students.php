<?php
// filepath: api/admin/students.php
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

// Get all students
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $stmt = $pdo->query("
            SELECT s.*, sec.name as section_name 
            FROM students s 
            LEFT JOIN sections sec ON s.section_id = sec.id 
            ORDER BY s.id DESC
        ");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'students' => $students
        ]);
        exit;
    }
    
    if ($action === 'get') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Student ID required']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT s.*, sec.name as section_name 
            FROM students s 
            LEFT JOIN sections sec ON s.section_id = sec.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            echo json_encode([
                'success' => true,
                'student' => $student
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Student not found']);
        }
        exit;
    }
    
    // Default: get all students
    $stmt = $pdo->query("
        SELECT s.*, sec.name as section_name 
        FROM students s 
        LEFT JOIN sections sec ON s.section_id = sec.id 
        ORDER BY s.id DESC
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'students' => $students
    ]);
    exit;
}

// Create new student
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email']) || !isset($data['password']) || !isset($data['name']) || !isset($data['grade'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email, password, name, and grade are required']);
        exit;
    }
    
    $email = trim($data['email']);
    $password = $data['password'];
    $name = trim($data['name']);
    $section_id = isset($data['section_id']) ? $data['section_id'] : null;
    $grade = trim($data['grade']);
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO students (email, password, name, section_id, grade) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$email, $hashedPassword, $name, $section_id, $grade]);
        
        $student_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Student created successfully',
            'student_id' => $student_id
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already exists']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create student']);
        }
    }
    exit;
}

// Update student
if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID required']);
        exit;
    }
    
    $id = $data['id'];
    $email = isset($data['email']) ? trim($data['email']) : null;
    $name = isset($data['name']) ? trim($data['name']) : null;
    $section_id = isset($data['section_id']) ? $data['section_id'] : null;
    $grade = isset($data['grade']) ? trim($data['grade']) : null;
    $password = isset($data['password']) ? $data['password'] : null;
    
    // Build update query
    $updates = [];
    $params = [];
    
    if ($email !== null) {
        $updates[] = 'email = ?';
        $params[] = $email;
    }
    if ($name !== null) {
        $updates[] = 'name = ?';
        $params[] = $name;
    }
    if ($section_id !== null) {
        $updates[] = 'section_id = ?';
        $params[] = $section_id;
    }
    if ($grade !== null) {
        $updates[] = 'grade = ?';
        $params[] = $grade;
    }
    if ($password !== null && !empty($password)) {
        $updates[] = 'password = ?';
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    $params[] = $id;
    
    try {
        $stmt = $pdo->prepare("UPDATE students SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Student updated successfully'
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already exists']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update student']);
        }
    }
    exit;
}

// Delete student
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Student deleted successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete student']);
    }
    exit;
}