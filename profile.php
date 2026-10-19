<?php
/**
 * Личный кабинет: редактирование профиля и смена пароля.
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

$pageTitle = 'Личный кабинет';
$activeNav = '';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'profile') {
        $name           = trim((string) ($_POST['name'] ?? ''));
        $phone          = trim((string) ($_POST['phone'] ?? ''));
        $company        = trim((string) ($_POST['company'] ?? ''));
        $specialization = trim((string) ($_POST['specialization'] ?? ''));
        $skills         = trim((string) ($_POST['skills'] ?? ''));
        $rate           = trim((string) ($_POST['rate'] ?? ''));
        $experience     = trim((string) ($_POST['experience'] ?? ''));
        $about          = trim((string) ($_POST['about'] ?? ''));

        if (mb_strlen($name) < 2) {
            $errors['name'] = 'Укажите имя или название организации.';
        }

        if (!$errors) {
            q(
                'UPDATE users
                    SET name = ?, phone = ?, company = ?, specialization = ?, skills = ?, rate = ?, experience = ?, about = ?
                  WHERE id = ?',
                [
                    $name,
                    $phone ?: null,
                    $user['role'] === 'customer' ? ($company ?: null) : null,
                    $user['role'] === 'freelancer' ? ($specialization ?: null) : null,
                    $user['role'] === 'freelancer' ? ($skills ?: null) : null,
                    $user['role'] === 'freelancer' && $rate !== '' ? max(0, (int) $rate) : null,
                    $user['role'] === 'freelancer' && $experience !== '' ? max(0, (int) $experience) : null,
                    $about ?: null,
                    (int) $user['id'],
                ]
            );
            flash('Профиль сохранён.', 'success');
            redirect('/profile.php');
        }
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $repeat  = (string) ($_POST['new_password_repeat'] ?? '');

        if (!password_verify($current, $user['password_hash'])) {
            $errors['current_password'] = 'Текущий пароль указан неверно.';
        }
        if (mb_strlen($new) < 6) {
            $errors['new_password'] = 'Новый пароль должен содержать не менее 6 символов.';
        }
        if ($new !== $repeat) {
            $errors['new_password_repeat'] = 'Пароли не совпадают.';
        }

        if (!$errors) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);
            flash('Пароль изменён.', 'success');
            redirect('/profile.php');
        }
    }
}

// Статистика пользователя
$stats = [];
if ($user['role'] === 'customer') {
    $stats = [
        ['label' => 'Заказов опубликовано', 'value' => (int) q_val('SELECT COUNT(*) FROM orders WHERE customer_id = ?', [$user['id']])],
        ['label' => 'Открытых заказов',     'value' => (int) q_val('SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status = "open"', [$user['id']])],
        ['label' => 'В работе',             'value' => (int) q_val('SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status = "in_progress"', [$user['id']])],
        ['label' => 'Завершено',            'value' => (int) q_val('SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status = "completed"', [$user['id']])],
    ];
} elseif ($user['role'] === 'freelancer') {
    $stats = [
        ['label' => 'Откликов отправлено', 'value' => (int) q_val('SELECT COUNT(*) FROM responses WHERE freelancer_id = ?', [$user['id']])],
        ['label' => 'Принято в работу',    'value' => (int) q_val('SELECT COUNT(*) FROM responses WHERE freelancer_id = ? AND status = "accepted"', [$user['id']])],
        ['label' => 'Заказов выполняется', 'value' => (int) q_val('SELECT COUNT(*) FROM orders WHERE freelancer_id = ? AND status = "in_progress"', [$user['id']])],
        ['label' => 'Завершено заказов',   'value' => (int) q_val('SELECT COUNT(*) FROM orders WHERE freelancer_id = ? AND status = "completed"', [$user['id']])],
    ];
} else {
    $stats = [
        ['label' => 'Пользователей', 'value' => (int) q_val('SELECT COUNT(*) FROM users')],
        ['label' => 'Заказов',       'value' => (int) q_val('SELECT COUNT(*) FROM orders')],
        ['label' => 'Откликов',      'value' => (int) q_val('SELECT COUNT(*) FROM responses')],
        ['label' => 'Сообщений',     'value' => (int) q_val('SELECT COUNT(*) FROM messages')],
    ];
}

require __DIR__ . '/includes/header.php';
?>

<section class="section-sm">
    <div class="container">
        <div class="profile-head">
            <span class="avatar avatar-lg"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
            <div>
                <h1><?= e($user['name']) ?></h1>
                <p><?= e($user['email']) ?>
                    <?= $user['phone'] ? ' · ' . e($user['phone']) : '' ?>
                </p>
                <span class="badge"><?= e(role_label($user['role'])) ?></span>
                <span class="badge"><?= e(user_status_label($user['status'])) ?></span>
            </div>
            <div class="profile-stats">
                <?php foreach ($stats as $stat): ?>
                    <div>
                        <strong><?= (int) $stat['value'] ?></strong>
                        <span><?= e($stat['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap mb-3">
            <?php if (is_customer()): ?>
                <a class="btn btn-primary" href="/order-form.php">Опубликовать заказ</a>
                <a class="btn btn-outline" href="/my-orders.php">Мои заказы</a>
            <?php elseif (is_freelancer()): ?>
                <a class="btn btn-primary" href="/orders.php">Найти заказ</a>
                <a class="btn btn-outline" href="/my-responses.php">Мои отклики</a>
            <?php endif; ?>
            <?php if (is_admin()): ?>
                <a class="btn btn-primary" href="/admin/index.php">Перейти в админ-панель</a>
            <?php endif; ?>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger">Проверьте правильность заполнения формы.</div>
        <?php endif; ?>

        <div class="grid grid-2">
            <div class="form-card">
                <h2>Данные профиля</h2>
                <form method="post" action="/profile.php" data-validate novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile">

                    <div class="form-group">
                        <label for="name">Имя / организация</label>
                        <input type="text" id="name" name="name" value="<?= e($user['name']) ?>" required class="<?= isset($errors['name']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($errors['name'])): ?><div class="error"><?= e($errors['name']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" value="<?= e($user['email']) ?>" disabled>
                            <div class="hint">Email изменяется только администратором</div>
                        </div>
                        <div class="form-group">
                            <label for="phone">Телефон</label>
                            <input type="tel" id="phone" name="phone" value="<?= e($user['phone']) ?>">
                        </div>
                    </div>

                    <?php if (is_customer()): ?>
                        <div class="form-group">
                            <label for="company">Компания</label>
                            <input type="text" id="company" name="company" value="<?= e($user['company']) ?>">
                        </div>
                    <?php endif; ?>

                    <?php if (is_freelancer()): ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="specialization">Специализация</label>
                                <input type="text" id="specialization" name="specialization" value="<?= e($user['specialization']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="rate">Ставка, ₽/час</label>
                                <input type="number" id="rate" name="rate" min="0" step="100" value="<?= e($user['rate']) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="experience">Опыт работы, лет</label>
                            <input type="number" id="experience" name="experience" min="0" max="60" value="<?= e($user['experience']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="skills">Навыки</label>
                            <input type="text" id="skills" name="skills" value="<?= e($user['skills']) ?>" placeholder="1С, REST API, СКД">
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="about">О себе</label>
                        <textarea id="about" name="about" rows="4" data-counter="#about-counter"><?= e($user['about']) ?></textarea>
                        <div class="hint" id="about-counter"></div>
                    </div>

                    <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                </form>
            </div>

            <div class="form-card">
                <h2>Смена пароля</h2>
                <form method="post" action="/profile.php" data-validate novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">

                    <div class="form-group">
                        <label for="current_password">Текущий пароль</label>
                        <input type="password" id="current_password" name="current_password" required class="<?= isset($errors['current_password']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($errors['current_password'])): ?><div class="error"><?= e($errors['current_password']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="new_password">Новый пароль</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6" class="<?= isset($errors['new_password']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($errors['new_password'])): ?><div class="error"><?= e($errors['new_password']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="new_password_repeat">Повтор нового пароля</label>
                        <input type="password" id="new_password_repeat" name="new_password_repeat" required class="<?= isset($errors['new_password_repeat']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($errors['new_password_repeat'])): ?><div class="error"><?= e($errors['new_password_repeat']) ?></div><?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-outline">Изменить пароль</button>
                </form>

                <div class="divider"></div>

                <h3 style="font-size:1rem;margin-bottom:.5rem">Информация об аккаунте</h3>
                <ul class="spec-list">
                    <li><span>Роль</span><span><?= e(role_label($user['role'])) ?></span></li>
                    <li><span>Дата регистрации</span><span><?= e(format_date($user['created_at'])) ?></span></li>
                    <li><span>Последний вход</span><span><?= e(format_datetime($user['last_login'])) ?></span></li>
                    <li><span>Статус</span><span><?= e(user_status_label($user['status'])) ?></span></li>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
