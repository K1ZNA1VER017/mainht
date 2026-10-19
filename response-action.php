<?php
/**
 * Действия с откликами: принятие, отклонение, отзыв, удаление.
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/orders.php');
}
verify_csrf();

$responseId = (int) ($_POST['response_id'] ?? 0);
$action     = (string) ($_POST['action'] ?? '');
$back       = (string) ($_POST['back'] ?? '/orders.php');
$back       = str_starts_with($back, '/') ? $back : '/orders.php';

$response = q_one(
    'SELECT r.*, o.customer_id, o.id AS order_id, o.status AS order_status, o.title AS order_title
       FROM responses r
       JOIN orders o ON o.id = r.order_id
      WHERE r.id = ?',
    [$responseId]
);

if ($response === null) {
    flash('Отклик не найден.', 'danger');
    redirect($back);
}

$isOrderOwner = (int) $response['customer_id'] === (int) $user['id'];
$isAuthor     = (int) $response['freelancer_id'] === (int) $user['id'];
$admin        = is_admin();

$pdo = db();

switch ($action) {
    case 'accept':
        if (!$isOrderOwner && !$admin) {
            flash('Принять отклик может только автор заказа.', 'danger');
            redirect($back);
        }

        $pdo->beginTransaction();
        try {
            q('UPDATE responses SET status = "accepted" WHERE id = ?', [$responseId]);
            q('UPDATE responses SET status = "rejected" WHERE order_id = ? AND id <> ? AND status = "pending"', [$response['order_id'], $responseId]);
            q('UPDATE orders SET freelancer_id = ?, status = "in_progress" WHERE id = ?', [(int) $response['freelancer_id'], (int) $response['order_id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('Не удалось принять отклик: ' . $e->getMessage(), 'danger');
            redirect($back);
        }

        flash('Отклик принят. Исполнитель назначен, заказ переведён в работу.', 'success');
        break;

    case 'reject':
        if (!$isOrderOwner && !$admin) {
            flash('Отклонить отклик может только автор заказа.', 'danger');
            redirect($back);
        }
        q('UPDATE responses SET status = "rejected" WHERE id = ?', [$responseId]);
        flash('Отклик отклонён.', 'info');
        break;

    case 'withdraw':
        if (!$isAuthor && !$admin) {
            flash('Отозвать отклик может только его автор.', 'danger');
            redirect($back);
        }
        if ($response['status'] === 'accepted') {
            flash('Нельзя отозвать уже принятый отклик. Обратитесь к администратору.', 'warning');
            redirect($back);
        }
        q('DELETE FROM responses WHERE id = ?', [$responseId]);
        flash('Отклик отозван.', 'info');
        break;

    case 'delete':
        if (!$admin) {
            flash('Удалять отклики может только администратор.', 'danger');
            redirect($back);
        }
        q('DELETE FROM responses WHERE id = ?', [$responseId]);
        flash('Отклик удалён администратором.', 'info');
        break;

    default:
        flash('Неизвестное действие.', 'danger');
}

redirect($back);
