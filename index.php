<?php
include_once 'core/head.php';
include_once 'core/setting.php';
 
?>

<body>

    <!-- Ссылка «к содержимому». Шапка .fixed-top перекрывает 72px, а до формы
         бронирования по кнопке Tab надо пройти всю навигацию, поэтому при
         клавиатурной навигации первые полосы страницы уезжали под шапку.
         Ссылка видима только с клавиатуры — наведение мышью её не показывает.
         Класс page-scroll обязателен: он отдаёт переход smooth-обработчику из
         booking-2026.js, который отступает на реальную высоту шапки. Без него
         сработал бы штатный переход по якорю и цель уехала под .fixed-top.
         tabindex="-1" на <main> позволяет перевести туда и фокус. -->
    <a class="bk-skip page-scroll" href="#main">Перейти к содержимому</a>

    <!-- Маска для значка App Store. Кнопки магазинов выводятся трижды
         ($store_btns в шапке, в промо и в подвале), а маска с одинаковым id в
         каждой копии дала бы невалидный дубль, поэтому определение одно на
         документ, и все копии кнопок ссылаются на него. -->
    <svg xmlns="http://www.w3.org/2000/svg" width="0" height="0"
        style="position:absolute" aria-hidden="true" focusable="false">
        <defs>
            <mask id="bk-apple-mask">
                <rect width="24" height="24" fill="#000"/>
                <circle cx="12" cy="13.4" r="5.5" fill="#fff"/>
                <ellipse cx="12" cy="17" rx="6.5" ry="4" fill="#fff"/>
                <circle cx="18" cy="11.2" r="3.9" fill="#000"/>
                <ellipse cx="12" cy="21.4" rx="3" ry="2.8" fill="#000"/>
            </mask>
        </defs>
    </svg>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top navbar-light">
        <div class="container">

            <!-- Text Logo - Use this if you don't have a graphic logo -->
            <!-- <a class="navbar-brand logo-text page-scroll" href="index.html">Pavo</a>
