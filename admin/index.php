<?php
/**
 * Дашборд администратора: ключевые показатели, распределение заказов
 * по категориям и последние события платформы.
 */
$pageTitle  = 'Дашборд';
$adminNav   = 'dashboard';
$breadcrumb = 'Дашборд';
require __DIR__ . '/_header.php';

$stats = [
    'users_total'      => (int) q_val('SELECT COUNT(*) FROM users'),
    'customers'        => (int) q_val('SELECT COUNT(*) FROM users WHERE role = "customer"'),
    'freelancers'      => (int) q_val('SELECT COUNT(*) FROM users WHERE role = "freelancer"'),
    'blocked'          => (int) q_val('SELECT COUNT(*) FROM users WHERE status = "blocked"'),
    'orders_total'     => (int) q_val('SELECT COUNT(*) FROM orders'),
    'orders_open'      => (int) q_val('SELECT COUNT(*) FROM orders WHERE status = "open"'),
    'orders_progress'  => (int) q_val('SELECT COUNT(*) FROM orders WHERE status = "in_progress"'),
    'orders_done'      => (int) q_val('SELECT COUNT(*) FROM orders WHERE status = "completed"'),
    'responses_total'  => (int) q_val('SELECT COUNT(*) FROM responses'),
    'responses_pend'   => (int) q_val('SELECT COUNT(*) FROM responses WHERE status = "pending"'),
    'budget_total'     => (int) q_val('SELECT COALESCE(SUM(budget), 0) FROM orders'),
    'budget_done'      => (int) q_val('SELECT COALESCE(SUM(budget), 0) FROM orders WHERE status = "completed"'),
    'messages_new'     => (int) q_val('SELECT COUNT(*) FROM messages WHERE status = "new"'),
    'orders_week'      => (int) q_val('SELECT COUNT(*) FROM orders WHERE created_at >= NOW() - INTERVAL 7 DAY'),
    'users_week'       => (int) q_val('SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 7 DAY'),
];

$byCategory = q_all(
    'SELECT c.name, COUNT(o.id) AS cnt, COALESCE(SUM(o.budget), 0) AS total
       FROM categories c
       LEFT JOIN orders o ON o.category_id = c.id
      GROUP BY c.id, c.name
      ORDER BY cnt DESC'
);
$maxCategory = max(1, (int) max(array_column($byCategory, 'cnt')));

$latestOrders = q_all(
    'SELECT o.id, o.title, o.budget, o.status, o.created_at, u.name AS customer_name
       FROM orders o JOIN users u ON u.id = o.customer_id
      ORDER BY o.created_at DESC LIMIT 5'
);

$latestUsers = q_all('SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 5');

