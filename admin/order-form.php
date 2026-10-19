<?php
/**
 * Редактирование заказа администратором: все поля, автор, исполнитель, статус.
 */
$pageTitle  = 'Заказ';
$adminNav   = 'orders';
$breadcrumb = 'Заказы / Карточка заказа';

require __DIR__ . '/_header.php';

$id    = (int) ($_GET['id'] ?? 0);
$order = $id > 0 ? q_one('SELECT * FROM orders WHERE id = ?', [$id]) : null;

if ($id > 0 && $order === null) {
    flash('Заказ не найден.', 'danger');
    redirect('/admin/orders.php');
}

$pageTitle = $order ? 'Редактирование заказа #' . $order['id'] : 'Новый заказ';

$categories = q_all('SELECT * FROM categories ORDER BY sort_order');
$customers  = q_all('SELECT id, name, email FROM users WHERE role = "customer" ORDER BY name');
$freelancers = q_all('SELECT id, name, specialization FROM users WHERE role = "freelancer" ORDER BY name');

$data = [
    'customer_id'   => $order['customer_id']   ?? ($customers[0]['id'] ?? 0),
    'freelancer_id' => $order['freelancer_id'] ?? 0,
    'category_id'   => $order['category_id']   ?? ($categories[0]['id'] ?? 0),
    'title'         => $order['title']         ?? '',
    'description'   => $order['description']   ?? '',
    'budget'        => $order['budget']        ?? 100000,
    'deadline'      => $order['deadline']      ?? date('Y-m-d', strtotime('+14 days')),
    'complexity'    => $order['complexity']    ?? 'medium',
    'skills'        => $order['skills']        ?? '',
    'status'        => $order['status']        ?? 'open',
    'views'         => $order['views']         ?? 0,
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (array_keys($data) as $key) {
        $data[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $data['customer_id']   = (int) $data['customer_id'];
    $data['freelancer_id'] = (int) $data['freelancer_id'];
    $data['category_id']   = (int) $data['category_id'];
    $data['views']         = (int) $data['views'];

    if (mb_strlen($data['title']) < 5) {
        $errors['title'] = 'Укажите название заказа.';
    }
    if (mb_strlen($data['description']) < 20) {
        $errors['description'] = 'Описание слишком короткое.';
    }
    if ((int) $data['budget'] < 0) {
        $errors['budget'] = 'Бюджет не может быть отрицательным.';
    }
    if (!in_array($data['status'], order_statuses(), true)) {
        $data['status'] = 'open';
    }
    if (!in_array($data['complexity'], complexities(), true)) {
        $data['complexity'] = 'medium';
    }
    if (!q_val('SELECT id FROM users WHERE id = ? AND role = "customer"', [$data['customer_id']])) {
        $errors['customer_id'] = 'Выберите заказчика.';
    }
    if (!q_val('SELECT id FROM categories WHERE id = ?', [$data['category_id']])) {
        $errors['category_id'] = 'Выберите категорию.';
    }
    if ($data['freelancer_id'] > 0 && !q_val('SELECT id FROM users WHERE id = ? AND role = "freelancer"', [$data['freelancer_id']])) {
        $errors['freelancer_id'] = 'Исполнитель не найден.';
    }

    if (!$errors) {
        $params = [
            $data['customer_id'],
            $data['category_id'],
            $data['freelancer_id'] > 0 ? $data['freelancer_id'] : null,
            $data['title'],
            $data['description'],
            (int) $data['budget'],
            $data['deadline'] ?: null,
            $data['complexity'],
            $data['skills'] ?: null,
            $data['status'],
        ];

        if ($order) {
            q(
                'UPDATE orders SET customer_id = ?, category_id = ?, freelancer_id = ?, title = ?, description = ?,
                        budget = ?, deadline = ?, complexity = ?, skills = ?, status = ?
                  WHERE id = ?',
                array_merge($params, [(int) $order['id']])
            );
            flash('Заказ обновлён.', 'success');
            redirect('/admin/orders.php');
        }

        q(
            'INSERT INTO orders (customer_id, category_id, freelancer_id, title, description, budget, deadline, complexity, skills, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $params
        );
        flash('Заказ создан администратором.', 'success');
        redirect('/admin/orders.php');
    }
}
?>

<div class="panel">
    <div class="panel-head">
        <h2><?= $order ? 'Заказ #' . (int) $order['id'] : 'Новый заказ' ?></h2>
        <div class="d-flex gap-1">
            <?php if ($order): ?>
                <a class="btn btn-sm btn-outline" href="/order.php?id=<?= (int) $order['id'] ?>" target="_blank">Открыть на сайте</a>
            <?php endif; ?>
            <a class="btn btn-sm btn-ghost" href="/admin/orders.php">← К списку</a>
        </div>
    </div>
    <div class="panel-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger">Исправьте ошибки в форме.</div>
        <?php endif; ?>

        <form method="post" action="/admin/order-form.php<?= $order ? '?id=' . (int) $order['id'] : '' ?>" data-validate novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="title">Название заказа *</label>
                <input type="text" id="title" name="title" value="<?= e($data['title']) ?>" required
                       class="<?= isset($errors['title']) ? 'is-invalid' : '' ?>">
                <?php if (isset($errors['title'])): ?><div class="error"><?= e($errors['title']) ?></div><?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="customer_id">Заказчик *</label>
                    <select id="customer_id" name="customer_id" required>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= (int) $customer['id'] ?>" <?= (int) $data['customer_id'] === (int) $customer['id'] ? 'selected' : '' ?>>
                                <?= e($customer['name']) ?> (<?= e($customer['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['customer_id'])): ?><div class="error"><?= e($errors['customer_id']) ?></div><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="category_id">Категория *</label>
                    <select id="category_id" name="category_id" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= (int) $data['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="freelancer_id">Исполнитель</label>
                    <select id="freelancer_id" name="freelancer_id">
                        <option value="0">Не назначен</option>
                        <?php foreach ($freelancers as $freelancer): ?>
                            <option value="<?= (int) $freelancer['id'] ?>" <?= (int) $data['freelancer_id'] === (int) $freelancer['id'] ? 'selected' : '' ?>>
                                <?= e($freelancer['name']) ?> — <?= e($freelancer['specialization'] ?: 'исполнитель') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Статус</label>
                    <select id="status" name="status">
                        <?php foreach (order_statuses() as $item): ?>
                            <option value="<?= e($item) ?>" <?= $data['status'] === $item ? 'selected' : '' ?>><?= e(order_status_label($item)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="budget">Бюджет, ₽</label>
                    <input type="number" id="budget" name="budget" min="0" step="1000" value="<?= e($data['budget']) ?>">
                </div>
                <div class="form-group">
                    <label for="deadline">Срок сдачи</label>
                    <input type="date" id="deadline" name="deadline" value="<?= e($data['deadline']) ?>">
                </div>
                <div class="form-group">
                    <label for="complexity">Сложность</label>
                    <select id="complexity" name="complexity">
                        <?php foreach (complexities() as $level): ?>
                            <option value="<?= e($level) ?>" <?= $data['complexity'] === $level ? 'selected' : '' ?>><?= e(complexity_label($level)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Описание задачи *</label>
                <textarea id="description" name="description" rows="8" required
                          data-counter="#description-counter"
                          class="<?= isset($errors['description']) ? 'is-invalid' : '' ?>"><?= e($data['description']) ?></textarea>
                <div class="hint" id="description-counter"></div>
                <?php if (isset($errors['description'])): ?><div class="error"><?= e($errors['description']) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="skills">Требуемые навыки</label>
                <input type="text" id="skills" name="skills" value="<?= e($data['skills']) ?>" placeholder="1С, REST API, СКД">
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary"><?= $order ? 'Сохранить изменения' : 'Создать заказ' ?></button>
                <a class="btn btn-ghost" href="/admin/orders.php">Отмена</a>
            </div>
        </form>
    </div>
</div>

<?php if ($order): ?>
    <div class="panel">
        <div class="panel-head">
            <h2>Отклики по заказу (<?= (int) q_val('SELECT COUNT(*) FROM responses WHERE order_id = ?', [$order['id']]) ?>)</h2>
            <a class="btn btn-sm btn-outline" href="/admin/responses.php?order_id=<?= (int) $order['id'] ?>">Управлять откликами</a>
        </div>
        <div class="panel-body p-0">
            <div class="table-wrap" style="border:0;box-shadow:none">
                <table class="data-table">
                    <thead><tr><th>Исполнитель</th><th>Цена</th><th>Срок</th><th>Статус</th><th>Дата</th></tr></thead>
                    <tbody>
                    <?php $orderResponses = q_all(
                        'SELECT r.*, u.name AS freelancer_name FROM responses r JOIN users u ON u.id = r.freelancer_id
                          WHERE r.order_id = ? ORDER BY r.created_at DESC',
                        [$order['id']]
                    ); ?>
                    <?php foreach ($orderResponses as $response): ?>
                        <tr>
                            <td class="cell-main"><?= e($response['freelancer_name']) ?></td>
                            <td><?= e(money((int) $response['price'])) ?></td>
                            <td><?= (int) $response['days'] ?> дн.</td>
                            <td><span class="badge badge-<?= e(response_status_class($response['status'])) ?>"><?= e(response_status_label($response['status'])) ?></span></td>
                            <td><?= e(format_date($response['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
