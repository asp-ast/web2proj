<?php
session_start();
require_once 'functions.php';
$pdo = getPDO();

$errors = [];
$values = [];
$success = false;
$message = '';
$authError = '';

$currentAppId = $_SESSION['app_id'] ?? null;
$isAuthenticated = $currentAppId && loadApplication($pdo, $currentAppId);

if (isset($_GET['logout'])) {
    unset($_SESSION['app_id']);
    session_destroy();
    header('Location: index.php');
    exit;
}

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? '')) die('Неверный CSRF-токен.');
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $appId = authenticateApplication($pdo, $login, $password);
    if ($appId) {
        $_SESSION['app_id'] = $appId;
        header('Location: index.php');
        exit;
    } else {
        $authError = 'Неверный логин или пароль.';
    }
}

// Обработка отправки формы (fallback без JS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    if (!checkCsrfToken($_POST['csrf_token'] ?? '')) die('Неверный CSRF-токен.');
    $form = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'birth_date' => trim($_POST['birth_date'] ?? ''),
        'gender' => $_POST['gender'] ?? '',
        'languages' => $_POST['languages'] ?? [],
        'biography' => trim($_POST['biography'] ?? ''),
        'contract_agreed' => $_POST['contract_agreed'] ?? ''
    ];
    $validationErrors = validate($form);
    if (!empty($validationErrors)) {
        setcookie('form_errors', json_encode($validationErrors), 0, '/');
        setcookie('form_values', json_encode($form), 0, '/');
        $errors = $validationErrors;
        $values = $form;
    } else {
        try {
            if ($isAuthenticated) {
                saveApplication($pdo, $form, $currentAppId);
                $success = true;
            } else {
                $appId = saveApplication($pdo, $form, null);
                $credentials = generateCredentials($pdo, $appId);
                $success = true;
                $message = "Логин: <b>{$credentials['login']}</b>, Пароль: <b>{$credentials['password']}</b> (сохраните)";
                setcookie('saved_data', json_encode($form), time() + 31536000, '/');
            }
            setcookie('form_errors', '', time() - 3600, '/');
            setcookie('form_values', '', time() - 3600, '/');
            $values = $form;
        } catch (Exception $e) {
            $errors['_db'] = 'Ошибка сохранения данных.';
            setcookie('form_errors', json_encode($errors), 0, '/');
            setcookie('form_values', json_encode($form), 0, '/');
            $values = $form;
        }
    }
}

