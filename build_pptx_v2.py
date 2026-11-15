# -*- coding: utf-8 -*-
"""
v2 (clean): «Вред вейпов и табака (Беларусь)», 16:9, КАРТИНКИ ИЗ СЕТИ.
Чистый stdlib. Шаблон python-pptx default.pptx как каркас пакета.

Исправления против v1 (причина «восстановления»):
  * docProps/{app,core}.xml НЕ трогаем — остаются шаблонными и консистентными
  * размер слайда 16:9 (12192000x6858000, screen16x9) вместо 4:3 шаблона
Изображения: поиск через API Wikimedia Commons -> скачивание превью
-> встраивание как media-части + rels + <p:pic> внутри слайда.
"""
import os
import re
import json
import sys
import shutil
import zipfile
import urllib.request
import urllib.parse
import xml.dom.minidom
import time
from xml.sax.saxutils import escape as _esc

WORK = os.path.dirname(os.path.abspath(__file__))
TPL = r"C:\Users\Admin\AppData\Local\Programs\Python\Python315\Lib\site-packages\pptx\templates\default.pptx"
OUT_NAME = "vaping_harm_presentation_belarus_v2.pptx"
OUT = os.path.join(WORK, OUT_NAME)
TMP = os.path.join(WORK, "_pptx_build")

EMU = 914400
SLIDE_W_IN = 13.333
SLIDE_H_IN = 7.5

NAVY = "1E2761"
RED = "D93A4A"
RED_SHAPE = "F96167"
BLUE = "2F3C7E"
GREEN = "2E7D32"
DGRAY = "3A3A3A"
GRAY = "6E6E6E"

UA = "Mozilla/5.0 (compatible; educational-deck-builder/2.0; contact: minskline.by)"


def escape(text):
    return _esc(text, {'"': "&quot;"})


# ------------------------------------------------------------------ geometry ids
_sp_id = [10]


def _next_id():
    _sp_id[0] += 1
    return _sp_id[0]


def emu(inches):
    return int(round(inches * EMU))


# ------------------------------------------------------------------оявление текста/фигур
def rpr(size, bold, color, font="Calibri", italic=False):
    b = ' b="1"' if bold else ""
    it = ' i="1"' if italic else ""
    return (f'<a:rPr lang="ru-RU" sz="{int(round(size * 100))}"{b}{it} dirty="0">'
            f'<a:solidFill><a:srgbClr val="{color}"/></a:solidFill>'
            f'<a:latin typeface="{font}"/><a:cs typeface="{font}"/></a:rPr>')


def add_textbox(left, top, w, h, lines):
    """lines: список (text, size, bold, color, italic, font) - по абзацу на элемент."""
    els = []
    for (text, size, bold, color, italic, font) in lines:
        els.append(f'<a:r>{rpr(size, bold, color, font, italic)}<a:t>{escape(text)}</a:t></a:r>')
    paragraphs = "".join(f"<a:p>{e}</a:p>" for e in els)
    sp_id = _next_id()
    tpl = (
        '<p:sp><p:nvSpPr><p:cNvPr id="@ID@" name="Text @ID@"/>'
        '<p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>'
        '<p:spPr><a:xfrm><a:off x="@X@" y="@Y@"/><a:ext cx="@W@" cy="@H@"/></a:xfrm>'
        '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/></p:spPr>'
        '<p:txBody><a:bodyPr wrap="square" rtlCol="0" anchor="t"/>'
        '<a:lstStyle/>' + paragraphs + '<a:p><a:endParaRPr lang="ru-RU"/></a:p>'
        '</p:txBody></p:sp>'
    )
    return (tpl.replace("@ID@", str(sp_id))
               .replace("@X@", str(emu(left)))
               .replace("@Y@", str(emu(top)))
               .replace("@W@", str(emu(w)))
               .replace("@H@", str(emu(h))))


def add_runs_textbox(left, top, w, h, paras):
    """paras: список абзацев; каждый абзац — список run-кортежей (text,size,bold,color,italic,font)."""
    paragraphs = ""
    for runs in paras:
        els = "".join(
            f'<a:r>{rpr(size, bold, color, font, italic)}<a:t>{escape(text)}</a:t></a:r>'
            for (text, size, bold, color, italic, font) in runs)
        paragraphs += f"<a:p>{els}</a:p>"
    sp_id = _next_id()
    tpl = (
        '<p:sp><p:nvSpPr><p:cNvPr id="@ID@" name="Text @ID@"/>'
        '<p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>'
        '<p:spPr><a:xfrm><a:off x="@X@" y="@Y@"/><a:ext cx="@W@" cy="@H@"/></a:xfrm>'
        '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/></p:spPr>'
        '<p:txBody><a:bodyPr wrap="square" rtlCol="0" anchor="t"/>'
        '<a:lstStyle/>' + paragraphs + '<a:p><a:endParaRPr lang="ru-RU"/></a:p>'
        '</p:txBody></p:sp>'
    )
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


# ------------------------------------------------------------------ картинки
_media_seq = [0]


def get_size(data):
    try:
        from io import BytesIO
        from PIL import Image
        with Image.open(BytesIO(data)) as im:
            return im.size
    except Exception:
        pass
    if data[:8] == b'\x89PNG\r\n\x1a\n':
        return int.from_bytes(data[16:20], "big"), int.from_bytes(data[20:24], "big")
    i = 2
    n = len(data)
    while i + 9 < n:
        if data[i] != 0xFF:
            i += 1
            continue
        marker = data[i + 1]
        if marker in (0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF):
            h = int.from_bytes(data[i + 5:i + 7], "big")
            w = int.from_bytes(data[i + 7:i + 9], "big")
            return w, h
        seg_len = int.from_bytes(data[i + 2:i + 4], "big")
        i += 2 + seg_len
    return 800, 600