Image Logo -->
            <a class="navbar-brand logo-image" href="/"><img src="images/logo.png" width="183" height="44"
                    alt="МинскЛайн" fetchpriority="high" decoding="async"></a>

            <!-- aria-label: без него кнопка объявляется просто «кнопка» —
                 иконка-гамбургер не читается. aria-expanded выставлен сразу
                 в разметке, а не только скриптом после первого клика, иначе
                 в момент загрузки состояние неизвестно. -->
            <button class="navbar-toggler p-0 border-0" type="button" data-toggle="offcanvas"
                aria-label="Открыть меню" aria-controls="navbarsExampleDefault" aria-expanded="false">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="navbar-collapse offcanvas-collapse" id="navbarsExampleDefault">
                <ul class="navbar-nav ml-auto">
                    <?php foreach ($main_mas_text['menu'] as $item) { ?>
                        <li class="nav-item">
                            <a class="nav-link page-scroll" href="<?= $item['url'] ?>"><?= $item['name'] ?></a>
                        </li>
                    <?php } ?>
                </ul>
                <span class="nav-item bk-social-top">
                    <?= $main_mas_text['app'] ?>
                </span>
            </div> <!-- end of navbar-collapse -->
        </div> <!-- end of container -->
    </nav> <!-- end of navbar -->
    <!-- end of navigation -->

    <main id="main" tabindex="-1">
    <!-- Header -->
    <header id="header" class="header">
        <div class="container">
            <div class="row">
                <div class="col-lg-7">
                    <div class="text-container">
                        <!-- Sits at the top of the text column so it mirrors the phone
                             mockup, which sits at the top of the image column. Both are
                             the first thing in their column, so the two halves of the
                             hero carry equal visual weight at the same height. -->
                        <p class="bk-badge">
                            <span class="bk-badge-mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M5 13l4 4L19 7" fill="none" stroke="currentColor"
                                        stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <span class="bk-badge-text">МинскЛайн — официальный перевозчик</span>
                        </p>
                        <?= $main_mas_text['header']['h1-large_text'] ?>
                        <p class="p-large bk-lead">
                            <?= $main_mas_text['header']['p-large'] ?>
                        </p>
                        <ul class="bk-phones">
                            <li><img class="bk-op-logo" src="images/op-mts.svg" width="64" height="40" alt="МТС">
                                <a href="tel:+375333323232">(033) 332-32-32</a>
                            </li>
                            <li><img class="bk-op-logo" src="images/op-a1.svg" width="64" height="40" alt="A1">
                                <a href="tel:+375293323232">(029) 332-32-32</a>
                            </li>
                            <li><img class="bk-op-logo" src="images/op-life.svg" width="64" height="40" alt="life:)">
                                <a href="tel:+375259323232">(025) 932-32-32</a>
                            </li>
                        </ul>
                        <?= $store_btns ?>
                    </div> <!-- end of text-container -->
                </div> <!-- end of col -->
                <div class="col-lg-5">
                    <div class="image-container">
                        <img class="img-fluid" src="images/header-smartphone.png" width="344" height="612"
                            alt="Приложение МинскЛайн на смартфоне" fetchpriority="high" decoding="async">
                    </div> <!-- end of image-container -->
                </div> <!-- end of col -->
            </div> <!-- end of row -->
        </div> <!-- end of container -->
    </header> <!-- end of header -->
    <!-- end of header -->

    <?php if ($main_mas_text['introduction']['enable']) { ?>
        <!-- Introduction -->
        <div class="basic-1">
            <div class="container">
                <div class="row">
                    <!-- .bk-promo-line is one cell holding the text and the buttons
                         side by side. As two col-lg-12 they stacked, and the pair of
                         Bootstrap columns put 30px of vertical padding at the join
                         plus a 15px inset all round — 60px of the block's height was
                         gutter, not content. -->
                    <div class="col-lg-12 bk-promo-line">
                        <?= $main_mas_text['introduction']['text'] ?>
                        <?= $store_btns ?>
                    </div><!-- end of col -->
                </div> <!-- end of row -->
            </div> <!-- end of container -->
        </div> <!-- end of basic-1 -->
        <!-- end of introduction -->
    <?php } ?>
    <!-- Новостной блок. Стоит перед модулем брони: цена проезда — ровно то,
         о чём человек узнаёт, заполняя форму, и новость должна быть до неё.
         <section>, а не <aside>: это основной контент страницы, а не
         второстепенная вставка, иначе скринридер переносит её в конец. -->
    <section class="bk-news" aria-labelledby="bk-news-title">
        <div class="container">
            <div class="bk-news-card">
                <div class="bk-news-body">
                    <span class="bk-news-badge">Новость</span>
                    <h2 class="bk-news-title" id="bk-news-title">Минсклайн: Новый уровень комфорта</h2>
                    <p class="bk-news-lead">С 16 октября 2026 года стоимость проезда составит 22 рубля.</p>
                    <p class="bk-news-text">Мы продолжаем инвестировать в обновление автопарка, безопасность и
                        пунктуальность, чтобы ваши ежедневные поездки оставались удобными и надежными.</p>
                    <p class="bk-news-text">Спасибо, что вы с нами!</p>
                </div>
                <!-- Фото 787×590 — обрезано из исходника 1280×590 (окно
                     x 70…857), потому что исходник шире слота 4:3 и
                     object-fit: cover срезал бы его по центру и отрезал
                     логотип, табло и номер — то есть ровно то, что делает
                     кадр узнаваемым. Пропорции файла и aspect-ratio в CSS
                     совпадают, поэтому cover ничего не режет.
                     width/height обязательны: без них до загрузки резервируется
                     нулевая высота и карточка прыгает.

                     ?v=$asset_v — не конвенция проекта (остальные картинки идут
                     без метки), но здесь она нужна: .htaccess отдаёт jpeg с
                     Cache-Control immutable на год. Заглушка и это фото делят
                     один URL, и без метки у вернувшихся посетителей осталась
                     бы заглушка. При замене фото поднять $asset_v.

                     loading="lazy" ЗДЕСЬ НЕ РАБОТАЕТ и удалён намеренно.
                     Проверено на реальной прокрутке: картинка уходит за нижний
                     край вьюпорта, проходит его (rectTop 906 → −594) и не
                     стартует — currentSrc пустой, naturalWidth 0x0 всё время.
                     Файл при этом отдаётся нормально, и new Image с тем же
                     URL грузит 787×590, то есть дело не в сети и не в кеше.
                     Причина — нативная отложенная загрузка на элементе,
                     чья высота приходит из грид-строки, и browser решает
                     не начинать загрузку. 71 КБ не стоят того, чтобы фото
                     в новости молча не появилось. -->
                <figure class="bk-news-media">
                    <img src="images/news-sprinter.jpg?v=<?= $asset_v ?>" width="787" height="590"
                        alt="Жёлтый микроавтобус Мерседес Спринтер Минсклайн: логотип на лобовом стекле, табло «Бобруйск — Минск» и госномер 6702ТК"
                        decoding="async">
                </figure>
            </div>
        </div>
    </section>

    <!-- Details 1 — Online booking -->
    <section id="details" class="basic-2 bk-section">
        <div class="container">

            <div id="apps" class="vue">
                <div class="reservation_form reservation_anchor"
                    style="background-color: <?= $color_back_reservation ?>">
                    <!-- No nested .container here on purpose: this section already
                    sits in one, and a second Bootstrap .container stacked its
                    own 15px gutters plus a 960px max-width on top, which ate
                    ~30px off every phone screen and capped the card on desktop.
                    The outer container is normalised in css/booking-2026.css
                    (`.bk-section > .container`); the wrapper below owns the
                    gutter and the 1160px measure. -->
                    <div reservation-form="reservation-form" class="reservation_form_wrapper">

                        <header class="bk-head">
                            <span class="bk-eyebrow">Онлайн-бронирование</span>
                            <h2 class="bk-title">Билет за пару минут</h2>
                            <p class="bk-sub">Выберите направление, дату и рейс — остальное подскажем.
                            Оплата при посадке, наличными или картой.</p>
                        </header>

                        <div result-screen="result-screen"></div>

                        <div main-form="main-form" class="bk-card">
                            <ol class="bk-steps" data-steps aria-label="Шаги бронирования">
                                <li data-step="route" data-state="active">
                                    <span class="bk-step-label">Маршрут</span>
                                </li>
                                <li data-step="trip" data-state="todo">
                                    <span class="bk-step-label">Рейс</span>
                                </li>
                                <li data-step="passenger" data-state="todo">
                                    <span class="bk-step-label">Пассажир</span>
                                </li>
                                <li data-step="confirm" data-state="todo">
                                    <span class="bk-step-label">Подтверждение</span>
                                </li>
                            </ol>

                            <div class="bk-body">
                                <div class="reservation_form_lines">

                                <!-- ── 1. Маршрут ──────────────────────────── -->
                                    <div class="reservation_form_line bk-row bk-row--route">
                                        <div class="reservation_form_item bk-field">
                                            <label class="bk-label" for="bk-from">Откуда</label>
                                            <div class="bk-select">
                                                <select id="bk-from" class="custom_form_control blue_skin"
                                                    v-model="city_a" :disabled="!cityFrom">
                                                    <option class="custom_form_control_value"
                                                        v-for="item in cityFrom" :value="item.value">
                                                        {{item.text}}
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Lives in the DOM between the two selects so that
                                             visual order, tab order and the accessibility
                                             tree all agree. On wide screens CSS drops it
                                             into its own grid lane between them; below
                                             768px it becomes a full-width row. -->
                                        <div class="reservation_form_direction_reverse"
                                            role="button" tabindex="0"
                                            aria-label="Поменять местами «Откуда» и «Куда»"
                                            @click="swapRoute" @keydown.enter.prevent="swapRoute"
                                            @keydown.space.prevent="swapRoute"></div>

                                        <div class="reservation_form_item bk-field">
                                            <label class="bk-label" for="bk-to">Куда</label>
                                            <div class="bk-select">
                                                <select id="bk-to" class="custom_form_control blue_skin"
                                                    v-model="city_b" :disabled="!cityTo">
                                                    <option class="custom_form_control_value"
                                                        v-for="item in cityTo" :value="item.value">
                                                        {{item.text}}
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="reservation_form_item bk-field bk-field--date">
                                            <label class="bk-label" for="bk-date">Дата</label>
                                            <div class="bk-datepicker" ref="datepicker"
                                                v-click-outside="closeDatepicker">
                                                <div class="bk-datepicker-control">
                                                    <input id="bk-date"
                                                        class="custom_form_control blue_skin form-control"
                                                        type="text"
                                                        v-model="dateFormatted"
                                                        @click="openDatepicker"
                                                        @focus="openDatepicker"
                                                        readonly
                                                        autocomplete="off"
                                                        placeholder="Выберите дату"
                                                        :aria-invalid="!date && touched ? 'true' : null"
                                                        aria-describedby="bk-date-hint"
                                                        required>
                                                    <span class="bk-datepicker-icon" aria-hidden="true">
                                                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none"
                                                            stroke="currentColor" stroke-width="1.75"
                                                            stroke-linecap="round" stroke-linejoin="round"
                                                            focusable="false">
                                                            <rect x="3.25" y="4.75" width="17.5" height="16" rx="3.25"/>
                                                            <path d="M3.25 9.5h17.5"/>
                                                            <path d="M8 2.5v4.25"/>
                                                            <path d="M16 2.5v4.25"/>
                                                            <circle cx="12" cy="15.25" r="1.75" fill="currentColor" stroke="none"/>
                                                        </svg>
                                                    </span>
                                                </div>
                                                <span id="bk-date-hint" class="bk-hint">Выберите дату поездки</span>
                                                
                                                <!-- Календарь -->
                                                <div class="bk-calendar" v-if="showDatepicker">
                                                    <div class="bk-calendar-header">
                                                        <button type="button" class="bk-calendar-nav" @click="prevMonth" aria-label="Предыдущий месяц">
                                                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                                                <polyline points="15 18 9 12 15 6"/>
                                                            </svg>
                                                        </button>
                                                        <span class="bk-calendar-title">{{ calendarTitle }}</span>
                                                        <button type="button" class="bk-calendar-nav" @click="nextMonth" aria-label="Следующий месяц">
                                                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                                                <polyline points="9 18 15 12 9 6"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <div class="bk-calendar-weekdays">
                                                        <span v-for="day in weekdays" :key="day">{{ day }}</span>
                                                    </div>
                                                    <div class="bk-calendar-days">
                                                        <button v-for="day in calendarDays" :key="day.date" type="button"
                                                            :class="day.classes" :disabled="day.disabled"
                                                            :aria-label="day.day + ' ' + monthNameLong(day.date)"
                                                            @click="selectDate(day.date)">
                                                            {{ day.day }}
                                                        </button>
                                                    </div>
                                                    <div class="bk-calendar-footer">
                                                        <button type="button" class="bk-calendar-today" @click="selectToday">{{ todayLabel }}</button>
                                                        <button type="button" class="bk-calendar-clear" @click="clearDate">{{ clearLabel }}</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                <!-- ── 2. Рейс и остановки ──────────────────── -->
                                    <div class="reservation_form_line bk-row bk-row--3">
                                        <div class="reservation_form_item bk-field">
                                            <label class="bk-label" for="bk-time">Время отправления</label>
                                            <div class="bk-select" v-if="reis.length > 0">
                                                <select id="bk-time" class="custom_form_control blue_skin with_note"
                                                    v-model="idReis">
                                                    <option v-for="item in reis" :value="item.Race"
                                                        :disabled="item.FreePlace == 0 || item.FreePlace == 1">

                                                        <template v-if="item.FreePlace == 0">
                                                            <label
                                                                class="select_time_no_places">{{item.time_reis}}
                                                            / Мест нет</label>
                                                        </template>


                                                        <template v-else-if="item.FreePlace >= 5">
                                                            <label
                                                                class="select_time_no_places">{{item.time_reis}}
                                                            / 5 мест</label>
                                                        </template>

                                                        <template v-else-if="item.FreePlace == 4">
                                                            <label
                                                                class="select_time_no_places">{{item.time_reis}}
                                                            / 4 места</label>
                                                        </template>

                                                        <template v-else-if="item.FreePlace == 3">
                                                            <label
                                                                class="select_time_no_places">{{item.time_reis}}
                                                            / 3 места</label>
                                                        </template>

                                                        <template v-else-if="item.FreePlace == 2">
                                                            <label
                                                                class="select_time_no_places">{{item.time_reis}}
                                                            / 2 места</label>
                                                        </template>

                                                        <template v-else-if="item.FreePlace == 1">
                                                            <label class="select_time_no_places"
                                                                disabled>{{item.time_reis}} / 1 место (Через
                                                            диспетчера)</label>
                                                        </template>

                                                        <template v-else>
                                                            <label
                                                                class="select_time_no_places">{{item.time_reis}}
                                                            / Мест нет</label>
                                                        </template>

                                                    </option>
                                                </select>
                                            </div>
                                            <div v-else-if="loadingReis" class="bk-skel bk-skel--ctl" aria-hidden="true"></div>
                                            <div v-else class="bk-empty" role="status">
                                                Рейсов на эту дату нет — выберите другую дату
                                            </div>
                                        </div>

                                        <div class="reservation_form_item bk-field">
                                            <label class="bk-label" for="bk-from-stop">Место посадки</label>
                                            <div class="bk-select" v-if="reis.length > 0">
                                                <select id="bk-from-stop"
                                                    class="custom_form_control blue_skin with_note"
                                                    v-model="idStopFrom">
                                                    <optgroup v-for="(item, index) in stopsFrom" :label="index">
                                                        <option v-for="value in item" :value="value.id_stop_name"
                                                            :disabled="value.TimeBus == '(Через диспетчера)'">
                                                            {{value.stop_name}}
                                                            {{value.TimeBus}}
                                                        </option>
                                                    </optgroup>
                                                </select>
                                            </div>
                                            <div v-else class="bk-skel bk-skel--ctl"></div>
                                        </div>

                                        <div class="reservation_form_item bk-field">
                                            <label class="bk-label" for="bk-to-stop">Место прибытия</label>
                                            <div class="bk-select" v-if="reis.length > 0">
                                                <select id="bk-to-stop"
                                                    class="custom_form_control blue_skin with_note"
                                                    v-model="idStopTo">
                                                    <optgroup v-for="(item, index) in stopsTo" :label="index">
                                                        <option v-for="value in item" :value="value.id_stop_name"
                                                            :disabled="value.TimeBus == '(Через диспетчера)'">
                                                            {{value.stop_name}}
                                                        {{value.TimeBus}}</option>
                                                    </optgroup>
                                                </select>
                                            </div>
                                            <div v-else class="bk-skel bk-skel--ctl"></div>
                                        </div>
                                    </div>

                                <!-- ── 3. Пассажир ──────────────────────────── -->
                                    <div class="reservation_form_line bk-row bk-row--2" full-only-elements="">
                                        <div class="reservation_form_item bk-field">
                                            <label class="bk-label" for="bk-name">
                                            <span class="bk-req" aria-hidden="true">*</span>
                                                Как к вам обращаться
                                            </label>
                                            <div class="custom_form_control blue_skin">
                                                <input id="bk-name" v-model.trim="second_name"
                                                    class="custom_form_control_input" type="text"
                                                    autocomplete="given-name" placeholder="Имя"
                                                    :aria-invalid="second_name.length === 0 && touched ? 'true' : null"
                                                    required>
                                            </div>
                                        </div>

                                        <div class="reservation_form_item bk-field">
                                            <label class="bk-label" for="bk-phone">
                                            <span class="bk-req" aria-hidden="true">*</span>
                                                Номер телефона в международном формате:
                                            </label>
                                            <div class="custom_form_control blue_skin">
                                                <!-- maxlength=17, а не 13: маска в booking-2026.js
                                                     форматирует номер как «+375 29 123 45 67»
                                                     — ровно 17 символов со знаком и пробелами.
                                                     При 13 поле переставало принимать ввод
                                                     на 13-м символе, то есть последние цифры
                                                     номера не вводились вообще. -->
                                                <input id="bk-phone" v-model.trim="number_phone"
                                                    class="custom_form_control_input" type="tel" inputmode="tel"
                                                    autocomplete="tel" maxlength="17" placeholder="+375 29 000 00 00"
                                                    :aria-invalid="phoneDigits < 12 && touched ? 'true' : null"
                                                    aria-describedby="bk-phone-hint" required>
                                            </div>
                                            <span id="bk-phone-hint" class="bk-hint">На этот номер придёт код
                                            подтверждения в СМС</span>
                                        </div>
                                    </div>

                                <!-- ── Комментарий ─────────────────────────── -->
                                    <div class="reservation_form_line">
                                        <div class="reservation_form_item full bk-field">
                                            <button type="button" class="dashed_link"
                                                :aria-expanded="isComment ? 'true' : 'false'"
                                                aria-controls="bk-comment" @click="isComment = !isComment">
                                                Добавить комментарий
                                            </button>
                                            <div v-show="isComment" id="bk-comment" class="bk-collapse">
                                                <label class="bk-label" for="bk-comment-input">Комментарий</label>
                                                <div class="custom_form_control textarea blue_skin">
                                                    <textarea id="bk-comment-input" v-model.trim="comment" rows="2"
                                                        class="custom_form_control_input"
                                                    placeholder="Например: два детских кресла, багаж"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                <!-- ── 4. Билеты, цена, кнопка ──────────────── -->
                                    <div save-btn-line="save-btn-line" class="reservation_form_line bk-row bk-row--3 bk-row--pay">
                                        <div class="reservation_form_item bk-field bk-tickets">
                                            <span class="bk-label" id="bk-tickets-label">Билеты</span>
                                            <div class="bk-segment" role="radiogroup"
                                                aria-labelledby="bk-tickets-label">
                                                <input type="radio" id="bk-t1" name="bk-tickets" :value="1"
                                                    v-model.number="place">
                                                <label for="bk-t1">1</label>

                                                <input type="radio" id="bk-t2" name="bk-tickets" :value="2"
                                                    v-model.number="place">
                                                <label for="bk-t2">2</label>

                                                <input type="radio" id="bk-t3" name="bk-tickets" :value="3"
                                                    v-model.number="place">
                                                <label for="bk-t3">3</label>

                                                <input type="radio" id="bk-t4" name="bk-tickets" :value="4"
                                                    v-model.number="place">
                                                <label for="bk-t4">4</label>

                                                <input type="radio" id="bk-t5" name="bk-tickets" :value="5"
                                                    v-model.number="place">
                                                <label for="bk-t5">5</label>
                                            </div>
                                        </div>

                                        <!-- Label moved out of the tinted box so it sits on the
                                             same baseline as the "Билеты" caption and the value
                                             box lines up with the segmented control. The old
                                             .reservation_form_equal_symbol wrapper was a 40x70
                                             theme float and is no longer needed. -->
                                        <div class="reservation_form_item bk-field bk-field--price">
                                            <span class="bk-label" id="bk-price-label">К оплате</span>
                                            <div class="bk-price" role="group" aria-labelledby="bk-price-label">
                                                <output class="reservation_form_price bk-price-value"
                                                    form-price="form-price" aria-live="polite"
                                                    :data-empty="(!priceReis) ? 'true' : null">
                                                <span>{{price}}</span>
                                                </output>
                                            </div>
                                        </div>

                                        <div v-if="!enableCode" class="reservation_form_item bk-field">
                                            <div class="reservation_form_btn form_btn yellow upper"
                                                role="button" tabindex="0" @click="requestCode"
                                                @keydown.enter="requestCode"
                                                :class="second_name.length != 0 && phoneDigits >= 12 ? '': 'disabled'"
                                                :aria-disabled="!(second_name.length != 0 && phoneDigits >= 12)">
                                                Забронировать
                                            </div>
                                            <button type="button" class="bk-resend"
                                                v-if="second_name.length != 0 && phoneDigits >= 12"
                                                @click="openCodeDialog">
                                                У меня уже есть код
                                            </button>
                                        </div>
                                        <div v-else class="reservation_form_item bk-field">
                                            <div class="reservation_form_btn form_btn yellow upper disabled"
                                                aria-disabled="true">
                                                Забронировать
                                            </div>
                                        </div>
                                    </div>

                                <!-- ── Примечания ───────────────────────────── -->
                                    <ul class="bk-notes">
                                        <li class="bk-note bk-note--pay">
                                            <span class="bk-star" aria-hidden="true">*</span>
                                            <span>Оплата проезда осуществляется при посадке в автобус банковскими
                                            платежными картами или наличными денежными средствами.</span>
                                        </li>
                                        <li class="bk-note bk-note--warn">
                                            <span aria-hidden="true">**</span>
                                            <span><b>АС «Юго-западная»</b> — бронирование только через диспетчера.
                                                Время отправления: 6:40***, 7:10, 7:40, 8:10, 8:40, 9:00, 9:25,
                                                10:00, 10:25, 11:00, 11:30, 12:00, 12:10*, 12:30, 12:50***,
                                                13:00, 13:30, 14:00, 14:30, 15:00, 15:30, 16:00, 16:30, 17:00,
                                                17:20, 17:40, 18:00, 18:30, 19:00, 19:30, 19:50***, 20:00, 20:30,
                                                21:00, 21:30, 22:00, 22:35***, 22:50 («***» — уточняйте у
                                            диспетчера).</span>
                                        </li>
                                        <li class="bk-note">
                                            <span class="bk-star" aria-hidden="true">*</span>
                                            <span>Поля, отмеченные звёздочкой, обязательны для заполнения.</span>
                                        </li>
                                        <li class="bk-note">
                                            <span aria-hidden="true">*</span>
                                            <span>Расчётное время в пути — 2 часа, но может быть изменено в
                                                зависимости от погодных условий и других
                                            форс-мажорных обстоятельств.</span>
                                        </li>
                                    </ul>
                                </div>

                            <!-- ── Подтверждение по СМС ─────────────────────── -->
                                <div class="my_orders_form" v-if="enableCode">
                                    <p class="bk-otp">
                                        Введите код из СМС, чтобы завершить бронирование. Повторный код можно
                                        получить через <b>{{currentTime}}&nbsp;с</b>.
                                    </p>
                                    <div class="code_form">
                                        <div class="code_form_line">
                                            <div class="code_form_input">
                                                <label class="bk-label" for="bk-code">Код из СМС</label>
                                                <div class="custom_form_control small blue_skin">
                                                    <!-- Код из СМС — 4 цифры (так его
                                                         присылает шлюз). Раньше стояло 5,
                                                         и поле предлагало «00000», то есть
                                                         подсказка обещала лишний символ. -->
                                                    <input id="bk-code" v-model="code"
                                                        class="custom_form_control_input" type="text"
                                                        inputmode="numeric" maxlength="4"
                                                        autocomplete="one-time-code" placeholder="0000"
                                                        @keyup.enter="bron">
                                                </div>
                                            </div>
                                            <div class="code_form_btn">
                                                <div class="form_btn yellow small" role="button" tabindex="0"
                                                    @click="bron" @keydown.enter="bron" code-btn="">
                                                    Принять код
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <!-- ── Кабинет пользователя ─────────────────────── -->
                                <div class="my_orders_form bk-user">
                                    <h3 class="bk-panel-title">Кабинет пользователя</h3>
                                    <p class="bk-hint">Введите номер телефона, затем код из СМС, чтобы посмотреть
                                    или отменить бронь.</p>

                                    <div class="code_form bk-user__form" code-form="">
                                        <div class="code_form_line">

                                            <template v-if="arrTravel.length == 0">
                                                <div class="code_form_input" field="code">
                                                    <label class="bk-label" for="bk-travel-phone">Номер телефона в международном формате:</label>
                                                    <div class="custom_form_control small blue_skin">
                                                        <input id="bk-travel-phone" v-model="numberTravel"
                                                            class="custom_form_control_input" type="tel"
                                                            inputmode="tel" autocomplete="tel" maxlength="17"
                                                            placeholder="+375 29 000 00 00">
                                                    </div>
                                                    <div class="code_form_btn bk-user__actions" v-if="!enableCodeTravel">
                                                        <div class="form_btn yellow small" role="button"
                                                            tabindex="0" @click="requestTravelCode"
                                                            @keydown.enter="requestTravelCode">
                                                            Получить код
                                                        </div>
                                                        <button type="button" class="bk-resend"
                                                            v-if="travelDigits >= 12"
                                                            @click="openTravelCodeDialog">
                                                            У меня уже есть код
                                                        </button>
                                                    </div>
                                                    <div v-else class="code_form_btn bk-user__actions">
                                                        <div class="form_btn yellow small disabled"
                                                        aria-disabled="true">Получить код</div>
                                                        <p class="bk-hint">Введите код из СМС. Повторный код —
                                                        через {{currentTimeTravel}}&nbsp;с.</p>
                                                    </div>
                                                </div>
                                                <div class="code_form_input" v-if="enableCodeTravel" field="code">
                                                    <label class="bk-label" for="bk-travel-code">Код из СМС</label>
                                                    <div class="custom_form_control small blue_skin">
                                                        <input id="bk-travel-code" v-model="codeTravel"
                                                            class="custom_form_control_input" type="text"
                                                            inputmode="numeric" maxlength="4"
                                                            autocomplete="one-time-code" placeholder="0000"
                                                            @keyup.enter="getTravel">
                                                    </div>
                                                    <div class="code_form_btn">
                                                        <div class="form_btn yellow small" role="button"
                                                            tabindex="0" @click="getTravel"
                                                            @keydown.enter="getTravel">
                                                            Принять код
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>

                                            <div class="code_form_input" v-for="item in arrTravel"
                                                :key="item.id">
                                                <div class="bk-trip">
                                                    <span><b>Дата рейса:</b> {{item.reis.date_reis}}
                                                    {{item.reis.time_reis}}</span>
                                                    <span><b>Отправление:</b> {{item.startCity.name}}
                                                    {{item.startStop.stop_name}}</span>
                                                    <span><b>Билетов:</b> {{item.count_ticket}}</span>
                                                    <span v-if="item.busTicket != null"><b>Автобус:</b>
                                                        {{item.busTicket.bus.model_bus}}
                                                    {{item.busTicket.bus.number_bus}}</span>
                                                    <div class="form_btn yellow small" role="button" tabindex="0"
                                                        @click="travelOtmena(item.id)"
                                                        @keydown.enter="travelOtmena(item.id)">
                                                        Отменить бронирование
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                <!-- Mobile sticky action bar -->
                    <div class="bk-sticky" data-visible="false" aria-hidden="true">
                        <div class="bk-sticky-price">
                            <span>К оплате</span>
                            <span data-sticky-price>{{price}}&nbsp;BYN</span>
                        </div>
                        <div class="form_btn yellow" role="button" tabindex="0" @click="scrollToBooking"
                            @keydown.enter="scrollToBooking"
                            :class="canBook ? '' : 'disabled'">
                            Забронировать
                        </div>
                    </div>

                <!-- Toasts -->
                    <div class="bk-toasts" data-toasts role="status" aria-live="polite" aria-atomic="false"></div>

                </div>
            </div>

        </div> <!-- end of container -->
    </section>
    <!-- end of details 1 -->


    <!-- Features -->
    <div id="features" class="cards-1">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">

                    <?php foreach ($main_mas_block as $item) { ?>
                        <!-- Card -->
                        <div class="card">
                            <div class="card-image">
                                <!-- alt="" : иконка декоративная, рядом стоит
                                     <h3> с тем же текстом. С прошлым alt="<?= $item['title'] ?>"
                                     скринридер читал «Багаж, Багаж». -->
                                <img class="img-fluid" src="<?= $item['image'] ?>" alt=""
                                    width="64" height="64" loading="lazy" decoding="async">
                            </div>
                            <div class="card-body">
                                <h3 class="card-title"><?= $item['title'] ?></h3>
                                <p><?= $item['text'] ?></p>
                            </div>
                        </div>
                        <!-- end of card -->
                    <?php } ?>

                </div> <!-- end of col -->
            </div> <!-- end of row -->
        </div> <!-- end of container -->
    </div> <!-- end of cards-1 -->
    <!-- end of features -->

    <?php foreach ($main_news_block as $key => $item) { ?>
        <!-- Details 1 -->
        <div class="basic-2">
            <div class="container">
                <div class="row">
                    <?php if (($key + 1) % 2 === 0) { ?>

                        <div class="col-lg-5">
                            <div class="text-container">
                                <h2><?= $item['title'] ?></h2>
                                <?= $item['subtitle'] ?>
                            </div> <!-- end of text-container -->
                        </div> <!-- end of col -->
                        <div class="col-lg-7">
                            <div class="image-container">
                                <img class="img-fluid" src="<?= $item['image'] ?>" alt="<?= $item['title'] ?>"
                                    loading="lazy" decoding="async">
                            </div> <!-- end of image-container -->
                        </div> <!-- end of col -->

                    <?php } else { ?>

                        <div class="col-lg-7">
                            <div class="image-container">
                                <img class="img-fluid" src="<?= $item['image'] ?>" alt="<?= $item['title'] ?>"
                                    loading="lazy" decoding="async">
                            </div> <!-- end of image-container -->
                        </div> <!-- end of col -->

                        <div class="col-lg-5">
                            <div class="text-container">
                                <h2><?= $item['title'] ?></h2>
                                <?= $item['subtitle'] ?>
                            </div> <!-- end of text-container -->
                        </div> <!-- end of col -->

                    <?php } ?>
                </div> <!-- end of row -->
            </div> <!-- end of container -->
        </div> <!-- end of basic-2 -->
        <!-- end of details 1 -->
    <?php } ?>

    <!-- Pricing -->
    <div id="pricing" class="cards-2">
        <div class="decoration-bg"></div>
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <h2 class="h2-heading"><?= $main_mas_text['contact']['title'] ?></h2>
                    <p class="p-heading"><?= $main_mas_text['contact']['subtitle'] ?></p>
                </div> <!-- end of col -->
            </div> <!-- end of row -->
            <div class="row">
                <div class="col-lg-12">
                    <!-- Card-->
                    <div class="card">
                        <div class="card-body">
                            <ul class="list-unstyled li-space-lg bk-contacts">
                                <?php foreach ($main_mas_block_contact as $item) { ?>
                                    <li class="media">
                                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                                        <div class="media-body">
                                            <?= $item['text'] ?>
                                        </div>
                                    </li>
                                <?php } ?>
                            </ul>
                        </div>
                    </div> <!-- end of card -->
                    <!-- end of card -->
                </div> <!-- end of col -->
            </div> <!-- end of row -->
        </div> <!-- end of container -->
    </div> <!-- end of cards-2 -->
    <!-- end of pricing -->
    </main> <!-- end of main -->


    <!-- Footer -->
    <div class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">

                    <div class="social-container">
                        <p class="p-large"><?= $main_mas_text['header']['p-large'] ?></p>
                        <?= $store_btns ?>

                    </div>
                    <h3 class="bk-social-title">Мы в сети Instagram и VK.</h3>
                    <div class="social-container">
                        <?php
                        foreach ($mas_icon_in_footer as $item) {
                            if ($item['enable']) {
                                ?>
                                <span class="fa-stack">
                                    <a href="<?= $item['link'] ?>" aria-label="<?= htmlspecialchars($item['title']) ?>"
                                        rel="noopener noreferrer" target="_blank">
                                        <i class="fas fa-circle fa-stack-2x" aria-hidden="true"></i>
                                        <i class="<?= $item['class'] ?>" aria-hidden="true"></i>
                                    </a>
                                </span>
                            <?php }
                        } ?>
                    </div> <!-- end of social-container -->
                </div> <!-- end of col -->
                <div class="col-lg-12">
                    <a href="https://5s.by/">Разработано платформой 5s.by</a>
                </div>
            </div> <!-- end of row -->
        </div> <!-- end of container -->
    </div> <!-- end of footer -->
    <!-- end of footer -->

    <!-- Scripts
         jQuery / Bootstrap JS / Swiper / MagnificPopup / jquery.easing are no
         longer loaded: the page uses no carousel, lightbox, dropdown or modal,
         and the navbar + smooth-scroll behaviour moved to booking-2026.js.
         That removes ~305 KB of render-blocking JavaScript. -->
    <script defer src="js/booking-2026.js?v=<?= $asset_v ?>"></script>

    <!-- Google tag (gtag.js) 