// Заполнение из Cookies / БД
if (!$isAuthenticated) {
    if (isset($_COOKIE['form_errors'])) {
        $errors = json_decode($_COOKIE['form_errors'], true) ?: [];
        setcookie('form_errors', '', time() - 3600, '/');
    }
    if (isset($_COOKIE['form_values'])) {
        $values = json_decode($_COOKIE['form_values'], true) ?: [];
        setcookie('form_values', '', time() - 3600, '/');
    }
    if (empty($values) && isset($_COOKIE['saved_data'])) {
        $decoded = json_decode($_COOKIE['saved_data'], true);
        if (is_array($decoded)) {
            $values = $decoded;
        } else {
            setcookie('saved_data', '', time() - 3600, '/');
        }
    }
} else {
    $app = loadApplication($pdo, $currentAppId);
    if ($app) {
        $values = [
            'full_name' => $app['full_name'],
            'phone' => $app['phone'],
            'email' => $app['email'],
            'birth_date' => $app['birth_date'],
            'gender' => $app['gender'],
            'biography' => $app['biography'] ?? '',
            'contract_agreed' => $app['contract_agreed'] ? '1' : '0',
            'languages' => $app['languages'] ?? []
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="libs/bootstrap-4.0.0-dist/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="libs/slick/slick/slick.css"/>
    <link rel="stylesheet" type="text/css" href="libs/slick/slick/slick-theme.css"/>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="libs/jquery-3.4.1.min.js"></script>
    <script src="libs/bootstrap-4.0.0-dist/js/bootstrap.min.js"></script>
    <script type="text/javascript" src="libs/slick/slick/slick.min.js"></script>
    <title>Проект WEB</title>
</head>
<body data-authenticated="<?= $isAuthenticated ? 'true' : 'false' ?>" 
      data-user-id="<?= $isAuthenticated ? $currentAppId : '' ?>">

    <!-- 1. Хедер -->
    <header id="head">
        <video autoplay loop muted>
            <source src="imgs/video.mp4" type="video/mp4">
        </video>
        <img src="imgs/druplicon.svg" class="header_back_img" alt="">
        <div class="container">

            <div class="menus">
                <div class="mobile_head_menu">
                    <div class="container">
                        <a href="/" class="logo_a"><img src="imgs/logoText.svg" class="logo" alt=""></a>
                        <div class="burger_menu">
                            <div class="burger_menu_1"></div>
                        </div>
                    </div>
                </div>
        
                <nav>
                    <ul class="menu">
                        <li><a href="">ПОДДЕРЖКА DRUPAL</a></li>
                        <li class="menu_podcat">
                            <span><a href="">АДМИНИСТРИРОВАНИЕ</a></span>
                            <ul>
                                <li><a href="">МИГРАЦИЯ</a></li>
                                <li><a href="">БЭКАПЫ</a></li>
                                <li><a href="">АУДИТ БЕЗОПАСНОСТИ</a></li>
                                <li><a href="">ОПТИМИЗАЦИЯ СКОРОСТИ</a></li>
                                <li><a href="">ПЕРЕЕЗД НА HTTPS</a></li>
                            </ul>
                        </li>
                        <li><a href="">ПРОДВИЖЕНИЕ</a></li>
                        <li><a href="">РЕКЛАМА</a></li>
                        <li class="menu_podcat">
                            <span><a href="">О НАС</a></span>
                            <ul>
                                <li><a href="">КОМАНДА</a></li>
                                <li><a href="">DRUPALGIVE</a></li>
                                <li><a href="">БЛОГ</a></li>
                                <li><a href="">КУРСЫ DRUPAL</a></li>
                            </ul>
                        </li>
                        <li><a href="">ПРОЕКТЫ</a></li>
                        <li><a href="">КОНТАКТЫ</a></li>
                    </ul>
                </nav>
            </div>

            <div class="head_f">
                <div>
                    <h1>Поддержка<br> сайтов на Drupal</h1>
                    <h2>Сопровождение и поддержка сайтов<br>на CMS Drupal любых версий и запущенности</h2>
                    <button class="header_tarif_button">ТАРИФЫ</button>
                </div>
                <div class="header_categories">
                    <div>
                        <div class="zag header_zag_big">#1</div>
                        <div class="podzag">Drupal-разработчик в России по версии Рейтинга Рунета</div>
                    </div>
                    <div>
                        <div class="zag">3+</div>
                        <div class="podzag">средний опыт специалистов более 3 лет</div>
                    </div>
                    <div>
                        <div class="zag">14</div>
                        <div class="podzag">лет опыта в сфере Drupal</div>
                    </div>
                    <div>
                        <div class="zag">50+</div>
                        <div class="podzag">модулей и тем в формате DrupalGive</div>
                    </div>
                    <div>
                        <div class="zag">90 000</div>
                        <div class="podzag">часов поддержки сайтов на Drupal</div>
                    </div>
                    <div>
                        <div class="zag">300+</div>
                        <div class="podzag">Проектов на поддержке</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main>

        <!-- 2. Сервисы -->
        <section id="services">
            <div class="container">
                <h1>13 лет совершенствуем<br>компетенции в Друпал<br>поддержке!</h1>
                <div class="podzag1">Разрабатываем и оптимизируем модули, расширяем<br>функциональность сайтов, обновляем дизайн</div>

                <div class="services_parts">
                    <div class="services_part">
                        <div class="services_part_sor">
                            <div class="services_imgs">
                                <img src="imgs/servicesback.svg" alt="">
                                <img src="imgs/services1.svg" alt="">
                            </div>
                            <div class="podzag1">Добавление<br>информации на сайт,<br>создание новых<br>разделов</div>
                        </div>
                    </div>
                    <div class="services_part">
                        <div class="services_part_sor">
                            <div class="services_imgs">
                                <img src="imgs/servicesback.svg" alt="">
                                <img src="imgs/services2.svg" alt="">
                            </div>
                            <div class="podzag1">Разработка и<br> оптимизация модулей<br> сайта</div>
                        </div>
                    </div>
                    <div class="services_part">
                        <div class="services_part_sor">
                            <div class="services_imgs">
                                <img src="imgs/servicesback.svg" alt="">
                                <img src="imgs/services3.svg" alt="">
                            </div>
                            <div class="podzag1">Интеграция с CRM, 1C,<br>платежными системами,<br>любыми веб-сервисами</div>
                        </div>
                    </div>
                    <div class="services_part">
                        <div class="services_part_sor">
                            <div class="services_imgs">
                                <img src="imgs/servicesback.svg" alt="">
                                <img src="imgs/services4.svg" alt="">
                            </div>
                            <div class="podzag1">Любые доработки<br> функционала и дизайна</div>
                        </div>
                    </div>
                    <div class="services_part">
                        <div class="services_part_sor">
                            <div class="services_imgs">
                                <img src="imgs/servicesback.svg" alt="">
                                <img src="imgs/services5.svg" alt="">
                            </div>
                            <div class="podzag1">Аудит и мониторинг<br>безопасности Drupal<br>сайтов</div>
                        </div>
                    </div>
                    <div class="services_part">
                        <div class="services_part_sor">
                            <div class="services_imgs">
                                <img src="imgs/servicesback.svg" alt="">
                                <img src="imgs/services6.svg" alt="">
                            </div>
                            <div class="podzag1">Миграция, импорт<br>контента и апгрейд<br>Drupal</div>
                        </div>
                    </div>
                    <div class="services_part">
                        <div class="services_part_sor">
                            <div class="services_imgs">
                                <img src="imgs/servicesback.svg" alt="">
                                <img src="imgs/services7.svg" alt="">
                            </div>
                            <div class="podzag1">Оптимизация<br>и ускорение Drupal-сайтов</div>
                        </div>
                    </div>
                    <div class="services_part">
                        <div class="services_part_sor">
                            <div class="services_imgs">
                                <img src="imgs/servicesback.svg" alt="">
                                <img src="imgs/services8.svg" alt="">
                            </div>
                            <div class="podzag1">Веб-маркетинг,<br>консультации и работы<br> по SEO</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. Поддержка -->
        <section id="supports">
            <div class="container">
                <h1>Поддержка<br>от Drupal-coder</h1>
                <div class="supports_list">
                    <div>
                        <div class="support_num">01.</div>
                        <div class="zag">Постановка задачи по Email</div>
                        <div class="podzag">Удобная и привычная модель постановки задач, при которой задачи фиксируются и никогда не теряются.</div>
                        <img src="imgs/support1.svg" alt="">
                    </div>
                    <div>
                        <div class="support_num">02.</div>
                        <div class="zag">Система Helpdesk – отчетность, прозрачность</div>
                        <div class="podzag">Возможность посмотреть все заявки в работе и отработанные часы в личном кабинете через браузер.</div>
                        <img src="imgs/support2.svg" alt="">
                    </div>
                    <div>
                        <div class="support_num">03.</div>
                        <div class="zag">Расширенная техническая поддержка</div>
                        <div class="podzag">Возможность организации расширенной техподдержки с 6:00 до 22:00 без выходных.</div>
                        <img src="imgs/support3.svg" alt="">
                    </div>
                    <div>
                        <div class="support_num">04.</div>
                        <div class="zag">Персональный менеджер проекта</div>
                        <div class="podzag">Ваш менеджер проекта  всегда в курсе текущего состояния проекта и в любой момент готов ответить на любые вопросы.</div>
                        <img src="imgs/support4.svg" alt="">
                    </div>
                    <div>
                        <div class="support_num">05.</div>
                        <div class="zag">Удобные способы оплаты</div>
                        <div class="podzag">Безналичный расчет по договору или электронные деньги: WebMoney, Яндекс.Деньги, Paypal.</div>
                        <img src="imgs/support5.svg" alt="">
                    </div>
                    <div>
                        <div class="support_num">06.</div>
                        <div class="zag">Работаем с SLA и NDA</div>
                        <div class="podzag">Работа в рамках соглашений о конфиденциальности и об уровне качетсва работ.</div>
                        <img src="imgs/support6.svg" alt="">
                    </div>
                    <div>
                        <div class="support_num">07.</div>
                        <div class="zag">Штатные специалисты</div>
                        <div class="podzag">Надежные штатные специалисты, никаких фрилансеров.</div>
                        <img src="imgs/support7.svg" alt="">
                    </div>
                    <div>
                        <div class="support_num">08.</div>
                        <div class="zag">Удобные каналы связи</div>
                        <div class="podzag">Консультации по телефону, скайпу, в месенджерах.</div>
                        <img src="imgs/support8.svg" alt="">
                    </div>
                </div>
            </div>
        </section>

        <!-- 4. Экспертиза -->
        <section id="expertise">
            <div class="container">
                <h1>Экспертиза в Drupal,<br> опыт 14 лет!</h1>
 
                <div class="header_categories">
                    <div>
                        <div class="podzag">Только системный подход –<br> контроль версий,<br> резервирование и<br> тестирование!</div>
                    </div>
                    <div>
                        <div class="podzag">Только Drupal сайты, не<br> берем на поддержку сайты<br> на других CMS!</div>
                    </div>
                    <div>
                        <div class="podzag">Учавствуем в разработке<br> ядра Drupal и модулей на<br> Drupal.org, разрабатываем<br> <a href="">свои модули Drupal</a></div>
                    </div>
                    <div>
                        <div class="podzag">Поддерживаем сайты на<br> Drupal 5, 6, 7 и 8</div>
                    </div>
                </div>
            </div>
            <img src="imgs/druplicon1.svg" class="expertise_back_img" alt="">
            <img src="imgs/laptop.png" class="expertise_laptop" alt="">
        </section>

        <!-- 5. Тарифы -->
        <section id="tarif">
            <div class="container">
                <h1>Тарифы</h1>
                
                <div class="tarif_categories">
                    <div class="tarif_category">
                        <div class="tarif_title">Стартовый</div>
                        <div class="tarif_price">2000<div>₽</div></div>
                        <div class="tarif_podprise">в час</div>
                        <hr>
                        <div class="tarif_items">
                            <div class="tarif_item">Консультации и работы по SEO</div>
                            <div class="tarif_item">Услуги дизайнера</div>
                            <div class="tarif_item">Неиспользованные оплаченные часы переносятся на следующий месяц</div>
                            <div class="tarif_item">Предоплата от 6 000 рублей в месяц</div>
                        </div>
                        <button class="tarif_button">СВЯЖИТЕСЬ С НАМИ!</button>
                    </div>

                    <div class="tarif_category active">
                        <div class="tarif_title">Бизнес</div>
                        <div class="tarif_price">2000<div>₽</div></div>
                        <div class="tarif_podprise">в час</div>
                        <hr>
                        <div class="tarif_items">
                            <div class="tarif_item">Консультации и работы по SEO</div>
                            <div class="tarif_item">Услуги дизайнера</div>
                            <div class="tarif_item">Высокое время реакции – до 2 рабочих дней</div>
                            <div class="tarif_item">Неиспользованные оплаченные часы не переносятся</div>
                            <div class="tarif_item">Предоплата от 30 000 рублей в месяц</div>
                        </div>
                        <button class="tarif_button button_select">СВЯЖИТЕСЬ С НАМИ!</button>
                    </div>

                    <div class="tarif_category">
                        <div class="tarif_title">VIP</div>
                        <div class="tarif_price">1800<div>₽</div></div>
                        <div class="tarif_podprise">в час</div>
                        <hr>
                        <div class="tarif_items">
                            <div class="tarif_item">Консультации и работы по SEO</div>
                            <div class="tarif_item">Услуги дизайнера</div>
                            <div class="tarif_item">Максимальное время реакции – в день обращения</div>
                            <div class="tarif_item">Неиспользованные оплаченные часы не переносятся</div>
                            <div class="tarif_item">Предоплата от 270 000 рублей в месяц</div>
                        </div>
                        <button class="tarif_button">СВЯЖИТЕСЬ С НАМИ!</button>
                    </div>
                </div>

                <div class="tarif_info subtitle">Вам не подходят наши тарифы? Оставьте заявку и мы<br> предложим вам индивидуальные условия!</div>
                <div class="tarif_individual">
                    <a class="podzag">Получить индивидуальный тариф</a>
                </div>
            </div>
        </section>

        <!-- 6. Задачи -->
        <section id="tasks">
            <div class="container">
                <h1>Наши профессиональные разработчики выполняют быстро любые задачи</h1>
                <div class="tasks_parts">
                    <div class="tasks_part">
                        <div class="tasks_part_sor">
                            <div class="tasks_imgs">
                                <img src="imgs/servicesback.svg" alt="">
                                <img src="imgs/tasks1.svg" alt="">
                            </div>
                            <h1>от 1ч</h1>
                            <div class="podzag1">Настройка события GA в<br>интернет-магазине</div>
                        </div>
                    </div>
                    <div class="tasks_part">
                        <div class="tasks_part_sor">
                            <div class="tasks_imgs">
                                <img src="imgs/servicesback.svg" alt="">
                                <img src="imgs/tasks2.svg" alt="">
                            </div>
                            <h1>от 20ч</h1>
                            <div class="podzag1">Разработка мобильной<br>версии сайта</div>
                        </div>
                    </div>
                    <div class="tasks_part">
                        <div class="tasks_part_sor">
                            <div class="tasks_imgs">
                                <img src="imgs/servicesback.svg" alt="">
                                <img src="imgs/tasks3.svg" alt="">
                            </div>
                            <h1>от 8ч</h1>
                            <div class="podzag1">Интеграция модуля<br>оплаты</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 7. Команда -->
        <section id="team">
            <div class="container">
                <h1>Команда</h1>
                <div id="teammates">
                    <div class="teammate">
                        <div class="teammate2">
                            <img src="imgs/team1.jpg" alt="">
                            <div class="name">Сергей Синица</div>
                            <div class="podzag">Руководитель отдела веб-разработки, канд. техн. наук, заместитель директора</div>
                        </div>
                    </div>
                    <div class="teammate">
                        <div class="teammate2">
                            <img src="imgs/team2.jpg" alt="">
                            <div class="name">Роман Агабеков</div>
                            <div class="podzag">Руководитель отдела DevOPS, директор</div>
                        </div>
                    </div>
                    <div class="teammate">
                        <div class="teammate2">
                            <img src="imgs/team3.jpg" alt="">
                            <div class="name">Сергей Синица</div>
                            <div class="podzag">Руководитель отдела поддержки сайтов</div>
                        </div>
                    </div>
                    <div class="teammate">
                        <div class="teammate2">
                            <img src="imgs/team4.jpg" alt="">
                            <div class="name">Дарья Бочкарёва</div>
                            <div class="podzag">Руководитель отдела продвижения, контекстной рекламы и контент-поддержки сайтов</div>
                        </div>
                    </div>
                    <div class="teammate">
                        <div class="teammate2">
                            <img src="imgs/team5.jpg" alt="">
                            <div class="name">Ирина Торкунова</div>
                            <div class="podzag">Менеджер по работе с клиентами</div>
                        </div>
                    </div>
                </div>
                <button class="team_button">Вся команда</button>
            </div>
        </section>

        <!-- 8. Кейсы -->
        <section id="cases">
            <div class="container">
                <div class="m-5">
                <h1 class="text-flex text-center pb-4">Последние кейсы</h1>
                <div class="fleximg">
                    <ul>
                    <li>
                        <img src="imgs/image 9.2.png" alt="1" loading="lazy" />
                        <h4>
                        Настройка выгрузкии YML для <br />
                        Яндекс. Маркета
                        </h4>
                        <p>
                        22.04.2019<br />
                        Эти слова совершенно справедливы, <br />однако гипнотический
                        рифф продолжает <br />паузный пласт
                        </p>
                    </li>
                    <li class="case_d2">
                        <div id="tekst_sverhu_kartinki">
                        <img src="imgs/image 11.1.png" alt="2" loading="lazy" />
                        <div class="tekst_sverhu_kartinki_1">
                            <h4>
                            Настройка выгрузкии YML для <br />
                            Яндекс. Маркета
                            </h4>
                        </div>
                        </div>
                    </li>
                    <li>
                        <div id="tekst_sverhu_kartinki">
                        <img src="imgs/image 8.3.png" alt="3" loading="lazy" />
                        <div class="tekst_sverhu_kartinki_1">
                            <h4>
                            Настройка выгрузкии YML для <br />
                            Яндекс. Маркета
                            </h4>
                            <span>22.04.2019</span>
                        </div>
                        </div>
                    </li>
                    <li>
                        <div id="tekst_sverhu_kartinki">
                        <img src="imgs/image 7.3.png" alt="4" loading="lazy" />
                        <div class="tekst_sverhu_kartinki_1">
                            <h4>
                            Настройка выгрузкии YML для <br />
                            Яндекс. Маркета
                            </h4>

                            <span>22.04.2019</span>
                        </div>
                        </div>
                    </li>
                    <li>
                        <img src="imgs/image 6.2.png" alt="5" loading="lazy" />
                        <h4>
                        Настройка выгрузкии YML для <br />
                        Яндекс. Маркета
                        </h4>
                        <p>
                        22.04.2019<br />
                        Эти слова совершенно справедливы, <br />однако гипнотический
                        рифф продолжает <br />паузный пласт
                        </p>
                    </li>

                    <li class="case_d2">
                        <div id="tekst_sverhu_kartinki">
                        <img src="imgs/image 10.1.png" alt="6" loading="lazy" />
                        <div class="tekst_sverhu_kartinki_1">
                            <h4>
                            Настройка выгрузкии YML для <br />
                            Яндекс. Маркета
                            </h4>
                        </div>
                        </div>
                    </li>
                    <li>
                        <div id="tekst_sverhu_kartinki">
                        <img src="imgs/image 6.3.png" alt="7" loading="lazy" />
                        <div class="tekst_sverhu_kartinki_1">
                            <h4>
                            Настройка выгрузкии YML для <br />
                            Яндекс. Маркета
                            </h4>
                            <span>22.04.2019</span>
                        </div>
                        </div>
                    </li>
                    </ul>
                </div>
                </div>
            </div>
        </section>

        <!-- 9. Отзывы -->
        <section id="reviews">              
            <div class="container3">
                <div class="text-slider">
                    <h1>Отзывы</h1>
                    <div class="container">
                        <div class="txts">
                            <div class="row txts1">
                                <div class="rero1">
                                    <div class="a" style="overflow: hidden; transition: 0.5s">
                                        <div class="aa">
                                            <div class="">
                                                <img src="imgs/review2.png" />
                                                <div class="pt-3 slidertext_text">
                                                    Долгие поиски единственного и неповторимого мастера
                                                    на многострадальный сайт www.cielparfum.com, который
                                                    был собран крайне некомпетентным программистом и раз
                                                    в месяц стабильно грозил погибнуть, привели меня на
                                                    сайт и, в итоге, к ребятам из Drupal-coder. И вот
                                                    уже практически полгода как не проходит и дня, чтобы
                                                    я не поудивлялась и не порадовалась своему везению!
                                                    Починили все, что не работало - от поиска до
                                                    отображения меню. Провели редизайн - не отходя от
                                                    желаемого, но со своими существенными и
                                                    качественными дополнениями. Осуществили ряд проектов
                                                    - конкурсы, тесты и тд. А уж мелких починок и
                                                    доработок - не счесть! И главное - все качественно и
                                                    быстро (не взирая на не самый "быстрый" тариф). Есть
                                                    вопросы - замечательный Алексей всегда подскажет,
                                                    поддержит, отремонтирует и/или просто сделает с
                                                    нуля. Есть задумка для реализации - замечательный
                                                    Сергей обсудит и предложит идеальный вариант. Есть
                                                    проблема - замечательные Надежда и Роман починят,
                                                    поправят, сделают! Ребята доказали, что эта CMS -
                                                    мощная и грамотная система управления. Надеюсь, что
                                                    наше сотрудничество затянется надолго! Спасибо!!!
                                                </div>
                                                <div class="slidertext_names">
                                                    С уважением, Наталья Сушкова руководитель Отдела веб-проектов Группы компаний «Си Эль парфюм»&nbsp;<a href="http://www.cielparfum.com/">http://www.cielparfum.com/</a>
                                                </div>
                                            </div>
                                            <div class="">
                                                <img src="imgs/review1.png" />
                                                <div class="pt-3 slidertext_text">
                                                Сергей — профессиональный, высококвалифицированый
                                                программист с огромным опытом в ИТ. Я долгое время
                                                общался с топ-фрилансерами (вся первая двадцатка) на
                                                веблансере и могу сказать, что С СЕРГЕЕМ ОНИ И РЯДОМ
                                                НЕ ВАЛЯЛИСЬ. Работаем с Сергеем до сих пор. С ним
                                                приятно работать, я доволен.
                                                </div>
                                                <div class="slidertext_names">
                                                    Сергей Чепурко, руководитель проекта&nbsp;<a href="http://www.personal-writer.com/">www.personal-writer.com</a>&nbsp;/&nbsp;<a href="http://www.writers-united.org/">www.writers-united.org</a>
                                                </div>
                                            </div>
                                            <div class="">
                                                <img src="imgs/review3.jpg" />
                                                <div class="pt-3 slidertext_text">
                                                Выражаю глубочайшую благодарность команде
                                                специалистов компании "Инитлаб" и лично Дмитрию
                                                Купянскому и Алексею Синице. Сайт был первоклассно
                                                перевёрстан из старой табличной модели в новую на
                                                базе Drupal с дополнительной функциональностью. Всё
                                                было сделано с высочайшим качеством и точно в сроки.
                                                Всем кому хочется без нервов и лишних вопросов
                                                разработать сайт рекомендую обращаться именно к этой
                                                команде профессионалов.
                                                </div>
                                                <div class="slidertext_names">
                                                    Леонид Александрович Ледовский
                                                </div>
                                            </div>
                                            <div class="">
                                                <img src="imgs/review4.png" />
                                                <div class="pt-3 slidertext_text">
                                                Моя электронная газета www.nashagazeta.ch существует
                                                в Швейцарии уже 10 лет. За это время мы сменили 7
                                                специалистов по техподдержке, и только сейчас, в
                                                последние несколько месяцев, с начала сотрудничества
                                                с Алексеем Синицей и его командой, я впервые
                                                почувствовала, что у меня есть надежный технический
                                                тыл. Без громких слов и обещаний, ребята просто
                                                спокойно и качественно делают работу, быстро
                                                реагируют, находят решения, предлагают конкретные
                                                варианты улучшения функционирования сайта и сами их
                                                оперативно осуществляют. Сотрудничество с ними –
                                                одно удовольствие!
                                                </div>
                                                <div class="slidertext_names">
                                                    Надежда Сикорская, Женева, Швейцария
                                                </div>
                                            </div>
                                            <div class="">
                                                <img src="imgs/review5.png" />
                                                <div class="pt-3 slidertext_text">
                                                Наша компания Estee Design занимается дизайном
                                                интерьеров. Сайт сверстан на Drupal. Искали
                                                программистов под выполнение ряда небольших
                                                изменений и корректировок по сайту. Пообщавшись с
                                                Алексеем Синицей, приняли решение о начале работ с
                                                компанией Initlab/drupal-coder. Сотрудничеством
                                                довольны на 100%. Четкая и понятная система
                                                коммуникации, достаточно оперативное решение по
                                                задачам. Дали рекомендации по улучшению програмной
                                                части сайта, исправили ряд скрытых ошибок. Никогда
                                                не пишу отзывы (нет времени), но в данном случае, по
                                                просьбе Алексея, не могу не рекомендовать Initlab
                                                другим людям - действительно компания
                                                профессионалов.
                                                </div>
                                                <div class="slidertext_names">
                                                    Кузин Вадим, руководитель строительного направления Дизайн-бюро интерьеров Estee <a href="http://estee-design.ru">estee-design.ru</a>
                                                </div>
                                            </div>
                                            <div class="">
                                                <img src="imgs/review6.png" />
                                                <div class="pt-3 slidertext_text">
                                                Наша компания за несколько лет сменила несколько
                                                команд программистов и специалистов техподдержки, и
                                                почти отчаялась найти на российском рынке адекватное
                                                профессиональное предложение по разумной цене. Пока
                                                мы не начали работать с командой "Инитлаб",
                                                воплощающей в себе все наши представления о
                                                нормальной системе взаимодействия в сочетании с
                                                профессиональным неравнодушием. Впервые в моей
                                                деловой практике я чувствую надежно прикрытыми
                                                важнейшие задачи в жизни электронного СМИ, при том,
                                                что мои коллеги работают за сотни километров от нас
                                                и мы никогда не встречались лично.
                                                </div>
                                                <div class="slidertext_names">
                                                    Константин Бельский, зам. генерального директора портала <a href="http://www.cableman.ru/">Кабельщик.ру</a>
                                                </div>
                                            </div>
                                            <div class="">
                                                <img src="imgs/review7.png" />
                                                <div class="pt-3 slidertext_text">
                                                За довольно продолжительный срок (2014 - 2016 годы)
                                                весьма плотной работы (интернет-магазин на безумно
                                                замороченном Drupal 6: устраняли косяки
                                                разработчиков, ускоряли сайт, сделали множество
                                                новых функций и т.п.) - только самые добрые эмоции
                                                от работы с командой Initlab / Drupal-coder: всегда
                                                можно рассчитывать на быструю и толковую помощь,
                                                поддержку, совет. Даже сейчас, не смотря на то, что
                                                мы почти год не работали на постоянной основе
                                                (банально закончились задачи), случайно возникшая
                                                проблема с сайтом была решена мгновенно! В общем,
                                                только самые искренние благодарности и рекомендации!
                                                Спасибо!
                                                </div>
                                                <div class="slidertext_names">
                                                    С уважением, Владислав, <a href="http://serebro.ag">Serebro.Ag</a>
                                                </div>
                                            </div>
                                            <div class="">
                                                <img src="imgs/review8.jpg" />
                                                <div class="pt-3 slidertext_text">
                                                Хотел поблагодарить за работу над нашими сайтами. За
                                                4 месяца работы привели сайт в порядок, а самое
                                                главное получили инструмент, с помощью мы теперь
                                                можем быстро и красиво выставлять контент для
                                                образования и работы с предприятиями
                                                http://lpcma.tsu.ru/ru/post/reference_material
                                                </div>
                                                <div class="slidertext_names">
                                                    Дмитрий Новиков, <a href="http://lpcma.tsu.ru">lpcma.tsu.ru</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row row_review_btns">
                                    <div class="review_btns">
                                        <div class="button_slide">
                                        <button class="b1 b11">
                                            <img src="imgs/arrow-left.svg" alt="arrow-left" />
                                        </button>
                
                                        <div class="review_nums ednum">01</div>
                                        <div class="review_nums rwns">/</div>
                                        <div class="review_nums">08</div>
                
                                        <button class="b2 b22">
                                            <img src="imgs/arrow-right.svg" alt="arrow-right" />
                                        </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="review_bg"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 10. Партнеры -->
        <section id="partners">
            <div class="container">
                <h1>С нами работают</h1>
                <h2>Десятки компаний доверяют нам самое ценное, что у них есть в интернете – свои сайты. Мы делаем всё, чтобы наше сотрудничество было долгим.</h2>
            </div>
            <div id="companies">
                <div class="slick autoplay">
                    <div class="partner">
                        <img src="imgs/partner1.svg" alt="">
                    </div>
                    <div class="partner">
                        <img src="imgs/partner2.svg" alt="">
                    </div>
                    <div class="partner">
                        <img src="imgs/partner3.svg" alt="">
                    </div>
                </div>
                <div class="slick autoplay2">
                    <div class="partner">
                        <img src="imgs/partner1.svg" alt="">
                    </div>
                    <div class="partner">
                        <img src="imgs/partner2.svg" alt="">
                    </div>
                    <div class="partner">
                        <img src="imgs/partner3.svg" alt="">
                    </div>
                </div>
            </div>
        </section>

        <!-- 11. FAQ -->
        <section id="FAQ">
            <div class="container">
                <h1>FAQ</h1>
                <div id="AskList">
                    <div id="FAQ1li" class="active">
                        <div class="faq_zag">Кто непосредственно занимается поддержкой?</div>
                        <div class="text">Сайты поддерживают штатные сотрудники ООО «Инитлаб», г. Краснодар, прошедшие специальное обучение и имеющие опыт работы с Друпал от 4 до 15 лет: 8 web разработчиков, 2 специалиста по SEO, 4 системных администратора.</div>
                    </div>
                    <div id="FAQ2li">
                        <div class="faq_zag">Как организована работа поддержки?</div>
                        <div class="text">Клиент выбирает один из тарифов и делает предоплату. Все заявки на работы по сайту заводятся в нашу систему Service desk. В течение времени реакции по тарифу закрепленный за проектом специалист приступает к выполнению заявки, фиксирует время, потраченное на выполнение заявки и закрывает заявку по окончанию работы. Клиент может посмотреть все заявки в работе и отработанные часы в своем кабинете через веб-интерфейс.</div>
                    </div>
                    <div id="FAQ3li">
                        <div class="faq_zag">Что происходит, когда отработаны все предоплаченные часы за месяц?</div>
                        <div class="text">Мы уведомляем Клиента, о том, что последующие заявки в этом месяце будут выполняться с доплатой согласно тарифу либо будут перенесены по срокам на следующий месяц. В конце месяца, в котором были заявки с доплатой, клиентам отправляется счет на доплату по факту выполненных работ сверх предоплаты тарифного плана.</div>
                    </div>
                    <div id="FAQ4li">
                        <div class="faq_zag">Что происходит, когда не отработаны все предоплаченные часы за месяц?</div>
                        <div class="text">Предоплата не переносится на следующий месяц и деньги не возвращаются. Для продолжения поддержки сайта необходимо оплатить предоплату согласно выбранному тарифу за следующий месяц.</div>
                    </div>
                    <div id="FAQ5li">
                        <div class="faq_zag">Как происходит оценка и согласование планируемого времени на выполнение заявок?</div>
                        <div class="text">Заявки, выполнение которых укладывается в оставшееся предоплаченное время, по умолчанию не оцениваются и время не согласовывается, мы стараемся сэкономить время и быстро выполнить работу. Если видим, что задача не укладывается в предоплаченное время, то делаем примерную оценку по времени и согласовываем оценку трудоемкости с Клиентом. Клиент может явно попросить предварительно оценить работу по времени и согласовать трудоемкость, направив заявку с припиской в теме письма «Оценить работу». В этом случае время на оценку также оплачивается согласно тарифу поддержки. Если время фактического выполнения превышает заранее согласованное, то уточняем оценку, договариваемся о возможной доплате и переносе сроков по задаче. На наше усмотрение большие задачи, трудоемкость которых мы можем заранее оценить, например, разработка отдельных модулей или самостоятельных разделов сайта по подробному техническому заданию, могут быть вынесены из поддержки в отдельный проект на договорных условиях.</div>
                    </div>
                    <div id="FAQ6li">
                        <div class="faq_zag">Сколько программистов выделяется на проект?</div>
                        <div class="text">1-2 человека. За каждым проектом закрепляется один ответственный специалист, через которого проходят все заявки. Все заявки становятся в очередь к ответственному в порядке их поступления в Helpdesk. Возможно привлечение дополнительных штатных специалистов в поддержку проекта в случаях, когда:
                            • объем заявок стабильно большой и ответственный не успевает выполнять задачи;
                            • ответственный не может начать работу по заявке в срок (заболел, ушел в отпуск);
                            • специфика задачи такова, что кто-то в команде сделает эту работу существенно быстрее.</div>
                    </div>
                    <div id="FAQ7li">
                        <div class="faq_zag">Как подать заявку на внесение изменений на сайте?</div>
                        <div class="text">Для подачи заявки на внесение изменений на сайте необходимо написать письмо на email поддержки с указанием в теме письма домена сайта и подробным описанием вносимых изменений.</div>
                    </div>
                    <div id="FAQ8li">
                        <div class="faq_zag">Как подать заявку на добавление пользователя, изменение настроек веб-сервера и других задач по администрированию?</div>
                        <div class="text">Для любых задач по администрированию необходимо написать письмо на email поддержки с указанием домена сайта и описанием задачи.</div>
                    </div>
                    <div id="FAQ9li">
                        <div class="faq_zag">В течение какого времени начинается работа по заявке?</div>
                        <div class="text">Время реакции зависит типа заявки и от выбранного тарифного плана. Время реакции на сбои, обнаруживаемые системой мониторинга, от 2-х часов в рабочее время. Время реакции на заявки по доработке сайта от 24-х часов в рабочее время. Время реакции может быть более гибко определено в SLA.</div>
                    </div>
                    <div id="FAQ10li">
                        <div class="faq_zag">В какое время работает поддержка?</div>
                        <div class="text">Время работы поддержки с 10 до 18 часов (MSK, GMT+3) в рабочие дни. Мы также можем выполнять работы сверхурочно по договоренности с оплатой по двойной ставке часа.</div>
                    </div>
                    <div id="FAQ11li">
                        <div class="faq_zag">Подходят ли услуги поддержки, если необходимо произвести обновление ядра Drupal или модулей?</div>
                        <div class="text">Обновление безопасности в рамках одной ветки Drupal делается в режиме поддержки на всех тарифных планах. Обновление, связанное с переходом на следующую ветку Drupal, например апгрейд с 6.х на 7.х, лучше делать в рамках отдельного проекта, стоимость и сроки которого мы можем предложить после проведения аудита апгрейда. Аудит апгрейда включает в себя анализ путей миграции и трудоемкости обновления для всех модулей, блоков кода, Views, тем оформления, стоит от 6500 рублей и, как правило, занимает 1-3 дня, в зависимости от размера сайта.</div>
                    </div>
                    <div id="FAQ12li">
                        <div class="faq_zag">Можно ли пообщаться со специалистом голосом или в мессенджере?</div>
                        <div class="text">Для этого нужно написать заявку на консультацию на email поддержки и сообщить удобное время и контактные данные.</div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- 12. Футер с формой анкеты -->
    <footer id="form">
        <div class="container">
            <div class="form_contacts">
                <h1><?= $isAuthenticated ? 'Редактирование анкеты' : 'Оставить заявку на поддержку сайта' ?></h1>
                <h2>Заполните анкету, чтобы получить доступ к редактированию данных в будущем.</h2>
                <div class="form_contact">
                    <div class="form_phone"><a href="tel:+78002222673">8 800 222-26-73</a></div>
                    <div class="form_mail"><a href="mailto:info@drupal-coder.ru">info@drupal-coder.ru</a></div>
                </div>
            </div>

            <?php if ($isAuthenticated): ?>
                <p style="color:#fff;">Вы вошли как <strong><?= h($values['full_name'] ?? '') ?></strong>. <a href="?logout">Выйти</a></p>
            <?php else: ?>
                <div class="login-form">
                    <h3>Вход для редактирования</h3>
                    <?php if ($authError): ?><div class="error-block"><?= h($authError) ?></div><?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="login">
                        <div class="form-group"><label>Логин:</label><input type="text" name="login" required></div>
                        <div class="form-group"><label>Пароль:</label><input type="password" name="password" required></div>
                        <button type="submit">Войти</button>
                    </form>
                </div>
            <?php endif; ?>

            <div id="messageBox">
                <?php if ($success && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])): ?>
                    <div class="success-block">Данные успешно сохранены!</div>
                    <?php if ($message): ?><div class="message"><?= $message ?></div><?php endif; ?>
                <?php endif; ?>
            </div>
            <div id="errorBox">
                <?php if (!empty($errors)): ?>
                    <div class="error-block"><ul>
                        <?php foreach ($errors as $field => $msg): ?>
                            <li><strong><?= h($field) ?>:</strong> <?= h($msg) ?></li>
                        <?php endforeach; ?>
                    </ul></div>
                <?php endif; ?>
            </div>

            <form id="mainForm" method="post" action="index.php">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <div class="form-group <?= isset($errors['full_name']) ? 'error' : '' ?>">
                    <label for="full_name">ФИО</label>
                    <input type="text" id="full_name" name="full_name" value="<?= h($values['full_name'] ?? '') ?>" required placeholder="Ваше ФИО">
                    <?php if (isset($errors['full_name'])): ?><span class="error-hint"><?= h($errors['full_name']) ?></span><?php endif; ?>
                </div>

                <div class="form-group <?= isset($errors['phone']) ? 'error' : '' ?>">
                    <label for="phone">Телефон</label>
                    <input type="tel" id="phone" name="phone" value="<?= h($values['phone'] ?? '') ?>" required placeholder="Телефон">
                    <?php if (isset($errors['phone'])): ?><span class="error-hint"><?= h($errors['phone']) ?></span><?php endif; ?>
                </div>

                <div class="form-group <?= isset($errors['email']) ? 'error' : '' ?>">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" value="<?= h($values['email'] ?? '') ?>" required placeholder="E-mail">
                    <?php if (isset($errors['email'])): ?><span class="error-hint"><?= h($errors['email']) ?></span><?php endif; ?>
                </div>

                <div class="form-group <?= isset($errors['birth_date']) ? 'error' : '' ?>">
                    <label for="birth_date">Дата рождения</label>
                    <input type="date" id="birth_date" name="birth_date" value="<?= h($values['birth_date'] ?? '') ?>" required>
                    <?php if (isset($errors['birth_date'])): ?><span class="error-hint"><?= h($errors['birth_date']) ?></span><?php endif; ?>
                </div>

                <div class="form-group <?= isset($errors['gender']) ? 'error' : '' ?>">
                    <label>Пол</label>
                    <div class="radio-group">
                        <label><input type="radio" name="gender" value="male" <?= ($values['gender'] ?? '') === 'male' ? 'checked' : '' ?>> Мужской</label>
                        <label><input type="radio" name="gender" value="female" <?= ($values['gender'] ?? '') === 'female' ? 'checked' : '' ?>> Женский</label>
                    </div>
                    <?php if (isset($errors['gender'])): ?><span class="error-hint"><?= h($errors['gender']) ?></span><?php endif; ?>
                </div>

                <div class="form-group <?= isset($errors['languages']) ? 'error' : '' ?>">
                    <label for="languages">Любимый язык программирования</label>
                    <select name="languages[]" id="languages" multiple required>
                        <?php
                        $langs = [1=>'Pascal',2=>'C',3=>'C++',4=>'JavaScript',5=>'PHP',6=>'Python',7=>'Java',8=>'Haskell',9=>'Clojure',10=>'Prolog',11=>'Scala',12=>'Go'];
                        $selected = $values['languages'] ?? [];
                        foreach ($langs as $id => $name):
                            $sel = in_array((string)$id, array_map('strval', $selected)) ? 'selected' : '';
                            echo "<option value=\"$id\" $sel>$name</option>";
                        endforeach;
                        ?>
                    </select>
                    <?php if (isset($errors['languages'])): ?><span class="error-hint"><?= h($errors['languages']) ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="biography">Биография</label>
                    <textarea id="biography" name="biography" rows="5" placeholder="Расскажите о себе"><?= h($values['biography'] ?? '') ?></textarea>
                </div>

                <div class="form-group checkbox-group <?= isset($errors['contract']) ? 'error' : '' ?>">
                    <input type="checkbox" id="contract" name="contract_agreed" value="1" <?= ($values['contract_agreed'] ?? '') === '1' ? 'checked' : '' ?> required>
                    <label for="contract">С контрактом ознакомлен(а)</label>
                    <?php if (isset($errors['contract'])): ?><span class="error-hint"><?= h($errors['contract']) ?></span><?php endif; ?>
                </div>

                <button type="submit">Сохранить</button>
            </form>
        </div>

        <div class="razdel"></div>
        <div class="container">
            <div class="socials">
                <a class="social"><i class="fab fa-vk"></i></a>
                <a class="social"><i class="fab fa-telegram-plane"></i></a>
                <a class="social"><i class="fab fa-youtube"></i></a>
                <a class="social"><div class="social_fb">ФБ</div></a>
            </div>
            <div class="footer_end">
                ООО «Инитлаб», Краснодар, Россия.<br>
                Drupal является зарегистрированной торговой маркой Dries Buytaert.
            </div>
        </div>
    </footer>

    <script src="script.js"></script>
</body>
</html>