def http_get(url, timeout=25):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return r.read()


def http_get_retry(url, timeout=25, attempts=3):
    last = None
    for k in range(attempts):
        try:
            return http_get(url, timeout)
        except Exception as e:
            last = e
            time.sleep(4 + 4 * k)
            print("  retry", k + 1, "->", e)
    raise last


def commons_search(term, limit=6):
    q = urllib.parse.quote(term)
    url = (f"https://commons.wikimedia.org/w/api.php?action=query&format=json"
           f"&list=search&srnamespace=6&srlimit={limit}&srsearch={q}")
    try:
        data = json.loads(http_get_retry(url).decode("utf-8"))
        return [h["title"] for h in data.get("query", {}).get("search", [])]
    except Exception as e:
        print("  search fail:", term, "|", e)
        return []


def commons_info(title, thumb_w=1400):
    q = urllib.parse.quote(title)
    url = (f"https://commons.wikimedia.org/w/api.php?action=query&format=json"
           f"&prop=imageinfo&iiprop=url|size|mime&iiurlwidth={thumb_w}&titles={q}")
    try:
        data = json.loads(http_get_retry(url).decode("utf-8"))
        for _pg, info in data.get("query", {}).get("pages", {}).items():
            ii = (info.get("imageinfo") or [{}])[0]
            if ii.get("thumburl"):
                return {
                    "title": title,
                    "thumb": ii["thumburl"],
                    "mime": ii.get("mime", "image/jpeg"),
                    "ow": ii.get("width", 1),
                    "oh": ii.get("height", 1),
                }
    except Exception as e:
        print("  info fail:", title, "|", e)
    return None


def pick_image(queries, required_any=None, required_all=(), forbid=(), width=1400):
    """Первое фото из Commons, чьё имя проходит фильтры."""
    for term in queries:
        time.sleep(1.5)
        for title in commons_search(term):
            low = title.lower()
            if forbid and any(f in low for f in forbid):
                continue
            if required_all and not all(f in low for f in required_all):
                continue
            if required_any and not any(f in low for f in required_any):
                continue
            info = commons_info(title, width)
            if not info:
                continue
            try:
                data = http_get_retry(info["thumb"])
            except Exception as e:
                print("  dl fail:", e)
                continue
            print("  ->", info["title"], f"({info['ow']}x{info['oh']} {info['mime']})")
            return {"data": data, "title": info["title"], "w": info["ow"], "h": info["oh"]}
    return None


def emit_pic(img, box, pics):
    """box=(x,y,w,h) inches; pics: список слайда для rels (rId2, rId3, ...)."""
    data = img["data"]
    is_png = data[:8] == b'\x89PNG\r\n\x1a\n'
    ext = "png" if is_png else "jpeg"
    _media_seq[0] += 1
    target = f"ppt/media/image{_media_seq[0]}.{ext}"
    w, h = get_size(data)
    bw, bh = box[2], box[3]
    scale = min(bw / w if w else 1, bh / h if h else 1)
    dw, dh = w * scale, h * scale
    ox = box[0] + (bw - dw) / 2
    oy = box[1] + (bh - dh) / 2
    sp_id = _next_id()
    rel_index = len(pics) + 2          # rId1 занят layout'ом
    rid = f"rId{rel_index}"
    pics.append((rid, target))
    _media_cache[target] = data
    return (f'<p:pic><p:nvPicPr><p:cNvPr id="{sp_id}" name="Photo {sp_id}"/>'
            '<p:cNvPicPr><a:picLocks noChangeAspect="1"/></p:cNvPicPr><p:nvPr/></p:nvPicPr>'
            f'<p:blipFill><a:blip r:embed="{rid}"/><a:srcRect/>'
            '<a:stretch><a:fillRect/></a:stretch></p:blipFill>'
            '<p:spPr><a:xfrm><a:off x="@X@" y="@Y@"/><a:ext cx="@W@" cy="@H@"/></a:xfrm>'
            '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr></p:pic>'
            ).replace("@X@", str(emu(ox))).replace("@Y@", str(emu(oy))) \
             .replace("@W@", str(emu(dw))).replace("@H@", str(emu(dh)))


_media_cache = {}


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


# ------------------------------------------------------------------ слайды
def slide1(pics):
    sps = []
    sps.append(add_textbox(0.9, 1.6, 11.5, 1.2, [("Вред вейпов и табака:", 40, True, NAVY, False, "Arial")]))
    sps.append(add_textbox(0.9, 2.65, 11.5, 1.6,
                           [("Угроза здоровью для молодёжи", 40, True, NAVY, False, "Arial")]))
    sps.append(add_textbox(0.9, 4.3, 11.5, 0.6,
                           [("Пропаганда здорового образа жизни · Республика Беларусь · возраст 12–17",
                             16, False, DGRAY, False, "Calibri")]))
    sps.append(add_shape("rect", 0, 6.7, SLIDE_W_IN, 0.8, fill=NAVY))
    sps.append(add_shape("rect", 0.45, 6.7, 0.30, 0.8, fill=RED_SHAPE))
    sps.append(add_textbox(1.0, 6.82, 11.5, 0.55,
                           [("Выбирай здоровье!", 18, True, "FFFFFF", False, "Arial")]))
    return build_slide(sps)


