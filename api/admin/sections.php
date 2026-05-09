<?php
// filepath: api/admin/sections.php
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

// Get all sections
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $stmt = $pdo->query("
            SELECT id, name, grade, created_at 
            FROM sections 
            ORDER BY grade ASC, name ASC
        ");
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'sections' => $sections
        ]);
        exit;
    }
    
    // Get single section by ID
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT id, name, grade, created_at FROM sections WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($section) {
            echo json_encode(['success' => true, 'section' => $section]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Section not found']);
        }
        exit;
    }
    
    // Default: get all sections
    $stmt = $pdo->query("SELECT id, name, grade, created_at FROM sections ORDER BY grade ASC, name ASC");
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'sections' => $sections
    ]);
    exit;
}

// Create new section
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['name']) || empty($data['grade'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO sections (name, grade) VALUES (?, ?)");
        $stmt->execute([
            $data['name'],
            $data['grade']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Section created successfully',
            'section_id' => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error creating section: ' . $e->getMessage()]);
    }
    exit;
}

// Update section
if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Section ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE sections SET name = ?, grade = ? WHERE id = ?");
        $stmt->execute([
            $data['name'],
            $data['grade'],
            $data['id']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Section updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating section: ' . $e->getMessage()]);
    }
    exit;
}

// Delete section
if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Section ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Section deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting section: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);