<?php
/**
 * Управление каталогом услуг компании.
 */
$pageTitle  = 'Услуги';
$adminNav   = 'services';
$breadcrumb = 'Каталог услуг';

require __DIR__ . '/_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle') {
        q('UPDATE services SET is_active = 1 - is_active WHERE id = ?', [$id]);
        flash('Видимость услуги изменена.', 'success');
        redirect('/admin/services.php');
    }

    if ($action === 'delete') {
        q('DELETE FROM services WHERE id = ?', [$id]);
        flash('Услуга удалена.', 'info');
        redirect('/admin/services.php');
    }
}

$services = q_all(
    'SELECT s.*, c.name AS category_name
       FROM services s
       LEFT JOIN categories c ON c.id = s.category_id
      ORDER BY s.sort_order'
);
?>

<div class="toolbar">
    <span class="text-muted">Всего услуг: <?= count($services) ?></span>
    <a class="btn btn-sm btn-primary" href="/admin/service-form.php" style="margin-left:auto">+ Добавить услугу</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Услуга</th>
                <th>Категория</th>
                <th>Стоимость от</th>
                <th>Порядок</th>
                <th>Видимость</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($services as $service): ?>
            <tr>
                <td><?= (int) $service['id'] ?></td>
                <td style="max-width:420px">
                    <div class="d-flex align-center gap-1">
                        <img src="<?= e(icon_url($service['image'], 'bulb.png')) ?>" alt="" style="width:26px;height:26px;object-fit:contain">
                        <div>
                            <div class="cell-main"><?= e($service['title']) ?></div>
                            <div class="cell-sub">/service.php?slug=<?= e($service['slug']) ?></div>
                        </div>
                    </div>
                </td>
                <td><?= e($service['category_name'] ?? '—') ?></td>
                <td><?= e(money((int) $service['price_from'])) ?></td>
                <td><?= (int) $service['sort_order'] ?></td>
                <td>
                    <span class="badge badge-<?= $service['is_active'] ? 'success' : 'muted' ?>">
                        <?= $service['is_active'] ? 'Опубликована' : 'Скрыта' ?>
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <a class="btn btn-sm btn-outline" href="/admin/service-form.php?id=<?= (int) $service['id'] ?>">Изменить</a>
                        <a class="btn btn-sm btn-ghost" href="/service.php?slug=<?= e(urlencode($service['slug'])) ?>" target="_blank">На сайте</a>
                        <form method="post" action="/admin/services.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                            <input type="hidden" name="action" value="toggle">
                            <button class="btn btn-sm btn-ghost"><?= $service['is_active'] ? 'Скрыть' : 'Показать' ?></button>
                        </form>
                        <form method="post" action="/admin/services.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn btn-sm btn-danger" data-confirm="Удалить услугу?">Удалить</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
