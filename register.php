<?php
/**
 * Регистрация пользователей: заказчик или фрилансер.
 */
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('/profile.php');
}

$pageTitle = 'Регистрация';
$activeNav = '';

$data = [
    'role'           => 'customer',
    'name'           => '',
    'email'          => '',
    'phone'          => '',
    'company'        => '',
    'specialization' => '',
    'skills'         => '',
    'rate'           => '',
    'experience'     => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $data['role']           = ($_POST['role'] ?? 'customer') === 'freelancer' ? 'freelancer' : 'customer';
    $data['name']           = trim((string) ($_POST['name'] ?? ''));
    $data['email']          = trim((string) ($_POST['email'] ?? ''));
    $data['phone']          = trim((string) ($_POST['phone'] ?? ''));
    $data['company']        = trim((string) ($_POST['company'] ?? ''));
    $data['specialization'] = trim((string) ($_POST['specialization'] ?? ''));
    $data['skills']         = trim((string) ($_POST['skills'] ?? ''));
    $data['rate']           = trim((string) ($_POST['rate'] ?? ''));
    $data['experience']     = trim((string) ($_POST['experience'] ?? ''));

    $password = (string) ($_POST['password'] ?? '');
    $repeat   = (string) ($_POST['password_repeat'] ?? '');

    if (mb_strlen($data['name']) < 2) {
        $errors['name'] = 'Укажите имя или название организации.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email.';
    } elseif (q_val('SELECT id FROM users WHERE email = ?', [$data['email']])) {
        $errors['email'] = 'Пользователь с таким email уже зарегистрирован.';
    }
    if (mb_strlen($password) < 6) {
        $errors['password'] = 'Пароль должен содержать не менее 6 символов.';
    }
    if ($password !== $repeat) {
        $errors['password_repeat'] = 'Пароли не совпадают.';
    }
    if (empty($_POST['agreement'])) {
        $errors['agreement'] = 'Необходимо согласие на обработку персональных данных.';
    }
    if ($data['role'] === 'freelancer' && mb_strlen($data['specialization']) < 3) {
        $errors['specialization'] = 'Укажите вашу специализацию (например, «Программист 1С»).';
    }

    if (!$errors) {
        q(
            'INSERT INTO users (role, name, email, password_hash, phone, company, specialization, skills, rate, experience)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['role'],
                $data['name'],
                $data['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $data['phone'] ?: null,
                $data['role'] === 'customer' ? ($data['company'] ?: null) : null,
                $data['role'] === 'freelancer' ? $data['specialization'] : null,
                $data['role'] === 'freelancer' ? ($data['skills'] ?: null) : null,
                $data['role'] === 'freelancer' && $data['rate'] !== '' ? max(0, (int) $data['rate']) : null,
                $data['role'] === 'freelancer' && $data['experience'] !== '' ? max(0, (int) $data['experience']) : null,
            ]
        );

        $_SESSION['user_id'] = last_id();
        flash('Регистрация завершена. Добро пожаловать на платформу!', 'success');
        redirect($data['role'] === 'customer' ? '/order-form.php' : '/orders.php');
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="section-sm">
    <div class="container">
        <div class="auth-wrap">
            <div class="auth-promo">
                <h2>Присоединяйтесь к платформе</h2>
                <p class="text-muted">
                    «Высокие технологии» — официальный франчайзи «1С». Мы соединяем компании,
                    которым нужна автоматизация, с проверенными исполнителями.
                </p>
                <ul>
                    <li>
                        <span class="promo-check">1</span>
                        <span>
                            <strong>Заказчикам</strong>
                            Публикуйте заказы, получайте отклики и выбирайте исполнителя по цене и опыту.
                        </span>
                    </li>
                    <li>
                        <span class="promo-check">2</span>
                        <span>
                            <strong>Фрилансерам</strong>
                            Находите проекты по 1С, интеграциям и аналитике, откликайтесь в один клик.
                        </span>
                    </li>
                    <li>
                        <span class="promo-check">3</span>
                        <span>
                            <strong>Контроль качества</strong>
                            Администратор платформы модерирует пользователей, заказы и отклики.
                        </span>
                    </li>
                </ul>
            </div>

            <div class="auth-card">
                <h1>Регистрация</h1>
                <p class="text-muted mb-2">Выберите роль — от неё зависят возможности кабинета.</p>

                <form method="post" action="/register.php" data-validate novalidate>
                    <?= csrf_field() ?>

                    <div class="role-picker">
                        <label class="role-option">
                            <input type="radio" name="role" value="customer" <?= $data['role'] === 'customer' ? 'checked' : '' ?>>
                            <span>
                                <strong>Заказчик</strong>
                                <em>Публикую заказы и выбираю исполнителей</em>
                            </span>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="freelancer" <?= $data['role'] === 'freelancer' ? 'checked' : '' ?>>
                            <span>
                                <strong>Фрилансер</strong>
                                <em>Откликаюсь на заказы и выполняю работы</em>
                            </span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="name">Имя / организация *</label>
                        <input type="text" id="name" name="name" value="<?= e($data['name']) ?>" required>
                        <?php if (!empty($errors['name'])): ?><div class="error"><?= e($errors['name']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" value="<?= e($data['email']) ?>" required>
                            <?php if (!empty($errors['email'])): ?><div class="error"><?= e($errors['email']) ?></div><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="phone">Телефон</label>
                            <input type="tel" id="phone" name="phone" value="<?= e($data['phone']) ?>" placeholder="+7 (___) ___-__-__">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Пароль *</label>
                            <input type="password" id="password" name="password" data-password required minlength="6">
                            <div class="hint">Минимум 6 символов</div>
                            <?php if (!empty($errors['password'])): ?><div class="error"><?= e($errors['password']) ?></div><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="password_repeat">Повтор пароля *</label>
                            <input type="password" id="password_repeat" name="password_repeat" data-password-repeat required>
                            <?php if (!empty($errors['password_repeat'])): ?><div class="error"><?= e($errors['password_repeat']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div data-role-fields="customer">
                        <div class="form-group">
                            <label for="company">Компания</label>
                            <input type="text" id="company" name="company" value="<?= e($data['company']) ?>" placeholder="ООО «Ваша компания»">
                        </div>
                    </div>

                    <div data-role-fields="freelancer">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="specialization">Специализация *</label>
                                <input type="text" id="specialization" name="specialization" value="<?= e($data['specialization']) ?>" placeholder="Программист 1С">
                                <?php if (!empty($errors['specialization'])): ?><div class="error"><?= e($errors['specialization']) ?></div><?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="rate">Ставка, ₽/час</label>
                                <input type="number" id="rate" name="rate" min="0" step="100" value="<?= e($data['rate']) ?>" placeholder="1500">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="experience">Опыт работы, лет</label>
                            <input type="number" id="experience" name="experience" min="0" max="60" value="<?= e($data['experience']) ?>" placeholder="5">
                        </div>
                        <div class="form-group">
                            <label for="skills">Навыки</label>
                            <input type="text" id="skills" name="skills" value="<?= e($data['skills']) ?>" placeholder="1С, REST API, СКД">
                            <div class="hint">Перечислите навыки через запятую</div>
                        </div>
                    </div>

                    <div class="form-group checkbox-row">
                        <input type="checkbox" id="agreement" name="agreement" value="1" required>
                        <label for="agreement">Я согласен на обработку персональных данных</label>
                    </div>
                    <?php if (!empty($errors['agreement'])): ?><div class="error mb-2"><?= e($errors['agreement']) ?></div><?php endif; ?>

                    <button type="submit" class="btn btn-primary btn-block">Зарегистрироваться</button>
                </form>

                <div class="auth-footer">
                    Уже есть аккаунт? <a href="/login.php">Войти</a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Показываем поля, соответствующие выбранной роли
(function () {
    var radios = document.querySelectorAll('input[name="role"]');
    function toggle() {
        var role = document.querySelector('input[name="role"]:checked').value;
        document.querySelectorAll('[data-role-fields]').forEach(function (block) {
            block.style.display = block.getAttribute('data-role-fields') === role ? '' : 'none';
        });
    }
    radios.forEach(function (r) { r.addEventListener('change', toggle); });
    toggle();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
