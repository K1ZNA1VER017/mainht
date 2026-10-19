<?php
/**
 * ДИАГНОСТИКА УСТАНОВКИ.
 *
 * Откройте в браузере: http://<имя-вашего-домена>/check.php
 * Страница проверит окружение, файлы, подключение к базе данных и подскажет,
 * почему может открываться старая версия сайта.
 *
 * Для работы сайта этот файл не нужен — после проверки его можно удалить.
 */

declare(strict_types=1);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
error_reporting(E_ALL);

$root = __DIR__;
$results = [];
$okCount = 0;
$warnCount = 0;
$errCount = 0;

function result(string $group, string $title, string $status, string $details = ''): void
{
    global $results, $okCount, $warnCount, $errCount;
    $results[$group][] = ['title' => $title, 'status' => $status, 'details' => $details];
    if ($status === 'ok') {
        $okCount++;
    } elseif ($status === 'warn') {
        $warnCount++;
    } else {
        $errCount++;
    }
}

/* ---------- Версия сборки ---------- */
$build = 'не найдена';
if (is_file($root . '/includes/version.php')) {
    require_once $root . '/includes/version.php';
    $build = defined('APP_BUILD') ? APP_BUILD : 'не определена';
    result('Сборка проекта', 'Метка сборки', 'ok', APP_BUILD);
} else {
    result('Сборка проекта', 'Файл includes/version.php', 'err', 'Файл отсутствует — на сервере старая версия проекта. Замените файлы полностью.');
}

/* ---------- Среда выполнения ---------- */
result('Среда выполнения', 'Версия PHP', version_compare(PHP_VERSION, '8.0', '>=') ? 'ok' : 'err',
    PHP_VERSION . ' (для работы приложения нужен PHP 8.0 или новее)');

$extensions = [
    'pdo_mysql' => 'PDO MySQL — подключение к базе данных',
    'mbstring'  => 'mbstring — работа с русским текстом',
    'session'   => 'session — авторизация пользователей',
];
foreach ($extensions as $ext => $label) {
    result('Среда выполнения', $label, extension_loaded($ext) ? 'ok' : 'err',
        extension_loaded($ext) ? 'Расширение загружено' : 'Расширение не загружено. Включите его в настройках PHP OpenServer.');
}

/* ---------- Файлы проекта ---------- */
$requiredFiles = [
    'index.php'                 => 'Главная страница (PHP-версия)',
    'config/config.php'         => 'Файл настроек подключения к БД',
    'includes/functions.php'    => 'Ядро приложения',
    'includes/header.php'       => 'Шапка сайта',
    'includes/footer.php'       => 'Подвал сайта',
    'assets/css/base.css'       => 'Базовые стили',
    'assets/css/app.css'        => 'Стили компонентов',
    'assets/css/theme.css'      => 'Современная тема оформления',
    'assets/js/app.js'          => 'Клиентские скрипты',
    'admin/index.php'           => 'Админ-панель',
    'sql/database.sql'          => 'Дамп базы данных',
];
foreach ($requiredFiles as $file => $label) {
    result('Файлы проекта', $label, is_file($root . '/' . $file) ? 'ok' : 'err',
        is_file($root . '/' . $file) ? $file : 'НЕ НАЙДЕН: ' . $file);
}

/* ---------- Признаки нового дизайна ---------- */
$themeFile = $root . '/assets/css/theme.css';
if (is_file($themeFile)) {
    $themeCss = (string) file_get_contents($themeFile);
    $isNew = str_contains($themeCss, 'hero-modern')
        && str_contains($themeCss, '--brand-500')
        && str_contains($themeCss, 'footer a');
    result('Новый дизайн', 'Содержимое theme.css', $isNew ? 'ok' : 'err',
        $isNew
            ? 'Тема v2 на месте (' . number_format(strlen($themeCss) / 1024, 1, ',', ' ') . ' КБ, обновлён ' . date('d.m.Y H:i', (int) filemtime($themeFile)) . ')'
            : 'Файл theme.css есть, но он от старой версии. Скопируйте файлы архива заново.');
} else {
    result('Новый дизайн', 'Содержимое theme.css', 'err', 'Файл не найден — новый дизайн не будет применён.');
}

