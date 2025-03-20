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

    // Путь к файлу базы данных SQLite (должен совпадать с form-handler.php)
    $dbPath = __DIR__ . '/database/form_data.sqlite';

    try {
        // Подключение к базе данных SQLite
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Функция для отображения таблицы
        function displayTable($pdo, $tableName) {
            echo "<h2>Таблица: {$tableName}</h2>";
            $stmt = $pdo->query("SELECT * FROM {$tableName}");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($results) {
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
            } else {
                echo "<p>Таблица пуста.</p>";
            }
        }

        // Отображение таблицы users
        displayTable($pdo, 'users');

        // Отображение таблицы form_submissions
        displayTable($pdo, 'form_submissions');

        // Отображение таблицы geo_data
        displayTable($pdo, 'geo_data');

    } catch (PDOException $e) {
        die("Ошибка подключения к базе данных: " . $e->getMessage());
    }

    ?>

</body>
</html>