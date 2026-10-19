<?php
/**
 * Общая «шапка» публичных страниц сайта.
 * Ожидает переменные: $pageTitle (необязательно), $activeNav (необязательно).
 */
require_once __DIR__ . '/functions.php';

$user      = current_user();
$pageTitle = $pageTitle ?? 'Высокие технологии';
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — Высокие технологии</title>
    <link rel="stylesheet" href="<?= asset('assets/css/base.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/theme.css') ?>">
</head>
<body>
<header class="header">
    <div class="container">
        <a class="logo" href="/index.php">
            <img src="/assets/img/logo.png" alt="Высокие технологии" class="logo-img">
        </a>

        <nav class="nav" id="mainNav">
            <a href="/index.php"       class="nav-link <?= $activeNav === 'home' ? 'active' : '' ?>">Главная</a>
            <a href="/orders.php"      class="nav-link <?= $activeNav === 'orders' ? 'active' : '' ?>">Заказы</a>
            <a href="/freelancers.php" class="nav-link <?= $activeNav === 'freelancers' ? 'active' : '' ?>">Исполнители</a>
            <a href="/services.php"    class="nav-link <?= $activeNav === 'services' ? 'active' : '' ?>">Услуги</a>
            <a href="/contacts.php"    class="nav-link <?= $activeNav === 'contacts' ? 'active' : '' ?>">Контакты</a>
        </nav>

        <button class="nav-toggle" type="button" aria-label="Меню" aria-expanded="false"
                onclick="this.setAttribute('aria-expanded', this.getAttribute('aria-expanded') === 'true' ? 'false' : 'true'); document.getElementById('mainNav').classList.toggle('nav-open');">
            <span></span><span></span><span></span>
        </button>

        <div class="header-user">
            <?php if ($user): ?>
                <div class="user-menu" data-dropdown>
                    <button class="user-menu-toggle" type="button">
                        <span class="user-avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                        <span class="user-menu-name"><?= e(excerpt($user['name'], 18)) ?></span>
                        <span class="user-menu-arrow">▾</span>
                    </button>
                    <div class="user-menu-list">
                        <div class="user-menu-head">
                            <strong><?= e($user['name']) ?></strong>
                            <span class="badge badge-muted"><?= e(role_label($user['role'])) ?></span>
                        </div>
                        <a href="/profile.php">Личный кабинет</a>
                        <?php if (is_customer()): ?>
                            <a href="/order-form.php">Опубликовать заказ</a>
                            <a href="/my-orders.php">Мои заказы</a>
                        <?php elseif (is_freelancer()): ?>
                            <a href="/my-responses.php">Мои отклики</a>
                            <a href="/orders.php">Лента заказов</a>
                        <?php elseif (is_admin()): ?>
                            <a href="/admin/index.php">Админ-панель</a>
                        <?php endif; ?>
                        <a href="/logout.php" class="user-menu-logout">Выход</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/login.php" class="btn btn-sm btn-ghost">Войти</a>
                <a href="/register.php" class="btn btn-sm btn-primary">Регистрация</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php $flashMessages = flashes(); ?>
<?php if ($flashMessages): ?>
    <div class="container flash-wrapper">
        <?php foreach ($flashMessages as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main class="main">
