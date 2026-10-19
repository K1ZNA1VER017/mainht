<?php
/**
 * Сообщения формы обратной связи: просмотр, обработка, удаление.
 */
$pageTitle  = 'Сообщения';
$adminNav   = 'messages';
$breadcrumb = 'Обратная связь';

require __DIR__ . '/_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $msgId  = (int) ($_POST['message_id'] ?? 0);

    if ($action === 'processed') {
        q('UPDATE messages SET status = "processed" WHERE id = ?', [$msgId]);
        flash('Сообщение отмечено как обработанное.', 'success');
    } elseif ($action === 'new') {
        q('UPDATE messages SET status = "new" WHERE id = ?', [$msgId]);
        flash('Сообщение возвращено в список новых.', 'info');
    } elseif ($action === 'delete') {
        q('DELETE FROM messages WHERE id = ?', [$msgId]);
        flash('Сообщение удалено.', 'info');
    }

    redirect('/admin/messages.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$status = (string) ($_GET['status'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));

$where  = ['1 = 1'];
$params = [];

if (in_array($status, ['new', 'processed'], true)) {
    $where[]  = 'status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $where[]  = '(name LIKE ? OR email LIKE ? OR message LIKE ?)';
    $like     = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$condition = 'WHERE ' . implode(' AND ', $where);
$messages  = q_all("SELECT * FROM messages $condition ORDER BY (status = 'new') DESC, created_at DESC", $params);
?>

<div class="toolbar">
    <form method="get" action="/admin/messages.php">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Поиск по имени, email, тексту">
        <select name="status">
            <option value="">Все сообщения</option>
            <option value="new"       <?= $status === 'new' ? 'selected' : '' ?>>Новые</option>
            <option value="processed" <?= $status === 'processed' ? 'selected' : '' ?>>Обработанные</option>
        </select>
        <button class="btn btn-sm btn-primary" type="submit">Фильтровать</button>
        <a class="btn btn-sm btn-ghost" href="/admin/messages.php">Сбросить</a>
    </form>
    <input type="search" data-table-filter="#messagesTable" placeholder="Быстрый поиск по таблице" style="margin-left:auto">
</div>

<p class="text-muted mb-2">Найдено: <?= count($messages) ?> <?= e(plural(count($messages), ['сообщение', 'сообщения', 'сообщений'])) ?></p>

<div class="table-wrap">
    <table class="data-table" id="messagesTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Отправитель</th>
                <th>Услуга</th>
                <th>Сообщение</th>
                <th>Дата</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($messages as $row): ?>
            <tr>
                <td><?= (int) $row['id'] ?></td>
                <td>
                    <div class="cell-main"><?= e($row['name']) ?></div>
                    <div class="cell-sub"><?= e($row['email']) ?></div>
                    <div class="cell-sub"><?= e($row['phone'] ?: 'телефон не указан') ?></div>
                    <?php if ($row['company']): ?><div class="cell-sub"><?= e($row['company']) ?></div><?php endif; ?>
                </td>
                <td><?= e($row['service'] ?: '—') ?></td>
                <td style="max-width:340px"><div class="cell-sub" style="color:#444;font-size:.86rem"><?= e(excerpt($row['message'], 200)) ?></div></td>
                <td><?= e(format_datetime($row['created_at'])) ?></td>
                <td>
                    <span class="badge badge-<?= $row['status'] === 'new' ? 'warning' : 'success' ?>">
                        <?= $row['status'] === 'new' ? 'Новое' : 'Обработано' ?>
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <a class="btn btn-sm btn-outline" href="mailto:<?= e($row['email']) ?>">Ответить</a>
                        <form method="post" action="/admin/messages.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="message_id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="action" value="<?= $row['status'] === 'new' ? 'processed' : 'new' ?>">
                            <button class="btn btn-sm btn-ghost"><?= $row['status'] === 'new' ? 'Обработано' : 'Вернуть в новые' ?></button>
                        </form>
                        <form method="post" action="/admin/messages.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="message_id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn btn-sm btn-danger" data-confirm="Удалить сообщение?">Удалить</button>
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

<?php require __DIR__ . '/_footer.php'; ?>
