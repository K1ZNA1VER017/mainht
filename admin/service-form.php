<?php
/**
 * Создание и редактирование услуги.
 */
$pageTitle  = 'Услуга';
$adminNav   = 'services';
$breadcrumb = 'Каталог услуг / Карточка';

require __DIR__ . '/_header.php';

$id      = (int) ($_GET['id'] ?? 0);
$service = $id > 0 ? q_one('SELECT * FROM services WHERE id = ?', [$id]) : null;

if ($id > 0 && $service === null) {
    flash('Услуга не найдена.', 'danger');
    redirect('/admin/services.php');
}

$pageTitle = $service ? 'Редактирование: ' . $service['title'] : 'Новая услуга';

$categories = q_all('SELECT * FROM categories ORDER BY sort_order');
$images     = ['rocket.png', 'bulb.png', 'globe.png', 'shield.png', 'chart.png', 'cloud.png', 'key.png', 'diagram.png'];

$data = [
    'category_id'       => $service['category_id']       ?? ($categories[0]['id'] ?? 0),
    'title'             => $service['title']             ?? '',
    'slug'              => $service['slug']              ?? '',
    'short_description' => $service['short_description'] ?? '',
    'description'       => $service['description']       ?? '',
    'features'          => $service['features']          ?? '',
    'price_from'        => $service['price_from']        ?? 50000,
    'image'             => $service['image']             ?? 'rocket.png',
    'is_active'         => $service['is_active']         ?? 1,
    'sort_order'        => $service['sort_order']        ?? 100,
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (array_keys($data) as $key) {
        $data[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $data['category_id'] = (int) $data['category_id'];
    $data['price_from']  = (int) $data['price_from'];
    $data['sort_order']  = (int) $data['sort_order'];
    $data['is_active']   = isset($_POST['is_active']) ? 1 : 0;

    if (mb_strlen($data['title']) < 3) {
        $errors['title'] = 'Укажите название услуги.';
    }
    if ($data['slug'] === '') {
        $data['slug'] = translit($data['title']);
    }
    if (!in_array($data['image'], $images, true)) {
        $data['image'] = 'rocket.png';
    }
    if (q_val('SELECT id FROM services WHERE slug = ? AND id <> ?', [$data['slug'], $id])) {
        $errors['slug'] = 'Услуга с таким кодом уже существует.';
    }
    if (!q_val('SELECT id FROM categories WHERE id = ?', [$data['category_id']])) {
        $data['category_id'] = null;
    }

    if (!$errors) {
        $params = [
            $data['category_id'],
            $data['title'],
            $data['slug'],
            $data['short_description'] ?: null,
            $data['description'] ?: null,
            $data['features'] ?: null,
            $data['price_from'],
            $data['image'],
            $data['is_active'],
            $data['sort_order'],
        ];

        if ($service) {
            q(
                'UPDATE services SET category_id = ?, title = ?, slug = ?, short_description = ?, description = ?,
                        features = ?, price_from = ?, image = ?, is_active = ?, sort_order = ?
                  WHERE id = ?',
                array_merge($params, [(int) $service['id']])
            );
            flash('Услуга обновлена.', 'success');
        } else {
            q(
                'INSERT INTO services (category_id, title, slug, short_description, description, features, price_from, image, is_active, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                $params
            );
            flash('Услуга добавлена.', 'success');
        }

        redirect('/admin/services.php');
    }
}
?>

<div class="panel">
    <div class="panel-head">
        <h2><?= $service ? 'Услуга #' . (int) $service['id'] : 'Новая услуга' ?></h2>
        <a class="btn btn-sm btn-ghost" href="/admin/services.php">← К списку</a>
    </div>
    <div class="panel-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger">Исправьте ошибки в форме.</div>
        <?php endif; ?>

        <form method="post" action="/admin/service-form.php<?= $service ? '?id=' . (int) $service['id'] : '' ?>" data-validate novalidate>
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="title">Название *</label>
                    <input type="text" id="title" name="title" value="<?= e($data['title']) ?>" required
                           class="<?= isset($errors['title']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errors['title'])): ?><div class="error"><?= e($errors['title']) ?></div><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="slug">Символьный код</label>
                    <input type="text" id="slug" name="slug" value="<?= e($data['slug']) ?>" placeholder="web-integration">
                    <?php if (isset($errors['slug'])): ?><div class="error"><?= e($errors['slug']) ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category_id">Категория</label>
                    <select id="category_id" name="category_id">
                        <option value="0">Без категории</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= (int) $data['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="image">Иконка</label>
                    <select id="image" name="image">
                        <?php foreach ($images as $image): ?>
                            <option value="<?= e($image) ?>" <?= $data['image'] === $image ? 'selected' : '' ?>><?= e($image) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="price_from">Стоимость от, ₽</label>
                    <input type="number" id="price_from" name="price_from" min="0" step="500" value="<?= e($data['price_from']) ?>">
                </div>
                <div class="form-group">
                    <label for="sort_order">Порядок сортировки</label>
                    <input type="number" id="sort_order" name="sort_order" min="0" max="255" value="<?= e($data['sort_order']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="short_description">Краткое описание</label>
                <textarea id="short_description" name="short_description" rows="2"><?= e($data['short_description']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="description">Полное описание</label>
                <textarea id="description" name="description" rows="5"><?= e($data['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="features">Что входит в услугу</label>
                <textarea id="features" name="features" rows="6" placeholder="Каждый пункт с новой строки"><?= e($data['features']) ?></textarea>
                <div class="hint">Один пункт списка — одна строка</div>
            </div>

            <div class="form-group checkbox-row">
                <input type="checkbox" id="is_active" name="is_active" value="1" <?= $data['is_active'] ? 'checked' : '' ?>>
                <label for="is_active">Показывать услугу на сайте</label>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary"><?= $service ? 'Сохранить' : 'Добавить услугу' ?></button>
                <a class="btn btn-ghost" href="/admin/services.php">Отмена</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