def slide2(pics):
    sps = []
    sps.append(add_textbox(0.5, 0.35, 8.2, 0.7, [("Что такое вейпы?", 28, True, NAVY, False, "Arial")]))
    bullets = [
        "Электронные сигареты (e-cigarettes), POD-системы, электронные кальяны",
        "Жидкость нагревается и превращается в аэрозоль",
        "Состав: никотин, пропиленгликоль (PG), глицерин (VG), ароматизаторы",
        "При нагреве образуются: формальдегид, ацетальдегид, акролеин",
        "«Без запаха и безвредно» — миф: страдают лёгкие и сердце",
    ]
    y = 1.45
    for b in bullets:
        sps.append(add_textbox(0.6, y, 7.6, 0.5, [("• " + b, 14, False, DGRAY, False, "Calibri")]))
        y += 0.58
    sps.append(add_textbox(0.6, y + 0.1, 7.6, 0.5,
                           [("В Беларуси ~8–12% подростков пробовали вейпы", 15, True, RED, False, "Calibri")]))
    sps.append(add_textbox(0.6, y + 0.75, 7.6, 0.45,
                           [("Долгосрочные последствия вейпинга ещё не изучены",
                             12, False, GRAY, True, "Calibri")]))
    img = None
    try:
        img = pick_image(queries=["electronic cigarette vaping device", "vaping device photo",
                                  "e-cigarette vaporizer"],
                         required_any=["cigarette", "vape", "vaporiz"])
    except Exception as e:
        print("  slide2 image skipped:", e)
    if img:
        sps.append(emit_pic(img, (8.45, 1.45, 4.3, 4.4), pics))
        sps.append(add_textbox(8.45, 5.95, 4.3, 0.3,
                               [("Фото: Wikimedia Commons", 9, False, GRAY, False, "Calibri")]))
    else:
        sps.append(add_textbox(8.45, 3.4, 4.3, 0.6,
                               [("(место для фото устройства)", 11, False, GRAY, True, "Calibri")]))
    return build_slide(sps)


def slide3(pics):
    sps = []
    sps.append(add_textbox(0.5, 0.35, 12.3, 0.7,
                           [("Химические вещества: вейпы vs табак", 28, True, NAVY, False, "Arial")]))
    sps.append(add_textbox(0.55, 1.25, 5.9, 0.45, [("Вейпы", 16, True, RED, False, "Calibri")]))
    sps.append(add_textbox(6.85, 1.25, 5.9, 0.45, [("Табачный дым", 16, True, BLUE, False, "Calibri")]))
    vape = [
        "Никотин — быстрое привыкание",
        "Пропиленгликоль и глицерин (база)",
        "Ароматизаторы: диацетил, коричный альдегид",
        "Формальдегид, акролеин (при нагреве)",
        "До ~200–300 соединений в аэрозоле",
    ]
    smoke = [
        "7000+ химических соединений",
        "Минимум 70 известных канцерогенов",
        "Смолы и оксид углерода (CO)",
        "Мышьяк, кадмий, бензол, формальдегид",
        "~8 млн смертей в год в мире (ВОЗ)",
    ]
    y = 1.8
    for v, t in zip(vape, smoke):
        sps.append(add_textbox(0.55, y, 5.9, 0.4, [("• " + v, 13, False, DGRAY, False, "Calibri")]))
        sps.append(add_textbox(6.85, y, 5.9, 0.4, [("• " + t, 13, False, DGRAY, False, "Calibri")]))
        y += 0.52
    sps.append(add_textbox(1.1, 6.15, 11.6, 0.45,
                           [("«Меньше канцерогенов» не значит «безопасно»: оба варианта портят лёгкие",
                             13, False, GRAY, True, "Calibri")]))
    return build_slide(sps)


def slide4(pics):
    sps = []
    sps.append(add_textbox(0.5, 0.2, 12.3, 0.6,
                           [("Изменения лёгких: примеры рентгенов", 27, True, NAVY, False, "Arial")]))
    panels = [
        (0.5, "Здоровые лёгкие (пример)", NAVY,
         ["normal chest radiograph", "normal chest X-ray PA", "healthy chest radiograph"],
         ("normal", "healthy"), ("smoker", "cancer", "emphysema", "pneumo", "flu")),
        (4.77, "Воспаление на рентгене (пример)", RED,
         ["pneumonia chest radiograph", "chest X-ray pneumonia", "chest radiograph infiltration"],
         ("chest",), None),
        (9.04, "Эмфизема / ХОБЛ (пример)", BLUE,
         ["emphysema chest radiograph", "COPD chest X-ray", "emphysema lungs X-ray"],
         ("emphysema", "copd"), ()),
    ]
    for x, head, color, queries, need_any, forbid in panels:
        sps.append(add_textbox(x + 0.10, 0.95, 3.6, 0.55,
                               [(head, 14, True, color, False, "Calibri")]))
        img = None
        try:
            img = pick_image(queries=queries, required_any=need_any, forbid=forbid or (), width=1200)
        except Exception as e:
            print("  slide4 image skipped:", e)
        if img:
            sps.append(emit_pic(img, (x + 0.10, 1.55, 3.59, 3.30), pics))
            sps.append(add_textbox(x + 0.10, 4.90, 3.6, 0.32,
                                   [("Источник: Wikimedia Commons", 9, False, GRAY, False, "Calibri")]))
        else:
            sps.append(add_shape("rect", x + 0.1, 1.55, 3.6, 3.3, fill="F4F4F4", line="B8B8B8"))
            sps.append(add_textbox(x + 0.3, 3.05, 3.2, 0.4,
                                   [("(нет изображения)", 11, False, GRAY, False, "Calibri")]))
    rows = [
        ("Здоровые: ровный рисунок, чёткие контуры", DGRAY),
        ("Пневмония / воспаление: пятна (инфильтраты) в тени", RED),
        ("Эмфизема: изменённый рисунок, разрушение альвеол", BLUE),
    ]
    y = 5.5
    for txt, color in rows:
        sps.append(add_textbox(0.55, y, 12.2, 0.4, [("• " + txt, 13, False, color, False, "Calibri")]))
        y += 0.48
    return build_slide(sps)


