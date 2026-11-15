<?php
    //Переменные для настроек
    $color_back_reservation = 'rgb(33, 61, 73)';

    // Кнопки магазинов. Раньше эта пара копировалась трижды — в шапке, в блоке
    // «в твоём смартфоне!» и в подвале, — и правка иконок или высоты должна была
    // быть сделана в трёх местах. Одно определение: мест, где их выводить, три,
    // а кнопки — один и тот же продукт, и разъезжаться они не должны.
    //
    // Значки нарисованы руками, а не взяты у Apple и Google: их файлы нельзя
    // переносить, а свои — можно. Яблоко закрашено currentColor, поэтому кнопка
    // сама красит его в свой цвет текста: белым на тёмной кнопке и тёмным на
    // светлой (.footer), три цвета Play жёстко зашиты в саму фигуру.
    $store_btns = '
        <div class="bk-store-btns">
            <a class="bk-store-btn" href="https://apps.apple.com/ru/app/минсклайн/id6477600344"
                rel="noopener noreferrer" target="_blank">
                <svg class="bk-store-btn-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <!-- Яблоко — маска из простых фигур: тело (круг + эллипс)
                         белое, выкус и нижняя выемка вырезаны чёрным. Для
                         выреза с края силуэта маска обязательна: заливка по
                         nonzero не отличает «вне тела» от «в вырезе» и дорисо-
                         вывала бы кусок круга за силуэтом. Определение маски
                         одно на документ (index.php, начало body) — дублей id
                         в трёх копиях кнопок нет, цвет задаётся currentColor. -->
                    <path fill="currentColor" d="M11.9 8.2c0-2.6 1.8-4.6 4.4-4.8.2 2.6-1.6 4.6-4.4 4.8z"/>
                    <rect width="24" height="24" fill="currentColor" mask="url(#bk-apple-mask)"/>
                </svg>
                <span>App Store</span>
            </a>
            <a class="bk-store-btn"
                href="https://play.google.com/store/apps/details?id=com.minskline.MinskLine"
                rel="noopener noreferrer" target="_blank">
                <svg class="bk-store-btn-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M2 2v10h9.5z" fill="#00a0ff"/>
                    <path d="M2 12v10l9.5-10z" fill="#00d775"/>
                    <path d="M2 2l20 10L11.5 12z" fill="#ffce00"/>
                    <path d="M2 22l20-10H11.5z" fill="#ff3a44"/>
                </svg>
                <span>Google Play</span>
            </a>
        </div>
    ';

    $main_news_block = [
        // [
        //     'image' => 'images/details-1.jpg',
        //     'title' => 'Новость 1',
        //     'subtitle' => '<p>Описание для новости 1</p>'
        // ],
        // [
        //     'image' => 'images/details-2.jpg',
        //     'title' => 'Новость 2',
        //     'subtitle' => '<p>Описание для новости 2</p>'
        // ],
        // [
        //     'image' => 'images/details-3.jpg',
        //     'title' => 'Новость 3',
        //     'subtitle' => '<p>Описание для новости 3</p>'
        // ]
    ];
    //Общий массив для всех текстов на сайте
    $main_mas_text = [
        //Вывод меню на сайте
        'menu' => [
            'menu_1' => [
                'name' => 'Главная',
                'url' => '/'
            ],
            'menu_2' => [
                'name' => 'Бронирование',
                'url' => '#details'
            ],
            'menu_3' => [
                'name' => 'Контакты',
                'url' => '#pricing'
            ],
            // Ключ обязан быть уникальным: 'menu_3' встречался здесь дважды, и
            // PHP перезаписывал значение, сохраняя позицию — «Контакты»
            // исчезали из меню, оставался только «О нас». Раздел «Контакты»
            // при этом оставался на странице, но ссылка на него пропадала.
            'menu_4' => [
                'name' => 'О нас',
                'url' => '#features'
            ],
            // 'menu_5' => [
            //     'name' => 'Мобильная версия',
            //     'url' => 'http://m.minskline.by/'
            // ]
            ],
        // Иконки соцсетей в шапке. Ссылка-иконка обязана иметь доступное
        // имя, иначе скринридер объявляет просто «ссылка».
        'app' => '
            <a href="https://www.instagram.com/minsk_line3232/" aria-label="МинскЛайн в Instagram"
                rel="noopener noreferrer" target="_blank"><i class="fab fa-instagram" aria-hidden="true"></i></a>
            <a href="https://vk.com/minskline" aria-label="МинскЛайн во ВКонтакте"
                rel="noopener noreferrer" target="_blank"><i class="fab fa-vk" aria-hidden="true"></i></a>
        ',
        'header' => [
            'h1-large_text' => '
            <h1 class="bk-hero-title">
                <span class="bk-hero-brand">МинскЛайн</span>
                <span class="bk-hero-caption">
                    <span class="bk-hero-sub">Регулярные рейсы между городами</span>
                    <span class="bk-hero-route">
                        <span class="bk-hero-route-name">Бобруйск</span>
                        <span class="bk-hero-route-sep" aria-hidden="true">&#8212;</span>
                        <span class="bk-hero-route-name">Минск</span>
                    </span>
                </span>
            </h1>
            ',
            'p-large' => 'Ваше путешествие начинается с нас!'
        ],
       
          'introduction' => [
            'enable' => true, //Вывод блока с текстом (true - да false - нет)
            'text' => '
                <div class="bk-app-promo">
                    <div class="bk-app-promo-title">МинскЛайн в твоём смартфоне!</div>
                    <div class="bk-app-promo-text">
                        Доступно для скачивания на платформах: iOS и Android.
                    </div>
                </div>
            '
        ],
  
        
        'contact' => [
            'title' => 'Контакты',
            'subtitle' => 'Наши юридические координаты'
        ]
    ];

    //Массив блоков (Под бронированием)
    // Иконка каждого пункта соответствует его тексту: -1 (конверт) не подходил
    // к «Багажу», а -3 (столбики) стоял сразу у трёх пунктов подряд.
    $main_mas_block = [
        [
            'image' => 'images/features-icon-4.svg',
            'title' => 'Багаж',
            'text' => 'Бесплатно до двух единиц багажа.'
        ],
        [
            'image' => 'images/features-icon-2.svg',
            'title' => 'Стоимость проезда',
            'text' => 'Стоимость проезда составляет 20 BYN.'
        ],
        [
            'image' => 'images/features-icon-6.svg',
            'title' => 'Регулярность',
            'text' => 'Интервал отправления по маршруту 30 минут.'
        ]
        ,
        [
            'image' => 'images/features-icon-7.svg',
            'title' => 'Направление',
            'text' => 'Бобруйск - Минск - Бобруйск'
        ]
        ,
        [
            'image' => 'images/features-icon-8.svg',
            'title' => 'Оплата',
            'text' => 'Наличными или карточкой при посадке.'
        ]
        
        
        
        
        
    ];

    //Массив списка контактов
    // Каждый номер телефона оформлен ссылкой tel: — на мобильных она открывает
    // звонилку одним тапом, на десктопе ничего не ломает.
    $main_mas_block_contact = [
        ['text' => '213800, Могилевская обл, г. Бобруйск, ул. Пушкина д.159 офис 1А'],
        ['text' => 'Бухгалтерия, юридический отдел <a class="bk-tel" href="tel:+375296610844">+375 (29) 661-08-44</a>'],
        ['text' => '
            <div class="bk-dispatch-title">Диспетчерская служба</div>
            <ul class="bk-dispatch">
                <li><img class="bk-op-logo" src="images/op-a1.svg" width="64" height="40" alt="A1"><a class="bk-tel" href="tel:+375293323232">8 029 332 32 32</a></li>
                <li><img class="bk-op-logo" src="images/op-mts.svg" width="64" height="40" alt="МТС"><a class="bk-tel" href="tel:+375333323232">8 033 332 32 32</a></li>
                <li><img class="bk-op-logo" src="images/op-life.svg" width="64" height="40" alt="life:)"><a class="bk-tel" href="tel:+375259323232">8 025 932 32 32</a></li>
            </ul>
        '],
        ['text' => 'E-mail: <a class="bk-mail" href="mailto:manager@minskline.by">manager@minskline.by</a>']
    ];

    //Иконки соц сетей внизу
    // 'title' — доступное имя для ссылки-иконки (обязательно, иначе скринридер
    //              читает только «ссылка» без содержимого)
    $mas_icon_in_footer = [
        [
            'class' => 'fab fa-facebook-f fa-stack-1x',
            'enable' => false,
            'link' => '',
            'title' => 'Facebook'
        ],
        [
            'class' => 'fab fa-twitter fa-stack-1x',
            'enable' => false,
            'link' => '',
            'title' => 'Twitter'
        ],
        [
            'class' => 'fab fa-pinterest-p fa-stack-1x',
            'enable' => false,
            'link' => '',
            'title' => 'Pinterest'
        ],
        [
            'class' => 'fab fa-instagram fa-stack-1x',
            'enable' => true,
            'link' => 'https://www.instagram.com/minsk_line3232/',
            'title' => 'МинскЛайн в Instagram'
        ],
        [
            'class' => 'fab fa-vk fa-stack-1x',
            'enable' => true,
            'link' => 'https://vk.com/minskline',
            'title' => 'МинскЛайн во ВКонтакте'
        ]
    ]
?>
