<?php
/**
 * Действия с заказом: завершение, отмена, возврат в работу, удаление.
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/orders.php');
}
verify_csrf();

$orderId = (int) ($_POST['order_id'] ?? 0);
$action  = (string) ($_POST['action'] ?? '');
$back    = (string) ($_POST['back'] ?? '/my-orders.php');
$back    = str_starts_with($back, '/') ? $back : '/my-orders.php';

$order = q_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
if ($order === null) {
    flash('Заказ не найден.', 'danger');
    redirect('/orders.php');
}

$isOwner = (int) $order['customer_id'] === (int) $user['id'];
$admin   = is_admin();

if (!$isOwner && !$admin) {
    flash('Управлять заказом может только его автор.', 'danger');
    redirect($back);
}

switch ($action) {
    case 'complete':
        q('UPDATE orders SET status = "completed" WHERE id = ?', [$orderId]);
        flash('Заказ отмечен как выполненный.', 'success');
        break;

    case 'cancel':
        q('UPDATE orders SET status = "cancelled" WHERE id = ?', [$orderId]);
        flash('Заказ отменён.', 'info');
        break;

    case 'reopen':
        q('UPDATE orders SET status = "open", freelancer_id = NULL WHERE id = ?', [$orderId]);
        flash('Заказ снова открыт для откликов.', 'success');
        break;

    case 'delete':
        if (!$admin && !$isOwner) {
            flash('Удалить заказ может только автор или администратор.', 'danger');
            redirect($back);
        }
        q('DELETE FROM orders WHERE id = ?', [$orderId]);
        flash('Заказ удалён.', 'info');
        redirect('/my-orders.php');

    default:
        flash('Неизвестное действие.', 'danger');
}

redirect($back);
