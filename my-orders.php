<?php
/**
 * Кабинет заказчика: список опубликованных заказов и управление ими.
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_role(['customer']);

$pageTitle = 'Мои заказы';
$activeNav = 'orders';

$statusFilter = (string) ($_GET['status'] ?? '');
$where  = ['o.customer_id = ?'];
$params = [(int) $user['id']];

if (in_array($statusFilter, order_statuses(), true)) {
    $where[]  = 'o.status = ?';
    $params[] = $statusFilter;
}

$condition = 'WHERE ' . implode(' AND ', $where);

$orders = q_all(
    "SELECT o.*, c.name AS category_name,
            (SELECT COUNT(*) FROM responses r WHERE r.order_id = o.id) AS responses_count,
            (SELECT COUNT(*) FROM responses r WHERE r.order_id = o.id AND r.status = 'pending') AS pending_count
       FROM orders o
       LEFT JOIN categories c ON c.id = o.category_id
       $condition
       ORDER BY FIELD(o.status, 'open', 'in_progress', 'completed', 'cancelled'), o.created_at DESC",
    $params
);

require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <div class="d-flex justify-between align-center gap-2 flex-wrap">
            <div>
                <h1>Мои заказы</h1>
                <p>Управление публикациями и откликами исполнителей</p>
            </div>
            <a class="btn btn-primary" href="/order-form.php">+ Новый заказ</a>
        </div>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <div class="tabs">
            <a href="/my-orders.php" class="<?= $statusFilter === '' ? 'active' : '' ?>">Все (<?= (int) q_val('SELECT COUNT(*) FROM orders WHERE customer_id = ?', [$user['id']]) ?>)</a>
            <?php foreach (order_statuses() as $status): ?>
                <a href="/my-orders.php?status=<?= e($status) ?>" class="<?= $statusFilter === $status ? 'active' : '' ?>">
                    <?= e(order_status_label($status)) ?>
                    (<?= (int) q_val('SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status = ?', [$user['id'], $status]) ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($orders): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Заказ</th>
                            <th>Бюджет</th>
                            <th>Срок</th>
                            <th>Статус</th>
                            <th>Отклики</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <div class="cell-main"><a href="/order.php?id=<?= (int) $order['id'] ?>"><?= e(excerpt($order['title'], 60)) ?></a></div>
                                <div class="cell-sub"><?= e($order['category_name'] ?? 'Без категории') ?> · создан <?= e(format_date($order['created_at'])) ?></div>
                            </td>
                            <td><?= e(money((int) $order['budget'])) ?></td>
                            <td>
                                <?= e(format_date($order['deadline'])) ?>
                                <div class="cell-sub"><?= e(deadline_label($order['deadline'])) ?></div>
                            </td>
                            <td><span class="badge badge-<?= e(order_status_class($order['status'])) ?>"><?= e(order_status_label($order['status'])) ?></span></td>
                            <td>
                                <?= (int) $order['responses_count'] ?>
                                <?php if ((int) $order['pending_count'] > 0): ?>
                                    <div class="cell-sub">новых: <?= (int) $order['pending_count'] ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="btn btn-sm btn-outline" href="/order.php?id=<?= (int) $order['id'] ?>">Отклики</a>
                                    <a class="btn btn-sm btn-ghost" href="/order-form.php?id=<?= (int) $order['id'] ?>">Изменить</a>
                                    <?php if ($order['status'] === 'in_progress'): ?>
                                        <form method="post" action="/order-action.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <input type="hidden" name="back" value="/my-orders.php">
                                            <button class="btn btn-sm btn-primary" data-confirm="Отметить заказ как выполненный?">Завершить</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($order['status'] === 'open'): ?>
                                        <form method="post" action="/order-action.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="back" value="/my-orders.php">
                                            <button class="btn btn-sm btn-ghost" data-confirm="Отменить заказ?">Отменить</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array($order['status'], ['cancelled', 'completed'], true)): ?>
                                        <form method="post" action="/order-action.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                            <input type="hidden" name="action" value="reopen">
                                            <input type="hidden" name="back" value="/my-orders.php">
                                            <button class="btn btn-sm btn-ghost">Открыть снова</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <img src="/assets/img/key.png" alt="">
                <h3>Заказов пока нет</h3>
                <p>Опубликуйте первый заказ — исполнители откликнутся в течение рабочего дня.</p>
                <a class="btn btn-primary mt-2" href="/order-form.php">Опубликовать заказ</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
