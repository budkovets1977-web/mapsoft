# -*- coding: utf-8 -*-
"""Build 'Harm of vapes & tobacco (Belarus)' presentation WITHOUT importing pptx.

Strategy: unpack python-pptx's bundled default.pptx (valid package skeleton),
replace/create 9 slide parts by hand-written DrawingML, re-register everything,
and rezip. Pure stdlib -> avoids the Python 3.15b1 native crash in pptx.
"""
import os
import re
import sys
import shutil
import zipfile
import xml.dom.minidom
from xml.sax.saxutils import escape

WORK = os.path.dirname(os.path.abspath(__file__))
TPL = r"C:\Users\Admin\AppData\Local\Programs\Python\Python315\Lib\site-packages\pptx\templates\default.pptx"
OUT_NAME = "vaping_harm_presentation_belarus.pptx"
OUT = os.path.join(WORK, OUT_NAME)
TMP = os.path.join(WORK, "_pptx_build")

EMU = 914400  # per inch

# ---------------- palette ----------------
NAVY = "1E2761"
RED = "D93A4A"      # slightly darker for text readability
RED_SHAPE = "F96167"
BLUE = "2F3C7E"
GREEN = "2E7D32"
DGRAY = "3A3A3A"
GRAY = "6E6E6E"
GRAY = "6E6E6E"

SLIDE_W_IN = 13.333
SLIDE_H_IN = 7.5

SLIDE_XML_HEAD = (
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
    '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
    'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
    'xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
    '<p:cSld><p:spTree>'
    '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
    '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/>'
    '<a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
)


def emu(inches):
    return int(round(inches * EMU))


def xml_text_font(size, bold, color, font="Calibri", italic=False):
    it = ' i="1"' if italic else ""
    b = ' b="1"' if bold else ""
    return (f'<a:rPr lang="ru-RU" sz="{size}"{b}{it} dirty="0">'
            f'<a:solidFill><a:srgbClr val="{color}"/></a:solidFill>'
            f'<a:latin typeface="{font}"/><a:cs typeface="{font}"/></a:rPr>')


_sp_id = [10]


def _next_id():
    _sp_id[0] += 1
    return _sp_id[0]


def add_textbox(left, top, w, h, lines):
    """lines: list of (text, size, bold, color, italic, font)"""
    els = []
    for text, size, bold, color, italic, font in lines:
        el = f'<a:r>{xml_text_font(size, bold, color, font, italic)}<a:t>{escape(text)}</a:t></a:r>'
        els.append(el)
    paragraphs = "".join(f"<a:p>{e}</a:p>" for e in els)
    tpl = (
        '<p:sp><p:nvSpPr><p:cNvPr id="@ID@" name="Text @ID@"/>'
        '<p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>'
        '<p:spPr><a:xfrm><a:off x="@X@" y="@Y@"/><a:ext cx="@W@" cy="@H@"/></a:xfrm>'
        '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/></p:spPr>'
        '<p:txBody><a:bodyPr wrap="square" rtlCol="0" anchor="t"/>'
        '<a:lstStyle/>' + paragraphs + '<a:p><a:endParaRPr lang="ru-RU"/></a:p>'
        '</p:txBody></p:sp>'
    )
    sp_id = _next_id()
    return (tpl.replace("@ID@", str(sp_id))
               .replace("@X@", str(emu(left)))
               .replace("@Y@", str(emu(top)))
               .replace("@W@", str(emu(w)))
               .replace("@H@", str(emu(h))))


def add_shape(prst, left, top, w, h, fill=None, line=None, line_w=9525):
    fill_el = f'<a:solidFill><a:srgbClr val="{fill}"/></a:solidFill>' if fill else '<a:noFill/>'
    line_el = (f'<a:ln w="{line_w}"><a:solidFill><a:srgbClr val="{line}"/></a:solidFill></a:ln>'
               if line else '<a:ln><a:noFill/></a:ln>')
    tmp = (
        '<p:sp><p:nvSpPr><p:cNvPr id="@ID@" name="Shape @ID@"/>'
        '<p:cNvSpPr/><p:nvPr/></p:nvSpPr>'
        '<p:spPr><a:xfrm><a:off x="@X@" y="@Y@"/><a:ext cx="@W@" cy="@H@"/></a:xfrm>'
        f'<a:prstGeom prst="{prst}"><a:avLst/></a:prstGeom>{fill_el}{line_el}</p:spPr>'
        '<p:txBody><a:bodyPr/><a:lstStyle/><a:p/></p:txBody></p:sp>'
    )
    sp_id = _next_id()
    return (tmp.replace("@ID@", str(sp_id))
               .replace("@X@", str(emu(left)))
               .replace("@Y@", str(emu(top)))
               .replace("@W@", str(emu(w)))
               .replace("@H@", str(emu(h))))


