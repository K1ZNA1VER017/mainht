<?php
/**
 * Кабинет фрилансера: отправленные отклики и их статусы.
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_role(['freelancer']);

$pageTitle = 'Мои отклики';
$activeNav = 'orders';

$statusFilter = (string) ($_GET['status'] ?? '');
$where  = ['r.freelancer_id = ?'];
$params = [(int) $user['id']];

if (in_array($statusFilter, ['pending', 'accepted', 'rejected'], true)) {
    $where[]  = 'r.status = ?';
    $params[] = $statusFilter;
}

$responses = q_all(
    'SELECT r.*, o.title, o.budget, o.deadline, o.status AS order_status, o.customer_id,
            u.name AS customer_name, c.name AS category_name
       FROM responses r
       JOIN orders o ON o.id = r.order_id
       JOIN users u  ON u.id = o.customer_id
       LEFT JOIN categories c ON c.id = o.category_id
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY r.created_at DESC',
    $params
);

$totalPrice = (int) q_val(
    'SELECT COALESCE(SUM(price), 0) FROM responses r JOIN orders o ON o.id = r.order_id
      WHERE r.freelancer_id = ? AND r.status = "accepted"',
    [$user['id']]
);

require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <div class="d-flex justify-between align-center gap-2 flex-wrap">
            <div>
                <h1>Мои отклики</h1>
                <p>Отклики, отправленные заказчикам платформы</p>
            </div>
            <a class="btn btn-primary" href="/orders.php">Найти заказы</a>
        </div>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <div class="stats-strip mb-3">
            <div>
                <div class="num"><?= count($responses) ?></div>
                <div class="lbl">Откликов в выборке</div>
            </div>
            <div>
                <div class="num"><?= (int) q_val('SELECT COUNT(*) FROM responses WHERE freelancer_id = ? AND status = "accepted"', [$user['id']]) ?></div>
                <div class="lbl">Принято заказчиками</div>
            </div>
            <div>
                <div class="num"><?= e(money($totalPrice)) ?></div>
                <div class="lbl">Сумма принятых откликов</div>
            </div>
        </div>

        <div class="tabs">
            <a href="/my-responses.php" class="<?= $statusFilter === '' ? 'active' : '' ?>">Все</a>
            <?php foreach (['pending' => 'Ожидают', 'accepted' => 'Принятые', 'rejected' => 'Отклонённые'] as $key => $label): ?>
                <a href="/my-responses.php?status=<?= e($key) ?>" class="<?= $statusFilter === $key ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($responses): ?>
            <?php foreach ($responses as $response): ?>
                <div class="response-item <?= $response['status'] === 'accepted' ? 'is-accepted' : ($response['status'] === 'rejected' ? 'is-rejected' : '') ?>">
                    <div class="response-head">
                        <div>
                            <h3 style="font-size:1.05rem;margin-bottom:.25rem">
                                <a href="/order.php?id=<?= (int) $response['order_id'] ?>"><?= e($response['title']) ?></a>
                            </h3>
                            <div class="order-meta-row">
                                <span class="badge badge-muted"><?= e($response['category_name'] ?? 'Без категории') ?></span>
                                <span>Заказчик: <?= e($response['customer_name']) ?></span>
                                <span class="dot">•</span>
                                <span>Заказ: <span class="badge badge-<?= e(order_status_class($response['order_status'])) ?>"><?= e(order_status_label($response['order_status'])) ?></span></span>
                            </div>
                        </div>
                        <span class="badge badge-<?= e(response_status_class($response['status'])) ?>"><?= e(response_status_label($response['status'])) ?></span>
                    </div>

                    <div class="response-body"><?= e(excerpt($response['message'], 260)) ?></div>

                    <div class="response-foot">
                        <div>
                            <span class="response-price"><?= e(money((int) $response['price'])) ?></span>
                            <span class="text-muted"> · срок <?= (int) $response['days'] ?> дн. · отправлен <?= e(format_datetime($response['created_at'])) ?></span>
                        </div>
                        <div class="d-flex gap-1 flex-wrap">
                            <a class="btn btn-sm btn-outline" href="/order.php?id=<?= (int) $response['order_id'] ?>">Открыть заказ</a>
                            <?php if ($response['status'] === 'pending'): ?>
                                <form method="post" action="/response-action.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="response_id" value="<?= (int) $response['id'] ?>">
                                    <input type="hidden" name="action" value="withdraw">
                                    <input type="hidden" name="back" value="/my-responses.php">
                                    <button class="btn btn-sm btn-ghost" data-confirm="Отозвать отклик?">Отозвать</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <img src="/assets/img/globe.png" alt="">
                <h3>Откликов пока нет</h3>
                <p>Найдите подходящий заказ в ленте и отправьте первое предложение.</p>
                <a class="btn btn-primary mt-2" href="/orders.php">Перейти к заказам</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
