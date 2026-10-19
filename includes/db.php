<?php
/**
 * Подключение к базе данных через PDO.
 * Параметры берутся из config/config.php.
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/../config/config.php';

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['dbname'],
        $config['charset']
    );

    try {
        $pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo '<h1>Ошибка подключения к базе данных</h1>';
        echo '<p>Проверьте параметры соединения в файле <b>config/config.php</b> '
            . 'и убедитесь, что база данных импортирована из <b>sql/database.sql</b>.</p>';
        echo '<p style="color:#999">Техническая информация: ' . htmlspecialchars($e->getMessage()) . '</p>';
        exit;
    }

    return $pdo;
}

/** Выполнить подготовленный запрос и вернуть statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Одна строка результата или null. */
function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Все строки результата. */
function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** Одно значение из первой строки результата. */
function q_val(string $sql, array $params = [], $default = null)
{
    $row = q($sql, $params)->fetch(PDO::FETCH_NUM);
    return $row === false ? $default : $row[0];
}

/** Последний вставленный ID. */
function last_id(): int
{
    return (int) db()->lastInsertId();
}
