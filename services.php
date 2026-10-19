<?php
/**
 * Каталог услуг компании (данные из таблицы services).
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Услуги';
$activeNav = 'services';

$services = q_all(
    'SELECT s.*, c.name AS category_name
       FROM services s
       LEFT JOIN categories c ON c.id = s.category_id
      WHERE s.is_active = 1
      ORDER BY s.sort_order'
);

require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <h1>Наши услуги</h1>
        <p>Комплексные решения для автоматизации бизнеса</p>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <div class="grid grid-3">
            <?php foreach ($services as $service): ?>
                <article class="order-card">
                    <img src="<?= e(icon_url($service['image'], 'bulb.png')) ?>" alt="" style="width:46px;height:46px;object-fit:contain">
                    <h3 style="font-size:1.15rem"><?= e($service['title']) ?></h3>
                    <p class="order-desc"><?= e($service['short_description']) ?></p>

                    <?php $features = features_array($service['features']); ?>
                    <?php if ($features): ?>
                        <ul style="list-style:none;display:grid;gap:.4rem;font-size:.88rem;color:var(--text-light)">
                            <?php foreach (array_slice($features, 0, 3) as $feature): ?>
                                <li>— <?= e($feature) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <div class="order-card-foot">
                        <span class="budget">от <?= e(money((int) $service['price_from'])) ?></span>
                        <a class="btn btn-sm btn-outline" href="/service.php?slug=<?= e(urlencode($service['slug'])) ?>">Подробнее</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="cta mt-3" style="border-radius:16px">
            <h2>Не нашли нужную услугу?</h2>
            <p>Опишите задачу — мы подберём исполнителя из пула платформы или выполним работу своими силами.</p>
            <div class="cta-actions">
                <a class="btn btn-primary" href="/order-form.php">Опубликовать заказ</a>
                <a class="btn btn-secondary" href="/contacts.php">Связаться с нами</a>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
