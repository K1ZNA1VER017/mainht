<?php
/**
 * Страница заказа: описание, отклики исполнителей и форма отклика.
 */
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
$id   = (int) ($_GET['id'] ?? 0);

$order = q_one(
    'SELECT o.*, c.name AS category_name, c.icon AS category_icon,
            u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
            u.company AS customer_company, u.created_at AS customer_since,
            f.name AS freelancer_name
       FROM orders o
       JOIN users u           ON u.id = o.customer_id
       LEFT JOIN users f      ON f.id = o.freelancer_id
       LEFT JOIN categories c ON c.id = o.category_id
      WHERE o.id = ?',
    [$id]
);

if ($order === null) {
    http_response_code(404);
    $pageTitle = 'Заказ не найден';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="container"><div class="empty-state">
            <h3>Заказ не найден</h3><p>Возможно, он был удалён или снят с публикации.</p>
            <a class="btn btn-primary" href="/orders.php">Вернуться к ленте заказов</a>
          </div></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Считаем просмотры (кроме просмотров автора заказа)
if (!$user || (int) $user['id'] !== (int) $order['customer_id']) {
    q('UPDATE orders SET views = views + 1 WHERE id = ?', [$id]);
}

$isOwner = $user && (int) $user['id'] === (int) $order['customer_id'];
$canManage = $isOwner || is_admin();

// Отклики: владелец и администратор видят все, фрилансер — только свой
$myResponse = null;
if ($user && $user['role'] === 'freelancer') {
    $myResponse = q_one('SELECT * FROM responses WHERE order_id = ? AND freelancer_id = ?', [$id, (int) $user['id']]);
}

$responses = [];
if ($canManage) {
    $responses = q_all(
        'SELECT r.*, u.name AS freelancer_name, u.specialization, u.rate, u.experience, u.email AS freelancer_email
           FROM responses r
           JOIN users u ON u.id = r.freelancer_id
          WHERE r.order_id = ?
          ORDER BY (r.status = "accepted") DESC, r.created_at DESC',
        [$id]
    );
}
$responsesCount = (int) q_val('SELECT COUNT(*) FROM responses WHERE order_id = ?', [$id]);

$pageTitle = $order['title'];
$activeNav = 'orders';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <p class="text-muted mb-1">
            <a href="/orders.php">← Все заказы</a>
            <?php if ($order['category_name']): ?>
                · <a href="/orders.php?category=<?= (int) $order['category_id'] ?>"><?= e($order['category_name']) ?></a>
            <?php endif; ?>
        </p>
        <div class="d-flex justify-between align-center gap-2 flex-wrap">
            <h1 style="max-width:820px"><?= e($order['title']) ?></h1>
            <span class="badge badge-<?= e(order_status_class($order['status'])) ?>"><?= e(order_status_label($order['status'])) ?></span>
        </div>
        <p>Опубликовано <?= e(format_datetime($order['created_at'])) ?> · просмотров: <?= (int) $order['views'] ?> · откликов: <?= $responsesCount ?></p>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <div class="detail-layout">
            <div>
                <div class="detail-card">
                    <h2>Описание задачи</h2>
                    <p style="white-space:pre-line"><?= e($order['description']) ?></p>

                    <?php $tags = skills_array($order['skills']); ?>
                    <?php if ($tags): ?>
                        <h3>Требуемые навыки</h3>
                        <div class="skills-tags">
                            <?php foreach ($tags as $tag): ?>
                                <span class="skill-tag"><?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($order['freelancer_name']): ?>
                        <h3>Исполнитель</h3>
                        <p>✅ <?= e($order['freelancer_name']) ?> — назначен по результатам отклика</p>
                    <?php endif; ?>
                </div>

                <div class="detail-card">
                    <div class="section-head" style="margin-bottom:1.25rem">
                        <h2>Отклики исполнителей (<?= $responsesCount ?>)</h2>
                    </div>

                    <?php if ($canManage): ?>
                        <?php if ($responses): ?>
                            <?php foreach ($responses as $response): ?>
                                <div class="response-item <?= $response['status'] === 'accepted' ? 'is-accepted' : ($response['status'] === 'rejected' ? 'is-rejected' : '') ?>">
                                    <div class="response-head">
                                        <div class="response-author">
                                            <span class="avatar"><?= e(mb_strtoupper(mb_substr($response['freelancer_name'], 0, 1))) ?></span>
                                            <div>
                                                <strong><?= e($response['freelancer_name']) ?></strong>
                                                <small>
                                                    <?= e($response['specialization'] ?: 'Исполнитель') ?> ·
                                                    опыт <?= (int) $response['experience'] ?> лет ·
                                                    <?= e(money((int) $response['rate'])) ?>/час
                                                </small>
                                            </div>
                                        </div>
                                        <span class="badge badge-<?= e(response_status_class($response['status'])) ?>">
                                            <?= e(response_status_label($response['status'])) ?>
                                        </span>
                                    </div>

                                    <div class="response-body"><?= nl2br(e($response['message'])) ?></div>

                                    <div class="response-foot">
                                        <div>
                                            <span class="response-price"><?= e(money((int) $response['price'])) ?></span>
                                            <span class="text-muted"> · срок <?= (int) $response['days'] ?> дн. · <?= e(format_datetime($response['created_at'])) ?></span>
                                        </div>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <?php if ($response['status'] === 'pending' && $order['status'] !== 'completed'): ?>
                                                <form method="post" action="/response-action.php">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="response_id" value="<?= (int) $response['id'] ?>">
                                                    <input type="hidden" name="action" value="accept">
                                                    <input type="hidden" name="back" value="/order.php?id=<?= (int) $order['id'] ?>">
                                                    <button class="btn btn-sm btn-primary" data-confirm="Принять отклик и назначить исполнителя?">Принять отклик</button>
                                                </form>
                                                <form method="post" action="/response-action.php">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="response_id" value="<?= (int) $response['id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="back" value="/order.php?id=<?= (int) $order['id'] ?>">
                                                    <button class="btn btn-sm btn-ghost">Отклонить</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (is_admin()): ?>
                                                <form method="post" action="/response-action.php">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="response_id" value="<?= (int) $response['id'] ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="back" value="/order.php?id=<?= (int) $order['id'] ?>">
                                                    <button class="btn btn-sm btn-danger" data-confirm="Удалить отклик?">Удалить</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <h3>Откликов пока нет</h3>
                                <p>Как только исполнители откликнутся, они появятся здесь.</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($myResponse): ?>
                            <div class="response-item <?= $myResponse['status'] === 'accepted' ? 'is-accepted' : '' ?>">
                                <div class="response-head">
                                    <strong>Ваш отклик</strong>
                                    <span class="badge badge-<?= e(response_status_class($myResponse['status'])) ?>"><?= e(response_status_label($myResponse['status'])) ?></span>
                                </div>
                                <div class="response-body"><?= nl2br(e($myResponse['message'])) ?></div>
                                <div class="response-foot">
                                    <div>
                                        <span class="response-price"><?= e(money((int) $myResponse['price'])) ?></span>
                                        <span class="text-muted"> · срок <?= (int) $myResponse['days'] ?> дн.</span>
                                    </div>
                                    <?php if ($myResponse['status'] === 'pending'): ?>
                                        <form method="post" action="/response-action.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="response_id" value="<?= (int) $myResponse['id'] ?>">
                                            <input type="hidden" name="action" value="withdraw">
                                            <input type="hidden" name="back" value="/order.php?id=<?= (int) $order['id'] ?>">
                                            <button class="btn btn-sm btn-ghost" data-confirm="Отозвать свой отклик?">Отозвать отклик</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="info-note">
                                Ваш отклик появится здесь после отправки. Всего по заказу: <?= $responsesCount ?>
                                <?= e(plural($responsesCount, ['отклик', 'отклика', 'откликов'])) ?>.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <aside>
                <div class="detail-card">
                    <h2>Условия</h2>
                    <ul class="spec-list">
                        <li><span>Бюджет</span><span><?= e(money((int) $order['budget'])) ?></span></li>
                        <li><span>Срок сдачи</span><span><?= e(format_date($order['deadline'])) ?></span></li>
                        <li><span>Осталось</span><span><?= e(deadline_label($order['deadline'])) ?></span></li>
                        <li><span>Сложность</span><span><?= e(complexity_label($order['complexity'])) ?></span></li>
                        <li><span>Категория</span><span><?= e($order['category_name'] ?? 'Не указана') ?></span></li>
                        <li><span>Статус</span><span><?= e(order_status_label($order['status'])) ?></span></li>
                        <li><span>Откликов</span><span><?= $responsesCount ?></span></li>
                    </ul>

                    <div class="divider"></div>

                    <h3>Заказчик</h3>
                    <div class="response-author">
                        <span class="avatar"><?= e(mb_strtoupper(mb_substr($order['customer_name'], 0, 1))) ?></span>
                        <div>
                            <strong><?= e($order['customer_name']) ?></strong>
                            <small>На платформе с <?= e(format_date($order['customer_since'])) ?></small>
                        </div>
                    </div>
                    <?php if ($canManage): ?>
                        <ul class="spec-list mt-2">
                            <li><span>Email</span><span><?= e($order['customer_email']) ?></span></li>
                            <li><span>Телефон</span><span><?= e($order['customer_phone'] ?: 'не указан') ?></span></li>
                        </ul>
                    <?php endif; ?>

                    <div class="divider"></div>

                    <?php if (is_freelancer()): ?>
                        <?php if ($order['status'] === 'open' && !$myResponse): ?>
                            <button class="btn btn-primary btn-block" type="button"
                                    data-fill-response
                                    data-order-id="<?= (int) $order['id'] ?>"
                                    data-order-title="<?= e($order['title']) ?>">
                                Откликнуться на заказ
                            </button>
                        <?php elseif ($myResponse): ?>
                            <p class="text-muted">Вы уже откликнулись на этот заказ.</p>
                        <?php else: ?>
                            <p class="text-muted">Заказ недоступен для новых откликов.</p>
                        <?php endif; ?>
                    <?php elseif ($isOwner): ?>
                        <a class="btn btn-primary btn-block mb-2" href="/order-form.php?id=<?= (int) $order['id'] ?>">Редактировать заказ</a>
                        <?php if ($order['status'] === 'in_progress'): ?>
                            <form method="post" action="/order-action.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <input type="hidden" name="action" value="complete">
                                <input type="hidden" name="back" value="/order.php?id=<?= (int) $order['id'] ?>">
                                <button class="btn btn-secondary btn-block" data-confirm="Отметить заказ как выполненный?">Завершить заказ</button>
                            </form>
                        <?php endif; ?>
                    <?php elseif (is_admin()): ?>
                        <a class="btn btn-primary btn-block mb-2" href="/admin/order-form.php?id=<?= (int) $order['id'] ?>">Редактировать в админке</a>
                    <?php else: ?>
                        <a class="btn btn-primary btn-block" href="/login.php?redirect=<?= e(urlencode('/order.php?id=' . (int) $order['id'])) ?>">
                            Войдите, чтобы откликнуться
                        </a>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php if (is_freelancer() && $order['status'] === 'open' && !$myResponse): ?>
    <div class="modal-backdrop" id="responseModal">
        <div class="modal">
            <button class="modal-close" type="button" onclick="closeModal('responseModal')">×</button>
            <h2>Отклик на заказ</h2>
            <p class="text-muted mb-2">Заказ: <span id="response-order-title"><?= e($order['title']) ?></span></p>

            <form method="post" action="/response-create.php" id="responseForm" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" id="response-order-id" value="<?= (int) $order['id'] ?>">

                <div class="form-group">
                    <label for="price">Ваша стоимость, ₽</label>
                    <input type="number" id="price" name="price" min="1000" step="1000" value="<?= (int) $order['budget'] ?>" required>
                    <div class="hint">Бюджет заказчика: <?= e(money((int) $order['budget'])) ?></div>
                </div>

                <div class="form-group">
                    <label for="days">Срок выполнения, дней</label>
                    <input type="number" id="days" name="days" min="1" max="365" value="14" required>
                </div>

                <div class="form-group">
                    <label for="message">Сопроводительное письмо</label>
                    <textarea id="message" name="message" rows="5" required
                              placeholder="Опишите опыт по похожим задачам, план работ и что входит в стоимость"></textarea>
                    <div class="hint" id="message-counter"></div>
                </div>

                <p class="text-muted" id="responseTotal"></p>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Отправить отклик</button>
                    <button type="button" class="btn btn-ghost" onclick="closeModal('responseModal')">Отмена</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
