<?php
/**
 * Авторизация пользователей.
 */
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('/profile.php');
}

$pageTitle = 'Вход';
$activeNav = '';

$email    = '';
$redirect = (string) ($_GET['redirect'] ?? '/profile.php');
if (!str_starts_with($redirect, '/')) {
    $redirect = '/profile.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $redirect = (string) ($_POST['redirect'] ?? '/profile.php');
    if (!str_starts_with($redirect, '/')) {
        $redirect = '/profile.php';
    }

    $user = q_one('SELECT * FROM users WHERE email = ?', [$email]);

    if ($user === null || !password_verify($password, $user['password_hash'])) {
        flash('Неверный email или пароль.', 'danger');
    } elseif ($user['status'] === 'blocked') {
        flash('Учётная запись заблокирована. Обратитесь к администратору платформы.', 'danger');
    } else {
        $_SESSION['user_id'] = (int) $user['id'];
        session_regenerate_id(true);
        q('UPDATE users SET last_login = NOW() WHERE id = ?', [(int) $user['id']]);

        flash('Вы вошли как ' . $user['name'] . '.', 'success');
        redirect($user['role'] === 'admin' ? '/admin/index.php' : $redirect);
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="section-sm">
    <div class="container">
        <div class="auth-wrap" style="grid-template-columns:1fr;max-width:520px;margin:0 auto">
            <div class="auth-card">
                <h1>Вход в кабинет</h1>
                <p class="text-muted mb-2">Введите данные вашей учётной записи.</p>

                <form method="post" action="/login.php" data-validate novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password">Пароль</label>
                        <input type="password" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Войти</button>
                </form>

                <div class="auth-footer">
                    Нет аккаунта? <a href="/register.php">Зарегистрироваться</a>
                </div>

                <div class="info-note mt-3">
                    <strong>Демонстрационные доступы:</strong><br>
                    администратор — admin@hightech.ru / admin123<br>
                    заказчик — customer@hightech.ru / customer123<br>
                    фрилансер — freelancer@hightech.ru / freelancer123
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
