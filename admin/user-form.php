<?php
/**
 * Создание и редактирование пользователя (админ-панель).
 */
$pageTitle  = 'Пользователь';
$adminNav   = 'users';
$breadcrumb = 'Пользователи / Карточка';

require __DIR__ . '/_header.php';

$id = (int) ($_GET['id'] ?? 0);

$userRow = $id > 0 ? q_one('SELECT * FROM users WHERE id = ?', [$id]) : null;
if ($id > 0 && $userRow === null) {
    flash('Пользователь не найден.', 'danger');
    redirect('/admin/users.php');
}

$pageTitle = $userRow ? 'Редактирование: ' . $userRow['name'] : 'Новый пользователь';

$data = [
    'role'           => $userRow['role']           ?? 'customer',
    'name'           => $userRow['name']           ?? '',
    'email'          => $userRow['email']          ?? '',
    'phone'          => $userRow['phone']          ?? '',
    'company'        => $userRow['company']        ?? '',
    'specialization' => $userRow['specialization'] ?? '',
    'skills'         => $userRow['skills']         ?? '',
    'rate'           => $userRow['rate']           ?? '',
    'experience'     => $userRow['experience']     ?? '',
    'about'          => $userRow['about']          ?? '',
    'status'         => $userRow['status']         ?? 'active',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (array_keys($data) as $key) {
        $data[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    if (!in_array($data['role'], ['customer', 'freelancer', 'admin'], true)) {
        $data['role'] = 'customer';
    }
    if (!in_array($data['status'], ['active', 'blocked'], true)) {
        $data['status'] = 'active';
    }

    $password = (string) ($_POST['password'] ?? '');

    if (mb_strlen($data['name']) < 2) {
        $errors['name'] = 'Укажите имя или название организации.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email.';
    } else {
        $exists = q_val('SELECT id FROM users WHERE email = ? AND id <> ?', [$data['email'], $id]);
        if ($exists) {
            $errors['email'] = 'Такой email уже используется.';
        }
    }
    if (!$userRow && mb_strlen($password) < 6) {
        $errors['password'] = 'Задайте пароль не короче 6 символов.';
    }
    if ($password !== '' && mb_strlen($password) < 6) {
        $errors['password'] = 'Пароль должен содержать не менее 6 символов.';
    }

    if (!$errors) {
        $params = [
            $data['role'],
            $data['name'],
            $data['email'],
            $data['phone'] ?: null,
            $data['company'] ?: null,
            $data['specialization'] ?: null,
            $data['skills'] ?: null,
            $data['rate'] !== '' ? max(0, (int) $data['rate']) : null,
            $data['experience'] !== '' ? max(0, (int) $data['experience']) : null,
            $data['about'] ?: null,
            $data['status'],
        ];

        if ($userRow) {
            $baseSql = 'UPDATE users SET role = ?, name = ?, email = ?, phone = ?, company = ?, specialization = ?,
                            skills = ?, rate = ?, experience = ?, about = ?, status = ?';

            if ($password !== '') {
                q($baseSql . ', password_hash = ? WHERE id = ?', array_merge($params, [password_hash($password, PASSWORD_DEFAULT), (int) $userRow['id']]));
            } else {
                q($baseSql . ' WHERE id = ?', array_merge($params, [(int) $userRow['id']]));
            }

            flash('Данные пользователя сохранены.', 'success');
            redirect('/admin/users.php');
        }

        $params[] = password_hash($password, PASSWORD_DEFAULT);
        q(
            'INSERT INTO users (role, name, email, phone, company, specialization, skills, rate, experience, about, status, password_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $params
        );

        flash('Пользователь создан.', 'success');
        redirect('/admin/users.php');
    }
}
?>

<div class="panel">
    <div class="panel-head">
        <h2><?= $userRow ? 'Карточка пользователя #' . (int) $userRow['id'] : 'Новый пользователь' ?></h2>
        <a class="btn btn-sm btn-ghost" href="/admin/users.php">← К списку</a>
    </div>
    <div class="panel-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger">Исправьте ошибки в форме.</div>
        <?php endif; ?>

        <form method="post" action="/admin/user-form.php<?= $userRow ? '?id=' . (int) $userRow['id'] : '' ?>" data-validate novalidate>
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="role">Роль *</label>
                    <select id="role" name="role" required>
                        <option value="customer"   <?= $data['role'] === 'customer' ? 'selected' : '' ?>>Заказчик</option>
                        <option value="freelancer" <?= $data['role'] === 'freelancer' ? 'selected' : '' ?>>Фрилансер</option>
                        <option value="admin"      <?= $data['role'] === 'admin' ? 'selected' : '' ?>>Администратор</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Статус</label>
                    <select id="status" name="status">
                        <option value="active"  <?= $data['status'] === 'active' ? 'selected' : '' ?>>Активен</option>
                        <option value="blocked" <?= $data['status'] === 'blocked' ? 'selected' : '' ?>>Заблокирован</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Имя / организация *</label>
                    <input type="text" id="name" name="name" value="<?= e($data['name']) ?>" required
                           class="<?= isset($errors['name']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errors['name'])): ?><div class="error"><?= e($errors['name']) ?></div><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="<?= e($data['email']) ?>" required
                           class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errors['email'])): ?><div class="error"><?= e($errors['email']) ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Телефон</label>
                    <input type="tel" id="phone" name="phone" value="<?= e($data['phone']) ?>">
                </div>
                <div class="form-group">
                    <label for="password"><?= $userRow ? 'Новый пароль (если нужно сменить)' : 'Пароль *' ?></label>
                    <input type="password" id="password" name="password" <?= $userRow ? '' : 'required' ?> minlength="6"
                           class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errors['password'])): ?><div class="error"><?= e($errors['password']) ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="company">Компания (для заказчика)</label>
                    <input type="text" id="company" name="company" value="<?= e($data['company']) ?>">
                </div>
                <div class="form-group">
                    <label for="specialization">Специализация (для фрилансера)</label>
                    <input type="text" id="specialization" name="specialization" value="<?= e($data['specialization']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="rate">Ставка, ₽/час</label>
                    <input type="number" id="rate" name="rate" min="0" step="100" value="<?= e($data['rate']) ?>">
                </div>
                <div class="form-group">
                    <label for="experience">Опыт, лет</label>
                    <input type="number" id="experience" name="experience" min="0" max="60" value="<?= e($data['experience']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="skills">Навыки</label>
                <input type="text" id="skills" name="skills" value="<?= e($data['skills']) ?>" placeholder="1С, REST API, СКД">
            </div>

            <div class="form-group">
                <label for="about">О пользователе</label>
                <textarea id="about" name="about" rows="4"><?= e($data['about']) ?></textarea>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary"><?= $userRow ? 'Сохранить' : 'Создать пользователя' ?></button>
                <a class="btn btn-ghost" href="/admin/users.php">Отмена</a>
            </div>
        </form>
    </div>
