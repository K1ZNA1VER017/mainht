<?php
/**
 * Общие функции приложения: сессии, авторизация, безопасность,
 * вспомогательные функции форматирования и работы со справочниками.
 */

declare(strict_types=1);

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ----------------------------------------------------------------
 *  Базовые помощники
 * ---------------------------------------------------------------- */

/** Экранирование вывода в HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Редирект и завершение работы скрипта. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Сохранить сообщение для показа после редиректа. */
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

/** Получить и очистить flash-сообщения. */
function flashes(): array
{
    $list = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $list;
}

/** Текущий адрес страницы (для возврата после авторизации). */
function current_url(): string
{
    return $_SERVER['REQUEST_URI'] ?? '/';
}

/** Обрезка текста до нужной длины. */
function excerpt(?string $text, int $length = 180): string
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length - 1, 'UTF-8') . '…';
}

/* ----------------------------------------------------------------
 *  Безопасность (CSRF)
 * ---------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/** Проверка токена для POST-запросов. */
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Ошибка проверки безопасности: неверный CSRF-токен. Вернитесь назад и повторите действие.');
    }
}

/* ----------------------------------------------------------------
 *  Пользователь и права доступа
 * ---------------------------------------------------------------- */

function current_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $user = q_one(
        'SELECT * FROM users WHERE id = ? AND status = "active"',
        [(int) $_SESSION['user_id']]
    );

    if ($user === null) {
        unset($_SESSION['user_id']);
    }

    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_role(): string
{
    return current_user()['role'] ?? 'guest';
}

function is_admin(): bool
{
    return user_role() === 'admin';
}

function is_customer(): bool
{
    return user_role() === 'customer';
}

function is_freelancer(): bool
{
    return user_role() === 'freelancer';
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('Для выполнения действия необходимо войти в систему.', 'warning');
        redirect('/login.php?redirect=' . urlencode(current_url()));
    }
    return $user;
}

/** Доступ только для указанных ролей. */
function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        flash('У вас нет прав на выполнение этого действия.', 'danger');
        redirect('/index.php');
    }
    return $user;
}

/* ----------------------------------------------------------------
 *  Справочники подписей (человекочитаемые значения)
 * ---------------------------------------------------------------- */

function role_label(string $role): string
{
    return [
        'customer'   => 'Заказчик',
        'freelancer' => 'Фрилансер',
        'admin'      => 'Администратор',
    ][$role] ?? $role;
}

function order_status_label(string $status): string
{
    return [
        'open'        => 'Открыт',
        'in_progress' => 'В работе',
        'completed'   => 'Завершён',
        'cancelled'   => 'Отменён',
    ][$status] ?? $status;
}

function order_status_class(string $status): string
{
    return [
        'open'        => 'warning',
        'in_progress' => 'info',
        'completed'   => 'success',
        'cancelled'   => 'danger',
    ][$status] ?? 'muted';
}

function response_status_label(string $status): string
{
    return [
        'pending'  => 'Ожидает решения',
        'accepted' => 'Принят',
        'rejected' => 'Отклонён',
    ][$status] ?? $status;
}

function response_status_class(string $status): string
{
    return ['pending' => 'warning', 'accepted' => 'success', 'rejected' => 'danger'][$status] ?? 'muted';
}

function complexity_label(string $complexity): string
{
    return ['low' => 'Низкая', 'medium' => 'Средняя', 'high' => 'Высокая'][$complexity] ?? $complexity;
}

function user_status_label(string $status): string
{
    return ['active' => 'Активен', 'blocked' => 'Заблокирован'][$status] ?? $status;
}

/** Список доступных статусов заказа для выпадающих списков. */
function order_statuses(): array
{
    return ['open', 'in_progress', 'completed', 'cancelled'];
}

function complexities(): array
{
    return ['low', 'medium', 'high'];
}

/* ----------------------------------------------------------------
 *  Форматирование
 * ---------------------------------------------------------------- */

function money(?int $amount): string
{
    return number_format((float) $amount, 0, ',', ' ') . ' ₽';
}

function format_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : '—';
}

function format_datetime(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y H:i', $ts) : '—';
}

/** «3 дня назад» / «через 5 дней» — подпись для даты сдачи. */
function deadline_label(?string $date): string
{
    if (!$date) {
        return 'срок не указан';
    }
    $diff = (int) floor((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
    if ($diff === 0) {
        return 'сдать сегодня';
    }
    if ($diff > 0) {
        return 'осталось ' . $diff . ' ' . plural($diff, ['день', 'дня', 'дней']);
    }
    $diff = abs($diff);
    return 'просрочен на ' . $diff . ' ' . plural($diff, ['день', 'дня', 'дней']);
}

/** Склонение существительного по числу: plural(3, ['день','дня','дней']). */
function plural(int $n, array $forms): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) {
        return $forms[2];
    }
    if ($n1 > 1 && $n1 < 5) {
        return $forms[1];
    }
    if ($n1 === 1) {
        return $forms[0];
    }
    return $forms[2];
}

/** Превращает строку навыков «1С, API» в массив тегов. */
function skills_array(?string $skills): array
{
    if (!$skills) {
        return [];
    }
    $parts = preg_split('/\s*,\s*/u', $skills, -1, PREG_SPLIT_NO_EMPTY);
    return $parts ?: [];
}

/** Особенности услуги: одна строка = один пункт списка. */
function features_array(?string $features): array
{
    if (!$features) {
        return [];
    }
    $lines = preg_split('/\r\n|\r|\n/u', $features, -1, PREG_SPLIT_NO_EMPTY);
    return array_map('trim', $lines ?: []);
}

/** Транслитерация русского текста в символьный код (например, для slug). */
function translit(string $text): string
{
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', ' ' => '-', '_' => '-',
    ];

    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9\-]+/u', '-', $text) ?? '';
    $text = preg_replace('/-+/', '-', $text) ?? '';

    return trim($text, '-');
}

/** Ссылка на статический ресурс с версией по времени изменения файла.
 *  Защищает от кэша браузера, когда CSS или JS обновляются. */
function asset(string $path): string
{
    $file = __DIR__ . '/../' . ltrim($path, '/');
    $version = is_file($file) ? filemtime($file) : time();
    return '/' . ltrim($path, '/') . '?v=' . $version;
}

/** Ссылка на иконку из assets/img с проверкой существования файла. */
function icon_url(?string $file, string $fallback = 'bulb.png'): string
{
    $file = $file ?: $fallback;
    $path = __DIR__ . '/../assets/img/' . $file;
    if (!is_file($path)) {
        $file = $fallback;
    }
    return '/assets/img/' . rawurlencode($file);
}

/* ----------------------------------------------------------------
 *  Пагинация
 * ---------------------------------------------------------------- */

/**
 * Простая пагинация: возвращает смещение и массив страниц для вывода.
 */
function paginate(int $total, int $perPage = 9): array
{
    $perPage = max(1, $perPage);
    $pages   = max(1, (int) ceil($total / $perPage));
    $current = max(1, min($pages, (int) ($_GET['page'] ?? 1)));

    return [
        'total'    => $total,
        'per_page' => $perPage,
        'pages'    => $pages,
        'current'  => $current,
        'offset'   => ($current - 1) * $perPage,
    ];
}

/** Сборка URL с сохранением текущих GET-параметров. */
function url_with(array $params): string
{
    $query = array_merge($_GET, $params);
    $query = array_filter($query, static fn($v) => $v !== '' && $v !== null);
    $base  = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return $query ? $base . '?' . http_build_query($query) : $base;
}
