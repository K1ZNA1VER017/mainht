<?php
/**
 * Публичный профиль исполнителя и его выполненные работы.
 */
require_once __DIR__ . '/includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);

$freelancer = q_one(
    'SELECT * FROM users WHERE id = ? AND role = "freelancer"',
    [$id]
);

if ($freelancer === null) {
    http_response_code(404);
    $pageTitle = 'Исполнитель не найден';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="container"><div class="empty-state">
            <h3>Исполнитель не найден</h3><p>Возможно, профиль был удалён.</p>
            <a class="btn btn-primary" href="/freelancers.php">Все исполнители</a>
          </div></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$completedOrders = q_all(
    'SELECT o.id, o.title, o.budget, o.status, o.created_at, c.name AS category_name
       FROM orders o
       LEFT JOIN categories c ON c.id = o.category_id
      WHERE o.freelancer_id = ?
      ORDER BY o.created_at DESC
      LIMIT 8',
    [$id]
);

$stats = [
    'responses' => (int) q_val('SELECT COUNT(*) FROM responses WHERE freelancer_id = ?', [$id]),
    'accepted'  => (int) q_val('SELECT COUNT(*) FROM responses WHERE freelancer_id = ? AND status = "accepted"', [$id]),
    'done'      => (int) q_val('SELECT COUNT(*) FROM orders WHERE freelancer_id = ? AND status = "completed"', [$id]),
];

$pageTitle = $freelancer['name'];
$activeNav = 'freelancers';
require __DIR__ . '/includes/header.php';
?>

<section class="section-sm">
    <div class="container">
        <p class="text-muted mb-2"><a href="/freelancers.php">← Все исполнители</a></p>

        <div class="profile-head">
            <span class="avatar avatar-lg"><?= e(mb_strtoupper(mb_substr($freelancer['name'], 0, 1))) ?></span>
            <div>
                <h1><?= e($freelancer['name']) ?></h1>
                <p><?= e($freelancer['specialization'] ?: 'Исполнитель платформы') ?></p>
                <span class="badge">Опыт <?= (int) $freelancer['experience'] ?> лет</span>
                <span class="badge"><?= e(money((int) $freelancer['rate'])) ?> / час</span>
                <?php if ($freelancer['status'] === 'blocked'): ?>
                    <span class="badge badge-danger">Профиль заблокирован</span>
                <?php endif; ?>
            </div>
            <div class="profile-stats">
                <div><strong><?= $stats['responses'] ?></strong><span>откликов</span></div>
                <div><strong><?= $stats['accepted'] ?></strong><span>принято</span></div>
                <div><strong><?= $stats['done'] ?></strong><span>выполнено</span></div>
            </div>
        </div>

        <div class="detail-layout">
            <div>
                <div class="detail-card">
                    <h2>О специалисте</h2>
                    <p><?= nl2br(e($freelancer['about'] ?: 'Исполнитель пока не заполнил описание.')) ?></p>

                    <?php $tags = skills_array($freelancer['skills']); ?>
                    <?php if ($tags): ?>
                        <h3>Компетенции</h3>
                        <div class="skills-tags">
                            <?php foreach ($tags as $tag): ?>
                                <span class="skill-tag"><?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="detail-card">
                    <h2>Проекты исполнителя</h2>
                    <?php if ($completedOrders): ?>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr><th>Заказ</th><th>Категория</th><th>Бюджет</th><th>Статус</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($completedOrders as $item): ?>
                                    <tr>
                                        <td class="cell-main"><a href="/order.php?id=<?= (int) $item['id'] ?>"><?= e(excerpt($item['title'], 60)) ?></a></td>
                                        <td><?= e($item['category_name'] ?? '—') ?></td>
                                        <td><?= e(money((int) $item['budget'])) ?></td>
                                        <td><span class="badge badge-<?= e(order_status_class($item['status'])) ?>"><?= e(order_status_label($item['status'])) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Исполнитель ещё не выполнял заказы на платформе.</p>
                    <?php endif; ?>
                </div>
            </div>

            <aside>
                <div class="detail-card">
                    <h2>Контакты</h2>
                    <ul class="spec-list">
                        <li><span>Специализация</span><span><?= e($freelancer['specialization'] ?: '—') ?></span></li>
                        <li><span>Ставка</span><span><?= e(money((int) $freelancer['rate'])) ?>/час</span></li>
                        <li><span>Опыт</span><span><?= (int) $freelancer['experience'] ?> лет</span></li>
                        <li><span>На платформе с</span><span><?= e(format_date($freelancer['created_at'])) ?></span></li>
                    </ul>

                    <?php if (is_customer()): ?>
                        <div class="divider"></div>
                        <p class="text-muted mb-2">Свяжитесь с исполнителем после принятия его отклика на ваш заказ:</p>
                        <a class="btn btn-primary btn-block" href="/order-form.php">Опубликовать заказ</a>
                    <?php elseif (is_admin()): ?>
                        <div class="divider"></div>
                        <a class="btn btn-outline btn-block" href="/admin/user-form.php?id=<?= (int) $freelancer['id'] ?>">Редактировать в админке</a>
                    <?php else: ?>
                        <div class="divider"></div>
                        <p class="text-muted">Контакты исполнителя доступны заказчикам после принятия отклика.</p>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
