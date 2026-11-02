<?php
require_once 'config.php';
require_once 'db.php';
require_once 'auth.php';
require_once 'classes/Receipt.php';
require_once 'classes/Report.php';

$database = new Database();
$db = $database->connect();
$auth = new Auth($db);
$receipt = new Receipt($db);
$report = new Report($db, $receipt);

// Init schema if requested
if (isset($_GET['action']) && $_GET['action'] === 'init') {
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100), email VARCHAR(100) UNIQUE, phone VARCHAR(30), password VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $db->exec("CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT, name VARCHAR(100), phone VARCHAR(30),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $db->exec("CREATE TABLE IF NOT EXISTS receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT, client_id INT NULL, amount DECIMAL(12,2), receipt_date DATE,
        method VARCHAR(50), note TEXT, file_path VARCHAR(255),
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo json_encode(['success' => true, 'message' => 'Base de datos inicializada']);
    exit;
}

// Auth middleware
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';
if (!$token && isset($_GET['token'])) {
    $token = $_GET['token'];
}
$payload = $auth->verifyToken($token);
$publicActions = ['login', 'register', 'init'];
$action = $_GET['action'] ?? '';
if (!in_array($action, $publicActions) && !$payload) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token inválido o expirado']);
    exit;
}
$userId = $payload['user_id'] ?? null;

switch ($action) {
    case 'login':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($auth->login($data['email'] ?? '', $data['password'] ?? ''));
        break;

    case 'register':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($auth->register($data['name'] ?? '', $data['email'] ?? '', $data['phone'] ?? '', $data['password'] ?? ''));
        break;

    case 'me':
        $stmt = $db->prepare("SELECT id, name, email, phone FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'user' => $stmt->fetch()]);
        break;

    case 'clients':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $receipt->addClient($userId, $data['name'] ?? '', $data['phone'] ?? '');
            echo json_encode(['success' => (bool)$id, 'id' => $id]);
        } else {
            echo json_encode(['success' => true, 'clients' => $receipt->listClients($userId)]);
        }
        break;

    case 'receipts':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle multipart file upload
            $clientId = $_POST['client_id'] ?? null;
            $amount = $_POST['amount'] ?? 0;
            $date = $_POST['date'] ?? date('Y-m-d');
            $method = $_POST['method'] ?? '';
            $note = $_POST['note'] ?? '';

            $filePath = null;
            if (!empty($_FILES['file']['tmp_name'])) {
                $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
                $name = uniqid('rec_', true) . '.' . $ext;
                $filePath = UPLOAD_DIR . $name;
                if (!move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error al guardar archivo']);
                    exit;
                }
            }

            $id = $receipt->addReceipt($userId, $clientId, $amount, $date, $method, $note, $filePath);
            echo json_encode(['success' => (bool)$id, 'id' => $id]);
        } else {
            $clientId = $_GET['client_id'] ?? null;
            $start = $_GET['start'] ?? null;
            $end = $_GET['end'] ?? null;
            echo json_encode(['success' => true, 'receipts' => $receipt->listReceipts($userId, $clientId, $start, $end)]);
        }
        break;

    case 'receipt':
        $id = (int)($_GET['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            echo json_encode(['success' => $receipt->deleteReceipt($id, $userId)]);
        } else {
            echo json_encode(['success' => true, 'receipt' => $receipt->getReceipt($id, $userId)]);
        }
        break;

    case 'total':
        $clientId = (int)($_GET['client_id'] ?? 0);
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;
        echo json_encode(['success' => true, 'total' => $receipt->totalByClient($userId, $clientId, $start, $end)]);
        break;

    case 'report_pdf':
        $clientId = (int)($_GET['client_id'] ?? 0);
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;
        $report->exportPdf($userId, $clientId ?: null, $start, $end);
        break;

    case 'report_excel':
        $clientId = (int)($_GET['client_id'] ?? 0);
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;
        $report->exportCsv($userId, $clientId ?: null, $start, $end);
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint no encontrado']);
        break;
}
?>