def build_slide(sp_list):
    body = "".join(sp_list)
    return (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
        '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
        'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
        'xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
        '<p:cSld><p:spTree>'
        '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
        '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/>'
        '<a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
        + body +
        '</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>'
    )


# ---------------- slide content ----------------
def slide1():
    u = emu
    sps = []
    sps.append(add_textbox(0.9, 1.6, 11.5, 1.2, [("Вред вейпов и табака:", 40, True, NAVY, False, "Arial")]))
    sps.append(add_textbox(0.9, 2.65, 11.5, 1.6,
                           [("Угроза здоровью для молодёжи", 40, True, NAVY, False, "Arial")]))
    sps.append(add_textbox(0.9, 4.3, 11.5, 0.6,
                           [("Пропаганда здорового образа жизни · Республика Беларусь · возраст 12–17",
                             16, False, DGRAY, False, "Calibri")]))
    # bottom navy ribbon
    sps.append(add_shape("rect", 0, 6.7, SLIDE_W_IN, 0.8, fill=NAVY))
    # red accent chip
    sps.append(add_shape("rect", 0.45, 6.7, 0.30, 0.8, fill=RED_SHAPE))
    sps.append(add_textbox(1.0, 6.82, 11.5, 0.55,
                           [("Выбирай здоровье!", 18, True, "FFFFFF", False, "Arial")]))
    return build_slide(sps)


def slide2():
    sps = []
    sps.append(add_textbox(0.5, 0.35, 12.3, 0.7, [("Что такое вейпы?", 28, True, NAVY, False, "Arial")]))
    bullets = [
        "Электронные сигареты (e-cigarettes), pod-системы, электронные кальяны",
        "Принцип работы: жидкость нагревается и превращается в аэрозоль",
        "Состав жидкости: никотин, пропиленгликоль (PG), глицерин (VG), ароматизаторы",
        "При нагреве образуются токсичные вещества: формальдегид, ацетальдегид, акролеин",
        "«Без запаха и безвредно» — миф: поражаются лёгкие и сердечно-сосудистая система",
    ]
    y = 1.45
    for b in bullets:
        sps.append(add_textbox(0.6, y, 12.1, 0.45, [("• " + b, 15, False, DGRAY, False, "Calibri")]))
        y += 0.52
    sps.append(add_textbox(0.6, y + 0.1, 12.1, 0.5,
                           [("В Беларуси ~8–12% подростков пробовали вейпы (данные GYTS)",
                             16, True, RED, False, "Calibri")]))
    sps.append(add_textbox(0.6, y + 0.75, 12.1, 0.45,
                           [("Долгосрочные последствия пока не изучены — каждый затяг это эксперимент над собой",
                             12, False, GRAY, True, "Calibri")]))
    sps.append(add_shape("ellipse", 0.6, 6.35, 0.38, 0.38, fill=RED_SHAPE))
    return build_slide(sps)


