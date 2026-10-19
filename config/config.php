<?php
/**
 * Настройки подключения к СУБД MySQL/MariaDB в OpenServer.
 *
 * После импорта database.sql укажите здесь параметры вашей локальной БД.
 * В OpenServer 6 в поле host используйте имя модуля БД, например, MySQL-8.0.
 */
return [
    'host'     => 'MySQL-8.4',
    'dbname'   => 'hightech',
    'user'     => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
];
