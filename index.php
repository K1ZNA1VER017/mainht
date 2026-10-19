<?php
/**
 * Главная страница: витрина платформы — свежие заказы, категории, услуги.
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Главная';
$activeNav = 'home';

$stats = [
    'orders'      => (int) q_val('SELECT COUNT(*) FROM orders'),
    'open'        => (int) q_val('SELECT COUNT(*) FROM orders WHERE status = "open"'),
    'customers'   => (int) q_val('SELECT COUNT(*) FROM users WHERE role = "customer" AND status = "active"'),
    'freelancers' => (int) q_val('SELECT COUNT(*) FROM users WHERE role = "freelancer" AND status = "active"'),
    'done'        => (int) q_val('SELECT COUNT(*) FROM orders WHERE status = "completed"'),
    'budget'      => (int) q_val('SELECT COALESCE(SUM(budget), 0) FROM orders WHERE status IN ("open", "in_progress")'),
    'responses'   => (int) q_val('SELECT COUNT(*) FROM responses'),
];

$latestOrders = q_all(
    'SELECT o.*, c.name AS category_name, u.name AS customer_name,
            (SELECT COUNT(*) FROM responses r WHERE r.order_id = o.id) AS responses_count
       FROM orders o
       JOIN users u           ON u.id = o.customer_id
       LEFT JOIN categories c ON c.id = o.category_id
      WHERE o.status = "open"
      ORDER BY o.created_at DESC
      LIMIT 6'
);

$categories = q_all('SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.category_id = c.id AND o.status = "open") AS orders_count
                       FROM categories c ORDER BY c.sort_order LIMIT 6');

$services = q_all('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order LIMIT 3');

require __DIR__ . '/includes/header.php';
?>

<section class="hero-modern">
    <div class="container hero-inner">
        <span class="hero-badge">Официальный франчайзи фирмы «1С»</span>

        <h1 class="hero-title-modern">
            Биржа заказов и исполнителей<br>для <em>автоматизации бизнеса</em>
        </h1>

        <p class="hero-sub">
            Заказчики публикуют задачи по 1С, интеграциям и аналитике — проверенные фрилансеры
            откликаются и выполняют работу. Администратор платформы контролирует качество.
        </p>

        <div class="hero-actions">
            <a href="/orders.php" class="btn btn-lg btn-primary">Найти заказ</a>
            <?php if (is_customer()): ?>
                <a href="/order-form.php" class="btn btn-lg btn-secondary">Опубликовать заказ</a>
            <?php elseif (is_freelancer()): ?>
                <a href="/my-responses.php" class="btn btn-lg btn-secondary">Мои отклики</a>
            <?php else: ?>
                <a href="/register.php" class="btn btn-lg btn-secondary">Стать исполнителем</a>
            <?php endif; ?>
        </div>

        <div class="hero-trust">
            <div><b><?= $stats['orders'] ?></b> заказов на платформе</div>
            <div><b><?= $stats['freelancers'] ?></b> исполнителей в пуле</div>
            <div><b><?= e(money($stats['budget'])) ?></b> бюджет активных задач</div>
            <div><b>100%</b> контроль администратора</div>
        </div>
    </div>
</section>

<section class="metrics-strip">
    <div class="container">
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="icon"><img src="/assets/img/globe.png" alt=""></div>
                <div class="value"><?= $stats['open'] ?></div>
                <div class="label">Открытых заказов ждут исполнителя</div>
            </div>
            <div class="metric-card">
                <div class="icon"><img src="/assets/img/key.png" alt=""></div>
                <div class="value"><?= $stats['responses'] ?></div>
                <div class="label">Откликов отправлено фрилансерами</div>
            </div>
            <div class="metric-card">
                <div class="icon"><img src="/assets/img/shield.png" alt=""></div>
                <div class="value"><?= $stats['done'] ?></div>
                <div class="label">Проектов успешно завершено</div>
            </div>
            <div class="metric-card">
                <div class="icon"><img src="/assets/img/diagram.png" alt=""></div>
                <div class="value"><?= $stats['customers'] ?></div>
                <div class="label">Компаний размещают задачи</div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container z-1">
        <div class="section-head">
            <div>
                <h2>Как это работает</h2>
                <p>Три шага от задачи до готового результата</p>
            </div>
        </div>
        <div class="grid grid-3">
            <div class="order-card">
                <span class="badge badge-warning">Шаг 1 · Заказчик</span>
                <h3 style="font-size:1.1rem">Публикация задачи</h3>
                <p class="order-desc">Опишите задачу, укажите категорию, бюджет, срок и требуемые навыки.
                   Заказ сразу появится в общей ленте.</p>
            </div>
            <div class="order-card">
                <span class="badge badge-info">Шаг 2 · Фрилансер</span>
                <h3 style="font-size:1.1rem">Отклики исполнителей</h3>
                <p class="order-desc">Специалисты предлагают свою стоимость и срок выполнения,
                   добавляя сопроводительное письмо с планом работ.</p>
            </div>
            <div class="order-card">
                <span class="badge badge-success">Шаг 3 · Сделка</span>
                <h3 style="font-size:1.1rem">Выбор и выполнение</h3>
                <p class="order-desc">Заказчик принимает подходящий отклик, заказ переходит в работу,
                   а после сдачи отмечается как выполненный.</p>
            </div>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Свежие заказы</h2>
                <p>Актуальные задачи от компаний-заказчиков</p>
            </div>
            <a href="/orders.php" class="btn btn-sm btn-outline">Все заказы →</a>
        </div>

        <?php if ($latestOrders): ?>
            <div class="grid grid-3">
                <?php foreach ($latestOrders as $order): ?>
                    <?php require __DIR__ . '/includes/order-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>Пока нет открытых заказов</h3>
                <p>Зарегистрируйтесь как заказчик и опубликуйте первую задачу.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($categories): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Категории работ</h2>
                <p>Выберите направление, чтобы найти подходящие задачи</p>
            </div>
        </div>
        <div class="grid grid-3">
            <?php foreach ($categories as $category): ?>
                <a class="order-card" href="/orders.php?category=<?= (int) $category['id'] ?>" style="text-decoration:none;color:inherit">
                    <div class="d-flex align-center gap-2">
                        <img src="<?= e(icon_url($category['icon'], 'key.png')) ?>" alt="" style="width:34px;height:34px;object-fit:contain">
                        <h3 style="font-size:1.05rem"><?= e($category['name']) ?></h3>
                    </div>
                    <p class="order-desc"><?= e($category['description']) ?></p>
                    <div class="order-card-foot">
                        <span class="badge badge-warning"><?= (int) $category['orders_count'] ?> откр. заказов</span>
                        <span class="text-muted">Подробнее →</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Услуги компании</h2>
                <p>Комплексные решения для автоматизации бизнеса</p>
            </div>
            <a href="/services.php" class="btn btn-sm btn-outline">Все услуги →</a>
        </div>
        <div class="grid grid-3">
            <?php foreach ($services as $service): ?>
                <div class="order-card">
                    <img src="<?= e(icon_url($service['image'], 'bulb.png')) ?>" alt="" style="width:42px;height:42px;object-fit:contain">
                    <h3 style="font-size:1.1rem"><?= e($service['title']) ?></h3>
                    <p class="order-desc"><?= e($service['short_description']) ?></p>
                    <div class="order-card-foot">
                        <span class="budget">от <?= e(money($service['price_from'])) ?></span>
                        <a class="btn btn-sm btn-outline" href="/service.php?slug=<?= e(urlencode($service['slug'])) ?>">Подробнее</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="cta">
    <div class="container">
        <h2>Готовы начать работу?</h2>
        <p>Заказчикам — размещение заказов и подбор исполнителей. Фрилансерам — поток проектов по 1С и интеграциям.</p>
        <div class="cta-actions">
            <?php if (!is_logged_in()): ?>
                <a href="/register.php" class="btn btn-lg btn-primary">Зарегистрироваться</a>
                <a href="/login.php" class="btn btn-lg btn-secondary">Войти в кабинет</a>
            <?php else: ?>
                <a href="/profile.php" class="btn btn-lg btn-primary">Личный кабинет</a>
                <a href="/orders.php" class="btn btn-lg btn-secondary">Смотреть заказы</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