def slide3():
    sps = []
    sps.append(add_textbox(0.5, 0.35, 12.3, 0.7,
                           [("Химические вещества: вейпы vs табак", 28, True, NAVY, False, "Arial")]))
    sps.append(add_textbox(0.55, 1.25, 5.9, 0.45, [("Вейпы", 16, True, RED, False, "Calibri")]))
    sps.append(add_textbox(6.85, 1.25, 5.9, 0.45, [("Табачный дым", 16, True, BLUE, False, "Calibri")]))
    vape = [
        "Никотин — быстрое формирование зависимости",
        "Пропиленгликоль и глицерин (база аэрозоля)",
        "Ароматизаторы: диацетил, коричный альдегид",
        "Формальдегид, ацетальдегид, акролеин (при нагреве)",
        "До ~200–300 соединений в аэрозоле",
    ]
    smoke = [
        "7000+ химических соединений",
        "Минимум 70 известных канцерогенов",
        "Смолы и оксид углерода (CO)",
        "Мышьяк, кадмий, бензол, формальдегид",
        "Причина ~8 млн смертей в год в мире (ВОЗ)",
    ]
    y = 1.8
    for v, t in zip(vape, smoke):
        sps.append(add_textbox(0.55, y, 5.9, 0.4, [("• " + v, 13, False, DGRAY, False, "Calibri")]))
        sps.append(add_textbox(6.85, y, 5.9, 0.4, [("• " + t, 13, False, DGRAY, False, "Calibri")]))
        y += 0.52
    sps.append(add_shape("ellipse", 0.6, 6.15, 0.35, 0.35, fill=RED_SHAPE))
    sps.append(add_shape("ellipse", 0.6, 6.05, 0.35, 0.35, fill=BLUE))
    sps.append(add_textbox(1.1, 6.2, 11.6, 0.45,
                           [("«Меньше канцерогенов» не значит «безопасно»: оба варианта портят лёгкие",
                             13, False, GRAY, True, "Calibri")]))
    return build_slide(sps)


def slide4():
    sps = []
    sps.append(add_textbox(0.5, 0.35, 12.3, 0.7,
                           [("Рентгенография: сравнение лёгких", 28, True, NAVY, False, "Arial")]))
    panels = [
        (0.5, "Здоровые лёгкие", NAVY, ["Ровный рисунок", "Чёткие контуры", "Цвет: однородный"]),
        (4.77, "Лёгкие вейпера", RED, ["Тёмные пятна (инфильтраты)", "Воспаление", "Поражение альвеол"]),
        (9.04, "Лёгкие курильщика", BLUE, ["Хронический бронхит", "Эмфизема", "Деструкция ткани"]),
    ]
    for x, head, color, items in panels:
        sps.append(add_shape("rect", x, 1.35, 3.79, 2.75, fill="FFFFFF", line="B8B8B8"))
        sps.append(add_textbox(x + 0.18, 1.5, 3.45, 0.4, [(head, 15, True, color, False, "Calibri")]))
        sps.append(add_textbox(x + 0.2, 2.0, 3.4, 2.0,
                               [("\n".join("• " + it for it in items), 12, False, DGRAY, False, "Calibri")]))
        sps.append(add_textbox(x + 0.2, 3.75, 3.4, 0.35, [("(место для снимка)", 10, False, "9A9A9A", True, "Calibri")]))
    rows = [
        ("Здоровье: чистые лёгкие", DGRAY),
        ("Вейпер: мелкие очаги воспаления, снижение объёма", RED),
        ("Курильщик: хронический бронхит, эмфизема, разрушение", BLUE),
    ]
    y = 4.65
    for txt, color in rows:
        sps.append(add_textbox(0.55, y, 12.2, 0.4, [("• " + txt, 14, False, color, False, "Calibri")]))
        y += 0.48
    sps.append(add_textbox(0.55, 6.35, 12.2, 0.45,
                           [("Изменения на рентгене видны уже после 1–2 лет — не жди появления симптомов",
                             12, False, GRAY, True, "Calibri")]))
    return build_slide(sps)


def slide5():
    sps = []
    sps.append(add_textbox(0.5, 0.35, 12.3, 0.7,
                           [("Болезни органов дыхания от вейпов", 28, True, NAVY, False, "Arial")]))
    sps.append(add_textbox(0.55, 1.3, 6.4, 0.75,
                           [("EVALI", 15, True, RED, False, "Calibri"),
                            ("Поражение лёгких, связанное с вейпингом", 13, False, DGRAY, False, "Calibri")]))
    sps.append(add_textbox(0.55, 2.05, 6.4, 2.6,
                           [("\n".join([
                               "• Внезапная одышка, боль в груди, лихорадка",
                               "• Развивается за считанные дни",
                               "• Возможна дыхательная недостаточность и ИВЛ",
                               "• Группа риска — подростки и молодёжь",
                               "• США, 2020: ~2 800 госпитализаций, 68 смертей",
                           ]), 13, False, DGRAY, False, "Calibri")]))
    sps.append(add_textbox(0.55, 4.8, 6.4, 0.5,
                           [("В РБ ~8–12% подростков пробовали вейпы", 14, True, RED, False, "Calibri")]))
    sps.append(add_textbox(7.2, 1.3, 5.5, 0.45,
                           [("Дополнительные риски", 15, True, NAVY, False, "Calibri")]))
    risks = [
        "Хронический бронхит, постоянный кашель",
        "Снижение объёма и функции лёгких",
        "Риск фиброза лёгочной ткани",
        "Ослабленный иммунитет, частые ОРВИ",
        "Шанс начать курить сигареты — в 3 раза выше",
    ]
    y = 1.85
    for r in risks:
        sps.append(add_textbox(7.2, y, 5.5, 0.55, [("• " + r, 13, False, DGRAY, False, "Calibri")]))
        y += 0.62
    sps.append(add_shape("ellipse", 0.55, 6.3, 0.4, 0.4, fill=RED_SHAPE))
    return build_slide(sps)


