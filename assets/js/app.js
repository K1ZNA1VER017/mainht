/* =========================================================
   Веб-приложение «Высокие технологии» — клиентские скрипты
   ========================================================= */
(function () {
    'use strict';

    /* ---------- Выпадающее меню пользователя ---------- */
    document.querySelectorAll('[data-dropdown]').forEach(function (menu) {
        var toggle = menu.querySelector('.user-menu-toggle');
        if (!toggle) return;

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            menu.classList.toggle('open');
        });
    });

    document.addEventListener('click', function () {
        document.querySelectorAll('[data-dropdown].open').forEach(function (menu) {
            menu.classList.remove('open');
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('[data-dropdown].open, .modal-backdrop.open').forEach(function (el) {
                el.classList.remove('open');
            });
        }
    });

    /* ---------- Модальные окна ---------- */
    window.openModal = function (id) {
        var modal = document.getElementById(id);
        if (modal) modal.classList.add('open');
    };

    window.closeModal = function (id) {
        var modal = document.getElementById(id);
        if (modal) modal.classList.remove('open');
    };

    document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
        backdrop.addEventListener('click', function (event) {
            if (event.target === backdrop) backdrop.classList.remove('open');
        });
    });

    /* ---------- Подтверждение опасных действий ---------- */
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (event) {
            if (!window.confirm(el.getAttribute('data-confirm') || 'Вы уверены?')) {
                event.preventDefault();
            }
        });
    });

    /* ---------- Счётчик символов у textarea ---------- */
    document.querySelectorAll('textarea[data-counter]').forEach(function (area) {
        var target = document.querySelector(area.getAttribute('data-counter'));
        if (!target) return;

        var update = function () {
            target.textContent = area.value.length + ' символов';
        };
        area.addEventListener('input', update);
        update();
    });

    /* ---------- Быстрый фильтр строк таблицы (админ-панель) ---------- */
    document.querySelectorAll('[data-table-filter]').forEach(function (input) {
        var table = document.querySelector(input.getAttribute('data-table-filter'));
        if (!table) return;

        input.addEventListener('input', function () {
            var needle = input.value.trim().toLowerCase();
            var visible = 0;

            table.querySelectorAll('tbody tr').forEach(function (row) {
                var match = row.textContent.toLowerCase().indexOf(needle) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            var empty = document.querySelector('[data-table-empty]');
            if (empty) empty.style.display = visible ? 'none' : '';
        });
    });

    /* ---------- Проверка форм на клиенте ---------- */
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var valid = true;

            form.querySelectorAll('[required]').forEach(function (field) {
                var empty = !field.value.trim();
                field.classList.toggle('is-invalid', empty);
                if (empty) valid = false;
            });

            var email = form.querySelector('input[type="email"]');
            if (email && email.value.trim() && !/^[^@\s]+@[^@\s]+\.[a-zA-Zа-яА-Я]{2,}$/.test(email.value.trim())) {
                email.classList.add('is-invalid');
                valid = false;
            }

            var password = form.querySelector('[data-password]');
            var repeat = form.querySelector('[data-password-repeat]');
            if (password && repeat && password.value !== repeat.value) {
                repeat.classList.add('is-invalid');
                valid = false;
                window.alert('Пароли не совпадают.');
            }

            if (!valid) {
                event.preventDefault();
                var firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
            }
        });
    });

    /* ---------- Заполнение формы отклика выбранным заказом ---------- */
    document.querySelectorAll('[data-fill-response]').forEach(function (button) {
        button.addEventListener('click', function () {
            var orderTitle = button.getAttribute('data-order-title');
            var field = document.querySelector('#response-order-title');
            if (field) field.textContent = orderTitle;

            var idField = document.querySelector('#response-order-id');
            if (idField) idField.value = button.getAttribute('data-order-id');

            openModal('responseModal');
        });
    });

    /* ---------- Подсчёт стоимости отклика (цена × дни, справочно) ---------- */
    var responseForm = document.querySelector('#responseForm');
    if (responseForm) {
        var price = responseForm.querySelector('[name="price"]');
        var days = responseForm.querySelector('[name="days"]');
        var output = document.querySelector('#responseTotal');

        var recalc = function () {
            if (!output) return;
            var p = parseInt(price && price.value ? price.value : '0', 10) || 0;
            var d = parseInt(days && days.value ? days.value : '0', 10) || 0;
            output.textContent = p > 0
                ? 'Предложение: ' + p.toLocaleString('ru-RU') + ' ₽ за ' + d + ' дн.'
                : '';
        };

        [price, days].forEach(function (field) {
            if (field) field.addEventListener('input', recalc);
        });
        recalc();
    }
})();
