<?php
require_once 'config.php';

header('Content-Type: application/json');

$term = trim($_GET['term'] ?? '');

$suggestions = [];

if ($term !== '') {
    $stmt = $conn->prepare("SELECT name FROM products WHERE name LIKE :term ORDER BY name ASC LIMIT 10");
    $stmt->execute([':term' => "%$term%"]);
    $suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

echo json_encode($suggestions);
