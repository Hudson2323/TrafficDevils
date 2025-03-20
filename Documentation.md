# Документація користувача

## Структура проекту

Проект складається з двох основних частин:

### 1. Frontend_server
Містить усі файли для клієнтської частини:
- HTML/CSS/JavaScript файли для користувацького інтерфейсу
- Форми для збору даних від користувачів
- Стилі та зображення

### 2. Backend_server
Містить серверну частину:
- Обробник форм (form-handler.php)
- Система логування
- База даних SQLite
- Система безпеки з CSRF-захистом

## Інструкції з встановлення на локальному сервері

### Вимоги
- PHP 7.4 або вище
- Підтримка SQLite в PHP
- Веб-сервер (Apache, Nginx)

### Кроки встановлення

1. **Підготовка сервера**
   ```bash
   # Встановлення необхідних пакетів (для Ubuntu/Debian)
   sudo apt update
   sudo apt install apache2 php php-sqlite3 php-curl
   
   # Увімкнення необхідних модулів PHP
   sudo phpenmod pdo_sqlite
   sudo systemctl restart apache2
   ```

2. **Розміщення файлів**
   - Скопіюйте папку `Frontend_server` в кореневу директорію веб-сервера (зазвичай `/var/www/html/`)
   - Скопіюйте папку `Backend_server` в кореневу директорію веб-сервера

3. **Налаштування прав доступу**
   ```bash
   # Встановлення прав на директорії
   sudo chmod -R 755 /var/www/html/Frontend_server
   sudo chmod -R 755 /var/www/html/Backend_server
   
   # Створення та налаштування директорії для бази даних
   sudo mkdir -p /var/www/html/Backend_server/database
   sudo chmod 775 /var/www/html/Backend_server/database
   
   # Створення та налаштування директорії для логів
   sudo mkdir -p /var/www/html/Backend_server/logs
   sudo chmod 775 /var/www/html/Backend_server/logs
   
   # Встановлення власника (замініть www-data на користувача вашого веб-сервера)
   sudo chown -R www-data:www-data /var/www/html/Backend_server
   ```

4. **Налаштування CORS та безпеки**
   - Відкрийте файл `Backend_server/form-handler.php`
   - Змініть масив `$allowedOrigins` для вказівки вашого домену:
     ```php
     $allowedOrigins = [
         'http://localhost', // Для локальної розробки
         'http://ваш-домен.com' // Ваш реальний домен
     ];
     ```
   - Змініть секретний ключ для CSRF-захисту:
     ```php
     $secret = 'ваш-секретний-ключ-змініть-це';
     ```

5. **Перевірка встановлення**
   - Відкрийте в браузері `http://localhost/Frontend_server/`
   - Перевірте роботу форм відправки даних
   - Перевірте логи в директорії `Backend_server/logs/`

## Опис API

### Обробник форм (form-handler.php)

**Endpoint:** `http://ваш-сервер/Backend_server/form-handler.php`

**Метод:** POST

**Заголовки:**
- `Content-Type: application/json`
- `X-CSRF-TOKEN: [токен]` (генерується на основі домену та секретного ключа)

**Параметри запиту:**
```json
{
  "name": "Ім'я користувача",
  "surname": "Прізвище",
  "email": "email@example.com",
  "phone_code": "UA",
  "phone": "+380XXXXXXXX",
  "select_time": "Weekdays",
  "select_price": "$2000 - $4000",
  "comments": "Коментар користувача",
  "ip": "IP користувача",
  "title": "Заголовок сторінки",
  "formId": "ID форми",
  "url": "URL сторінки"
}
```

**Успішна відповідь:**
```json
{
  "success": true,
  "redirectUrl": "/thank-you.html",
  "message": "Дані успішно відправлені"
}
```

**Відповідь з помилкою:**
```json
{
  "success": false,
  "message": "Помилка перевірки безпеки"
}
```

## Базові функції

### 1. Збір даних через форми
- Система підтримує кілька типів форм (registrationForm1, registrationForm2)
- Валідація даних на стороні клієнта та сервера
- Захист від XSS-атак через санітизацію вхідних даних

### 2. Зберігання даних
- Дані зберігаються в SQLite базі даних
- Автоматичне створення таблиць при першому запуску
- Підтримка міграцій схеми бази даних

### 3. Безпека
- CSRF-захист для запобігання міжсайтовій підробці запитів
- Валідація джерела запитів через CORS
- Маскування конфіденційних даних у логах

### 4. Логування
- Детальне логування всіх запитів
- Окремі логи для помилок
- Геолокація користувачів за IP-адресою

### 5. Перенаправлення
- Автоматичне перенаправлення на сторінку подяки після успішної відправки форми

## Усунення несправностей

### Проблеми з правами доступу
Якщо виникають помилки доступу до файлів або директорій:
```bash
sudo chown -R www-data:www-data /var/www/html/Backend_server
sudo chmod -R 755 /var/www/html/Backend_server
sudo chmod 775 /var/www/html/Backend_server/database
sudo chmod 775 /var/www/html/Backend_server/logs
```

### Проблеми з CORS
Якщо форми не відправляються через CORS-помилки:
1. Перевірте масив `$allowedOrigins` у файлі `form-handler.php`
2. Переконайтеся, що домен вказано у правильному форматі (з протоколом http/https)
3. Перевірте заголовки запиту в інструментах розробника браузера

### Проблеми з базою даних
Якщо база даних не створюється або виникають помилки при роботі з нею:
1. Перевірте наявність модуля SQLite в PHP: `php -m | grep sqlite`
2. Перевірте права доступу до директорії `database`
3. Перегляньте логи помилок у `logs/errors.log`