-- ============================================================
--  База данных веб-приложения «Высокие технологии» — биржа заказов
--  СУБД: MySQL 5.7+ / MariaDB 10.3+ (OpenServer 6, модуль MySQL-8.0)
--  Кодировка: utf8mb4
--
--  ИМПОРТ: phpMyAdmin -> Импорт -> выбрать этот файл.
--  Учётные записи после импорта (пароли указаны для входа на сайте):
--    admin@hightech.ru        / admin123      — администратор
--    customer@hightech.ru     / customer123   — заказчик
--    freelancer@hightech.ru   / freelancer123 — фрилансер
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `hightech_portal`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `hightech_portal`;

-- ------------------------------------------------------------
--  Таблица: пользователи (заказчики, фрилансеры, администраторы)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role`           ENUM('customer','freelancer','admin') NOT NULL DEFAULT 'customer',
    `name`           VARCHAR(120)  NOT NULL COMMENT 'ФИО / название организации',
    `email`          VARCHAR(160)  NOT NULL,
    `password_hash`  VARCHAR(255)  NOT NULL,
    `phone`          VARCHAR(40)   DEFAULT NULL,
    `company`        VARCHAR(160)  DEFAULT NULL COMMENT 'Компания заказчика',
    `specialization` VARCHAR(160)  DEFAULT NULL COMMENT 'Специализация фрилансера',
    `skills`         VARCHAR(500)  DEFAULT NULL COMMENT 'Навыки через запятую',
    `rate`           INT UNSIGNED  DEFAULT NULL COMMENT 'Ставка, руб./час',
    `experience`     TINYINT UNSIGNED DEFAULT NULL COMMENT 'Опыт работы, лет',
    `about`          TEXT          DEFAULT NULL,
    `status`         ENUM('active','blocked') NOT NULL DEFAULT 'active',
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login`     DATETIME      DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Таблица: категории заказов
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(120) NOT NULL,
    `slug`        VARCHAR(120) NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `icon`        VARCHAR(80)  DEFAULT NULL COMMENT 'Файл иконки из assets/img',
    `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 100,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Таблица: услуги компании (публичный каталог)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id`       INT UNSIGNED DEFAULT NULL,
    `slug`              VARCHAR(120) NOT NULL,
    `title`             VARCHAR(160) NOT NULL,
    `short_description` VARCHAR(400) DEFAULT NULL,
    `description`       TEXT         DEFAULT NULL,
    `features`          TEXT         DEFAULT NULL COMMENT 'Список возможностей, по одному пункту в строке',
    `price_from`        INT UNSIGNED DEFAULT NULL COMMENT 'Стоимость от, руб.',
    `image`             VARCHAR(80)  DEFAULT NULL,
    `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`        TINYINT UNSIGNED NOT NULL DEFAULT 100,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_services_slug` (`slug`),
    KEY `fk_services_category` (`category_id`),
    CONSTRAINT `fk_services_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Таблица: заказы (публикуют заказчики)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`   INT UNSIGNED NOT NULL,
    `category_id`   INT UNSIGNED DEFAULT NULL,
    `freelancer_id` INT UNSIGNED DEFAULT NULL COMMENT 'Исполнитель, принятый по отклику',
    `title`         VARCHAR(200) NOT NULL,
    `description`   TEXT         NOT NULL,
    `budget`        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Бюджет, руб.',
    `deadline`      DATE         DEFAULT NULL COMMENT 'Срок сдачи',
    `complexity`    ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    `skills`        VARCHAR(255) DEFAULT NULL COMMENT 'Требуемые навыки',
    `status`        ENUM('open','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
    `views`         INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orders_status` (`status`),
    KEY `idx_orders_category` (`category_id`),
    KEY `fk_orders_customer` (`customer_id`),
    KEY `fk_orders_freelancer` (`freelancer_id`),
    CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_orders_freelancer` FOREIGN KEY (`freelancer_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_orders_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Таблица: отклики фрилансеров на заказы
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `responses`;
CREATE TABLE `responses` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`      INT UNSIGNED NOT NULL,
    `freelancer_id` INT UNSIGNED NOT NULL,
    `message`       TEXT         NOT NULL,
    `price`         INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Предложенная стоимость, руб.',
    `days`          SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Срок выполнения, дней',
    `status`        ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_response_order_freelancer` (`order_id`, `freelancer_id`),
    KEY `fk_responses_freelancer` (`freelancer_id`),
    CONSTRAINT `fk_responses_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_responses_freelancer` FOREIGN KEY (`freelancer_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Таблица: сообщения формы обратной связи
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120) NOT NULL,
    `email`      VARCHAR(160) NOT NULL,
    `phone`      VARCHAR(40)  DEFAULT NULL,
    `company`    VARCHAR(160) DEFAULT NULL,
    `service`    VARCHAR(160) DEFAULT NULL,
    `message`    TEXT         NOT NULL,
    `status`     ENUM('new','processed') NOT NULL DEFAULT 'new',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_messages_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  ДЕМОНСТРАЦИОННЫЕ ДАННЫЕ
-- ============================================================

-- Пользователи (пароли: admin123 / customer123 / freelancer123)
INSERT INTO `users`
    (`id`, `role`, `name`, `email`, `password_hash`, `phone`, `company`, `specialization`, `skills`, `rate`, `experience`, `about`, `status`, `created_at`) VALUES
(1, 'admin',      'Администратор системы', 'admin@hightech.ru',      '$2y$12$s87qiqcQM2WdFwrfr6Th0uRAsA5IVhPj47C0vDxkNjkCU60I5EWkG', '+7 (495) 123-45-67', 'ООО «Высокие технологии»', NULL, NULL, NULL, NULL, 'Служебная учётная запись администратора портала.', 'active', NOW() - INTERVAL 120 DAY),
(2, 'customer',   'ООО «Ромашка»',         'customer@hightech.ru',   '$2y$12$n0xhhZnSXL7ncksF8L/kcudnueXa3LfOskvWIjlfMxJuyYGowFzXa', '+7 (495) 777-11-22', 'ООО «Ромашка»', NULL, NULL, NULL, NULL, 'Торгово-производственная компания, автоматизируем учёт на базе 1С.', 'active', NOW() - INTERVAL 90 DAY),
(3, 'customer',   'ООО «ТехноСтрой»',      'customer2@hightech.ru',  '$2y$12$n0xhhZnSXL7ncksF8L/kcudnueXa3LfOskvWIjlfMxJuyYGowFzXa', '+7 (812) 555-33-44', 'ООО «ТехноСтрой»', NULL, NULL, NULL, NULL, 'Строительный холдинг, нуждаемся в интеграции и аналитике.', 'active', NOW() - INTERVAL 60 DAY),
(4, 'freelancer', 'Иванов Алексей',        'freelancer@hightech.ru', '$2y$12$0ZLg8NCFeFenwYIA.VpzcOuT0ds8SH7C1UqTv4qCIvbJ1pQiMmN8u', '+7 (903) 111-22-33', NULL, 'Программист 1С', '1С:Бухгалтерия, 1С:УТ, REST API, СКД, расширения конфигураций', 1800, 7, 'Сертифицированный специалист 1С. Более 40 внедрений в торговле и производстве. Работаю по договору, есть портфолио.', 'active', NOW() - INTERVAL 85 DAY),
(5, 'freelancer', 'Петрова Мария',         'freelancer2@hightech.ru','$2y$12$0ZLg8NCFeFenwYIA.VpzcOuT0ds8SH7C1UqTv4qCIvbJ1pQiMmN8u', '+7 (921) 444-55-66', NULL, 'Веб-разработчик', 'PHP, MySQL, JavaScript, React, REST API, интеграции', 1500, 5, 'Fullstack-разработчик. Интегрирую 1С с веб-сервисами, делаю личные кабинеты и API.', 'active', NOW() - INTERVAL 70 DAY),
(6, 'freelancer', 'Сидоров Дмитрий',       'freelancer3@hightech.ru','$2y$12$0ZLg8NCFeFenwYIA.VpzcOuT0ds8SH7C1UqTv4qCIvbJ1pQiMmN8u', '+7 (926) 777-88-99', NULL, 'Аналитик данных', 'Power BI, SQL, Python, дашборды, ABC-XYZ анализ', 2000, 4, 'Аналитик BI. Строю отчётность и дашборды по данным из 1С и других источников.', 'active', NOW() - INTERVAL 40 DAY),
(7, 'freelancer', 'Кузнецова Ольга',       'freelancer4@hightech.ru','$2y$12$0ZLg8NCFeFenwYIA.VpzcOuT0ds8SH7C1UqTv4qCIvbJ1pQiMmN8u', '+7 (911) 222-33-44', NULL, 'Мобильный разработчик', 'React Native, Flutter, REST API, мобильная 1С', 1700, 6, 'Разрабатываю кроссплатформенные мобильные приложения для учётных систем.', 'blocked', NOW() - INTERVAL 30 DAY);

-- Категории заказов
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `icon`, `sort_order`) VALUES
(1, '1С разработка',        '1c-development',   'Доработки конфигураций, отчёты, обработки, расширения',        'key.png',        10),
(2, 'Веб-интеграция',       'web-integration',  'Интеграция 1С с сайтами, CRM, маркетплейсами, API',            'globe.png',      20),
(3, 'Мобильные приложения', 'mobile-apps',      'Приложения для удалённой работы с данными учётных систем',      'phone.png',      30),
(4, 'Аналитика данных',     'analytics',        'Дашборды, отчётность, BI-системы, прогнозирование',            'chart.png',      40),
(5, 'Техподдержка',         'support',          'Сопровождение, обновления, консультации пользователей',         'shield.png',     50),
(6, 'Облачные решения',     'cloud',            'Размещение 1С в облаке, миграция, масштабирование',             'cloud.png',      60);

-- Услуги компании
INSERT INTO `services`
    (`id`, `category_id`, `slug`, `title`, `short_description`, `description`, `features`, `price_from`, `image`, `is_active`, `sort_order`) VALUES
(1, 1, '1c-implementation', 'Продажа и внедрение 1С',
 'Подбор, установка и настройка программных продуктов 1С с глубокой адаптацией под бизнес-процессы',
 'Как официальный франчайзи фирмы «1С» мы гарантируем качество и надёжность решений. Наши специалисты имеют многолетний опыт внедрения систем 1С в компаниях различных отраслей — от малого бизнеса до крупных корпораций.',
 'Анализ требований\nПодбор оптимальной конфигурации\nУстановка и настройка системы\nИмпорт справочников и остатков\nОбучение сотрудников\nТехническая поддержка после запуска',
 50000, 'rocket.png', 1, 10),
(2, 1, 'custom-development', 'Индивидуальные доработки 1С',
 'Расширение функциональности стандартных решений 1С под уникальные требования предприятия',
 'Стандартные конфигурации 1С не всегда полностью соответствуют специфическим бизнес-процессам предприятия. Мы дорабатываем типовые и нетиповые конфигурации, создаём дополнительные отчёты, обработки и интеграционные решения.',
 'Доработка отчётов и аналитических панелей\nМодификация документов и справочников\nОптимизация производительности\nРасширения конфигураций без потери обновляемости\nВнешние обработки и отчёты',
 1500, 'bulb.png', 1, 20),
(3, 2, 'web-integration', 'Веб-интеграция и аналитика',
 'Интеграция 1С с веб-сервисами, мобильными приложениями и системами бизнес-аналитики',
 'Мы предлагаем комплексные решения по интеграции 1С с внешними системами, созданию API и разработке мобильных приложений. Решения позволяют синхронизировать данные между 1С и веб-магазинами, CRM, службами доставки и маркетплейсами.',
 'REST API и OData для обмена данными\nИнтеграция с интернет-магазинами и CRM\nОбмен в форматах JSON / XML\nСоздание личных кабинетов и порталов\nМобильные решения',
 100000, 'globe.png', 1, 30),
(4, 5, 'technical-support', 'Техническая поддержка',
 'Комплексное обслуживание и сопровождение систем 1С для бесперебойной работы',
 'Профессиональная техническая поддержка обеспечивает стабильную работу учётной системы. Мы предоставляем оперативные консультации, регулярные обновления и профилактическое обслуживание.',
 'Консультации специалистов\nОбновление конфигураций\nРезервное копирование\nМониторинг производительности\nПрофилактика сбоев',
 5000, 'shield.png', 1, 40),
(5, 4, 'business-analytics', 'Бизнес-аналитика',
 'Настройка систем отчётности и аналитических панелей для эффективного управления бизнесом',
 'Превратите данные вашей системы 1С в мощный инструмент для принятия управленческих решений. Мы создаём системы бизнес-аналитики, которые предоставляют ключевые метрики, выявляют тенденции и прогнозируют результаты деятельности.',
 'Финансовая и торговая аналитика\nКлиентская аналитика и RFM-сегментация\nДашборды Power BI\n1С:Аналитика и СКД\nПрогнозирование показателей',
 80000, 'chart.png', 1, 50),
(6, 6, 'cloud-solutions', 'Облачные решения',
 'Развёртывание и поддержка 1С в облачной инфраструктуре для гибкости и масштабируемости',
 'Переведите вашу систему 1С в облако и получите все преимущества современных облачных технологий: сокращение затрат на ИТ-инфраструктуру, гибкость, масштабируемость и доступ к системе из любой точки мира.',
 'Готовая инфраструктура 1С:Облако\nАвтоматическое обновление\nРезервное копирование\nДоступ 24/7 и безопасность\nМасштабирование ресурсов',
 2500, 'cloud.png', 1, 60);

-- Заказы заказчиков
INSERT INTO `orders`
    (`id`, `customer_id`, `category_id`, `freelancer_id`, `title`, `description`, `budget`, `deadline`, `complexity`, `skills`, `status`, `views`, `created_at`) VALUES
(1, 2, 2, NULL, 'Интеграция 1С:Розница с интернет-магазином',
 'Требуется интеграция конфигурации 1С:Розница с действующим интернет-магазином на WordPress. Необходим двусторонний обмен товарами, ценами, остатками и заказами, а также выгрузка статусов заказов из магазина в 1С.',
 150000, CURDATE() + INTERVAL 21 DAY, 'medium', '1С, API, WordPress', 'open', 47, NOW() - INTERVAL 3 DAY),
(2, 3, 4, NULL, 'Дашборд руководителя в Power BI по данным 1С',
 'Нужен интерактивный дашборд для руководства: выручка, маржа, дебиторская задолженность, выполнение плана продаж по менеджерам. Источник данных — 1С:Управление торговлей. Требуется автоматическое обновление раз в сутки.',
 90000, CURDATE() + INTERVAL 30 DAY, 'medium', 'Power BI, SQL, 1С', 'open', 31, NOW() - INTERVAL 5 DAY),
(3, 2, 1, 4, 'Доработка отчёта «Валовая прибыль» в 1С:Бухгалтерия',
 'Необходимо доработать типовой отчёт: добавить разрез по складам и группировку по номенклатурным группам. Работать через расширение конфигурации, чтобы не потерять возможность обновления.',
 70000, CURDATE() + INTERVAL 14 DAY, 'low', '1С, СКД, расширения', 'in_progress', 82, NOW() - INTERVAL 18 DAY),
(4, 3, 3, NULL, 'Мобильное приложение для ввода заявок с объектов',
 'Прорабы должны вносить данные с объектов в 1С через мобильное приложение: фото, комментарии, объёмы работ. Требуется офлайн-режим и последующая синхронизация через REST API.',
 300000, CURDATE() + INTERVAL 60 DAY, 'high', 'React Native, REST API, 1С', 'open', 64, NOW() - INTERVAL 8 DAY),
(5, 2, 6, NULL, 'Перенос базы 1С:ЗУП в облако и настройка доступа',
 'Требуется миграция базы 1С:ЗУП (около 40 Гб) в облачную инфраструктуру, настройка удалённого доступа для 25 сотрудников, резервное копирование и тестовый контур.',
 120000, CURDATE() + INTERVAL 25 DAY, 'medium', '1С, облака, администрирование', 'open', 19, NOW() - INTERVAL 2 DAY),
(6, 3, 1, 4, 'Оптимизация проведения документа «Реализация товаров»',
 'Документ проводится более 40 секунд из-за неоптимальных запросов в модуле. Нужно провести анализ, оптимизировать запросы и индексы, замерить результат до и после.',
 60000, CURDATE() - INTERVAL 3 DAY, 'medium', '1С, СКД, оптимизация', 'completed', 120, NOW() - INTERVAL 40 DAY),
(7, 2, 2, NULL, 'Синхронизация 1С с маркетплейсами Ozon и Wildberries',
 'Настроить автоматическую выгрузку карточек, цен и остатков и загрузку заказов с двух маркетплейсов. Обмен по API, журналирование ошибок и отчёт по расхождениям.',
 200000, CURDATE() + INTERVAL 45 DAY, 'high', '1С, API, маркетплейсы', 'open', 55, NOW() - INTERVAL 1 DAY),
(8, 3, 1, NULL, 'Разработка отчёта по себестоимости заказов',
 'Необходим отчёт по фактической себестоимости заказов покупателей с детализацией по материалам и работам. Конфигурация 1С:Управление нашей фирмой.',
 45000, CURDATE() + INTERVAL 10 DAY, 'low', '1С, СКД', 'cancelled', 12, NOW() - INTERVAL 25 DAY);

-- Отклики фрилансеров
INSERT INTO `responses` (`id`, `order_id`, `freelancer_id`, `message`, `price`, `days`, `status`, `created_at`) VALUES
(1, 1, 5, 'Делал аналогичную интеграцию для двух магазинов на WooCommerce. Предлагаю обмен через REST API с очередью и логированием. В стоимость входит настройка обмена заказами и статусами.', 140000, 18, 'pending', NOW() - INTERVAL 2 DAY),
(2, 1, 4, 'Могу закрыть со стороны 1С: настройка типового обмена с сайтом, доработка правил регистрации документов. Со стороны сайта потребуется помощь веб-разработчика.', 120000, 20, 'pending', NOW() - INTERVAL 1 DAY),
(3, 2, 6, 'Соберу дашборд в Power BI с ежедневным автообновлением через шлюз. Включу модель данных, меры DAX и обучение вашей команды.', 85000, 25, 'pending', NOW() - INTERVAL 4 DAY),
(4, 3, 4, 'Работу выполню через расширение конфигурации. Покажу результат на тестовой базе, после подтверждения перенесу на рабочую.', 68000, 10, 'accepted', NOW() - INTERVAL 17 DAY),
(5, 3, 5, 'Могу подключиться к доработке со стороны веб-интерфейса, если потребуется выгрузка отчёта на портал.', 70000, 12, 'rejected', NOW() - INTERVAL 16 DAY),
(6, 4, 7, 'Разработаю приложение на React Native с офлайн-хранением в SQLite и синхронизацией при появлении сети.', 290000, 50, 'pending', NOW() - INTERVAL 6 DAY),
(7, 6, 4, 'Провёл анализ: проблема в запросах в модуле проведения. Оптимизировал, время проведения сократилось с 42 до 3 секунд.', 55000, 7, 'accepted', NOW() - INTERVAL 35 DAY),
(8, 5, 5, 'Настрою перенос базы и разработаю регламент резервного копирования. Есть опыт миграции крупных баз ЗУП.', 115000, 20, 'pending', NOW() - INTERVAL 1 DAY);

-- Сообщения формы обратной связи
INSERT INTO `messages` (`name`, `email`, `phone`, `company`, `service`, `message`, `status`, `created_at`) VALUES
('Смирнов Игорь', 'smirnov@romashka.ru', '+7 (495) 111-22-33', 'ООО «Ромашка»', 'Продажа и внедрение 1С', 'Интересует переход с 1С:УНФ на 1С:УТ. Просим рассчитать стоимость и сроки.', 'new', NOW() - INTERVAL 2 DAY),
('Егорова Анна', 'egorova@tehnostroy.ru', '+7 (812) 555-99-00', 'ООО «ТехноСтрой»', 'Интеграция и аналитика', 'Нужна консультация по интеграции 1С с системой электронного документооборота.', 'processed', NOW() - INTERVAL 12 DAY);
