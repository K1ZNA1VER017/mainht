<?php
/**
 * Страница «404 — не найдено».
 */
require_once __DIR__ . '/includes/functions.php';

http_response_code(404);
$pageTitle = 'Страница не найдена';
$activeNav = '';

require __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="empty-state" style="max-width:620px;margin:0 auto">
            <img src="/assets/img/globe.png" alt="">
            <h3 style="font-size:1.6rem">404 — страница не найдена</h3>
            <p>Возможно, адрес указан с ошибкой или страница была удалена.</p>
            <div class="d-flex gap-2 justify-center mt-2" style="justify-content:center">
                <a class="btn btn-primary" href="/index.php">На главную</a>
                <a class="btn btn-outline" href="/orders.php">К заказам</a>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
