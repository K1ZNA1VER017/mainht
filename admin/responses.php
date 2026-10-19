<?php
/**
 * Управление откликами исполнителей: фильтры, модерация, удаление.
 */
$pageTitle  = 'Отклики';
$adminNav   = 'responses';
$breadcrumb = 'Отклики';

require __DIR__ . '/_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action     = (string) ($_POST['action'] ?? '');
    $responseId = (int) ($_POST['response_id'] ?? 0);

    $response = q_one('SELECT r.*, o.id AS order_id FROM responses r JOIN orders o ON o.id = r.order_id WHERE r.id = ?', [$responseId]);
    if ($response === null) {
        flash('Отклик не найден.', 'danger');
        redirect('/admin/responses.php');
    }

    switch ($action) {
        case 'accept':
            $pdo = db();
            $pdo->beginTransaction();
            try {
                q('UPDATE responses SET status = "accepted" WHERE id = ?', [$responseId]);
                q('UPDATE responses SET status = "rejected" WHERE order_id = ? AND id <> ? AND status = "pending"', [$response['order_id'], $responseId]);
                q('UPDATE orders SET freelancer_id = ?, status = "in_progress" WHERE id = ?', [(int) $response['freelancer_id'], (int) $response['order_id']]);
                $pdo->commit();
                flash('Отклик принят, заказ переведён в работу.', 'success');
            } catch (Throwable $e) {
                $pdo->rollBack();
                flash('Ошибка при принятии отклика: ' . $e->getMessage(), 'danger');
            }
            break;

        case 'reject':
            q('UPDATE responses SET status = "rejected" WHERE id = ?', [$responseId]);
            flash('Отклик отклонён.', 'info');
            break;

        case 'pending':
            q('UPDATE responses SET status = "pending" WHERE id = ?', [$responseId]);
            flash('Отклик возвращён в статус «Ожидает решения».', 'info');
            break;

        case 'delete':
            q('DELETE FROM responses WHERE id = ?', [$responseId]);
            flash('Отклик удалён.', 'info');
            break;

        default:
            flash('Неизвестное действие.', 'danger');
    }

    redirect('/admin/responses.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$search  = trim((string) ($_GET['q'] ?? ''));
$status  = (string) ($_GET['status'] ?? '');
$orderId = (int) ($_GET['order_id'] ?? 0);

$where  = ['1 = 1'];
$params = [];

if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR o.title LIKE ? OR r.message LIKE ?)';
    $like     = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if (in_array($status, ['pending', 'accepted', 'rejected'], true)) {
    $where[]  = 'r.status = ?';
    $params[] = $status;
}
if ($orderId > 0) {
    $where[]  = 'r.order_id = ?';
    $params[] = $orderId;
}

$condition = 'WHERE ' . implode(' AND ', $where);
$total     = (int) q_val("SELECT COUNT(*) FROM responses r JOIN orders o ON o.id = r.order_id JOIN users u ON u.id = r.freelancer_id $condition", $params);
$page      = paginate($total, 20);

$responses = q_all(
    "SELECT r.*, u.name AS freelancer_name, u.email AS freelancer_email, u.specialization,
            o.title AS order_title, o.budget AS order_budget, o.status AS order_status,
            cu.name AS customer_name
       FROM responses r
       JOIN orders o ON o.id = r.order_id
       JOIN users u  ON u.id = r.freelancer_id
       JOIN users cu ON cu.id = o.customer_id
       $condition
       ORDER BY (r.status = 'pending') DESC, r.created_at DESC
       LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);
?>

<div class="toolbar">
    <form method="get" action="/admin/responses.php">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Поиск: исполнитель, заказ, текст">
        <select name="status">
            <option value="">Все статусы</option>
            <?php foreach (['pending' => 'Ожидает', 'accepted' => 'Принятые', 'rejected' => 'Отклонённые'] as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-primary" type="submit">Фильтровать</button>
        <a class="btn btn-sm btn-ghost" href="/admin/responses.php">Сбросить</a>
    </form>
    <input type="search" data-table-filter="#responsesTable" placeholder="Быстрый поиск по таблице" style="margin-left:auto">
</div>

<?php if ($orderId > 0): ?>
    <div class="info-note mb-2">Показаны отклики только по заказу #<?= $orderId ?>. <a href="/admin/responses.php">Показать все</a></div>
<?php endif; ?>

<p class="text-muted mb-2">Найдено: <?= (int) $total ?> <?= e(plural($total, ['отклик', 'отклика', 'откликов'])) ?></p>

<div class="table-wrap">
    <table class="data-table" id="responsesTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Исполнитель</th>
                <th>Заказ</th>
                <th>Предложение</th>
                <th>Сопроводительное письмо</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($responses as $row): ?>
            <tr>
                <td><?= (int) $row['id'] ?></td>
                <td>
                    <div class="cell-main"><?= e($row['freelancer_name']) ?></div>
                    <div class="cell-sub"><?= e($row['specialization'] ?: '—') ?></div>
                    <div class="cell-sub"><?= e($row['freelancer_email']) ?></div>
                </td>
                <td style="max-width:260px">
                    <div class="cell-main"><a href="/order.php?id=<?= (int) $row['order_id'] ?>" target="_blank"><?= e(excerpt($row['order_title'], 48)) ?></a></div>
                    <div class="cell-sub">
                        заказчик: <?= e($row['customer_name']) ?>
                        · бюджет: <?= e(money((int) $row['order_budget'])) ?>
                        · <span class="badge badge-<?= e(order_status_class($row['order_status'])) ?>"><?= e(order_status_label($row['order_status'])) ?></span>
                    </div>
                </td>
                <td>
                    <div class="cell-main"><?= e(money((int) $row['price'])) ?></div>
                    <div class="cell-sub"><?= (int) $row['days'] ?> дн.</div>
                </td>
                <td style="max-width:280px">
                    <div class="cell-sub" style="font-size:.85rem;color:#444"><?= e(excerpt($row['message'], 160)) ?></div>
                </td>
                <td><span class="badge badge-<?= e(response_status_class($row['status'])) ?>"><?= e(response_status_label($row['status'])) ?></span></td>
                <td>
                    <div class="actions">
                        <?php if ($row['status'] !== 'accepted'): ?>
                            <form method="post" action="/admin/responses.php<?= $_SERVER['QUERY_STRING'] ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="response_id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="action" value="accept">
                                <button class="btn btn-sm btn-primary" data-confirm="Принять отклик и назначить исполнителя?">Принять</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($row['status'] !== 'rejected'): ?>
                            <form method="post" action="/admin/responses.php<?= $_SERVER['QUERY_STRING'] ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="response_id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button class="btn btn-sm btn-ghost">Отклонить</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="/admin/responses.php<?= $_SERVER['QUERY_STRING'] ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="response_id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="action" value="pending">
                                <button class="btn btn-sm btn-ghost">Вернуть в ожидание</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="/admin/responses.php<?= $_SERVER['QUERY_STRING'] ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="response_id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn btn-sm btn-danger" data-confirm="Удалить отклик?">Удалить</button>
                        </form>
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
