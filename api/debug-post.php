<?php
// filepath: api/debug-post.php
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

echo json_encode([
    'method' => $method,
    'input' => $input,
    'raw_input' => file_get_contents('php://input')
]);