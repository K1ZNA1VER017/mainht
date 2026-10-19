<?php
/**
 * Шапка админ-панели: проверка прав, боковое меню, счётчики разделов.
 * Ожидает переменные: $pageTitle, $adminNav (ключ активного пункта), $breadcrumb.
 */
require_once __DIR__ . '/../includes/functions.php';

$admin = require_role(['admin']);

$pageTitle  = $pageTitle ?? 'Админ-панель';
$adminNav   = $adminNav ?? 'dashboard';
$breadcrumb = $breadcrumb ?? 'Админ-панель';

$counters = [
    'users'      => (int) q_val('SELECT COUNT(*) FROM users'),
    'orders'     => (int) q_val('SELECT COUNT(*) FROM orders'),
    'open'       => (int) q_val('SELECT COUNT(*) FROM orders WHERE status = "open"'),
    'responses'  => (int) q_val('SELECT COUNT(*) FROM responses'),
    'pending'    => (int) q_val('SELECT COUNT(*) FROM responses WHERE status = "pending"'),
    'categories' => (int) q_val('SELECT COUNT(*) FROM categories'),
    'services'   => (int) q_val('SELECT COUNT(*) FROM services'),
    'messages'   => (int) q_val('SELECT COUNT(*) FROM messages WHERE status = "new"'),
];

$menu = [
    'dashboard'  => ['url' => '/admin/index.php',      'label' => 'Дашборд',        'count' => null],
    'users'      => ['url' => '/admin/users.php',      'label' => 'Пользователи',   'count' => $counters['users']],
    'orders'     => ['url' => '/admin/orders.php',     'label' => 'Заказы',         'count' => $counters['orders']],
    'responses'  => ['url' => '/admin/responses.php',  'label' => 'Отклики',        'count' => $counters['responses']],
    'categories' => ['url' => '/admin/categories.php', 'label' => 'Категории',      'count' => $counters['categories']],
    'services'   => ['url' => '/admin/services.php',   'label' => 'Услуги',         'count' => $counters['services']],
    'messages'   => ['url' => '/admin/messages.php',   'label' => 'Сообщения',      'count' => $counters['messages']],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — Админ-панель</title>
    <link rel="stylesheet" href="<?= asset('assets/css/base.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/theme.css') ?>">
</head>
<body class="admin-body">
<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="admin-logo">
            <img src="/assets/img/logo.png" alt="Высокие технологии">
            <span>Панель администратора</span>
        </div>

        <nav class="admin-nav">
            <div class="admin-nav-title">Управление</div>
            <?php foreach ($menu as $key => $item): ?>
                <a href="<?= e($item['url']) ?>" class="<?= $adminNav === $key ? 'active' : '' ?>">
                    <span><?= e($item['label']) ?></span>
                    <?php if ($item['count'] !== null): ?>
                        <span class="count"><?= (int) $item['count'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar-foot">
            <a href="/index.php" target="_blank">← Открыть сайт</a>
            <a href="/profile.php">Профиль: <?= e(excerpt($admin['name'], 16)) ?></a>
            <a href="/logout.php">Выход</a>
            <span style="display:block;padding:.5rem .75rem 0;font-size:.72rem;color:#5F6A77">сборка <?= APP_BUILD ?></span>
        </div>
    </aside>

    <div class="admin-content">
        <div class="admin-topbar">
            <div>
                <div class="breadcrumb">Админ-панель / <?= e($breadcrumb) ?></div>
                <h1><?= e($pageTitle) ?></h1>
            </div>
            <div class="d-flex gap-1 flex-wrap">
                <?php if ($counters['open'] > 0): ?>
                    <a class="btn btn-sm btn-outline" href="/admin/orders.php?status=open">Открытых заказов: <?= $counters['open'] ?></a>
                <?php endif; ?>
                <?php if ($counters['messages'] > 0): ?>
                    <a class="btn btn-sm btn-outline" href="/admin/messages.php?status=new">Новых сообщений: <?= $counters['messages'] ?></a>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach (flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
