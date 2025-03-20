<?php
// Заголовки CORS для разрешения кросс-доменных запросов
$allowedOrigins = [
    'http://zafaley.com', // Замените на реальный домен фронтенд-сервера
    
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Credentials: true");
}

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

// Ініціалізація бази даних SQLite
function initDatabase() {
    $dbPath = __DIR__ . '/database/form_data.sqlite';
    $dbDir = dirname($dbPath);
    
    // Добавляем подробное логирование
    logError('Текущие права доступа:');
    logError('Директория скрипта (' . __DIR__ . '): ' . substr(sprintf('%o', fileperms(__DIR__)), -4));
    if (file_exists($dbDir)) {
        logError('Директория БД (' . $dbDir . '): ' . substr(sprintf('%o', fileperms($dbDir)), -4));
    }
    
    // Проверяем пользователя PHP
    logError('PHP работает под пользователем: ' . exec('whoami'));
    
    try {
        // Создаем пустой файл базы данных, если он не существует
        if (!file_exists($dbPath)) {
            logError('Создаем файл базы данных...');
            if (@touch($dbPath)) {
                chmod($dbPath, 0664);
                logError('Файл базы данных создан успешно');
            } else {
                logError('Не удалось создать файл базы данных');
                return null;
            }
        }
        
        // Пробуем создать соединение
        try {
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            logError('PDO соединение успешно создано');
            
            // Создаем таблицы
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
            logError('Ошибка PDO: ' . $e->getMessage());
            logError('Путь к БД: ' . $dbPath);
            logError('Права доступа к файлу БД: ' . (file_exists($dbPath) ? substr(sprintf('%o', fileperms($dbPath)), -4) : 'файл не существует'));
            throw $e;
        }
    } catch (Exception $e) {
        logError('Общая ошибка: ' . $e->getMessage());
        return null;
    }
}

// СИМУЛЯЦИЯ ОШИБОК ДЛЯ ОТЛАДКИ
// Установите этот флаг в true, чтобы активировать симуляцию ошибок
$debugMode = false;
$errorStage = 'validation'; // Возможные значения: 'data', 'duplicate', 'validation', 'database', 'response'

// Функция для симуляции ошибки на определенном этапе
function simulateErrorIfNeeded($stage, $debugMode, $errorStage) {
    if ($debugMode && $stage === $errorStage) {
        $response = [
            "success" => false,
            "message" => "СИМУЛЯЦИЯ ОШИБКИ: Ошибка на этапе '{$stage}'",
            "debug_info" => "Эта ошибка специально сгенерирована для отладки"
        ];
        logRequest([], $response, false);
        echo json_encode($response);
        exit;
    }
}

// Чтение данных из curl-запроса или обычного POST
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// Симуляция ошибки получения данных
simulateErrorIfNeeded('data', $debugMode, $errorStage);

// Если данные не удалось декодировать, проверяем $_POST
if (!$data && !empty($_POST)) {
    $data = $_POST;
}

// Проверка наличия данных
if (!$data) {
    $response = ["success" => false, "message" => "Невірний формат даних"];
    logRequest([], $response, false);
    echo json_encode($response);
    exit;
}

// Проверка дубликатов до валидации
$email = $data['email'] ?? '';
$phone = $data['phone'] ?? '';
$ip = $data['ip'] ?? $_SERVER['REMOTE_ADDR'];

// Симуляция ошибки на этапе подключения к базе данных
simulateErrorIfNeeded('duplicate', $debugMode, $errorStage);

$pdo = initDatabase();
if (!$pdo) {
    $response = ["success" => false, "message" => "Помилка підключення до бази даних"];
    logRequest($data, $response, false);
    echo json_encode($response);
    exit;
}

// Проверка на дубликаты
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email OR phone = :phone LIMIT 1');
$stmt->execute([
    ':email' => $email,
    ':phone' => $phone
]);

$existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
if ($existingUser) {
    $response = [
        "success" => false, 
        "isDuplicate" => true,
        "message" => "Користувач з такою електронною поштою або телефоном вже існує"
    ];
    logRequest($data, $response, false);
    echo json_encode($response);
    exit;
}

// Валідація на стороні сервера
// Симуляция ошибки валидации
simulateErrorIfNeeded('validation', $debugMode, $errorStage);

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
$geoData = getGeoData($ip);
$data['geo'] = $geoData;

// Запись данных в базу данных
// Симуляция ошибки при записи в базу данных
simulateErrorIfNeeded('database', $debugMode, $errorStage);

try {
    $pdo->beginTransaction();
    
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
    
    // Додавання запису про відправку форми
    $stmt = $pdo->prepare('
        INSERT INTO form_submissions (user_id, form_id, page_url, page_title, user_agent, status) 
        VALUES (:user_id, :form_id, :page_url, :page_title, :user_agent, :status)
    ');
    
    $stmt->execute([
        ':user_id' => $userId,
        ':form_id' => $data['formId'] ?? 'unknown',
        ':page_url' => $data['url'] ?? 'unknown',
        ':page_title' => $data['title'] ?? 'unknown',
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ':status' => 'completed'
    ]);
    
    $pdo->commit();
    
    // Симуляция ошибки при формировании ответа
    simulateErrorIfNeeded('response', $debugMode, $errorStage);
    
    $response = [
        "success" => true,
        "redirectUrl" => "/thank-you.html",
        "message" => "Дані успішно відправлені"
    ];
    
} catch (PDOException $e) {
    $pdo->rollBack();
    logError('Database error: ' . $e->getMessage());
    
    $response = [
        "success" => false,
        "message" => "Помилка збереження даних: " . $e->getMessage()
    ];  
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