</div>

<?php if ($userRow): ?>
    <div class="panel">
        <div class="panel-head"><h2>Связанные записи</h2></div>
        <div class="panel-body">
            <?php if ($userRow['role'] === 'customer'): ?>
                <?php $userOrders = q_all('SELECT id, title, budget, status FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 10', [$userRow['id']]); ?>
                <?php if ($userOrders): ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Заказ</th><th>Бюджет</th><th>Статус</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($userOrders as $o): ?>
                                <tr>
                                    <td class="cell-main"><?= e(excerpt($o['title'], 60)) ?></td>
                                    <td><?= e(money((int) $o['budget'])) ?></td>
                                    <td><span class="badge badge-<?= e(order_status_class($o['status'])) ?>"><?= e(order_status_label($o['status'])) ?></span></td>
                                    <td><a class="btn btn-sm btn-ghost" href="/admin/order-form.php?id=<?= (int) $o['id'] ?>">Открыть</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">У заказчика пока нет заказов.</p>
                <?php endif; ?>
            <?php else: ?>
                <?php $userResponses = q_all(
                    'SELECT r.id, r.price, r.status, r.created_at, o.title
                       FROM responses r JOIN orders o ON o.id = r.order_id
                      WHERE r.freelancer_id = ? ORDER BY r.created_at DESC LIMIT 10',
                    [$userRow['id']]
                ); ?>
                <?php if ($userResponses): ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Заказ</th><th>Цена отклика</th><th>Статус</th><th>Дата</th></tr></thead>
                            <tbody>
                            <?php foreach ($userResponses as $r): ?>
                                <tr>
                                    <td class="cell-main"><?= e(excerpt($r['title'], 60)) ?></td>
                                    <td><?= e(money((int) $r['price'])) ?></td>
                                    <td><span class="badge badge-<?= e(response_status_class($r['status'])) ?>"><?= e(response_status_label($r['status'])) ?></span></td>
                                    <td><?= e(format_date($r['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Откликов у пользователя нет.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