def slide5(pics):
    sps = []
    sps.append(add_textbox(0.5, 0.35, 12.3, 0.7,
                           [("Болезни органов дыхания от вейпов", 28, True, NAVY, False, "Arial")]))
    sps.append(add_textbox(0.55, 1.3, 6.2, 0.75,
                           [("EVALI", 15, True, RED, False, "Calibri"),
                            ("Поражение лёгких, связанное с вейпингом", 13, False, DGRAY, False, "Calibri")]))
    lines = [
        "Внезапная одышка, боль в груди, лихорадка",
        "Развивается за считанные дни",
        "Возможна дыхательная недостаточность и ИВЛ",
        "Группа риска — подростки и молодёжь",
        "США, 2020: ~2 800 госпитализаций, 68 смертей",
    ]
    paras = [("• " + l, 13, False, DGRAY, False, "Calibri") for l in lines]
    sps.append(add_textbox(0.55, 2.15, 6.2, 2.6, paras))
    sps.append(add_textbox(0.55, 4.9, 6.4, 0.5,
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
        sps.append(add_textbox(7.25, y, 5.4, 0.55, [("• " + r, 12, False, DGRAY, False, "Calibri")]))
        y += 0.62
    return build_slide(sps)


def slide6(pics):
    sps = []
    sps.append(add_textbox(0.5, 0.35, 12.3, 0.7,
                           [("Болезни органов дыхания от курения", 28, True, NAVY, False, "Arial")]))
    items = [
        "Рак лёгких — №1 смертности от рака в мире (~85% случаев — курение)",
        "ХОБЛ — неизлечимое прогрессирующее заболевание",
        "Хронический бронхит — ежедневный кашель с мокротой",
        "Эмфизема — разрушение альвеол",
        "Повышенный риск туберкулёза и пневмоний",
    ]
    y = 1.45
    for it in items:
        sps.append(add_textbox(0.6, y, 6.9, 0.55, [("• " + it, 13, False, DGRAY, False, "Calibri")]))
        y += 0.6
    sps.append(add_textbox(0.6, y + 0.05, 7.0, 0.7,
                           [("РБ: ~35–40 тыс. смертей в год связаны с табаком (Минздрав)",
                             14, True, BLUE, False, "Calibri")]))
    img = None
    try:
        img = pick_image(queries=["Healthy lung-smokers lung", "Human lungs smoker vs non smoker",
                                  "Smoker's lungs"],
                         required_any=("smok",), required_all=("lung",),
                         forbid=("jar", "specimen", "webm", "svg", ".png", "demonstration"))
    except Exception as e:
        print("  slide6 image skipped:", e)
    if img:
        sps.append(emit_pic(img, (7.65, 1.45, 5.05, 4.0), pics))
        sps.append(add_textbox(7.65, 5.5, 5.05, 0.35,
                               [("Фото (иллюстративно) · Wikimedia Commons", 9, False, GRAY, False, "Calibri")]))
    else:
        sps.append(add_textbox(7.65, 3.0, 5.05, 0.5,
                               [("(место для фото)", 11, False, GRAY, False, "Calibri")]))
    return build_slide(sps)


def slide7(pics):
    sps = []
    sps.append(add_textbox(0.5, 0.35, 12.3, 0.7,
                           [("Сравнительная статистика (Беларусь)", 28, True, NAVY, False, "Arial")]))
    sps.append(add_shape("rect", 0.55, 1.5, 5.6, 0.6, fill=RED_SHAPE))
    sps.append(add_textbox(0.8, 1.56, 5.2, 0.28, [("ВЕЙПЫ", 14, True, "FFFFFF", False, "Calibri")]))
    sps.append(add_textbox(0.8, 1.82, 5.2, 0.26,
                           [("~8–12% подростков РБ пробовали", 11, False, "FFFFFF", False, "Calibri")]))
    sps.append(add_shape("rect", 0.55, 2.7, 5.6, 0.6, fill=BLUE))
    sps.append(add_textbox(0.8, 2.76, 5.2, 0.28, [("КУРЕНИЕ", 14, True, "FFFFFF", False, "Calibri")]))
    sps.append(add_textbox(0.8, 3.02, 5.2, 0.26,
                           [("27% взрослых в РБ · 35–40 тыс. смертей/год", 11, False, "FFFFFF", False, "Calibri")]))
    rows = [
        ("Вейпы: зависимость, воспаление лёгких, EVALI, «мост» к сигаретам", RED),
        ("Курение: 7000+ соединений, 70+ канцерогенов, рак и ХОБЛ", BLUE),
    ]
    y = 4.3
    for txt, color in rows:
        sps.append(add_textbox(0.55, y, 12.2, 0.45, [("• " + txt, 14, False, color, False, "Calibri")]))
        y += 0.5
    sps.append(add_textbox(0.55, 5.4, 12.2, 0.5,
                           [("Каждая пачка и каждая заправка — деньги и здоровье из общего семейного кармана",
                             13, False, DGRAY, True, "Calibri")]))
    return build_slide(sps)


def slide8(pics):
    sps = []
    sps.append(add_textbox(0.5, 0.35, 12.3, 0.7,
                           [("Что произойдёт, если отказаться", 28, True, NAVY, False, "Arial")]))
    steps = [
        ("Через 20 минут", "пульс и давление приходят в норму"),
        ("Через 12 часов", "уровень угарного газа (CO) в крови нормализуется"),
        ("Через 3 месяца", "функция лёгких улучшается на 5–10%"),
        ("Через 1 год", "риск ишемической болезни сердца вдвое ниже"),
        ("Через 5–15 лет", "риск инсульта — как у никогда не курившего"),
    ]
    y = 1.45
    for when, rest in steps:
        sps.append(add_runs_textbox(0.6, y, 12.1, 0.45, [[
            (when + "  — ", 15, True, GREEN, False, "Calibri"),
            (rest, 15, False, DGRAY, False, "Calibri"),
        ]]))
        y += 0.62
    sps.append(add_textbox(0.6, y + 0.2, 12.1, 0.5,
                           [("Чем раньше бросишь — тем быстрее восстановятся лёгкие. "
                             "Лёгкие формируются до ~25 лет!", 14, True, GREEN, False, "Calibri")]))
    return build_slide(sps)


def slide9(pics):
    sps = []
    sps.append(add_textbox(0.5, 0.35, 12.3, 0.7,
                           [("Выводы и призыв", 28, True, NAVY, False, "Arial")]))
    items = [
        "Вейпы не безвредны: воспаление и повреждение лёгких",
        "Курение — причина №1 предотвратимых смертей в РБ",
        "Рентген показывает изменения уже у молодых",
        "12–17 лет: лёгкие растут, вред накапливается на всю жизнь",
        "Лучшее решение — никогда не начинать. Это доступно каждому",
    ]
    y = 1.45
    for i, it in enumerate(items, 1):
        sps.append(add_runs_textbox(0.6, y, 12.1, 0.45, [[
            (f"{i}.  ", 15, True, NAVY, False, "Calibri"),
            (it, 15, False, DGRAY, False, "Calibri"),
        ]]))
        y += 0.62
    sps.append(add_textbox(0.6, y + 0.05, 12.1, 0.5,
                           [("Помощь: телефон доверия для детей и молодёжи 8 801 100 16 11 (бесплатно), центры дружеского обслуживания «ЗОЖ», школьный психолог",
                             12, False, GRAY, False, "Calibri")]))
    sps.append(add_textbox(0.6, 5.6, 12.1, 0.45,
                           [("Источники: Минздрав РБ, ВОЗ, CDC, GYTS (школьные опросы РБ), 2020–2024",
                             11, False, GRAY, False, "Calibri")]))
    sps.append(add_shape("rect", 0, 6.7, SLIDE_W_IN, 0.8, fill=NAVY))
    sps.append(add_shape("rect", 0.45, 6.7, 0.3, 0.8, fill=RED_SHAPE))
    sps.append(add_textbox(1.0, 6.8, 11.5, 0.55,
                           [("«Выбирай здоровье! Твой выбор влияет на будущее»", 18, True, "FFFFFF", False, "Arial")]))
    return build_slide(sps)


# ------------------------------------------------------------------ заметки для лектора

NM_TPL = (r"C:\Users\Admin\AppData\Local\Programs\Python\Python315\Lib"
          r"\site-packages\pptx\templates\notesMaster.xml")

NOTES = {
    1: ("Слайд 1 — Титульный",
        "Приветствие и рамка занятия (5 минут). Представьтесь и объясните формат: это не лекция «о вреде курения», а честный разбор с цифрами и снимками — без запугивания и морализаторства. Такой тон критичен для возраста 12–17 лет: страх работает плохо, аргументы работают хорошо.",
        "Опишите правила разговора: любые вопросы принимаются без насмешек; о личном опыте никого не вынуждаем; «рассказать о знакомом» можно без имени. Это снижает защитную реакцию.",
        "Разминка: попросите поднять руку тех, кто видел вейп у друзей или знакомых. По школьным опросам в классе из 25 человек это обычно 2–3 человека — выводы аудитория сделает сама.",
        "Скажите тайминг: 25–35 минут с обсуждением. Главная рамка занятия: лёгкие — ресурс, который расходуется незаметно и восстанавливается лишь частично."),
    2: ("Слайд 2 — Что такое вейпы",
        "Сначала терминология: вейп, e-cigarette, pod-система, электронный кальян. Устройство простое: батарея нагревает спираль, жидкость испаряется, пользователь вдыхает аэрозоль. Никотиновые соли в pod-системах дают 20–50 мг/мл — за один день вдыхается доза никотина, сопоставимая с пачкой сигарет, но процесс незаметен.",
        "Ключевой тезис: «пар» — вводящее в заблуждение слово. Это аэрозоль из микрокапель; капли достигают альвеол, где газообмен. Нет горения — не значит нет вреда: при нагреве пропиленгликоль и глицерин распадаются на формальдегид и акролеин (те же вещества, что в сигаретном дыму) — просто в меньших концентрациях. «Меньше» не значит «безопасно».",
        "Цифра на слайде: по данным школьных опросов (GYTS и близкие исследования по РБ), 8–12% подростков хотя бы раз пробовали вейп. Для 12–17-летних важно: большинство тех, кто «попробовал один раз», оказывается с привычкой на недели — никотиновая соль формирует зависимость быстрее табака.",
        "Миф «без запаха — значит безвредно» разберите по существу: запаха нет, потому что нет горения, а аэрозоль оседает на стенках альвеол и сосудах — «невидимый» вред не менее реальный."),
    3: ("Слайд 3 — Химия: вейпы vs табак",
        "Не перечисляйте все соединения — берите по одному яркому примеру на колонку. Табачный дым: смолы (канцерогены, оседают в лёгких); угарный газ CO (занимает место кислорода в гемоглобине — поэтому курильщики «задыхаются» на физкультуре); тяжёлые металлы (кадмий накапливается в почках и костях). Итого ВОЗ: 7000+ соединений, минимум 70 доказанных канцерогенов.",
        "Вейпы: диацетил — ароматизатор «масляного вкуса» — связан в исследованиях с облитерирующим бронхиолитом (болезнь «попкорновых фабрик» в США). Глицерин и ПГ при нагреве дают формальдегид (канцероген 1 группы IARC). Итог: в аэрозоле до 200–300 соединений — зависит от жидкости, мощности и «сухого» нагрева спирали.",
        "Важный вопрос из зала: «а ведь вейп безопаснее сигареты?». Ответ честный: доза канцерогенов ниже, но зависимость — та же и формируется быстрее у подростков; безвредной дозы никотина для растущего мозга не существует — важнейшие структуры мозга развиваются примерно до 25 лет."),
    4: ("Слайд 4 — Как лёгкие выглядят на рентгене",
        "Обязательно проговорите: это ПРИМЕРЫ снимков из медицинской практики (Wikimedia Commons), а не рентгены «типичного вейпера». Рентген показывает последствия патологии, а не диагноз «вейпинг».",
        "Как читать: слева — норма: равномерное просветление, тонкий сосудистый рисунок, чёткие контуры корней лёгких и сердца. Центр — воспаление (пример пневмонии): светлые пятна-инфильтраты — альвеолы заполнены жидкостью, свет не проходит сквозь них. Справа — ХОБЛ/эмфизема: избыточная прозрачность, «вытянутые» межрёберные промежутки, уплощённая диафрагма — альвеолы разрушены и не держат воздушный объём.",
        "Важная мысль: рентген — «поздний» инструмент. Первые сигналы появляются гораздо раньше: утренний кашель, снижение выносливости, одышка на лестнице. Если снимок уже показывает изменения — потеряна значительная часть функции.",
        "Не превращайте слайд в запугивание «так будет у каждого» — это мгновенно рушит доверие аудитории. Правильная формулировка: «каждая затяжка повышает вероятность, а организм подростка копит вред впрок»."),
    5: ("Слайд 5 — Болезни от вейпов (EVALI и другие)",
        "EVALI: e-cigarette or vaping product use-associated lung injury. Вспышка 2019–2020 в США: CDC зафиксировал около 2 800 госпитализаций и 68 смертей (на февраль 2020). Причина связана прежде всего с подпольными THC-картриджами с витамином Е ацетатом, но случаи были и от никотиновых жидкостей — покупая жидкость сомнительного происхождения, ты не знаешь её состав.",
        "Симптомы для самопроверки класса: внезапная одышка, боль в груди, кашель, лихорадка, желудочно-кишечные симптомы; лечение в стационаре, у части — искусственная вентиляция лёгких. От начала симптомов до госпитализации проходили дни.",
        "Более частый «тихий» вред: снижение физической выносливости (падение VO2max у подростков-вейперов заметно раньше), хронический кашель, ослабленный иммунитет и частые ОРВИ.",
        "Самая важная цифра: у подростков-вейперов вероятность начать курить сигареты в разы выше — «врата» в табак. Спросите анонимно поднятием рук: «кто замечал кашель по утрам?» — это оживляет и включает личное вовлечение."),
    6: ("Слайд 6 — Болезни от курения",
        "Фото справа — реальное сравнение (Wikimedia Commons): розовое здоровое лёгкое против почерневшего лёгкого курильщика — смолы оседают в ткани и не выводятся. Фотография сама по себе производит впечатление — не спешите, дайте классу её рассмотреть.",
        "Цифры: рак лёгких — смертельное заболевание №1 в мире среди раков, около 85% случаев связаны с курением. ХОБЛ — четвёртая причина смерти в мире по ВОЗ: непоправимая прогрессирующая болезнь. Эмфизема необратима: разрушенные альвеолы не восстанавливаются.",
        "Беларусь: курят ~27% взрослых (официальные опросы); около 35–40 тыс. смертей в год связаны с табаком (расчёт Минздрава) — для наглядности: это в разы больше, чем погибает в дорожных авариях. Пассивное курение часто недооценивают: спросите, кто дышит чьим дымом дома — тема часто зацепляет сильнее личной статистики."),
    7: ("Слайд 7 — Статистика: Беларусь",
        "Проговорите цифры спокойно — без морали и повышения тона: 8–12% подростков пробовали вейп; 27% взрослых курят; 35–40 тыс. смертей в год в РБ связаны с табаком. Подчеркните динамику: подростковый вейпинг «догоняет» табак — скорость внедрения новых продуктов выше, чем когда-либо у сигарет.",
        "Финансовый угол (важен для подростков): предложите посчитать самим — сколько стоит pod или жидкость в неделю, месяц, год. У каждого будет свой результат, но обычно выходит сумма школьных перекусов за месяц или спортсекция. Не превращайте в стыд — это математика, а не обвинение.",
        "Сделайте паузу: попросите назвать 1–2 причины, почему человек не бросает (зачастую — недооценка зависимости). Выведите на главный мотив слайда: каждая заправка и пачка — это деньги и здоровье из общего семейного кармана."),
    8: ("Слайд 8 — Что произойдёт, если отказаться",
        "Таймлайн CDC — прогоните по строкам: 20 минут — пульс и давление нормализуются; 12 часов — угарный газ в крови выходит, кислородный обмен восстанавливается; 3 месяца — функция лёгких растёт на 5–10%, уходят симптомы хронического бронхита; 1 год — риск коронарной болезни вдвое ниже; 5–15 лет — риск инсульта сравнивается с уровнем некурящего.",
        "Главный мотив для подростка: лёгкие физически формируются до ~25 лет. Отказ в 15–17 лет даёт максимум восстановимого. Но скажите и правду: часть структур (например, альвеолы, разрушенные эмфиземой) не восстанавливается — поэтому «не начинать» многократно лучше, чем «бросить потом».",
        "Сделайте практический шаг: предложите поставить напоминание в телефон, договориться с другом о парном отказе («мы оба перестаём и поддерживаем»), найти себе замену в стрессовых ситуациях (прогулка, спорт, вода). Маленькие доступные шаги работают лучше идеального решения «с понедельника»."),
    9: ("Слайд 9 — Выводы и призыв",
        "Резюмируйте пять пунктов сами, не перекладывая на класс: вейпы не безвредны (воспаление и изменение лёгких); курение — главная предотвратимая причина смертей в РБ; рентген видит изменения уже у молодых; в 12–17 лет лёгкие растут — вред накапливается «впрок»; лучшее решение — никогда не начинать.",
        "Разбор «попробовать один раз не бывает»: никотиновая соль формирует зависимость за 2–4 недели регулярного использования, а «один раз на вечеринке» обычно затягивается на месяцы. Это не случайность — это задуманная модель, и теперь вы её видите.",
        "Ресурсы: телефон доверия для детей и молодёжи 8 801 100 16 11 (бесплатно, анонимно); волонтёрские проекты при центрах гигиены и общественного здоровья; близкий взрослый или школьный психолог. Возможное домашнее задание (добровольное): посчитать вместе со знакомым курильщиком, сколько тот тратит в месяц.",
        "Источники на слайде: Минздрав РБ, ВОЗ, CDC, GYTS (школьные опросы). Завершите позитивно: «вы теперь знаете то, чего не знают многие взрослые, — и можете защитить и себя, и близких». Оставьте 3–5 минут на вопросы из зала."),
}


def build_notes_slide(idx, paras):
    heading = paras[0]
    body = paras[1:]
    heading_p = (f'<a:p><a:r>{rpr(1300, True, NAVY)}'
                 f'<a:t>{escape(heading)}</a:t></a:r></a:p>')
    body_ps = "".join(
        f'<a:p><a:r>{rpr(1200, False, DGRAY)}<a:t>{escape(p)}</a:t></a:r></a:p>'
        for p in body)
    return (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
        '<p:notes xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
        'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
        'xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
        '<p:cSld><p:spTree>'
        '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
        '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/>'
        '<a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
        '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Slide Image"/>'
        '<p:cNvSpPr><a:spLocks noGrp="1" noRot="1" noChangeAspect="1"/></p:cNvSpPr>'
        '<p:nvPr><p:ph type="sldImg"/></p:nvPr></p:nvSpPr>'
        '<p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/>'
        '<a:p><a:endParaRPr lang="ru-RU"/></a:p></p:txBody></p:sp>'
        '<p:sp><p:nvSpPr><p:cNvPr id="3" name="Notes"/>'
        '<p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr>'
        '<p:nvPr><p:ph type="body"/></p:nvPr></p:nvSpPr>'
        '<p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/>'
        + heading_p + body_ps + '</p:txBody></p:sp>'
        '</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:notes>'
    )


# ------------------------------------------------------------------ паке

def main():
    if not os.path.exists(TPL):
        print("template missing", TPL)
        sys.exit(1)
    if os.path.exists(TMP):
        shutil.rmtree(TMP)
    os.makedirs(TMP)
    with zipfile.ZipFile(TPL) as zf:
        zf.extractall(TMP)

    slides_dir = os.path.join(TMP, "ppt", "slides")
    rels_dir = os.path.join(slides_dir, "_rels")
    os.makedirs(rels_dir, exist_ok=True)

    # ---- собрать 9 слайдов ----
    specs = [slide1, slide2, slide3, slide4, slide5, slide6, slide7, slide8, slide9]
    slide_pics = {}       # slideN -> [(rid, target), ...]
    slide_texts = {}
    for i, spec in enumerate(specs, 1):
        pics = []
        xml_text = spec(pics)
        slide_pics[i] = pics
        slide_texts[i] = xml_text
    # ---- записать слайды и rels ----
    for i, xml_text in slide_texts.items():
        with open(os.path.join(slides_dir, f"slide{i}.xml"), "w", encoding="utf-8") as f:
            f.write(xml_text)
        rel_entries = [
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout7.xml"/>'
        ]
        for rid, target in slide_pics[i]:
            rel_entries.append(
                f'<Relationship Id="{rid}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../{target.split("/", 1)[1]}"/>'
            )
        with open(os.path.join(rels_dir, f"slide{i}.xml.rels"), "w", encoding="utf-8") as f:
            f.write('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
                    '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    + "".join(rel_entries) + '</Relationships>')

    # ---- media parts ----
    os.makedirs(os.path.join(TMP, "ppt", "media"), exist_ok=True)
    for target, payload in _media_cache.items():
        path = os.path.join(TMP, *target.split("/"))
        with open(path, "wb") as f:
            f.write(payload)
    media_parts = list(_media_cache.items())

    # ---- заметки лектора (notesSlides) ----
    notes_dir = os.path.join(TMP, "ppt", "notesSlides")
    notes_rels_dir = os.path.join(notes_dir, "_rels")
    os.makedirs(notes_rels_dir, exist_ok=True)
    for i, paras in NOTES.items():
        with open(os.path.join(notes_dir, f"notesSlide{i}.xml"), "w", encoding="utf-8") as f:
            f.write(build_notes_slide(i, paras))
        with open(os.path.join(notes_rels_dir, f"notesSlide{i}.xml.rels"), "w",
                  encoding="utf-8") as f:
            f.write(
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                f'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="../slides/slide{i}.xml"/>'
                '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesMaster" Target="../notesMasters/notesMaster1.xml"/>'
                '</Relationships>')
        # добавить в rels слайда связь с его заметкой
        slide_rels_path = os.path.join(rels_dir, f"slide{i}.xml.rels")
        with open(slide_rels_path, encoding="utf-8") as f:
            srel = f.read()
        srel = srel.replace(
            "</Relationships>",
            f'<Relationship Id="rId20" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesSlide" Target="../notesSlides/notesSlide{i}.xml"/></Relationships>')
        with open(slide_rels_path, "w", encoding="utf-8") as f:
            f.write(srel)

    # ---- notesMaster: копия эталонного шаблона python-pptx ----
    nm_dir = os.path.join(TMP, "ppt", "notesMasters")
    nm_rels_dir = os.path.join(nm_dir, "_rels")
    os.makedirs(nm_rels_dir, exist_ok=True)
    shutil.copy2(NM_TPL, os.path.join(nm_dir, "notesMaster1.xml"))
    with open(os.path.join(nm_rels_dir, "notesMaster1.xml.rels"), "w", encoding="utf-8") as f:
        f.write(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>'
            '</Relationships>')

    # ---- presentation.xml ----
    pres_path = os.path.join(TMP, "ppt", "presentation.xml")
    with open(pres_path, encoding="utf-8") as f:
        pres = f.read()
    ids = "".join(f'<p:sldId id="{255 + i}" r:id="rId10{i}"/>' for i in range(1, 10))
    lst = f"<p:sldIdLst>{ids}</p:sldIdLst>"
    if "<p:sldIdLst>" in pres:
        pres = re.sub(r"<p:sldIdLst>.*?</p:sldIdLst>", lst, pres, flags=re.DOTALL)
    elif re.search(r"<p:sldIdLst\s*/>", pres):
        pres = re.sub(r"<p:sldIdLst\s*/>", lst, pres, count=1)
    else:
        pres = pres.replace("</p:sldMasterIdLst>", "</p:sldMasterIdLst>" + lst)
    # 16:9 föry size
    pres = re.sub(r'<p:sldSz[^/]*/>', '<p:sldSz cx="12192000" cy="6858000" type="screen16x9"/>',
                  pres, count=1)
    # удалить опциональный reference на notesMaster, если вдруг попал в шаблон
    # (notesMasterIdLst вставляется ниже, удалять ссылку больше не нужно)
    # Примечание: notesMasterIdLst в presentation.xml НЕ пишем — PowerPoint
    # на этом окружении отвергает файл при его наличии (у python-pptx его тоже нет;
    # связь notesMaster через presentation.xml.rels работает корректно без него).
    with open(pres_path, "w", encoding="utf-8") as f:
        f.write(pres)

    # ---- presentation.xml.rels: слайды + notesMaster ----
    rels_path = os.path.join(TMP, "ppt", "_rels", "presentation.xml.rels")
    with open(rels_path, encoding="utf-8") as f:
        rels = f.read()
    adds = "".join(
        f'<Relationship Id="rId10{i}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide{i}.xml"/>'
        for i in range(1, 10)
    )
    adds += ('<Relationship Id="rId30" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesMaster" Target="notesMasters/notesMaster1.xml"/>')
    rels = rels.replace("</Relationships>", adds + "</Relationships>")
    with open(rels_path, "w", encoding="utf-8") as f:
        f.write(rels)

    # ---- [Content_Types].xml ----
    ct_path = os.path.join(TMP, "[Content_Types].xml")
    with open(ct_path, encoding="utf-8") as f:
        ct = f.read()
    ct = re.sub(r'<Override PartName="/ppt/slides/slide\d+\.xml"[^>]*/>', "", ct)
    adds = "".join(
        f'<Override PartName="/ppt/slides/slide{i}.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>'
        for i in range(1, 10)
    )
    adds += '<Override PartName="/ppt/notesMasters/notesMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.notesMaster+xml"/>'
    adds += "".join(
        f'<Override PartName="/ppt/notesSlides/notesSlide{i}.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.notesSlide+xml"/>'
        for i in range(1, 10)
    )
    ct = ct.replace("</Types>", adds + "</Types>")
    # png default при необходимости (jpeg-Default уже есть у thumbnail)
    if any(t.endswith(".png") for t, _ in media_parts):
        ct = ct.replace("</Types>", '<Default Extension="png" ContentType="image/png"/></Types>')
    with open(ct_path, "w", encoding="utf-8") as f:
        f.write(ct)

    # ---- zip ----
    if os.path.exists(OUT):
        os.remove(OUT)
    with zipfile.ZipFile(OUT, "w", zipfile.ZIP_DEFLATED) as zf:
        for root, _dirs, files in os.walk(TMP):
            for name in files:
                full = os.path.join(root, name)
                arc = os.path.relpath(full, TMP).replace("\\", "/")
                zf.write(full, arc)

    # ---- проверка ----
    with zipfile.ZipFile(OUT) as zf:
        if zf.testzip():
            print("corrupt member")
            sys.exit(1)
        names = set(zf.namelist())
        for name in names:
            if name.endswith(".xml") or name.endswith(".rels"):
                xml.dom.minidom.parseString(zf.read(name))
        for target in _media_cache:
            if target not in names:
                print("media missing", target)
                sys.exit(1)
    print(f"OK built: {OUT} ({os.path.getsize(OUT)} bytes)")

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