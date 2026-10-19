<?php
/**
 * Карточка заказа. Ожидает переменную $order (строка из orders + join-поля).
 */
?>
<article class="order-card">
    <div class="order-card-head">
        <h3><a href="/order.php?id=<?= (int) $order['id'] ?>"><?= e($order['title']) ?></a></h3>
        <span class="budget"><?= e(money($order['budget'])) ?></span>
    </div>

    <div class="order-meta-row">
        <?php if (!empty($order['category_name'])): ?>
            <span class="badge badge-muted"><?= e($order['category_name']) ?></span>
        <?php endif; ?>
        <span><?= e(complexity_label($order['complexity'])) ?> сложность</span>
        <span class="dot">•</span>
        <span><?= e(format_date($order['deadline'])) ?></span>
        <?php if (isset($order['status'])): ?>
            <span class="badge badge-<?= e(order_status_class($order['status'])) ?>"><?= e(order_status_label($order['status'])) ?></span>
        <?php endif; ?>
    </div>

    <p class="order-desc"><?= e(excerpt($order['description'], 150)) ?></p>

    <?php $tags = skills_array($order['skills'] ?? null); ?>
    <?php if ($tags): ?>
        <div class="skills-tags">
            <?php foreach (array_slice($tags, 0, 4) as $tag): ?>
                <span class="skill-tag"><?= e($tag) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="order-card-foot">
        <span class="text-muted">
            <?= e(excerpt($order['customer_name'] ?? 'Заказчик', 24)) ?>
            <?php if (isset($order['responses_count'])): ?>
                · откликов: <?= (int) $order['responses_count'] ?>
            <?php endif; ?>
        </span>
        <a class="btn btn-sm btn-outline" href="/order.php?id=<?= (int) $order['id'] ?>">Открыть</a>
    </div>
</article>