def slide6():
    sps = []
    sps.append(add_textbox(0.5, 0.35, 12.3, 0.7,
                           [("Болезни органов дыхания от курения", 28, True, NAVY, False, "Arial")]))
    items = [
        "Рак лёгких — причина №1 смертности от рака в мире (~85% случаев связывают с курением)",
        "ХОБЛ — хроническая обструктивная болезнь лёгких: неизлечимое прогрессирующее заболевание",
        "Хронический бронхит — ежедневный кашель с мокротой, частые бронхиты",
        "Эмфизема — разрушение альвеол, лёгкие теряют способность насыщать кровь кислородом",
        "Туберкулёз и пневмония — риск выше из-за ослабленного иммунитета дыхательных путей",
    ]
    y = 1.5
    for it in items:
        sps.append(add_textbox(0.6, y, 12.1, 0.55, [("• " + it, 14, False, DGRAY, False, "Calibri")]))
        y += 0.62
    sps.append(add_textbox(0.6, y + 0.15, 12.1, 0.55,
                           [("По данным Минздрава РБ: ~35 000–40 000 смертей в год связаны с табаком",
                             15, True, BLUE, False, "Calibri")]))
    sps.append(add_shape("ellipse", 0.6, 6.35, 0.4, 0.4, fill=BLUE))
    return build_slide(sps)


def slide7():
    sps = []
    sps.append(add_textbox(0.5, 0.35, 12.3, 0.7,
                           [("Сравнительная статистика (Беларусь)", 28, True, NAVY, False, "Arial")]))
    # bars
    sps.append(add_shape("rect", 0.55, 1.5, 5.6, 0.6, fill=RED_SHAPE))
    sps.append(add_textbox(0.8, 1.62, 5.2, 0.4, [("ВЕЙПЫ", 15, True, "FFFFFF", False, "Calibri")]))
    sps.append(add_textbox(0.8, 2.12, 5.2, 0.4, [("~8–12% подростков РБ пробовали", 12, False, "FFFFFF", False, "Calibri")]))
    sps.append(add_shape("rect", 0.55, 2.7, 5.6, 0.6, fill=BLUE))
    sps.append(add_textbox(0.8, 2.82, 5.2, 0.4, [("КУРЕНИЕ", 15, True, "FFFFFF", False, "Calibri")]))
    sps.append(add_textbox(0.8, 3.32, 5.2, 0.4, [("27% взрослых в РБ · 35 000+ смертей/год", 12, False, "FFFFFF", False, "Calibri")]))
    notes = [
        ("Вейпы: зависимость, воспаление лёгких, EVALI, «мост» к сигаретам", RED),
        ("Курение: 7000+ соединений, 70+ канцерогенов, рак и ХОБЛ", BLUE),
    ]
    y = 4.3
    for txt, color in notes:
        sps.append(add_textbox(0.55, y, 12.2, 0.45, [("• " + txt, 14, False, color, False, "Calibri")]))
        y += 0.5
    sps.append(add_textbox(0.55, 5.4, 12.2, 0.5,
                           [("Обе привычки — прибыль индустрии из твоего кармана: ~1 500 р/год на вейп или сигареты",
                             13, False, DGRAY, True, "Calibri")]))
    return build_slide(sps)


