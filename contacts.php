<?php
/**
 * Контакты и форма обратной связи (сообщения сохраняются в таблицу messages).
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Контакты';
$activeNav = 'contacts';

$services = q_all('SELECT title FROM services WHERE is_active = 1 ORDER BY sort_order');

$data = ['name' => '', 'company' => '', 'email' => '', 'phone' => '', 'service' => '', 'message' => ''];
$errors = [];
$sent = false;

$user = current_user();
if ($user) {
    $data['name']  = $user['name'];
    $data['email'] = $user['email'];
    $data['phone'] = (string) $user['phone'];
    $data['company'] = (string) $user['company'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (array_keys($data) as $key) {
        $data[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if (mb_strlen($data['name']) < 2) {
        $errors['name'] = 'Укажите имя.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email.';
    }
    if (mb_strlen($data['message']) < 10) {
        $errors['message'] = 'Опишите вопрос подробнее (не менее 10 символов).';
    }
    if (empty($_POST['agreement'])) {
        $errors['agreement'] = 'Необходимо согласие на обработку персональных данных.';
    }

    if (!$errors) {
        q(
            'INSERT INTO messages (name, email, phone, company, service, message)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['name'],
                $data['email'],
                $data['phone'] ?: null,
                $data['company'] ?: null,
                $data['service'] ?: null,
                $data['message'],
            ]
        );

        $sent = true;
        flash('Сообщение отправлено. Менеджер свяжется с вами в рабочее время.', 'success');
        $data = ['name' => '', 'company' => '', 'email' => '', 'phone' => '', 'service' => '', 'message' => ''];
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <h1>Свяжитесь с нами</h1>
        <p>Мы готовы ответить на ваши вопросы и предложить оптимальное решение</p>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <div class="contacts-grid">
            <div class="contact-info">
                <h2>Наши контакты</h2>

                <div class="contact-item">
                    <img class="contact-icon" src="/assets/img/marker.png" alt="">
                    <div class="contact-details">
                        <h3>Адрес</h3>
                        <p>г. Москва, ул. Примерная, д. 123<br>Бизнес-центр «Технопарк»</p>
                    </div>
                </div>

                <div class="contact-item">
                    <img class="contact-icon" src="/assets/img/phone.png" alt="">
                    <div class="contact-details">
                        <h3>Телефон</h3>
                        <p>+7 (495) 123-45-67</p>
                        <p>+7 (800) 123-45-68 (бесплатный)</p>
                    </div>
                </div>

                <div class="contact-item">
                    <img class="contact-icon" src="/assets/img/envelope.png" alt="">
                    <div class="contact-details">
                        <h3>Email</h3>
                        <p>info@hightech.ru</p>
                        <p>support@hightech.ru</p>
                    </div>
                </div>

                <div class="contact-item">
                    <img class="contact-icon" src="/assets/img/clock.png" alt="">
                    <div class="contact-details">
                        <h3>Время работы</h3>
                        <p>Пн–Пт: 9:00 — 18:00</p>
                        <p>Сб–Вс: выходные</p>
                    </div>
                </div>

                <div class="info-note mt-3">
                    Уже зарегистрированы? Опубликуйте заказ в <a href="/order-form.php">личном кабинете</a> —
                    так вы быстрее получите отклики исполнителей.
                </div>
            </div>

            <div class="contact-form-container">
                <h2>Форма обратной связи</h2>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">Проверьте заполнение формы.</div>
                <?php endif; ?>

                <form class="contact-form" method="post" action="/contacts.php" data-validate novalidate>
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label for="name">Ваше имя *</label>
                        <input type="text" id="name" name="name" value="<?= e($data['name']) ?>" required
                               class="<?= isset($errors['name']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($errors['name'])): ?><div class="error"><?= e($errors['name']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="company">Компания</label>
                        <input type="text" id="company" name="company" value="<?= e($data['company']) ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" value="<?= e($data['email']) ?>" required
                                   class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>">
                            <?php if (isset($errors['email'])): ?><div class="error"><?= e($errors['email']) ?></div><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="phone">Телефон</label>
                            <input type="tel" id="phone" name="phone" value="<?= e($data['phone']) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="service">Интересующая услуга</label>
                        <select id="service" name="service">
                            <option value="">Выберите услугу</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= e($service['title']) ?>" <?= $data['service'] === $service['title'] ? 'selected' : '' ?>>
                                    <?= e($service['title']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="Другое" <?= $data['service'] === 'Другое' ? 'selected' : '' ?>>Другое</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message">Сообщение *</label>
                        <textarea id="message" name="message" rows="5" required
                                  data-counter="#message-counter"
                                  placeholder="Опишите вашу задачу или вопрос..."
                                  class="<?= isset($errors['message']) ? 'is-invalid' : '' ?>"><?= e($data['message']) ?></textarea>
                        <div class="hint" id="message-counter"></div>
                        <?php if (isset($errors['message'])): ?><div class="error"><?= e($errors['message']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-group checkbox-row">
                        <input type="checkbox" id="agreement" name="agreement" value="1" required>
                        <label for="agreement">Я согласен на обработку персональных данных</label>
                    </div>
                    <?php if (isset($errors['agreement'])): ?><div class="error mb-2"><?= e($errors['agreement']) ?></div><?php endif; ?>

                    <button type="submit" class="btn btn-primary btn-block">Отправить сообщение</button>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="map-section">
    <div class="container">
        <h2>Как нас найти</h2>
        <div class="map-placeholder">
            <div class="map-content">
                <div class="map-icon">🗺️</div>
                <h3>Карта проезда</h3>
                <p>г. Москва, ул. Примерная, д. 123</p>
                <p>Ближайшее метро: «Технологическая»</p>
                <div class="transport-info">
                    <div class="transport-item"><span class="transport-icon">🚇</span><span>5 минут от метро</span></div>
                    <div class="transport-item"><span class="transport-icon">🚗</span><span>Парковка у офиса</span></div>
                    <div class="transport-item"><span class="transport-icon">🚌</span><span>Остановка у входа</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
