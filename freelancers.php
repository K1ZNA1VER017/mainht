<?php
/**
 * Каталог исполнителей с фильтрами по специализации и ставке.
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Исполнители';
$activeNav = 'freelancers';

$search = trim((string) ($_GET['q'] ?? ''));
$where  = ['u.role = "freelancer"', 'u.status = "active"'];
$params = [];

if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR u.specialization LIKE ? OR u.skills LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$condition = 'WHERE ' . implode(' AND ', $where);

$total = (int) q_val("SELECT COUNT(*) FROM users u $condition", $params);
$page  = paginate($total, 9);

$freelancers = q_all(
    "SELECT u.*,
            (SELECT COUNT(*) FROM responses r WHERE r.freelancer_id = u.id AND r.status = 'accepted') AS accepted_count,
            (SELECT COUNT(*) FROM orders o WHERE o.freelancer_id = u.id AND o.status = 'completed') AS completed_count
       FROM users u
       $condition
       ORDER BY accepted_count DESC, u.created_at ASC
       LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);

require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <h1>Исполнители</h1>
        <p>Проверенные фрилансеры платформы: <?= (int) $total ?> <?= e(plural($total, ['специалист', 'специалиста', 'специалистов'])) ?></p>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <div class="filters-bar">
            <form class="filters-form" method="get" action="/freelancers.php">
                <div class="form-group">
                    <label for="q">Поиск исполнителя</label>
                    <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Имя, специализация или навык">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Найти</button>
                </div>
            </form>
        </div>

        <?php if ($freelancers): ?>
            <div class="grid grid-3">
                <?php foreach ($freelancers as $freelancer): ?>
                    <article class="order-card">
                        <div class="response-author">
                            <span class="avatar"><?= e(mb_strtoupper(mb_substr($freelancer['name'], 0, 1))) ?></span>
                            <div>
                                <strong><?= e($freelancer['name']) ?></strong>
                                <small><?= e($freelancer['specialization'] ?: 'Исполнитель') ?></small>
                            </div>
                        </div>

                        <div class="order-meta-row">
                            <span class="badge badge-muted">опыт <?= (int) $freelancer['experience'] ?> лет</span>
                            <span class="badge badge-warning"><?= e(money((int) $freelancer['rate'])) ?>/час</span>
                        </div>

                        <p class="order-desc"><?= e(excerpt($freelancer['about'] ?: 'Исполнитель платформы «Высокие технологии».', 130)) ?></p>

                        <?php $tags = skills_array($freelancer['skills']); ?>
                        <?php if ($tags): ?>
                            <div class="skills-tags">
                                <?php foreach (array_slice($tags, 0, 4) as $tag): ?>
                                    <span class="skill-tag"><?= e($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="order-card-foot">
                            <span class="text-muted">Выполнено заказов: <?= (int) $freelancer['completed_count'] ?></span>
                            <a class="btn btn-sm btn-outline" href="/freelancer.php?id=<?= (int) $freelancer['id'] ?>">Профиль</a>
                        </div>
                    </article>
                <?php endforeach; ?>
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
        <?php else: ?>
            <div class="empty-state">
                <img src="/assets/img/bulb.png" alt="">
                <h3>Исполнители не найдены</h3>
                <p>Измените условия поиска или <a href="/freelancers.php">сбросьте фильтр</a>.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
