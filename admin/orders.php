<?php
/**
 * Управление заказами: фильтры, смена статуса, редактирование, удаление.
 */
$pageTitle  = 'Заказы';
$adminNav   = 'orders';
$breadcrumb = 'Заказы';

require __DIR__ . '/_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action  = (string) ($_POST['action'] ?? '');
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $order   = q_one('SELECT * FROM orders WHERE id = ?', [$orderId]);

    if ($order === null) {
        flash('Заказ не найден.', 'danger');
        redirect('/admin/orders.php');
    }

    switch ($action) {
        case 'status':
            $status = (string) ($_POST['status'] ?? '');
            if (in_array($status, order_statuses(), true)) {
                q('UPDATE orders SET status = ? WHERE id = ?', [$status, $orderId]);
                flash('Статус заказа обновлён: ' . order_status_label($status) . '.', 'success');
            } else {
                flash('Недопустимый статус.', 'danger');
            }
            break;

        case 'delete':
            q('DELETE FROM orders WHERE id = ?', [$orderId]);
            flash('Заказ удалён вместе с откликами.', 'info');
            break;

        default:
            flash('Неизвестное действие.', 'danger');
    }

    redirect('/admin/orders.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$search   = trim((string) ($_GET['q'] ?? ''));
$status   = (string) ($_GET['status'] ?? '');
$category = (int) ($_GET['category'] ?? 0);

$where  = ['1 = 1'];
$params = [];

if ($search !== '') {
    $where[]  = '(o.title LIKE ? OR o.description LIKE ? OR u.name LIKE ?)';
    $like     = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if (in_array($status, order_statuses(), true)) {
    $where[]  = 'o.status = ?';
    $params[] = $status;
}
if ($category > 0) {
    $where[]  = 'o.category_id = ?';
    $params[] = $category;
}

$condition = 'WHERE ' . implode(' AND ', $where);
$total     = (int) q_val("SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.customer_id $condition", $params);
$page      = paginate($total, 20);

$orders = q_all(
    "SELECT o.*, u.name AS customer_name, f.name AS freelancer_name, c.name AS category_name,
            (SELECT COUNT(*) FROM responses r WHERE r.order_id = o.id) AS responses_count
       FROM orders o
       JOIN users u           ON u.id = o.customer_id
       LEFT JOIN users f      ON f.id = o.freelancer_id
       LEFT JOIN categories c ON c.id = o.category_id
       $condition
       ORDER BY o.created_at DESC
       LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);

$categories = q_all('SELECT * FROM categories ORDER BY sort_order');
?>

<div class="toolbar">
    <form method="get" action="/admin/orders.php">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Поиск: заказ, заказчик">
        <select name="status">
            <option value="">Все статусы</option>
            <?php foreach (order_statuses() as $item): ?>
                <option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= e(order_status_label($item)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="category">
            <option value="0">Все категории</option>
            <?php foreach ($categories as $item): ?>
                <option value="<?= (int) $item['id'] ?>" <?= $category === (int) $item['id'] ? 'selected' : '' ?>><?= e($item['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-primary" type="submit">Фильтровать</button>
        <a class="btn btn-sm btn-ghost" href="/admin/orders.php">Сбросить</a>
    </form>
    <input type="search" data-table-filter="#ordersTable" placeholder="Быстрый поиск по таблице" style="margin-left:auto">
    <a class="btn btn-sm btn-ghost" href="/order-form.php">+ Новый заказ (как заказчик)</a>
</div>

<p class="text-muted mb-2">Найдено: <?= (int) $total ?> <?= e(plural($total, ['заказ', 'заказа', 'заказов'])) ?></p>

<div class="table-wrap">
    <table class="data-table" id="ordersTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Заказ</th>
                <th>Заказчик</th>
                <th>Бюджет</th>
                <th>Срок</th>
                <th>Отклики</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $row): ?>
            <tr>
                <td><?= (int) $row['id'] ?></td>
                <td style="max-width:320px">
                    <div class="cell-main"><a href="/order.php?id=<?= (int) $row['id'] ?>" target="_blank"><?= e(excerpt($row['title'], 56)) ?></a></div>
                    <div class="cell-sub">
                        <?= e($row['category_name'] ?? 'Без категории') ?>
                        · <?= e(complexity_label($row['complexity'])) ?> сложность
                        · создан <?= e(format_date($row['created_at'])) ?>
                    </div>
                </td>
                <td>
                    <div><?= e($row['customer_name']) ?></div>
                    <div class="cell-sub"><?= e($row['freelancer_name'] ? 'исполнитель: ' . $row['freelancer_name'] : 'исполнитель не назначен') ?></div>
                </td>
                <td><?= e(money((int) $row['budget'])) ?></td>
                <td><?= e(format_date($row['deadline'])) ?></td>
                <td><?= (int) $row['responses_count'] ?></td>
                <td>
                    <form method="post" action="/admin/orders.php<?= $_SERVER['QUERY_STRING'] ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="order_id" value="<?= (int) $row['id'] ?>">
                        <select name="status" onchange="this.form.submit()" style="padding:.35rem .5rem;border-radius:8px;border:1px solid var(--border-light);font-family:inherit;font-size:.85rem">
                            <?php foreach (order_statuses() as $item): ?>
                                <option value="<?= e($item) ?>" <?= $row['status'] === $item ? 'selected' : '' ?>><?= e(order_status_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td>
                    <div class="actions">
                        <a class="btn btn-sm btn-outline" href="/admin/order-form.php?id=<?= (int) $row['id'] ?>">Изменить</a>
                        <form method="post" action="/admin/orders.php<?= $_SERVER['QUERY_STRING'] ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="order_id" value="<?= (int) $row['id'] ?>">
                            <button class="btn btn-sm btn-danger" data-confirm="Удалить заказ и все его отклики?">Удалить</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="empty-state" data-table-empty style="display:none;border:0">
        <p>По вашему запросу ничего не найдено.</p>
    </div>
</div>

<?php if ($page['pages'] > 1): ?>
    <nav class="pagination">
        <?php for ($i = 1; $i <= $page['pages']; $i++): ?>
            <?php if ($i === $page['current']): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= e(url_with(['page' => $i])) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