$latestResponses = q_all(
    'SELECT r.id, r.price, r.status, r.created_at, o.title, u.name AS freelancer_name
       FROM responses r
       JOIN orders o ON o.id = r.order_id
       JOIN users u  ON u.id = r.freelancer_id
      ORDER BY r.created_at DESC LIMIT 5'
);
?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><img src="/assets/img/globe.png" alt=""></div>
        <div class="value"><?= $stats['orders_total'] ?></div>
        <div class="label">Всего заказов</div>
        <div class="delta">+<?= $stats['orders_week'] ?> за последнюю неделю</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><img src="/assets/img/key.png" alt=""></div>
        <div class="value"><?= $stats['orders_open'] ?></div>
        <div class="label">Открытых заказов</div>
        <div class="delta">в работе: <?= $stats['orders_progress'] ?> · завершено: <?= $stats['orders_done'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><img src="/assets/img/bulb.png" alt="" onerror="this.style.display='none'"></div>
        <div class="value"><?= $stats['users_total'] ?></div>
        <div class="label">Пользователей</div>
        <div class="delta">заказчиков: <?= $stats['customers'] ?> · исполнителей: <?= $stats['freelancers'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><img src="/assets/img/envelope.png" alt=""></div>
        <div class="value"><?= $stats['responses_total'] ?></div>
        <div class="label">Откликов</div>
        <div class="delta">ожидают решения: <?= $stats['responses_pend'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><img src="/assets/img/chart.png" alt="" onerror="this.style.display='none'"></div>
        <div class="value"><?= e(money($stats['budget_total'])) ?></div>
        <div class="label">Суммарный бюджет заказов</div>
        <div class="delta">выполнено на <?= e(money($stats['budget_done'])) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><img src="/assets/img/shield.png" alt=""></div>
        <div class="value"><?= $stats['messages_new'] ?></div>
        <div class="label">Новых сообщений</div>
        <div class="delta">заблокировано пользователей: <?= $stats['blocked'] ?></div>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Заказы по категориям</h2>
        <a class="btn btn-sm btn-outline" href="/admin/categories.php">Управлять категориями</a>
    </div>
    <div class="panel-body">
        <?php if ($byCategory): ?>
            <div class="bar-chart">
                <?php foreach ($byCategory as $row): ?>
                    <div class="bar-col">
                        <span class="bar-value"><?= (int) $row['cnt'] ?></span>
                        <div class="bar" style="height: <?= max(4, (int) round(($row['cnt'] / $maxCategory) * 100)) ?>%"></div>
                        <span class="bar-label"><?= e($row['name']) ?><br><?= e(money((int) $row['total'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">Категории ещё не добавлены.</p>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-2">
    <div class="panel">
        <div class="panel-head">
            <h2>Последние заказы</h2>
            <a class="btn btn-sm btn-outline" href="/admin/orders.php">Все заказы</a>
        </div>
        <div class="panel-body p-0">
            <div class="table-wrap" style="border:0;box-shadow:none">
                <table class="data-table">
                    <tbody>
                    <?php foreach ($latestOrders as $order): ?>
                        <tr>
                            <td>
                                <div class="cell-main"><a href="/admin/order-form.php?id=<?= (int) $order['id'] ?>"><?= e(excerpt($order['title'], 44)) ?></a></div>
                                <div class="cell-sub"><?= e($order['customer_name']) ?> · <?= e(format_date($order['created_at'])) ?></div>
                            </td>
                            <td><?= e(money((int) $order['budget'])) ?></td>
                            <td><span class="badge badge-<?= e(order_status_class($order['status'])) ?>"><?= e(order_status_label($order['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2>Последние отклики</h2>
            <a class="btn btn-sm btn-outline" href="/admin/responses.php">Все отклики</a>
        </div>
        <div class="panel-body p-0">
            <div class="table-wrap" style="border:0;box-shadow:none">
                <table class="data-table">
                    <tbody>
                    <?php foreach ($latestResponses as $response): ?>
                        <tr>
                            <td>
                                <div class="cell-main"><?= e($response['freelancer_name']) ?></div>
                                <div class="cell-sub"><?= e(excerpt($response['title'], 40)) ?> · <?= e(format_date($response['created_at'])) ?></div>
                            </td>
                            <td><?= e(money((int) $response['price'])) ?></td>
                            <td><span class="badge badge-<?= e(response_status_class($response['status'])) ?>"><?= e(response_status_label($response['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Новые пользователи</h2>
        <a class="btn btn-sm btn-outline" href="/admin/users.php">Все пользователи</a>
    </div>
    <div class="panel-body p-0">
        <div class="table-wrap" style="border:0;box-shadow:none">
            <table class="data-table">
                <thead>
                    <tr><th>Пользователь</th><th>Роль</th><th>Статус</th><th>Регистрация</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($latestUsers as $userRow): ?>
                    <tr>
                        <td>
                            <div class="cell-main"><?= e($userRow['name']) ?></div>
                            <div class="cell-sub"><?= e($userRow['email']) ?></div>
                        </td>
                        <td><?= e(role_label($userRow['role'])) ?></td>
                        <td><span class="badge badge-<?= $userRow['status'] === 'active' ? 'success' : 'danger' ?>"><?= e(user_status_label($userRow['status'])) ?></span></td>
                        <td><?= e(format_date($userRow['created_at'])) ?></td>
                        <td><a class="btn btn-sm btn-ghost" href="/admin/user-form.php?id=<?= (int) $userRow['id'] ?>">Открыть</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
