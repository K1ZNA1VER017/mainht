<?php
/**
 * Лента заказов: фильтры по категории, сложности, бюджету и поиск по названию.
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Заказы';
$activeNav = 'orders';

$categoryId = (int) ($_GET['category'] ?? 0);
$complexity = (string) ($_GET['complexity'] ?? '');
$budget     = (string) ($_GET['budget'] ?? '');
$status     = (string) ($_GET['status'] ?? 'open');
$search     = trim((string) ($_GET['q'] ?? ''));
$sort       = (string) ($_GET['sort'] ?? 'new');

$where  = [];
$params = [];

if ($categoryId > 0) {
    $where[]  = 'o.category_id = ?';
    $params[] = $categoryId;
}
if (in_array($complexity, complexities(), true)) {
    $where[]  = 'o.complexity = ?';
    $params[] = $complexity;
}
if ($budget === 'low') {
    $where[] = 'o.budget < 50000';
} elseif ($budget === 'medium') {
    $where[] = 'o.budget BETWEEN 50000 AND 200000';
} elseif ($budget === 'high') {
    $where[] = 'o.budget > 200000';
}
if (in_array($status, order_statuses(), true)) {
    $where[]  = 'o.status = ?';
    $params[] = $status;
} else {
    $status = '';
}
if ($search !== '') {
    $where[]  = '(o.title LIKE ? OR o.description LIKE ? OR o.skills LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$condition = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$orderBy = match ($sort) {
    'budget_asc'  => 'o.budget ASC',
    'budget_desc' => 'o.budget DESC',
    'deadline'    => 'o.deadline IS NULL, o.deadline ASC',
    default       => 'o.created_at DESC',
};

$total = (int) q_val("SELECT COUNT(*) FROM orders o $condition", $params);
$page  = paginate($total, 9);

$orders = q_all(
    "SELECT o.*, c.name AS category_name, u.name AS customer_name,
            (SELECT COUNT(*) FROM responses r WHERE r.order_id = o.id) AS responses_count
       FROM orders o
       JOIN users u           ON u.id = o.customer_id
       LEFT JOIN categories c ON c.id = o.category_id
       $condition
       ORDER BY $orderBy
       LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);

$categories = q_all('SELECT * FROM categories ORDER BY sort_order');

require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <h1>Лента заказов</h1>
        <p>Найдено <?= (int) $total ?> <?= e(plural($total, ['заказ', 'заказа', 'заказов'])) ?> по вашему запросу</p>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <div class="filters-bar">
            <form class="filters-form" method="get" action="/orders.php" data-validate>
                <div class="form-group">
                    <label for="q">Поиск</label>
                    <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Название, описание, навык">
                </div>
                <div class="form-group">
                    <label for="category">Категория</label>
                    <select id="category" name="category">
                        <option value="0">Все категории</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="complexity">Сложность</label>
                    <select id="complexity" name="complexity">
                        <option value="">Любая</option>
                        <?php foreach (complexities() as $level): ?>
                            <option value="<?= e($level) ?>" <?= $complexity === $level ? 'selected' : '' ?>>
                                <?= e(complexity_label($level)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="budget">Бюджет</label>
                    <select id="budget" name="budget">
                        <option value="">Любой</option>
                        <option value="low"    <?= $budget === 'low' ? 'selected' : '' ?>>До 50 000 ₽</option>
                        <option value="medium" <?= $budget === 'medium' ? 'selected' : '' ?>>50 000 — 200 000 ₽</option>
                        <option value="high"   <?= $budget === 'high' ? 'selected' : '' ?>>Свыше 200 000 ₽</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Статус</label>
                    <select id="status" name="status">
                        <option value="">Все статусы</option>
                        <?php foreach (order_statuses() as $item): ?>
                            <option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>>
                                <?= e(order_status_label($item)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sort">Сортировка</label>
                    <select id="sort" name="sort">
                        <option value="new"         <?= $sort === 'new' ? 'selected' : '' ?>>Сначала новые</option>
                        <option value="budget_desc" <?= $sort === 'budget_desc' ? 'selected' : '' ?>>Бюджет: по убыванию</option>
                        <option value="budget_asc"  <?= $sort === 'budget_asc' ? 'selected' : '' ?>>Бюджет: по возрастанию</option>
                        <option value="deadline"    <?= $sort === 'deadline' ? 'selected' : '' ?>>Ближайший срок</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Применить</button>
                </div>
            </form>
        </div>

        <?php if ($orders): ?>
            <div class="grid grid-3">
                <?php foreach ($orders as $order): ?>
                    <?php require __DIR__ . '/includes/order-card.php'; ?>
                <?php endforeach; ?>
            </div>

            <?php if ($page['pages'] > 1): ?>
                <nav class="pagination">
                    <?php if ($page['current'] > 1): ?>
                        <a href="<?= e(url_with(['page' => $page['current'] - 1])) ?>">←</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $page['pages']; $i++): ?>
                        <?php if ($i === $page['current']): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= e(url_with(['page' => $i])) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page['current'] < $page['pages']): ?>
                        <a href="<?= e(url_with(['page' => $page['current'] + 1])) ?>">→</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <img src="/assets/img/globe.png" alt="">
                <h3>Заказы не найдены</h3>
                <p>Попробуйте изменить параметры фильтра или <a href="/orders.php">сбросить их</a>.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
