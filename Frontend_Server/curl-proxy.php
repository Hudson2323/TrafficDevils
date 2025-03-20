<?php
header('Content-Type: application/json');

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Получение данных из запроса
$requestData = json_decode(file_get_contents('php://input'), true);

// Логирование запроса для отладки
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}
file_put_contents(
    $logDir . '/proxy_requests.log',
    date('Y-m-d H:i:s') . ' - Request: ' . json_encode($requestData, JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND
);

// Проверка наличия всех необходимых данных
if (!isset($requestData['url']) || !isset($requestData['method'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request - Missing required fields']);
    exit;
}

// Подготовка cURL запроса
$ch = curl_init($requestData['url']);

// Установка параметров запроса
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestData['method']);

// Если есть данные для отправки, добавляем их
if (isset($requestData['data'])) {
    $jsonData = json_encode($requestData['data']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ]);
}

// Добавление безопасности (опционально)
// Установка timeout
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

// Выполнение запроса
$response = curl_exec($ch);

// Логирование ответа для отладки
file_put_contents(
    $logDir . '/proxy_responses.log',
    date('Y-m-d H:i:s') . ' - Response: ' . $response . "\n",
    FILE_APPEND
);

// Проверка на ошибки
if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    
    // Логирование ошибки
    error_log("cURL Error: " . $error . " when calling " . $requestData['url']);
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to execute cURL request',
        'details' => $error
    ]);
    exit;
}

// Получение HTTP кода ответа
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Закрытие соединения
curl_close($ch);

// Если запрос был неуспешным
if ($httpCode >= 400) {
    http_response_code($httpCode);
    echo json_encode([
        'error' => 'Remote server returned error',
        'statusCode' => $httpCode,
        'response' => json_decode($response, true) ?: $response
    ]);
    exit;
}

// Отправка ответа клиенту
echo $response;
exit;
?> 