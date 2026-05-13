<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $pdo = new PDO('sqlite:database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения к БД']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$id = isset($request[1]) ? (int)$request[1] : null;

switch ($method) {
    case 'GET':
        $sort = $_GET['sort'] ?? 'desc';
        $filter = $_GET['filter'] ?? '';
        
        $sql = "SELECT * FROM feedback";
        $params = [];
        
        if (!empty($filter)) {
            $sql .= " WHERE name LIKE :filter OR message LIKE :filter";
            $params[':filter'] = "%$filter%";
        }
        
        $sql .= " ORDER BY created_at " . ($sort === 'asc' ? 'ASC' : 'DESC');
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($data);
        break;
    
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['name']) || empty($input['message'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Имя и сообщение обязательны']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO feedback (name, message) VALUES (:name, :message)");
        $stmt->execute([
            ':name' => htmlspecialchars(strip_tags($input['name'])),
            ':message' => htmlspecialchars(strip_tags($input['message']))
        ]);
        
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        break;
    
    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID не указан']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['message'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Сообщение не может быть пустым']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE feedback SET message = :message WHERE id = :id");
        $stmt->execute([
            ':message' => htmlspecialchars(strip_tags($input['message'])),
            ':id' => $id
        ]);
        
        echo json_encode(['success' => true]);
        break;
    
    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID не указан']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM feedback WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        echo json_encode(['success' => true]);
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Метод не поддерживается']);
}
?>