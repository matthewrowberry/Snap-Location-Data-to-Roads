<?php
header('Content-Type: application/json');
require '../config.php';

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get and validate input
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';

    if (empty($start) || empty($end)) {
        http_response_code(400);
        echo json_encode(['error' => 'Start and end date are required.']);
        exit;
    }

    // Optional: Validate date format
    $start_dt = DateTime::createFromFormat('Y-m-d H:i', $start);
    $end_dt = DateTime::createFromFormat('Y-m-d H:i', $end);

    if (!$start_dt || !$end_dt || $start_dt > $end_dt) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format or range. Use YYYY-MM-DD HH:MM.']);
        exit;
    }

    // Format for MySQL
    $start_mysql = $start_dt->format('Y-m-d H:i:s');
    $end_mysql = $end_dt->format('Y-m-d H:i:s');

    $stmt = $conn->prepare("
        SELECT latitude, longitude, datetime 
        FROM path 
        WHERE datetime >= :start 
          AND datetime <= :end 
        ORDER BY datetime ASC
    ");
    $stmt->execute([
        ':start' => $start_mysql,
        ':end' => $end_mysql
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Optional: Add basic info
    echo json_encode([
        'points' => $results,
        'count' => count($results),
        'range' => ['start' => $start, 'end' => $end]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}