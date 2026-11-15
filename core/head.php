<!DOCTYPE html>
<?php
    /* Метка версии ассетов. Подставляется к URL каждого CSS/JS ниже.
       Зачем: деплой делается перетаскиванием папки, и браузер продолжает
       отдавать старые booking-2026.css / booking-2026.js из своего кэша —
       правка лежит на сервере, а на странице её нет. Именно так «потерялась»
       починка маски телефона: файл новый, а браузер показывал старый.
       Меняйте значение при каждой выкладке. */
    $asset_v = '20261002-1';
?>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1d4ed8" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#101828" media="(prefers-color-scheme: dark)">

    <!-- SEO Meta Tags -->
    <meta name="description" content="Заказать маршрутку ☆ Бобруйск - Минск ☆ Ежедневное расписание. Низкая стоимость поездки по маршруту ✪ Бобруйск ➤ Минск ➤ Бобруйск ✪.">

    <!-- Open Graph / social -->
    <meta property="og:site_name" content="МинскЛайн, маршрутка Минск-Бобруйск-Минск" />
    <meta property="og:site" content="МинскЛайн, маршрутка Минск-Бобруйск-Минск" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://minskline.by/" />
    <meta property="og:title" content="МинскЛайн, маршрутка Минск-Бобруйск-Минск" />
    <meta property="og:description" content="Заказать маршрутку ☆ Бобруйск - Минск ☆ Ежедневное расписание. Низкая стоимость поездки по маршруту ✪ Бобруйск ➤ Минск ➤ Бобруйск ✪." />
    <meta property="og:image" content="https://minskline.by/images/header-smartphone.png" />
    <meta name="twitter:card" content="summary_large_image" />

    <!-- Webpage Title -->
    <title>МинскЛайн, маршрутка Минск-Бобруйск-Минск</title>

    <!-- Critical shell CSS: keeps first paint from flashing unstyled -->
    <style>
        /* h1.hidden / h2.none used to hide SEO headings here. Nothing in the
           markup carries either class any more (the only headings are
           h1.bk-hero-title, h2.bk-title and h2.h2-heading), so the rules were
           dead weight. */
        html { -webkit-text-size-adjust: 100%; }
        /* No padding-top: the navbar is .fixed-top, and the clearance under it
           comes from .header's own padding-block-start, not from the body. A
           body padding here would double it and push the hero down. */
        body { margin: 0; padding-top: 0; }

        /* Prevent long Russian words from forcing horizontal scroll on phones.
           `clip`, not `hidden`, and that is load-bearing: `overflow-x: hidden`
           makes the element a scroll container and forces the other axis to
           `auto`. On body that detached the document from the viewport --
           documentElement.scrollHeight collapsed to one viewport while body kept
           its full height -- which silently breaks window.scrollTo, the
           scroll-spy in js/booking-2026.js and position: sticky. `clip` refuses
           the overflow without establishing a scroll container. */
        html { overflow-x: clip; }
        body { overflow-x: clip; }
        img, svg, video { max-width: 100%; height: auto; }
    </style>

    <!-- Fonts: preconnect early, load non-blocking -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style"
        href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap"
        media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap">
    </noscript>

    <!--
        Stylesheet order matters.

        bootstrap.css / styles.css are render-blocking on purpose: they paint
        the navbar and hero, and a flash of unstyled content there is worse
        than the ~40 ms they cost.

        Font Awesome is gone - css/icons-2026.css draws the .fa-* glyphs as
        inline SVG masks. The swiper / magnific-popup opt-in block went with it:
        the site has no carousel and no lightbox, and the stylesheets are being
        removed.
    -->
    <link href="css/bootstrap.css?v=<?= $asset_v ?>" rel="stylesheet">
    <link href="css/styles.css?v=<?= $asset_v ?>" rel="stylesheet">
    <!-- app.css holds the legacy reservation-form rules; booking-2026.css must
         come after it so it can override them. -->
    <link href="css/app.css?v=<?= $asset_v ?>" rel="stylesheet">
    <link href="css/icons-2026.css?v=<?= $asset_v ?>" rel="stylesheet">
    <link href="css/booking-2026.css?v=<?= $asset_v ?>" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" href="images/favicon.png" sizes="any">
    <link rel="apple-touch-icon" href="images/favicon.png">

    <!--
        Vue + axios are needed by the booking widget, which renders above the
        fold. They are `defer`red: the browser downloads them in parallel with
        the rest of the document without blocking the parser, and they still
        finish executing before DOMContentLoaded — so the inline Vue bootstrap
        in index.php is wrapped in a DOMContentLoaded listener and sees them.

        polyfill.js (206 KB) used to be loaded synchronously here. It only
        matters for pre-ES2015 browsers; the template, the booking widget and
        the analytics all run unassisted on anything from the last decade.
    -->
    <script defer src="vue.min.js?v=<?= $asset_v ?>"></script>
    <script defer src="axios.js?v=<?= $asset_v ?>"></script>
</head>
