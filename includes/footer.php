    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-info">
                    <h4>ООО «Высокие технологии»</h4>
                    <p>Официальный франчайзи фирмы «1С». Площадка для размещения заказов
                       и подбора проверенных исполнителей.</p>
                </div>
                <div class="footer-nav">
                    <h4>Разделы</h4>
                    <p><a href="/orders.php">Лента заказов</a></p>
                    <p><a href="/freelancers.php">Исполнители</a></p>
                    <p><a href="/services.php">Услуги</a></p>
                    <p><a href="/contacts.php">Контакты</a></p>
                </div>
                <div class="footer-nav">
                    <h4>Участникам</h4>
                    <p><a href="/register.php">Регистрация заказчика</a></p>
                    <p><a href="/register.php">Стать исполнителем</a></p>
                    <p><a href="/order-form.php">Опубликовать заказ</a></p>
                    <p><a href="/login.php">Вход в кабинет</a></p>
                </div>
                <div class="footer-contacts">
                    <h4>Контакты</h4>
                    <p><a href="mailto:info@hightech.ru">info@hightech.ru</a></p>
                    <p><a href="tel:+74951234567">+7 (495) 123-45-67</a></p>
                    <p>Пн–Пт: 9:00 — 18:00</p>
                </div>
            </div>
            <div class="footer-copyright">
                <p>© <?= date('Y') ?> ООО «Высокие технологии». Все права защищены.</p>
                <p class="footer-note">Курсовой проект: PHP, MySQL, HTML, CSS, JavaScript · сборка <?= APP_BUILD ?></p>
            </div>
        </div>
    </footer>

    <script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
