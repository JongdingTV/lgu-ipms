<?php
// Import security functions
require dirname(__DIR__) . '/session-auth.php';

// Check authentication for API requests
check_auth();
check_suspicious_activity();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
if ($db->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $db->connect_error]));
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Fetch all contractors
        $result = $db->query("SELECT * FROM contractors ORDER BY created_at DESC");
        $contractors = [];
        while ($row = $result->fetch_assoc()) {
            $contractors[] = $row;
        }
        echo json_encode($contractors);
        break;

    case 'POST':
        // Create new contractor
        $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                echo json_encode(['error' => 'Invalid JSON input']);
                break;
            }
            $stmt = $db->prepare("INSERT INTO contractors (company, owner, license, email, phone, address, specialization, experience, rating, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
                break;
            }
            $stmt->bind_param('sssssssisds', $data['company'], $data['owner'], $data['license'], $data['email'], $data['phone'], $data['address'], $data['specialization'], $data['experience'], $data['rating'], $data['status'], $data['notes']);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $db->insert_id]);
            } else {
                echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            }
            $stmt->close();
            break;

    case 'PUT':
        // Update contractor
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'];
        $stmt = $db->prepare("UPDATE contractors SET company=?, owner=?, license=?, email=?, phone=?, address=?, specialization=?, experience=?, rating=?, status=?, notes=? WHERE id=?");
        $stmt->bind_param('sssssssisdsi', $data['company'], $data['owner'], $data['license'], $data['email'], $data['phone'], $data['address'], $data['specialization'], $data['experience'], $data['rating'], $data['status'], $data['notes'], $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => $stmt->error]);
        }
        $stmt->close();
        break;

    case 'DELETE':
        // Delete contractor
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'];
        $stmt = $db->prepare("DELETE FROM contractors WHERE id=?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => $stmt->error]);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['error' => 'Invalid request method']);
        break;
}

$db->close();