<script async src="https://www.googletagmanager.com/gtag/js?id=G-TGF9HTN9LE"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-TGF9HTN9LE');
</script> -->

    <!-- Google Tag Manager
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-PFZN3B');</script>
End Google Tag Manager -->

    <!--<script data-ad-client="ca-pub-2912774712388564" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script> -->

    <!-- Google Tag Manager (noscript) 
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PFZN3B"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
End Google Tag Manager (noscript) -->


    <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function (m, e, t, r, i, k, a) {
            m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments) };
            m[i].l = 1 * new Date();
            for (var j = 0; j < document.scripts.length; j++) { if (document.scripts[j].src === r) { return; } }
            k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a)
        })
            (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

        ym(44618485, "init", {
            clickmap: true,
            trackLinks: true,
            accurateTrackBounce: true,
            webvisor: true
        });
    </script>
    <noscript>
        <div><img src="https://mc.yandex.ru/watch/44618485" style="position:absolute; left:-9999px;" alt="" /></div>
    </noscript>
    <!-- /Yandex.Metrika counter -->

    <!-- Google Analytics (UA-68858451-4) удалён: Universal Analytics не
         принимает данные с июля 2023, скрипт грузился впустую. Счётчик
         Google Ads AW-950916794 ниже живёт и не тронут. Если нужна веб-аналитика
         сайта — завести GA4 (G-…); формат счётчика там другой. -->

    <!-- Global site tag (gtag.js) - Google Ads: 950916794 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-950916794"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());

        gtag('config', 'AW-950916794');
    </script>

    <!-- Event snippet for бронь conversion page -->
    <script>
        gtag('event', 'conversion', { 'send_to': 'AW-950916794/MhQNCJnh95ABELqtt8UD' });
    </script>





<script>
    /* Инлайновый скрипт в конце <body> выполняется во время разбора, ДО
       отложенных (defer) скриптов из core/head.php. Поэтому и директива, и
       создание экземпляра Vue обязаны жить внутри DOMContentLoaded — к этому
       моменту vue.min.js и axios.js уже гарантированно загружены. Раньше
       директива регистрировалась снаружи, падала с «Vue is not defined» и
       ломала всё онлайн-бронирование. */
    document.addEventListener('DOMContentLoaded', function () {
        /* Директива click-outside для закрытия выпадающих меню/календарей */
        Vue.directive('click-outside', {
            bind: function (el, binding, vnode) {
                el._clickOutside = function (event) {
                    if (!(el === event.target || el.contains(event.target))) {
                        binding.value(event);
                    }
                };
                document.addEventListener('click', el._clickOutside);
            },
            unbind: function (el) {
                document.removeEventListener('click', el._clickOutside);
            }
        });

    /* Локальная дата в формате YYYY-MM-DD (без сдвига часового пояса) */
    var now = new Date();
    var date = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    var app = new Vue({
        el: '#apps',
        data: {
            url: 'https://minsk-bobruisk.by/cp/api/v1/ap/',
            enable: false,
            enableCode: false,
            currentTime: 60,
            timerBool: false,
            today: date,
            touched: false,
            loading: false,
            //Блок для Мои поездки
            enableCodeTravel: false,
            codeTravel: null,
            currentTimeTravel: 60,
            timerBoolTravel: false,
            _interval: null,
            _intervalTravel: null,
            arrTravel: [],
            numberTravel: '',
            //Конец Блока для Мои поездки
            csrf: null,
            pricearr: null,
            city_a: null,
            city_b: null,
            date: '',
            loadingReis: false,
            reis: [],
            cityFrom: null,
            cityTo: null,
            stopsFrom: [],
            stopsTo: [],
            priceReis: null,
            idStopFrom: null,
            idStopTo: null,
            idCityFrom: null,
            idCityTo: null,
            idReis: null,
            ratePrice: 1,
            busSms: null,
            place: 1,
            time_reis: null,
            idList: null,
            isComment: false,
            code: null,
            first_name: '',
            second_name: '',
            number_phone: '',
            comment: '',
            // Календарь
            showDatepicker: false,
            calendarMonth: null,
            calendarYear: null,
            weekdays: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
            todayLabel: 'Сегодня',
            clearLabel: 'Очистить',
        },
        created: function created() {
            var _this = this;

            /* Оба запроса без обработчика отказа: promise отклонялся, ошибка
               уходила в консоль, а форма оставалась с пустым списком городов
               без единого слова пользователю. */
            axios.get(this.url + 'citya').then(function (response) {
                return _this.cityFrom = response.data, _this.city_a = 3;
            })["catch"](function () {
                _this.notify('Не удалось загрузить города. Проверьте соединение.', 'err');
            });
            axios.get(this.url + 'price').then(function (response) {
                _this.pricearr = Array.isArray(response.data) ? response.data : [];
            })["catch"](function () {
                /* Цена не пришла — priceBron() теперь это переживает, но
                   сказать пользователу всё равно полезно. */
                _this.notify('Не удалось загрузить цены. Позвоните диспетчеру.', 'err');
            });
        },
        watch: {
            number_phone: function number_phone(val) {
                if (val.indexOf('80', 0) == 0) {
                    this.number_phone = '375';
                }
            },
            numberTravel: function numberTravel(val) {
                if (val.indexOf('80', 0) == 0) {
                    this.numberTravel = '375';
                }
            },
            city_a: function city_a() {
                var _this2 = this;

                this.refreshReis(), this.city_b = null, axios.get(this.url + 'cityb?id=' + this.city_a).then(function (response) {
                    _this2.cityTo = response.data;
                    // Направление может оказаться пустым — тогда не падаем, а ждём выбора.
                    _this2.city_b = response.data.length ? response.data[0]['value'] : null;
                    _this2.date = date;
                });
            },
            date: function date() {
                var _this3 = this;

                /* Проверка на правдивость, а не `!= null`. clearDate() ставит
                   в date пустую строку, а не null: проверка на null её
                   пропускала, и уходил запрос расписания с датой «» — лишний
                   и бессмысленный (в превью он ещё и падал по CORS). Теперь
                   ложное значение любого вида — '', null — запрос не даёт.

                   Флаг нужен, чтобы отличать «грузятся рейсы» от «рейсов
                   нет»: без него при смене маршрута на секунду мелькало бы
                   «Рейсов на эту дату нет», хотя рейсы просто ещё не
                   приехали. Ветка else обязательна: если город не выбран,
                   запроса не будет, и без неё скелетон горел бы вечно. */
                if (this.city_b != null && this.date) {
                    this.loadingReis = true;
                    axios.get(this.url + 'reis?citya=' + this.city_a + '&cityb=' + this.city_b + '&date=' + this.date).then(function (response) {
                        _this3.loadingReis = false;
                        _this3.reis = Array.isArray(response.data) ? response.data : [];

                        // Рейсов на выбранную дату может не быть (например, на сегодня).
                        if (!_this3.reis.length) {
                            _this3.idReis = null;
                            _this3.notify('На ' + _this3.formatDate(_this3.date) + ' рейсов нет — выберите другую дату.', 'info');
                            return;
                        }

                        // По умолчанию берём первый рейс, где реально есть места:
                        // первый в списке нередко забит, а он и раньше выбирался.
                        var free = _this3.reis.filter(function (r) { return r.FreePlace >= 2; });
                        _this3.idReis = (free.length ? free[0] : _this3.reis[0])['Race'];

                        if (!free.length) {
                            _this3.notify('На ' + _this3.formatDate(_this3.date) + ' свободных мест нет — позвоните диспетчеру.', 'info');
                        }
                    }).catch(function () {
                        _this3.loadingReis = false;
                        _this3.notify('Не удалось загрузить расписание. Проверьте соединение.', 'err');
                    });
                } else {
                    this.loadingReis = false;
                }
            },
            idReis: function (val) {
                const pr = this;

                // Пустое значение: рейс не выбран — остановки не запрашиваем.
                if (!val) { pr.idList = null; return; }

                // Получение idList
                Object.keys(this.reis).forEach(keys => {
                    if (pr.reis[keys].Race === val) {
                        pr.idList = pr.reis[keys].idList;
                        pr.ratePrice = pr.reis[keys].ratePrice;
                    }
                });
                pr.priceBron();
                // Получение остановок с сервера
                axios.get(this.url + 'stops?id=' + val).then(response => {
                    pr.stopsFrom = response.data['start'] || {};
                    pr.stopsTo = response.data['end'] || {};

                    // Выбор первой остановки stopsFrom
                    if (Object.values(pr.stopsFrom).length > 0) {
                        const firstStopGroup = Object.values(pr.stopsFrom)[0];
                        const firstStop = firstStopGroup[0];

                        if (firstStopGroup.length > 1 && firstStopGroup[0].id_stop_name === firstStopGroup[1].id_stop_name) {
                            // Если id_stop_name у первой и второй остановки совпадают, выбираем вторую
                            pr.stopsFrom[Object.keys(pr.stopsFrom)[0]][0].id_stop_name = -1;
                            pr.idStopFrom = firstStopGroup[1].id_stop_name;
                            pr.time_reis = firstStopGroup[1].TimeBus;
                            pr.busSms = firstStopGroup[1].TimeBusSms;
                        } else if (firstStopGroup.length > 0 && firstStopGroup[0].id_stop_name >= 0 && firstStopGroup[0].TimeBus !== '(Через диспетчера)') {
                            // Иначе выбираем первую доступную остановку
                            pr.idStopFrom = firstStopGroup[0].id_stop_name;
                            pr.time_reis = firstStopGroup[0].TimeBus;
                            pr.busSms = firstStopGroup[0].TimeBusSms;
                        } else if (firstStopGroup.length > 1) {
                            // Если первая остановка заблокирована, выбираем вторую
                            pr.stopsFrom[Object.keys(pr.stopsFrom)[0]][0].id_stop_name = -1;
                            pr.idStopFrom = firstStopGroup[1].id_stop_name;
                            pr.time_reis = firstStopGroup[1].TimeBus;
                            pr.busSms = firstStopGroup[1].TimeBusSms;
                        }

                    }

                    // Выбор последней остановки stopsTo
                    if (Object.values(pr.stopsTo).length > 0) {
                        const lastStopGroup = Object.values(pr.stopsTo)[Object.values(pr.stopsTo).length - 1];
                        const lastStop = lastStopGroup[Object.keys(lastStopGroup)[Object.keys(lastStopGroup).length - 1]];

                        if (lastStop && lastStop.id_stop_name >= 0 && lastStopGroup.TimeBus != '(Через диспетчера)') {
                            // Если последняя остановка доступна
                            pr.idStopTo = lastStop.id_stop_name;
                        } else if (lastStopGroup.length > 1) {
                            // Если последняя остановка заблокирована, выбираем предпоследнюю
                            pr.idStopTo = lastStopGroup[lastStopGroup.length - 2].id_stop_name;
                        }
                    }
                });
            },

            idStopFrom: function idStopFrom(newValue) {
                var pr = this;
                Object.keys(this.stopsFrom).forEach(function (keys) {
                    pr.stopsFrom[keys].forEach(function (key) {
                        if (pr.idStopFrom === key.id_stop_name) {
                            pr.idCityFrom = key.cityID;
                            pr.time_reis = key.TimeBus;
                            pr.busSms = key.TimeBusSms;
                            pr.priceBron();
                        }
                    });
                });
            },
            idStopTo: function idStopTo() {
                var pr = this;
                Object.keys(this.stopsTo).forEach(function (keys) {
                    pr.stopsTo[keys].forEach(function (key) {
                        if (pr.idStopTo === key.id_stop_name) {
                            pr.idCityTo = key.cityID;
                            pr.priceBron();
                        }
                    });
                });
            },
            currentTime: function currentTime(time) {
                if (time === 0 && this.timerBool) {
                    this.enableCode = false, this.timerBool = false, this.currentTime = 60;
                }
            },            currentTimeTravel: function currentTimeTravel(time) {
                if (time === 0 && this.timerBoolTravel) {
                    this.enableCodeTravel = false, this.timerBoolTravel = false, this.currentTimeTravel = 60;
                }
            }
        },
        computed: {
            price: function price() {
                if (!this.priceReis || !this.place) return 0;
                return this.priceReis * this.place;
            },
            /* Количество ЦИФР, а не символов. Маска держит поле в виде
               «+375 29 123 45 67» — это 17 символов, но 12 цифр, и 8 из них
               приходится на «+375 » и пробелы. Проверка по длине строки
               пропускала номер с 9 цифрами: «+375 44 581 4» — это 13 символов,
               то есть форма считала его полным и разрешала бронь. */
            phoneDigits: function phoneDigits() {
                return (String(this.number_phone || '').match(/\d/g) || []).length;
            },
            travelDigits: function travelDigits() {
                return (String(this.numberTravel || '').match(/\d/g) || []).length;
            },
            canBook: function canBook() {
                return this.second_name.length !== 0 && this.phoneDigits >= 12;
            },
            dateFormatted: function dateFormatted() {
                if (!this.date) return '';
                var d = new Date(this.date + 'T00:00:00');
                var day = String(d.getDate()).padStart(2, '0');
                var month = String(d.getMonth() + 1).padStart(2, '0');
                var year = d.getFullYear();
                return day + '.' + month + '.' + year;
            },
            calendarTitle: function calendarTitle() {
                if (this.calendarMonth === null || this.calendarYear === null) return '';
                var months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                              'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
                return months[this.calendarMonth] + ' ' + this.calendarYear;
            },
            calendarDays: function calendarDays() {
                if (this.calendarMonth === null || this.calendarYear === null) return [];
                var year = this.calendarYear;
                var month = this.calendarMonth;
                var firstDay = new Date(year, month, 1);
                var lastDay = new Date(year, month + 1, 0);
                var daysInMonth = lastDay.getDate();
                var startingDay = firstDay.getDay(); // 0 = Sun, 1 = Mon, ...
                // Convert to Monday-based (0 = Mon)
                var startOffset = (startingDay + 6) % 7;
                var days = [];
                // Previous month days
                var prevMonthLastDay = new Date(year, month, 0).getDate();
                for (var i = startOffset - 1; i >= 0; i--) {
                    var day = prevMonthLastDay - i;
                    var date = new Date(year, month - 1, day);
                    days.push({
                        day: day,
                        date: this.toISODate(date),
                        classes: 'bk-calendar-day other-month disabled',
                        disabled: true
                    });
                }
                // Current month days
                var today = new Date();
                today.setHours(0, 0, 0, 0);
                var minDate = new Date(this.today);
                minDate.setHours(0, 0, 0, 0);
                for (var day = 1; day <= daysInMonth; day++) {
                    var date = new Date(year, month, day);
                    var dateStr = this.toISODate(date);
                    var isToday = date.getTime() === today.getTime();
                    var isSelected = this.date === dateStr;
                    var isDisabled = date < minDate;
                    var classes = 'bk-calendar-day';
                    if (isToday) classes += ' today';
                    if (isSelected) classes += ' selected';
                    if (isDisabled) classes += ' disabled';
                    days.push({
                        day: day,
                        date: dateStr,
                        classes: classes,
                        disabled: isDisabled
                    });
                }
                // Next month days to fill the grid (42 cells = 6 weeks)
                var totalCells = 42;
                var nextMonthDay = 1;
                while (days.length < totalCells) {
                    var date = new Date(year, month + 1, nextMonthDay);
                    days.push({
                        day: nextMonthDay,
                        date: this.toISODate(date),
                        classes: 'bk-calendar-day other-month disabled',
                        disabled: true
                    });
                    nextMonthDay++;
                }
                return days;
            }
        },
        methods: {
            /* ------------------------------------------------------------------
               UI-обёртки над API
               ------------------------------------------------------------------ */
            notify: function notify(message, kind, retried) {
                var vm = this;
                /* created() выполняется ДО монтирования, когда у компонента ещё
                   нет $el. Ошибка стартового запроса приходит в этот хук, и
                   прежний код упал бы здесь с TypeError — то есть сообщение об
                   ошибке само стало бы ошибкой. Ждём один тик, когда $el уже
                   есть. Ретрай строго один, чтобы не зациклиться. */
                var host = this.$el ? this.$el.querySelector('[data-toasts]') : null;
                if (!host) {
                    if (!retried && this.$nextTick) {
                        this.$nextTick(function () { vm.notify(message, kind, true); });
                    }
                    return;
                }
                var el = document.createElement('div');
                el.className = 'bk-toast';
                el.setAttribute('data-kind', kind || 'info');
                el.textContent = message;
                host.appendChild(el);
                window.setTimeout(function () {
                    el.setAttribute('data-leaving', 'true');
                    window.setTimeout(function () { el.remove(); }, 240);
                }, kind === 'err' ? 7000 : 4200);
            },

            /* Плавная прокрутка к форме (липкая кнопка на мобильных).
               Оффсет берём из реальной высоты шапки: у неё два состояния
               (.top-nav-collapse), и жёсткое число перестаёт совпадать с
               кромкой бара. Совпадает с scrollOffset() в booking-2026.js. */
            scrollToBooking: function scrollToBooking() {
                var target = this.$el.querySelector('[main-form]');
                if (!target) return;
                var bar = document.querySelector('.fixed-top');
                var off = (bar ? bar.getBoundingClientRect().height : 72) + 4;
                var top = target.getBoundingClientRect().top + window.pageYOffset - off;
                window.scrollTo({ top: top, behavior: 'smooth' });
            },

            /* Локальная дата -> 'YYYY-MM-DD'.
               Нельзя использовать toISOString(): он переводит в UTC, и в
               Минске (UTC+3) локальная полночь 29-го превращалась в 28-е —
               пользователь выбирал одну дату, а уезжал на рейс предыдущего дня. */
            toISODate: function toISODate(d) {
                return d.getFullYear() + '-' +
                       String(d.getMonth() + 1).padStart(2, '0') + '-' +
                       String(d.getDate()).padStart(2, '0');
            },

            /* '2026-09-28' -> 'сентября' для aria-label ячеек календаря */
            monthNameLong: function monthNameLong(iso) {
                var m = parseInt(String(iso).split('-')[1], 10) - 1;
                var months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
                              'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
                return isNaN(m) || !months[m] ? '' : months[m];
            },

            /* 2026-09-28 -> «28 сентября» для сообщений */
            formatDate: function formatDate(iso) {
                if (!iso) return '';
                var parts = String(iso).split('-');
                if (parts.length !== 3) return iso;
                var months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
                    'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
                var m = parseInt(parts[1], 10) - 1;
                if (isNaN(m) || !months[m]) return iso;
                return parseInt(parts[2], 10) + ' ' + months[m];
            },

            /* Показать поле ввода кода и сфокусировать его */
            focusCode: function focusCode(selector) {
                var self = this;
                this.$nextTick(function () {
                    var el = self.$el.querySelector(selector);
                    if (el) { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); el.focus(); }
                });
            },
            openCodeDialog: function openCodeDialog() {
                this.enableCode = true;
                this.focusCode('#bk-code');
            },
            openTravelCodeDialog: function openTravelCodeDialog() {
                this.enableCodeTravel = true;
                this.focusCode('#bk-travel-code');
            },

            /* Кнопка «Забронировать» */
            requestCode: function requestCode() {
                if (!this.canBook || this.loading) {
                    this.touched = true;
                    this.notify('Укажите имя и номер телефона', 'err');
                    return;
                }
                this.putSms(1);
            },
            requestTravelCode: function requestTravelCode() {
                if (this.travelDigits < 12 || this.loading) {
                    this.notify('Введите номер телефона полностью', 'err');
                    return;
                }
                this.putSms(0);
            },

            /* Настоящий обмен направлений */
            swapRoute: function swapRoute() {
                var a = this.city_a;
                this.city_a = this.city_b;
                this.city_b = a;
            },

            reverse: function reverse() {
                this.city_a = this.city_b;
            },
            putSms: function putSms(bron) {
                var _this5 = this;

                this.loading = true;
                if (bron === 1) {
                    axios.get(this.url + 'smsin?numberbron=' + this.number_phone + '&firstname=' + this.first_name + '&lastname=' + this.second_name + '&idreis=' + this.idReis).then(function (response) {
                        _this5.loading = false;
                        if (response.data['responce'] === 0) {
                            _this5.notify('Ошибка! ' + response.data['message'], 'err');
                            return;
                        }
                        if (response.data['responce'] === 1) {
                            _this5.startTimer();
                            _this5.focusCode('#bk-code');
                        }
                    })["catch"](function () {
                        _this5.loading = false;
                        _this5.notify('Не удалось отправить код. Проверьте интернет.', 'err');
                    });
                } else {
                    axios.get(this.url + 'smsin?numberbron=' + this.numberTravel).then(function (response) {
                        _this5.loading = false;
                        if (response.data['responce'] === 0) {
                            _this5.notify('Ошибка! ' + response.data['message'], 'err');
                            return;
                        }
                        _this5.startTimerTravel();
                        _this5.focusCode('#bk-travel-code');
                    })["catch"](function () {
                        _this5.loading = false;
                        _this5.notify('Не удалось отправить код. Проверьте интернет.', 'err');
                    });
                }
            },
            bron: function bron() {
                var _this6 = this;

                var infopas = {};
                infopas.idRace = this.idReis;
                infopas.start_stop = this.idStopFrom;
                infopas.end_stop = this.idStopTo;
                infopas.start_city = this.idCityFrom;
                infopas.end_city = this.idCityTo;
                infopas.price = this.priceReis * this.place;
                infopas.count = this.place;
                infopas.timesms = this.busSms;
                infopas.firstname = this.first_name;
                infopas.lastname = this.second_name;
                infopas.number = this.number_phone;
                infopas.comment = this.comment;
                infopas.code = this.code;
                var formData = new FormData();
                formData.append('data', JSON.stringify(infopas));
                axios.defaults.headers.common['X-CSRF-TOKEN'] = this.csrf, axios.post(this.url + 'bron', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }).then(function (response) {
                    _this6.loading = false;
                    if (response.data['responce'] === 0) {
                        _this6.code = '';
                        _this6.enableCode = false, _this6.timerBool = false, _this6.currentTime = 60;
                        _this6.notify('Ошибка! ' + response.data['message'], 'err');
                    }

                    if (response.data['responce'] === 1) {
                        _this6.code = '';
                        _this6.enableCode = false, _this6.timerBool = false, _this6.currentTime = 60;
                        _this6.notify('Билет забронирован!', 'ok');
                    }
                })["catch"](function (error) {
                    _this6.loading = false;
                    _this6.notify('Не удалось связаться с сервером. Попробуйте ещё раз.', 'err');
                });
            },
            getTravel: function getTravel() {
                var _this7 = this;

                axios.get(this.url + 'get-travel?number=' + this.numberTravel + '&code=' + this.codeTravel).then(function (response) {
                    if (response.data['responce'] === 0) _this7.notify('Ошибка! ' + response.data['message'], 'err');
                    if (response.data['responce'] === 1) _this7.arrTravel = response.data['message'];
                })["catch"](function () {
                    _this7.notify('Не удалось получить список поездок', 'err');
                });
            },
            travelOtmena: function travelOtmena(id) {
                var _this8 = this;

                axios.get(this.url + 'otm-travel?id=' + id).then(function (response) {
                    if (response.data) {
                        if (response.data['responce'] === 0) _this8.notify('Ошибка! ' + response.data['message'], 'err');
                        if (response.data['responce'] === 1) _this8.notify(response.data['message'], 'ok');
                        axios.get(_this8.url + 'get-travel?number=' + _this8.numberTravel + '&code=' + _this8.codeTravel).then(function (res) {
                            return _this8.arrTravel = res.data['message'];
                        });
                    } else _this8.notify('Ошибка: отмена после отправления рейса.', 'err');
                })["catch"](function () {
                    _this8.notify('Не удалось отменить бронирование', 'err');
                });
            },
            priceBron: function priceBron() {
                var priceOsn = 0;
                var priceDop = 0;

                /* Прайс приезжает отдельным запросом в created() и может не
                   прийти вовсе: сеть, CORS, ответ не-массив. Раньше здесь стояло
                   голое this.pricearr.length при pricearr === null — любая
                   неудача превращалась в TypeError посреди выбора остановки и
                   ломала всю форму. Молча уходим: «К оплате» покажет пустое
                   значение, пользователь позвонит диспетчеру. */
                if (!Array.isArray(this.pricearr)) return;

                for (var i = 0; i < this.pricearr.length; i++) {
                    if (this.idList === this.pricearr[i]['idList'] && this.idCityFrom === this.pricearr[i]['idCityFrom'] && this.idCityTo === this.pricearr[i]['idCityTo']) {
                        priceOsn = this.pricearr[i]['price'];
                        priceOsn = priceOsn * Number(this.ratePrice);
                    }
                }

                for (var _i = 0; _i < this.pricearr.length; _i++) {
                    if (this.idList === this.pricearr[_i]['idList'] && this.idStopFrom === this.pricearr[_i]['idStopFrom'] && this.idStopTo === this.pricearr[_i]['idStopTo']) {
                        priceDop = this.pricearr[_i]['price'];
                        priceDop = priceDop * Number(this.ratePrice);
                    }
                }

                /* Совпадений нет — цена неизвестна. Раньше здесь стояло
                   `return 8`, остаток отладки: ничего не присваивало, просто
                   выходило. Сбрасываем цену явно. */
                if (priceDop === 0 && priceOsn === 0) {
                    this.priceReis = null;
                    return;
                }

                if (priceDop !== 0) {
                    this.priceReis = priceDop;
                }

                if (priceOsn !== 0) {
                    this.priceReis = priceOsn;
                }
            },
            refreshReis: function refreshReis() {
                /* Раньше здесь стояло `this.idStopTo = null` дважды подряд —
                   копипаста. На поведение не влияло, но читалось как ошибка:
                   в списке сброса не хватало половины.

                   `reis` тоже обнуляется. Он не сбрасывался, и при смене
                   маршрута старое расписание секунду-две висело на экране
                   вместе со своей ценой — пока не приходил новый ответ.
                   Пока идёт загрузка, блоки рейсов и остановок показывают
                   скелетон (.bk-skel), см. флаг loadingReis. */
                this.city_b = null, this.date = null, this.reis = [], this.idReis = null, this.idStopFrom = null, this.idStopTo = null, this.idCityFrom = null, this.idCityTo = null, this.priceReis = null, this.ratePrice = 1;
                this.loadingReis = true;
            },
            /* Календарь */
            openDatepicker: function openDatepicker() {
                if (this.showDatepicker) return;
                var today = new Date();
                var minDate = new Date(this.today);
                minDate.setHours(0, 0, 0, 0);
                if (this.date) {
                    var selected = new Date(this.date + 'T00:00:00');
                    this.calendarMonth = selected.getMonth();
                    this.calendarYear = selected.getFullYear();
                } else if (today >= new Date(this.today)) {
                    this.calendarMonth = today.getMonth();
                    this.calendarYear = today.getFullYear();
                } else {
                    this.calendarMonth = new Date(this.today).getMonth();
                    this.calendarYear = new Date(this.today).getFullYear();
                }
                this.showDatepicker = true;
            },
            closeDatepicker: function closeDatepicker() {
                this.showDatepicker = false;
            },
            prevMonth: function prevMonth() {
                if (this.calendarMonth === 0) {
                    this.calendarMonth = 11;
                    this.calendarYear--;
                } else {
                    this.calendarMonth--;
                }
            },
            nextMonth: function nextMonth() {
                if (this.calendarMonth === 11) {
                    this.calendarMonth = 0;
                    this.calendarYear++;
                } else {
                    this.calendarMonth++;
                }
            },
            selectDate: function selectDate(dateStr) {
                var date = new Date(dateStr + 'T00:00:00');
                var minDate = new Date(this.today);
                minDate.setHours(0, 0, 0, 0);
                if (date < minDate) return;
                this.date = dateStr;
                this.showDatepicker = false;
            },
            selectToday: function selectToday() {
                var today = new Date();
                var minDate = new Date(this.today);
                minDate.setHours(0, 0, 0, 0);
                if (today >= minDate) {
                    var day = String(today.getDate()).padStart(2, '0');
                    var month = String(today.getMonth() + 1).padStart(2, '0');
                    var year = today.getFullYear();
                    this.date = year + '-' + month + '-' + day;
                    this.showDatepicker = false;
                }
            },
            clearDate: function clearDate() {
                /* Сбрасываем не только дату, но и зависящее от неё состояние.
                   Раньше после очистки оставался выбранным рейс прежней даты,
                   а рядом стояла его цена: форма показывала время отправления
                   и сумму при пустом поле «Дата». Рейсы не трогаем в
                   refreshReis() — там сбрасывается ещё и направление, чего
                   при очистке даты делать не нужно. */
                this.date = '';
                this.reis = [];
                this.idReis = null;
                this.idStopFrom = null;
                this.idStopTo = null;
                this.idCityFrom = null;
                this.idCityTo = null;
                this.priceReis = null;
                this.showDatepicker = false;
            },
            stopTimer: function stopTimer(key) {
                var self = this;
                this['timerBool' + key] = false;
                if (self['_interval' + key]) {
                    clearInterval(self['_interval' + key]);
                    self['_interval' + key] = null;
                }
            },
            startTimer: function startTimer() {
                var self = this;
                this.stopTimer('');
                this.currentTime = 60, this.timerBool = true, this.enableCode = true;
                this._interval = setInterval(function () {
                    if (self.timerBool) self.currentTime--; else self.stopTimer('');
                }, 1000);
            },
            startTimerTravel: function startTimerTravel() {
                var self = this;
                this.stopTimer('Travel');
                this.currentTimeTravel = 60, this.timerBoolTravel = true, this.enableCodeTravel = true;
                this._intervalTravel = setInterval(function () {
                    if (self.timerBoolTravel) self.currentTimeTravel--; else self.stopTimer('Travel');
                }, 1000);
            }
        },
        beforeDestroy: function beforeDestroy() {
            this.stopTimer('');
            this.stopTimer('Travel');
        }
    });
        });
</script>

</body>

</html>
