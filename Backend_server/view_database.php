<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Просмотр данных формы</title>
    <style>
        body { font-family: sans-serif; }
        h2 { margin-top: 2em; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
    </style>
</head>
<body>
    <h1>Содержимое базы данных формы</h1>

    <?php
    // Включаем вывод ошибок для отладки
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    echo "<div style='background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px solid #ddd;'>";
    echo "<h3>🔍 Отладочная информация:</h3>";

    // Путь к файлу базы данных SQLite
    $dbPath = __DIR__ . '/database/form_data.sqlite';
    echo "<p>📁 Путь к базе данных: " . htmlspecialchars($dbPath) . "</p>";

    try {
        echo "<p>⚡ Попытка подключения к базе данных...</p>";
        // Подключение к базе данных SQLite
        $pdo = new PDO('sqlite:' . $dbPath);
        echo "<p>✅ Подключение успешно установлено</p>";
        
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p>✅ Установлен режим обработки ошибок PDO</p>";

        // Функция для отображения таблицы
        function displayTable($pdo, $tableName) {
            echo "<p>🔄 Начало обработки таблицы: {$tableName}</p>";
            
            try {
                $stmt = $pdo->query("SELECT * FROM {$tableName}");
                echo "<p>✅ SQL-запрос выполнен успешно</p>";
                
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "<p>📊 Получено записей: " . count($results) . "</p>";

                if ($results) {
                    echo "<p>⚙️ Начало формирования HTML-таблицы</p>";
                    echo "<table><thead><tr>";
                    foreach (array_keys($results[0]) as $column) {
                        echo "<th>{$column}</th>";
                    }
                    echo "</tr></thead><tbody>";
                    foreach ($results as $row) {
                        echo "<tr>";
                        foreach ($row as $value) {
                            echo "<td>" . htmlspecialchars($value) . "</td>";
                        }
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                    echo "<p>✅ HTML-таблица сформирована успешно</p>";
                } else {
                    echo "<p>ℹ️ Таблица {$tableName} пуста</p>";
                }
            } catch (PDOException $e) {
                echo "<p>❌ Ошибка при работе с таблицей {$tableName}: " . 
                     htmlspecialchars($e->getMessage()) . "</p>";
            }
        }

        echo "<p>📋 Начало обработки всех таблиц...</p>";
        
        // Отображение таблицы users
        echo "<hr><p>👤 Обработка таблицы users:</p>";
        displayTable($pdo, 'users');

        // Отображение таблицы form_submissions
        echo "<hr><p>📝 Обработка таблицы form_submissions:</p>";
        displayTable($pdo, 'form_submissions');

        // Отображение таблицы geo_data
        echo "<hr><p>🌍 Обработка таблицы geo_data:</p>";
        displayTable($pdo, 'geo_data');

        echo "</div>";

    } catch (PDOException $e) {
        echo "<p>❌ Критическая ошибка: " . htmlspecialchars($e->getMessage()) . "</p>";
        die("Ошибка подключения к базе данных: " . $e->getMessage());
    }

    ?>

</body>
</html>