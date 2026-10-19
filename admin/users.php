<?php
/**
 * Управление пользователями: поиск, фильтры, блокировка, удаление.
 */
$pageTitle  = 'Пользователи';
$adminNav   = 'users';
$breadcrumb = 'Пользователи';

require __DIR__ . '/_header.php';

// Обработка быстрых действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($userId === (int) $admin['id']) {
        flash('Нельзя изменять собственную учётную запись из этого раздела.', 'warning');
        redirect('/admin/users.php');
    }

    $target = q_one('SELECT * FROM users WHERE id = ?', [$userId]);
    if ($target === null) {
        flash('Пользователь не найден.', 'danger');
        redirect('/admin/users.php');
    }

    switch ($action) {
        case 'block':
            q('UPDATE users SET status = "blocked" WHERE id = ?', [$userId]);
            flash('Пользователь «' . $target['name'] . '» заблокирован.', 'success');
            break;
        case 'unblock':
            q('UPDATE users SET status = "active" WHERE id = ?', [$userId]);
            flash('Пользователь «' . $target['name'] . '» разблокирован.', 'success');
            break;
        case 'delete':
            q('DELETE FROM users WHERE id = ?', [$userId]);
            flash('Пользователь «' . $target['name'] . '» и связанные с ним заказы удалены.', 'info');
            break;
        default:
            flash('Неизвестное действие.', 'danger');
    }

    redirect('/admin/users.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$search = trim((string) ($_GET['q'] ?? ''));
$role   = (string) ($_GET['role'] ?? '');
$status = (string) ($_GET['status'] ?? '');

$where  = ['1 = 1'];
$params = [];

if ($search !== '') {
    $where[]  = '(name LIKE ? OR email LIKE ? OR company LIKE ? OR specialization LIKE ?)';
    $like     = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if (in_array($role, ['customer', 'freelancer', 'admin'], true)) {
    $where[]  = 'role = ?';
    $params[] = $role;
}
if (in_array($status, ['active', 'blocked'], true)) {
    $where[]  = 'status = ?';
    $params[] = $status;
}

$condition = 'WHERE ' . implode(' AND ', $where);
$total     = (int) q_val("SELECT COUNT(*) FROM users $condition", $params);
$page      = paginate($total, 20);

$users = q_all(
    "SELECT u.*,
            (SELECT COUNT(*) FROM orders o WHERE o.customer_id = u.id) AS orders_count,
            (SELECT COUNT(*) FROM responses r WHERE r.freelancer_id = u.id) AS responses_count
       FROM users u
       $condition
       ORDER BY u.created_at DESC
       LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);
?>

<div class="toolbar">
    <form method="get" action="/admin/users.php">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Поиск: имя, email, компания">
        <select name="role">
            <option value="">Все роли</option>
            <?php foreach (['customer', 'freelancer', 'admin'] as $r): ?>
                <option value="<?= e($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">Все статусы</option>
            <option value="active"  <?= $status === 'active' ? 'selected' : '' ?>>Активные</option>
            <option value="blocked" <?= $status === 'blocked' ? 'selected' : '' ?>>Заблокированные</option>
        </select>
        <button class="btn btn-sm btn-primary" type="submit">Фильтровать</button>
        <a class="btn btn-sm btn-ghost" href="/admin/users.php">Сбросить</a>
    </form>
    <input type="search" data-table-filter="#usersTable" placeholder="Быстрый поиск по таблице" style="margin-left:auto">
    <a class="btn btn-sm btn-primary" href="/admin/user-form.php">+ Добавить пользователя</a>
</div>

<p class="text-muted mb-2">Найдено: <?= (int) $total ?> <?= e(plural($total, ['пользователь', 'пользователя', 'пользователей'])) ?></p>

<div class="table-wrap">
    <table class="data-table" id="usersTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Пользователь</th>
                <th>Роль</th>
                <th>Контакты</th>
                <th>Активность</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $row): ?>
            <tr>
                <td><?= (int) $row['id'] ?></td>
                <td>
                    <div class="cell-main"><?= e($row['name']) ?></div>
                    <div class="cell-sub">
                        <?= e($row['company'] ?: ($row['specialization'] ?: '—')) ?>
                        · регистрация <?= e(format_date($row['created_at'])) ?>
                    </div>
                </td>
                <td><span class="badge badge-muted"><?= e(role_label($row['role'])) ?></span></td>
                <td>
                    <div><?= e($row['email']) ?></div>
                    <div class="cell-sub"><?= e($row['phone'] ?: 'телефон не указан') ?></div>
                </td>
                <td>
                    <?php if ($row['role'] === 'customer'): ?>
                        <div class="cell-sub">заказов: <?= (int) $row['orders_count'] ?></div>
                    <?php elseif ($row['role'] === 'freelancer'): ?>
                        <div class="cell-sub">откликов: <?= (int) $row['responses_count'] ?></div>
                    <?php else: ?>
                        <div class="cell-sub">служебная запись</div>
                    <?php endif; ?>
                    <div class="cell-sub">вход: <?= e(format_datetime($row['last_login'])) ?></div>
                </td>
                <td>
                    <span class="badge badge-<?= $row['status'] === 'active' ? 'success' : 'danger' ?>">
                        <?= e(user_status_label($row['status'])) ?>
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <a class="btn btn-sm btn-outline" href="/admin/user-form.php?id=<?= (int) $row['id'] ?>">Изменить</a>

                        <?php if ((int) $row['id'] !== (int) $admin['id']): ?>
                            <form method="post" action="/admin/users.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                <?php if ($row['status'] === 'active'): ?>
                                    <input type="hidden" name="action" value="block">
                                    <button class="btn btn-sm btn-ghost" data-confirm="Заблокировать пользователя?">Блокировать</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="unblock">
                                    <button class="btn btn-sm btn-ghost">Разблокировать</button>
                                <?php endif; ?>
                            </form>

                            <form method="post" action="/admin/users.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="btn btn-sm btn-danger" data-confirm="Удалить пользователя вместе с его заказами и откликами?">Удалить</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:.8rem">это вы</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="empty-state" data-table-empty style="display:none;border:0">
        <p>По вашему запросу ничего не найдено.</p>
    </div>
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

<?php require __DIR__ . '/_footer.php'; ?>