/* ---------- Старые файлы, которые перекрывают новый сайт ---------- */
$oldCandidates = ['index.html', 'projects.html', 'services.html', 'contacts.html', 'prpject-template.html', 'styles.css'];
$oldFound = [];
foreach ($oldCandidates as $file) {
    if (is_file($root . '/' . $file)) {
        $oldFound[] = $file;
    }
}
foreach (glob($root . '/project-*.html') ?: [] as $file) {
    $oldFound[] = basename($file);
}
foreach (glob($root . '/service-*.html') ?: [] as $file) {
    $oldFound[] = basename($file);
}
if (is_dir($root . '/img')) {
    $oldFound[] = 'img/ (папка со старыми картинками)';
}

if ($oldFound) {
    result('Старые файлы', 'Найдены файлы прошлой версии сайта', 'err',
        'Обнаружено: ' . implode(', ', $oldFound) . '. Удалите их: пока файл index.html существует, '
        . 'сервер при открытии адреса без имени файла может показывать именно его — старый статический сайт.');
} else {
    result('Старые файлы', 'Файлов прошлой версии не найдено', 'ok', 'Папка очищена корректно.');
}

/* ---------- Расположение проекта ---------- */
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
$herePath = realpath($root);
if ($docRoot && $herePath && rtrim($docRoot, '/\\') !== rtrim($herePath, '/\\')) {
    result('Расположение проекта', 'Проект находится во вложенной папке', 'warn',
        'Корень домена: ' . $docRoot . ' · проект: ' . $herePath . '. '
        . 'Ссылки вида /assets/css/theme.css и /orders.php ведут в корень домена, поэтому вложенная папка может ломать стили и переходы. '
        . 'Лучше положить файлы проекта прямо в корень домена.');
} else {
    result('Расположение проекта', 'Проект в корне домена', 'ok', (string) $docRoot);
}

/* ---------- Подключение к базе данных ---------- */
$configPath = $root . '/config/config.php';
$pdo = null;

if (is_file($configPath)) {
    $config = @include $configPath;

    if (!is_array($config)) {
        result('База данных', 'Чтение config/config.php', 'err', 'Файл не возвращает массив настроек.');
    } else {
        $missing = array_diff(['host', 'dbname', 'user', 'password', 'charset'], array_keys($config));
        result('База данных', 'Параметры подключения', $missing ? 'err' : 'ok',
            $missing
                ? 'В config.php отсутствуют ключи: ' . implode(', ', $missing)
                : sprintf('host=%s · dbname=%s · user=%s · пароль %s',
                    $config['host'], $config['dbname'], $config['user'],
                    $config['password'] === '' ? 'пустой' : 'задан'));

        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['dbname'], $config['charset']);
            $pdo = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            result('База данных', 'Подключение к MySQL', 'ok',
                'Успешно. Сервер: ' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION));

            $tables = ['users', 'orders', 'responses', 'categories', 'services', 'messages'];
            foreach ($tables as $table) {
                try {
                    $count = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                    result('База данных', 'Таблица ' . $table, 'ok', 'записей: ' . $count);
                } catch (Throwable $e) {
                    result('База данных', 'Таблица ' . $table, 'err',
                        'Не найдена. Импортируйте sql/database.sql через phpMyAdmin.');
                }
            }
        } catch (Throwable $e) {
            result('База данных', 'Подключение к MySQL', 'err',
                $e->getMessage() . ' — проверьте: импортирован ли дамп sql/database.sql; '
                . 'имя модуля в поле host (в OpenServer укажите MySQL-8.0 или 127.0.0.1); пароль пользователя root.');
        }
    }
} else {
    result('База данных', 'Файл config/config.php', 'err', 'Файл не найден.');
}

