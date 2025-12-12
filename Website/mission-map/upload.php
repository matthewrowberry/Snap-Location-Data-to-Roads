<?php
// upload.php - Manual ID insert version
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
if ($input === false || empty($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty payload']);
    exit;
}

$lines = array_filter(array_map('trim', explode("\n", $input)));
if (empty($lines)) {
    http_response_code(400);
    echo json_encode(['error' => 'No data']);
    exit;
}

$mysqli = new mysqli($servername, $username, $password, $dbname);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}
$mysqli->set_charset('utf8mb4');

// Prepare statement with id
$stmt = $mysqli->prepare(
    "INSERT INTO path (id, datetime, latitude, longitude, original_ish) VALUES (?, ?, ?, ?, ?)"
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}

$stmt->bind_param("isddi", $id, $datetime, $latitude, $longitude, $original_ish);

$batch_size = 5000;
$inserted = 0;
$errors = [];

foreach ($lines as $line) {
    if (empty($line)) continue;

    $data = json_decode($line, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = "JSON decode error";
        continue;
    }

    if (!isset($data['id'], $data['datetime'], $data['latitude'], $data['longitude'], $data['original_ish'])) {
        $errors[] = "Missing fields";
        continue;
    }

    $id = (int)$data['id'];
    $datetime = $data['datetime'];
    $latitude = (float)$data['latitude'];
    $longitude = (float)$data['longitude'];
    $original_ish = (int)$data['original_ish'];

    // Validate ranges
    if ($id <= 0) {
        $errors[] = "Invalid id: $id";
        continue;
    }
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        $errors[] = "Coordinates out of range";
        continue;
    }

    if (!$stmt->execute()) {
        $errors[] = "Insert failed (id=$id): " . $stmt->error;
        continue;
    }

    $inserted++;
    if ($inserted % $batch_size === 0) {
        usleep(1000); // 1ms pause
    }
}

$stmt->close();
$mysqli->close();

echo json_encode([
    'status' => 'success',
    'inserted' => $inserted,
    'errors' => $errors
]);