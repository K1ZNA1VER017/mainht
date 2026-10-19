<?php
/**
 * Справочник категорий заказов: добавление, изменение, удаление.
 */
$pageTitle  = 'Категории';
$adminNav   = 'categories';
$breadcrumb = 'Категории заказов';

require __DIR__ . '/_header.php';

$icons = ['key.png', 'globe.png', 'phone.png', 'chart.png', 'shield.png', 'cloud.png', 'bulb.png', 'rocket.png', 'diagram.png', 'lightning.png'];

$editId  = (int) ($_GET['edit'] ?? 0);
$editing = $editId > 0 ? q_one('SELECT * FROM categories WHERE id = ?', [$editId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $catId  = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name        = trim((string) ($_POST['name'] ?? ''));
        $slug        = trim((string) ($_POST['slug'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $icon        = (string) ($_POST['icon'] ?? 'key.png');
        $sortOrder   = (int) ($_POST['sort_order'] ?? 100);

        if ($slug === '') {
            $slug = translit($name);
        }

        $errors = [];
        if (mb_strlen($name) < 2) {
            $errors[] = 'Укажите название категории.';
        }
        if (!in_array($icon, $icons, true)) {
            $icon = 'key.png';
        }
        if (q_val('SELECT id FROM categories WHERE slug = ? AND id <> ?', [$slug, $catId])) {
            $errors[] = 'Категория с таким символьным кодом уже существует.';
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash($error, 'danger');
            }
            redirect('/admin/categories.php' . ($catId ? '?edit=' . $catId : ''));
        }

        if ($catId > 0) {
            q(
                'UPDATE categories SET name = ?, slug = ?, description = ?, icon = ?, sort_order = ? WHERE id = ?',
                [$name, $slug, $description ?: null, $icon, $sortOrder, $catId]
            );
            flash('Категория обновлена.', 'success');
        } else {
            q(
                'INSERT INTO categories (name, slug, description, icon, sort_order) VALUES (?, ?, ?, ?, ?)',
                [$name, $slug, $description ?: null, $icon, $sortOrder]
            );
            flash('Категория добавлена.', 'success');
        }

        redirect('/admin/categories.php');
    }

    if ($action === 'delete') {
        $ordersCount = (int) q_val('SELECT COUNT(*) FROM orders WHERE category_id = ?', [$catId]);
        q('DELETE FROM categories WHERE id = ?', [$catId]);
        flash('Категория удалена. Заказы без категории: ' . $ordersCount . ' (у них категория сброшена).', 'info');
        redirect('/admin/categories.php');
    }
}

$categories = q_all(
    'SELECT c.*,
            (SELECT COUNT(*) FROM orders o WHERE o.category_id = c.id) AS orders_count,
            (SELECT COUNT(*) FROM services s WHERE s.category_id = c.id) AS services_count
       FROM categories c
       ORDER BY c.sort_order'
);
?>

<div class="grid" style="grid-template-columns:minmax(0,1fr) 380px;align-items:start">
    <div class="panel">
        <div class="panel-head">
            <h2>Список категорий (<?= count($categories) ?>)</h2>
            <span class="text-muted" style="font-size:.85rem">Категории используются в заказах, фильтрах и услугах</span>
        </div>
        <div class="panel-body p-0">
            <div class="table-wrap" style="border:0;box-shadow:none">
                <table class="data-table">
                    <thead>
                        <tr><th>ID</th><th>Категория</th><th>Код</th><th>Заказы</th><th>Услуги</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= (int) $category['id'] ?></td>
                            <td>
                                <div class="d-flex align-center gap-1">
                                    <img src="<?= e(icon_url($category['icon'], 'key.png')) ?>" alt="" style="width:22px;height:22px;object-fit:contain">
                                    <div>
                                        <div class="cell-main"><?= e($category['name']) ?></div>
                                        <div class="cell-sub"><?= e(excerpt($category['description'], 70)) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><code><?= e($category['slug']) ?></code></td>
                            <td><?= (int) $category['orders_count'] ?></td>
                            <td><?= (int) $category['services_count'] ?></td>
                            <td>
                                <div class="actions">
                                    <a class="btn btn-sm btn-outline" href="/admin/categories.php?edit=<?= (int) $category['id'] ?>">Изменить</a>
                                    <form method="post" action="/admin/categories.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                        <button class="btn btn-sm btn-danger" data-confirm="Удалить категорию? Заказы останутся без категории.">Удалить</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2><?= $editing ? 'Изменение категории #' . (int) $editing['id'] : 'Новая категория' ?></h2>
            <?php if ($editing): ?>
                <a class="btn btn-sm btn-ghost" href="/admin/categories.php">Отмена</a>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <form method="post" action="/admin/categories.php" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">

                <div class="form-group">
                    <label for="name">Название *</label>
                    <input type="text" id="name" name="name" value="<?= e($editing['name'] ?? '') ?>" required placeholder="Например, 1С разработка">
                </div>

                <div class="form-group">
                    <label for="slug">Символьный код</label>
                    <input type="text" id="slug" name="slug" value="<?= e($editing['slug'] ?? '') ?>" placeholder="1c-development">
                    <div class="hint">Латиницей, без пробелов. Оставьте пустым — сформируется автоматически.</div>
                </div>

                <div class="form-group">
                    <label for="description">Описание</label>
                    <textarea id="description" name="description" rows="3"><?= e($editing['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="icon">Иконка</label>
                    <select id="icon" name="icon">
                        <?php foreach ($icons as $icon): ?>
                            <option value="<?= e($icon) ?>" <?= ($editing['icon'] ?? '') === $icon ? 'selected' : '' ?>><?= e($icon) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="sort_order">Порядок сортировки</label>
                    <input type="number" id="sort_order" name="sort_order" min="0" max="255" value="<?= (int) ($editing['sort_order'] ?? 100) ?>">
                </div>

                <button class="btn btn-primary btn-block" type="submit"><?= $editing ? 'Сохранить' : 'Добавить категорию' ?></button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
