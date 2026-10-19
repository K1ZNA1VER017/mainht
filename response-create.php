<?php
/**
 * Обработчик отправки отклика фрилансера на заказ.
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_role(['freelancer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/orders.php');
}
verify_csrf();

$orderId = (int) ($_POST['order_id'] ?? 0);
$price   = (int) ($_POST['price'] ?? 0);
$days    = (int) ($_POST['days'] ?? 0);
$message = trim((string) ($_POST['message'] ?? ''));

$back = '/order.php?id=' . $orderId;

$order = q_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
if ($order === null) {
    flash('Заказ не найден.', 'danger');
    redirect('/orders.php');
}

if ($order['status'] !== 'open') {
    flash('Этот заказ закрыт для новых откликов.', 'warning');
    redirect($back);
}

$errors = [];
if ($price < 1000) {
    $errors[] = 'Укажите стоимость не менее 1 000 ₽.';
}
if ($days < 1 || $days > 365) {
    $errors[] = 'Срок выполнения должен быть от 1 до 365 дней.';
}
if (mb_strlen($message) < 30) {
    $errors[] = 'Сопроводительное письмо должно содержать не менее 30 символов.';
}

$exists = (int) q_val('SELECT COUNT(*) FROM responses WHERE order_id = ? AND freelancer_id = ?', [$orderId, (int) $user['id']]);
if ($exists > 0) {
    $errors[] = 'Вы уже отправляли отклик на этот заказ.';
}

if ($errors) {
    foreach ($errors as $error) {
        flash($error, 'danger');
    }
    redirect($back);
}

q(
    'INSERT INTO responses (order_id, freelancer_id, message, price, days, status)
     VALUES (?, ?, ?, ?, ?, "pending")',
    [$orderId, (int) $user['id'], $message, $price, $days]
);

flash('Отклик отправлен. Заказчик увидит его в своём кабинете.', 'success');
redirect($back);
