<?php
header('Content-Type: application/json');

// Ініціалізація бази даних SQLite
function initDatabase() {
    $dbPath = __DIR__ . '/database/form_data.sqlite';
    $dbDir = dirname($dbPath);
    
    // Створюємо директорію для бази даних, якщо вона не існує
    if (!file_exists($dbDir)) {
        mkdir($dbDir, 0755, true);
    }
    
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Створюємо таблиці, якщо вони не існують
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                surname TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                phone TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address TEXT
            );
            
            CREATE TABLE IF NOT EXISTS form_submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                form_id TEXT NOT NULL,
                page_url TEXT,
                page_title TEXT,
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status TEXT DEFAULT "pending",
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
            
            CREATE TABLE IF NOT EXISTS geo_data (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                country TEXT,
                country_code TEXT,
                city TEXT,
                region TEXT,
                ip_address TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
        ');
        
        return $pdo;
    } catch (PDOException $e) {
        logError('Database initialization error: ' . $e->getMessage());
        return null;
    }
}

// Читаємо вхідні дані
$data = json_decode(file_get_contents('php://input'), true);

// Валідація на стороні сервера
if (!$data) {
    $response = ["success" => false, "message" => "Невірний формат даних"];
    logRequest($data, $response, false);
    echo json_encode($response);
    exit;
}

// Перевірка обов'язкових полів
$requiredFields = ['name', 'surname', 'email', 'phone'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        $response = ["success" => false, "message" => "Поле {$field} є обов'язковим"];
        logRequest($data, $response, false);
        echo json_encode($response);
        exit;
    }
}

// Валідація email
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $response = ["success" => false, "message" => "Невірний формат email"];
    logRequest($data, $response, false);
    echo json_encode($response);
    exit;
}

// Валідація телефону
if (!preg_match('/^\+[0-9]{10,15}$/', $data['phone'])) {
    $response = ["success" => false, "message" => "Невірний формат телефону"];
    logRequest($data, $response, false);
    echo json_encode($response);
    exit;
}

// Отримання геоданих з IP
$ip = $data['ip'] ?? $_SERVER['REMOTE_ADDR'];
$geoData = getGeoData($ip);
$data['geo'] = $geoData;

// Підключення до бази даних
$pdo = initDatabase();
if (!$pdo) {
    $response = ["success" => false, "message" => "Помилка підключення до бази даних"];
    logRequest($data, $response, false);
    echo json_encode($response);
    exit;
}

// Запис даних в базу даних
try {
    $pdo->beginTransaction();
    
    // Перевірка на дублікати
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email OR phone = :phone LIMIT 1');
    $stmt->execute([
        ':email' => $data['email'],
        ':phone' => $data['phone']
    ]);
    
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingUser) {
        $userId = $existingUser['id'];
    } else {
        // Додавання нового користувача
        $stmt = $pdo->prepare('
            INSERT INTO users (name, surname, email, phone, ip_address) 
            VALUES (:name, :surname, :email, :phone, :ip)
        ');
        
        $stmt->execute([
            ':name' => $data['name'],
            ':surname' => $data['surname'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':ip' => $ip
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Додавання геоданих
        $stmt = $pdo->prepare('
            INSERT INTO geo_data (user_id, country, country_code, city, region, ip_address) 
            VALUES (:user_id, :country, :country_code, :city, :region, :ip)
        ');
        
        $stmt->execute([
            ':user_id' => $userId,
            ':country' => $geoData['country'] ?? 'Невідомо',
            ':country_code' => $geoData['country_code'] ?? 'XX',
            ':city' => $geoData['city'] ?? 'Невідомо',
            ':region' => $geoData['region'] ?? 'Невідомо',
            ':ip' => $ip
        ]);
    }
    
    // Додавання запису про відправку форми
    $stmt = $pdo->prepare('
        INSERT INTO form_submissions (user_id, form_id, page_url, page_title, user_agent, status) 
        VALUES (:user_id, :form_id, :page_url, :page_title, :user_agent, :status)
    ');
    
    $stmt->execute([
        ':user_id' => $userId,
        ':form_id' => $data['formId'] ?? 'unknown',
        ':page_url' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
        ':page_title' => $data['title'] ?? 'unknown',
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ':status' => 'completed'
    ]);
    
    $pdo->commit();
    
     "success" => true,
        "redirectUrl" => "/thank-you.html",
        "message" => "Дані успішно відправлені"
    ];
    
} catch (PDOException $e) {
    $pdo->rollBack();
    logError('Database error: ' . $e->getMessage());
    
    $response = [
        "success" => false,
        "message" => "Помилка збереження даних"
    ];$response = [
       
}

// Логування запиту
logRequest($data, $response, $response['success']);

// Повертаємо відповідь
echo json_encode($response);
exit;

/**
 * Отримання геоданих за IP
 * 
 * @param string $ip IP-адреса
 * @return array Геодані
 */
function getGeoData($ip) {
    try {
        $url = "https://ipapi.co/{$ip}/json/";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            return ['error' => 'Помилка отримання геоданих', 'ip' => $ip];
        }
        
        curl_close($ch);
        $geoData = json_decode($response, true);
        
        return [
            'country' => $geoData['country_name'] ?? 'Невідомо',
            'country_code' => $geoData['country_code'] ?? 'XX',
            'city' => $geoData['city'] ?? 'Невідомо',
            'region' => $geoData['region'] ?? 'Невідомо',
            'ip' => $ip
        ];
    } catch (Exception $e) {
        return ['error' => $e->getMessage(), 'ip' => $ip];
    }
}

/**
 * Логування запиту та відповіді
 * 
 * @param array $requestData Дані запиту
 * @param array $responseData Дані відповіді
 * @param bool $success Успішність обробки
 */
function logRequest($requestData, $responseData, $success) {
    $logDir = __DIR__ . '/logs';
    
    // Створюємо директорію для логів, якщо вона не існує
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/form_requests.log';
    
    // Підготовка даних для логування
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Маскуємо конфіденційні дані
    if (isset($requestData['email'])) {
        $parts = explode('@', $requestData['email']);
        if (count($parts) == 2) {
            $requestData['email'] = substr($parts[0], 0, 2) . '***@' . $parts[1];
        }
    }
    
    if (isset($requestData['phone'])) {
        $requestData['phone'] = substr($requestData['phone'], 0, 4) . '******' . substr($requestData['phone'], -2);
    }
    
    // Формуємо запис логу
    $logEntry = [
        'timestamp' => $timestamp,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'request_data' => $requestData,
        'response_data' => $responseData,
        'success' => $success
    ];
    
    // Записуємо в файл
    file_put_contents(
        $logFile, 
        json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n---\n", 
        FILE_APPEND
    );
}

/**
 * Логування помилок
 * 
 * @param string $errorMessage Повідомлення про помилку
 */
function logError($errorMessage) {
    $logDir = __DIR__ . '/logs';
    
    // Створюємо директорію для логів, якщо вона не існує
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/errors.log';
    
    // Формуємо запис логу
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$errorMessage}\n";
    
    // Записуємо в файл
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
?>