def slide8():
    sps = []
    sps.append(add_textbox(0.5, 0.35, 12.3, 0.7,
                           [("Что произойдёт, если отказаться", 28, True, NAVY, False, "Arial")]))
    steps = [
        ("Через 20 минут", "пульс и давление возвращаются к норме"),
        ("Через 12 часов", "уровень угарного газа (CO) в крови нормализуется"),
        ("Через 3 месяца", "функция лёгких улучшается на 5–10%"),
        ("Через 1 год", "риск ишемической болезни сердца вдвое ниже"),
        ("Через 5–15 лет", "риск инсульта — как у никогда не курившего"),
    ]
    y = 1.45
    for w, rest in steps:
        sps.append(add_textbox(0.6, y, 12.1, 0.5, [
            (w + ":  ", 15, True, GREEN, False, "Calibri"),
            (rest, 15, False, DGRAY, False, "Calibri"),
        ]))
        y += 0.62
    sps.append(add_textbox(0.6, y + 0.2, 12.1, 0.5,
                           [("Чем раньше бросишь — тем быстрее восстановятся лёгкие. Твои лёгкие формируются до ~25 лет!",
                             14, True, GREEN, False, "Calibri")]))
    sps.append(add_shape("ellipse", 0.6, 6.3, 0.4, 0.4, fill=GREEN))
    return build_slide(sps)


def slide9():
    sps = []
    sps.append(add_textbox(0.5, 0.35, 12.3, 0.7,
                           [("Выводы и призыв", 28, True, NAVY, False, "Arial")]))
    items = [
        "Вейпы не безвредны: вызывают воспаление и повреждение лёгких",
        "Курение — причина №1 предотвратимых смертей в РБ (35 000–40 000 в год)",
        "Рентген показывает изменения уже у молодых пользователей",
        "12–17 лет — критический период: лёгкие растут, вред накапливается на всю жизнь",
        "Лучшее решение — никогда не начинать. Это дёшево и реально",
    ]
    y = 1.45
    for i, it in enumerate(items, 1):
        sps.append(add_textbox(0.6, y, 12.1, 0.55, [
            (f"{i}.  ", 15, True, NAVY, False, "Calibri"),
            (it, 15, False, DGRAY, False, "Calibri"),
        ]))
        y += 0.62
    sps.append(add_textbox(0.6, y + 0.1, 12.1, 0.5,
                           [("Бесплатная помощь: портал e-ocenka.by · горячая линия Минздрава РБ",
                             13, False, GRAY, False, "Calibri")]))
    sps.append(add_textbox(0.6, 5.6, 12.1, 0.45,
                           [("Источники: Минздрав РБ, ВОЗ, CDC, GYTS (опросы школьников РБ), 2020–2024",
                             11, False, GRAY, False, "Calibri")]))
    sps.append(add_shape("rect", 0, 6.7, SLIDE_W_IN, 0.8, fill=NAVY))
    sps.append(add_shape("rect", 0.45, 6.7, 0.3, 0.8, fill=RED_SHAPE))
    sps.append(add_textbox(1.0, 6.8, 11.5, 0.55,
                           [("«Выбирай здоровье! Твой выбор влияет на будущее»", 18, True, "FFFFFF", False, "Arial")]))
    return build_slide(sps)


# ---------------- package assembly ----------------