/* ---------- Итоговое состояние ---------- */
if ($errCount > 0) {
    $summaryStatus = 'err';
    $summaryText = 'Найдены проблемы: ' . $errCount . '. Исправьте пункты, отмеченные красным.';
} elseif ($warnCount > 0) {
    $summaryStatus = 'warn';
    $summaryText = 'Критических ошибок нет, но есть предупреждения: ' . $warnCount . '.';
} else {
    $summaryStatus = 'ok';
    $summaryText = 'Всё в порядке: окружение, файлы и база данных готовы к работе.';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Диагностика установки — Высокие технологии</title>
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>
<main class="main">
<section class="page-header">
    <div class="container">
        <h1>Диагностика установки</h1>
        <p>Проверка окружения, файлов и базы данных проекта «Высокие технологии».
           Метка сборки: <strong><?= htmlspecialchars($build, ENT_QUOTES, 'UTF-8') ?></strong></p>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <div class="alert alert-<?= $summaryStatus === 'ok' ? 'success' : ($summaryStatus === 'warn' ? 'warning' : 'danger') ?>">
            <strong><?= $summaryText ?></strong>
            &nbsp;Успешно: <?= $okCount ?> · предупреждений: <?= $warnCount ?> · ошибок: <?= $errCount ?>
        </div>

        <div class="info-note mb-3">
            <strong>Проверка нового дизайна.</strong> Если вы видите эту страницу оформленной —
            с жёлтыми акцентами, скруглёнными панелями и тёмным подвалом — значит новые стили
            <code>theme.css</code> подключились. Если страница выглядит как «простой текст без оформления»,
            файлы архива лежат не в корне домена.
        </div>

        <?php foreach ($results as $group => $items): ?>
            <div class="panel">
                <div class="panel-head"><h2><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></h2></div>
                <div class="panel-body p-0">
                    <div class="table-wrap" style="border:0;box-shadow:none">
                        <table class="data-table">
                            <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td style="width:56px">
                                        <?php if ($item['status'] === 'ok'): ?>
                                            <span class="badge badge-success">OK</span>
                                        <?php elseif ($item['status'] === 'warn'): ?>
                                            <span class="badge badge-warning">!</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Ошибка</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cell-main" style="width:38%"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="cell-sub" style="font-size:.85rem;color:#44505C"><?= htmlspecialchars($item['details'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="panel">
            <div class="panel-head"><h2>Если сайт всё равно выглядит по-старому</h2></div>
            <div class="panel-body">
                <ol style="display:grid;gap:.7rem;padding-left:1.2rem;color:#333B45">
                    <li><strong>Удалите файлы прошлой версии</strong> из папки домена:
                        <code>index.html</code>, <code>styles.css</code>, <code>projects.html</code>,
                        <code>services.html</code>, <code>contacts.html</code>, все <code>project-*.html</code>,
                        <code>service-*.html</code> и папку <code>img/</code>. Новому приложению они не нужны.</li>
                    <li><strong>Проверьте структуру папки домена.</strong> Внутри должно лежать
                        сразу: <code>index.php</code>, <code>admin/</code>, <code>assets/</code>,
                        <code>config/</code>, <code>includes/</code>, <code>sql/</code>.
                        Если внутри оказалась папка <code>hightech/hightech/…</code> — поднимите файлы на уровень выше.</li>
                    <li><strong>Обновите страницу без кэша:</strong> Ctrl&nbsp;+&nbsp;F5 (Windows) или Cmd&nbsp;+&nbsp;Shift&nbsp;+&nbsp;R (macOS).</li>
                    <li><strong>Проверьте метку сборки</strong> в подвале сайта: там должно быть написано
                        «сборка <?= htmlspecialchars($build, ENT_QUOTES, 'UTF-8') ?>».
                        Если метки нет — вы открываете старые файлы, а не новый проект.</li>
                    <li><strong>Перезапустите OpenServer</strong> (меню «Перезапуск») и откройте сайт заново.</li>
                </ol>
            </div>
        </div>
    </div>
</section>
</main>
</body>
</html>
