<?php
/**
 * Публикация нового заказа или редактирование существующего (кабинет заказчика).
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_role(['customer']);
$id   = (int) ($_GET['id'] ?? 0);

$order = null;
if ($id > 0) {
    $order = q_one('SELECT * FROM orders WHERE id = ? AND customer_id = ?', [$id, (int) $user['id']]);
    if ($order === null) {
        flash('Заказ не найден или у вас нет к нему доступа.', 'danger');
        redirect('/my-orders.php');
    }
}

$pageTitle = $order ? 'Редактирование заказа' : 'Новый заказ';
$activeNav = 'orders';

$categories = q_all('SELECT * FROM categories ORDER BY sort_order');

$data = [
    'title'       => $order['title']       ?? '',
    'description' => $order['description'] ?? '',
    'budget'      => $order['budget']      ?? '100000',
    'deadline'    => $order['deadline']    ?? date('Y-m-d', strtotime('+14 days')),
    'complexity'  => $order['complexity']  ?? 'medium',
    'skills'      => $order['skills']      ?? '',
    'category_id' => $order['category_id'] ?? ($categories[0]['id'] ?? 0),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $data['title']       = trim((string) ($_POST['title'] ?? ''));
    $data['description'] = trim((string) ($_POST['description'] ?? ''));
    $data['budget']      = (string) ($_POST['budget'] ?? '');
    $data['deadline']    = (string) ($_POST['deadline'] ?? '');
    $data['complexity']  = (string) ($_POST['complexity'] ?? 'medium');
    $data['skills']      = trim((string) ($_POST['skills'] ?? ''));
    $data['category_id'] = (int) ($_POST['category_id'] ?? 0);

    if (mb_strlen($data['title']) < 10) {
        $errors['title'] = 'Название должно содержать не менее 10 символов.';
    }
    if (mb_strlen($data['description']) < 50) {
        $errors['description'] = 'Опишите задачу подробнее — не менее 50 символов.';
    }
    if ((int) $data['budget'] < 1000) {
        $errors['budget'] = 'Бюджет должен быть не менее 1 000 ₽.';
    }
    if (!in_array($data['complexity'], complexities(), true)) {
        $data['complexity'] = 'medium';
    }
    if ($data['deadline'] !== '' && strtotime($data['deadline']) === false) {
        $errors['deadline'] = 'Укажите корректную дату.';
    }
    if (!q_val('SELECT id FROM categories WHERE id = ?', [$data['category_id']])) {
        $errors['category_id'] = 'Выберите категорию.';
    }

    if (!$errors) {
        $params = [
            $data['category_id'],
            $data['title'],
            $data['description'],
            (int) $data['budget'],
            $data['deadline'] ?: null,
            $data['complexity'],
            $data['skills'] ?: null,
        ];

        if ($order) {
            $params[] = (int) $order['id'];
            q(
                'UPDATE orders
                    SET category_id = ?, title = ?, description = ?, budget = ?, deadline = ?, complexity = ?, skills = ?
                  WHERE id = ?',
                $params
            );
            flash('Изменения заказа сохранены.', 'success');
            redirect('/order.php?id=' . (int) $order['id']);
        }

        $params[] = (int) $user['id'];
        q(
            'INSERT INTO orders (category_id, title, description, budget, deadline, complexity, skills, customer_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "open")',
            $params
        );
        $newId = last_id();
        flash('Заказ опубликован и доступен исполнителям.', 'success');
        redirect('/order.php?id=' . $newId);
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <h1><?= $order ? 'Редактирование заказа' : 'Новый заказ' ?></h1>
        <p><?= $order ? 'Внесите изменения — исполнители увидят обновлённое описание' : 'Опишите задачу, укажите бюджет и сроки — исполнители откликнутся' ?></p>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <?php if ($errors): ?>
            <div class="alert alert-danger">Исправьте ошибки в форме и отправьте её снова.</div>
        <?php endif; ?>

        <div class="grid" style="grid-template-columns:minmax(0,1fr) 320px;align-items:start">
            <div class="form-card">
                <form method="post" action="/order-form.php<?= $order ? '?id=' . (int) $order['id'] : '' ?>" data-validate novalidate>
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label for="title">Название заказа *</label>
                        <input type="text" id="title" name="title" value="<?= e($data['title']) ?>" required
                               placeholder="Например: Интеграция 1С:Розница с интернет-магазином"
                               class="<?= isset($errors['title']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($errors['title'])): ?><div class="error"><?= e($errors['title']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="category_id">Категория *</label>
                            <select id="category_id" name="category_id" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>" <?= (int) $data['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                                        <?= e($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['category_id'])): ?><div class="error"><?= e($errors['category_id']) ?></div><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="complexity">Сложность</label>
                            <select id="complexity" name="complexity">
                                <?php foreach (complexities() as $level): ?>
                                    <option value="<?= e($level) ?>" <?= $data['complexity'] === $level ? 'selected' : '' ?>>
                                        <?= e(complexity_label($level)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="budget">Бюджет, ₽ *</label>
                            <input type="number" id="budget" name="budget" min="1000" step="1000" value="<?= e($data['budget']) ?>" required
                                   class="<?= isset($errors['budget']) ? 'is-invalid' : '' ?>">
                            <?php if (isset($errors['budget'])): ?><div class="error"><?= e($errors['budget']) ?></div><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="deadline">Срок сдачи</label>
                            <input type="date" id="deadline" name="deadline" value="<?= e($data['deadline']) ?>"
                                   class="<?= isset($errors['deadline']) ? 'is-invalid' : '' ?>">
                            <?php if (isset($errors['deadline'])): ?><div class="error"><?= e($errors['deadline']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Описание задачи *</label>
                        <textarea id="description" name="description" rows="8" required
                                  placeholder="Что нужно сделать, какие системы задействованы, ожидаемый результат, требования к исполнителю"
                                  data-counter="#description-counter"
                                  class="<?= isset($errors['description']) ? 'is-invalid' : '' ?>"><?= e($data['description']) ?></textarea>
                        <div class="hint" id="description-counter"></div>
                        <?php if (isset($errors['description'])): ?><div class="error"><?= e($errors['description']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="skills">Требуемые навыки</label>
                        <input type="text" id="skills" name="skills" value="<?= e($data['skills']) ?>" placeholder="1С, REST API, СКД">
                        <div class="hint">Перечислите через запятую — фрилансеры смогут найти заказ по этим тегам</div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary"><?= $order ? 'Сохранить изменения' : 'Опубликовать заказ' ?></button>
                        <a class="btn btn-ghost" href="<?= $order ? '/order.php?id=' . (int) $order['id'] : '/my-orders.php' ?>">Отмена</a>
                    </div>
                </form>
            </div>

            <aside>
                <div class="detail-card">
                    <h3>Как составить хороший заказ</h3>
                    <ul class="spec-list" style="display:block">
                        <li class="text-muted" style="display:block">1. Опишите текущую ситуацию: какие системы уже используются.</li>
                        <li class="text-muted" style="display:block">2. Перечислите конкретные задачи и ожидаемый результат.</li>
                        <li class="text-muted" style="display:block">3. Укажите реалистичный бюджет — так отклики будут точнее.</li>
                        <li class="text-muted" style="display:block">4. Добавьте теги навыков, чтобы заказ нашли нужные специалисты.</li>
                    </ul>
                    <div class="divider"></div>
                    <p class="text-muted">
                        После публикации отклики исполнителей появятся на странице заказа,
                        где вы сможете принять подходящее предложение.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