def main():
    if not os.path.exists(TPL):
        print("template missing:", TPL)
        sys.exit(1)

    if os.path.exists(TMP):
        shutil.rmtree(TMP)
    os.makedirs(TMP)
    with zipfile.ZipFile(TPL) as zf:
        zf.extractall(TMP)

    # target layout = Blank (python-pptx default template: 11 standard layouts)
    layout_path_for_rel = "../slideLayouts/slideLayout7.xml"

    # wipe old slides (template ships without slides part dir)
    slides_dir = os.path.join(TMP, "ppt", "slides")
    os.makedirs(slides_dir, exist_ok=True)
    rels_dir = os.path.join(slides_dir, "_rels")
    os.makedirs(rels_dir, exist_ok=True)
    for f in os.listdir(slides_dir):
        if f.endswith(".xml"):
            os.remove(os.path.join(slides_dir, f))
    for f in os.listdir(rels_dir):
        os.remove(os.path.join(rels_dir, f))

    specs = [slide1, slide2, slide3, slide4, slide5, slide6, slide7, slide8, slide9]
    for i, spec in enumerate(specs, 1):
        with open(os.path.join(slides_dir, f"slide{i}.xml"), "w", encoding="utf-8") as f:
            f.write(spec())
        with open(os.path.join(rels_dir, f"slide{i}.xml.rels"), "w", encoding="utf-8") as f:
            f.write(
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                f'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="{layout_path_for_rel}"/>'
                '</Relationships>'
            )

    # presentation.xml: rebuild sldIdLst
    pres_path = os.path.join(TMP, "ppt", "presentation.xml")
    with open(pres_path, encoding="utf-8") as f:
        pres = f.read()
    ids = "".join(f'<p:sldId id="{255 + i}" r:id="rId10{i}"/>' for i in range(1, 10))
    lst = f"<p:sldIdLst>{ids}</p:sldIdLst>"
    if "<p:sldIdLst>" in pres:
        pres = re.sub(r"<p:sldIdLst>.*?</p:sldIdLst>", lst, pres, flags=re.DOTALL)
    elif re.search(r"<p:sldIdLst\s*/>", pres):
        pres = re.sub(r"<p:sldIdLst\s*/>", lst, pres)
    else:
        pres = pres.replace("</p:sldMasterIdLst>", "</p:sldMasterIdLst>" + lst)
    with open(pres_path, "w", encoding="utf-8") as f:
        f.write(pres)

    # presentation.xml.rels: drop slide rels, add ours
    rels_path = os.path.join(TMP, "ppt", "_rels", "presentation.xml.rels")
    with open(rels_path, encoding="utf-8") as f:
        rels = f.read()
    rels = re.sub(r"<Relationship\b(?:[^>]*Type=\"[^\"]*relationships/slide\"[^>]*?>|[^>]*?/>)(?:(?!</Relationship>).)*?</Relationship\s*>",
                  "", rels, flags=re.DOTALL)
    rels = re.sub(r'<Relationship\b[^>]*?Relationships?="[^"]*relationships/slide"[^>]*/>',
                  "", rels)
    new_rels = "".join(
        f'<Relationship Id="rId10{i}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide{i}.xml"/>'
        for i in range(1, 10)
    )
    rels = rels.replace("</Relationships>", new_rels + "</Relationships>")
    with open(rels_path, "w", encoding="utf-8") as f:
        f.write(rels)

    # [Content_Types].xml: drop slide overrides, add ours
    ct_path = os.path.join(TMP, "[Content_Types].xml")
    with open(ct_path, encoding="utf-8") as f:
        ct = f.read()
    ct = re.sub(r'<Override PartName="/ppt/slides/slide\d+\.xml"[^>]*/>', "", ct)
    adds = "".join(
        f'<Override PartName="/ppt/slides/slide{i}.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>'
        for i in range(1, 10)
    )
    ct = ct.replace("</Types>", adds + "</Types>")
    with open(ct_path, "w", encoding="utf-8") as f:
        f.write(ct)

    # docProps minimal (Slides count)
    app_path = os.path.join(TMP, "docProps", "app.xml")
    if os.path.exists(app_path):
        with open(app_path, "w", encoding="utf-8") as f:
            f.write(
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
                '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
                'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
                '<Application>Microsoft Office PowerPoint</Application><Slides>9</Slides>'
                '<Company></Company><AppVersion>16.0000</AppAppVersion>'.replace("AppApp", "App") +
                '</Properties>'
            )

    # zip
    if os.path.exists(OUT):
        os.remove(OUT)
    with zipfile.ZipFile(OUT, "w", zipfile.ZIP_DEFLATED) as zf:
        for root, _dirs, files in os.walk(TMP):
            for name in files:
                full = os.path.join(root, name)
                arc = os.path.relpath(full, TMP).replace("\\", "/")
                zf.write(full, arc)

    # verify: parse every xml
    with zipfile.ZipFile(OUT) as zf:
        bad = zf.testzip()
        if bad:
            print("Corrupt member:", bad)
            sys.exit(1)
        for name in zf.namelist():
            if name.endswith(".xml") or name.endswith(".rels"):
                xml.dom.minidom.parseString(zf.read(name))
    size = os.path.getsize(OUT)
    print(f"OK built: {OUT} ({size} bytes)")

    # copy to Desktop
    desk = os.path.join(os.path.expanduser("~"), "Desktop")
    try:
        dst = os.path.join(desk, OUT_NAME)
        shutil.copy2(OUT, dst)
        print(f"Copied to Desktop: {dst}")
    except Exception as e:
        print("Desktop copy skipped:", e)

    shutil.rmtree(TMP, ignore_errors=True)


if __name__ == "__main__":
    main()