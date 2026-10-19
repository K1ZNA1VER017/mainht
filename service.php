<?php
/**
 * Детальная страница услуги (по slug).
 */
require_once __DIR__ . '/includes/functions.php';

$slug = trim((string) ($_GET['slug'] ?? ''));

$service = q_one(
    'SELECT s.*, c.name AS category_name
       FROM services s
       LEFT JOIN categories c ON c.id = s.category_id
      WHERE s.slug = ? AND s.is_active = 1',
    [$slug]
);

if ($service === null) {
    http_response_code(404);
    $pageTitle = 'Услуга не найдена';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="container"><div class="empty-state">
            <h3>Услуга не найдена</h3>
            <a class="btn btn-primary" href="/services.php">К списку услуг</a>
          </div></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$related = q_all(
    'SELECT * FROM services WHERE is_active = 1 AND id <> ? ORDER BY sort_order LIMIT 3',
    [(int) $service['id']]
);

$pageTitle = $service['title'];
$activeNav = 'services';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <p class="text-muted mb-1">
            <a href="/services.php">← Все услуги</a>
            <?php if ($service['category_name']): ?> · <?= e($service['category_name']) ?><?php endif; ?>
        </p>
        <div class="d-flex align-center gap-2 flex-wrap">
            <img src="<?= e(icon_url($service['image'], 'bulb.png')) ?>" alt="" style="width:48px;height:48px;object-fit:contain">
            <div>
                <h1><?= e($service['title']) ?></h1>
                <p><?= e($service['short_description']) ?></p>
            </div>
        </div>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <div class="detail-layout">
            <div>
                <div class="detail-card">
                    <h2>О услуге</h2>
                    <p><?= nl2br(e($service['description'])) ?></p>

                    <?php $features = features_array($service['features']); ?>
                    <?php if ($features): ?>
                        <h3>Что входит</h3>
                        <ul class="tasks-list" style="list-style:none;display:grid;gap:.6rem">
                            <?php foreach ($features as $feature): ?>
                                <li style="padding-left:1.6rem;position:relative">
                                    <span style="position:absolute;left:0;color:var(--accent-orange)">✓</span>
                                    <?= e($feature) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <aside>
                <div class="detail-card">
                    <h2>Стоимость</h2>
                    <p style="font-size:1.6rem;font-weight:700;color:var(--accent-orange)">
                        от <?= e(money((int) $service['price_from'])) ?>
                    </p>
                    <p class="text-muted">Итоговая стоимость зависит от объёма работ и рассчитывается после анализа задачи.</p>
                    <div class="divider"></div>
                    <a class="btn btn-primary btn-block mb-2" href="/order-form.php">Опубликовать заказ</a>
                    <a class="btn btn-outline btn-block" href="/contacts.php">Запросить консультацию</a>
                </div>

                <?php if ($related): ?>
                    <div class="detail-card">
                        <h2 style="font-size:1.1rem">Другие услуги</h2>
                        <ul class="spec-list">
                            <?php foreach ($related as $item): ?>
                                <li>
                                    <a href="/service.php?slug=<?= e(urlencode($item['slug'])) ?>"><?= e($item['title']) ?></a>
                                    <span><?= e(money((int) $item['price_from'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
