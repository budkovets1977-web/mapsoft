"""
Presentation: Harm of vapes and tobacco
Creates a 9-slide PowerPoint presentation about the dangers of vaping and smoking.
"""

from pptx import Presentation
from pptx.util import Inches, Pt
from pptx.enum.text import PP_ALIGN
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE

# ========== Color Palette ==========
DARK_NAVY = RGBColor(30, 39, 97)    # 1E2761 - primary dark blue
ACCENT_RED = RGBColor(249, 97, 103)  # F96167 - red for harm/warnings
ACCENT_BLUE = RGBColor(47, 60, 126)   # 2F3C7E - blue for tobacco
WHITE = RGBColor(255, 255, 255)
LIGHT_GRAY = RGBColor(211, 211, 211)
DARK_GRAY = RGBColor(51, 51, 51)
GREEN = RGBColor(76, 175, 80)
SOFT_GRAY = RGBColor(153, 153, 153)

def add_textbox(slide, left, top, width, height, text, font_size=18,
                bold=False, color=DARK_GRAY, alignment=PP_ALIGN.LEFT, font='Calibri'):
    """Add a text box with specified properties."""
    txBox = slide.shapes.add_textbox(Inches(left), Inches(top), Inches(width), Inches(height))
    tf = txBox.text_frame
    tf.word_wrap = True
    p = tf.paragraphs[0]
    p.text = text
    p.font.size = Pt(font_size)
    p.font.bold = bold
    p.font.color.rgb = color
    p.font.name = font
    p.alignment = alignment
    return txBox

def add_shape(slide, shape_type, left, top, width, height,
              fill_color=None, line_color=None):
    """Add a shape with optional fill and line color."""
    if shape_type == 'RECTANGLE':
        shp = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE,
                                      Inches(left), Inches(top),
                                      Inches(width), Inches(height))
    elif shape_type == 'CIRCLE':
        # OVAL creates an approximate circle
        shp = slide.shapes.add_shape(MSO_SHAPE.OVAL,
                                      Inches(left), Inches(top),
                                      Inches(width), Inches(height))
    
    if fill_color:
        shp.fill.solid()
        shp.fill.fore_color.rgb = fill_color
    else:
        shp.fill.background()
    
    if line_color:
        shp.line.color.rgb = line_color
    else:
        shp.line.fill.background()
    return shp

def create_presentation():
    """Create the full presentation about vaping and tobacco harm."""
    prs = Presentation()
    prs.slide_width = Inches(13.333)
    prs.slide_height = Inches(7.5)
    
    # ===== Slide 1: Title =====
    slide1 = prs.slides.add_slide(prs.slide_layouts[6])  # Blank layout
    background = slide1.background
    fill = background.fill
    fill.solid()
    fill.fore_color.rgb = WHITE
    
    add_textbox(slide1, 1, 1.5, 11, 1.5, "Вред вейпов и табака:",
                36, True, DARK_NAVY, PP_ALIGN.LEFT, 'Arial')
    add_textbox(slide1, 1, 3.5, 11, 1.5, "Угроза здоровью",
                36, True, DARK_NAVY, PP_ALIGN.LEFT, 'Arial')
    add_textbox(slide1, 1, 5.5, 11, 0.6, "Для пропаганды здорового образа жизни",
                16, False, DARK_GRAY, PP_ALIGN.LEFT, 'Calibri')
    
    # Bottom ribbon
    add_shape(slide1, 'RECTANGLE', 0, 6.8, 13.333, 0.8, fill_color=DARK_NAVY)
    
    # ===== Slide 2: What are vapes? =====
    slide2 = prs.slides.add_slide(prs.slide_layouts[6])
    add_textbox(slide2, 0.5, 0.3, 12.3, 0.6, "Что такое вейпы?",
                28, True, DARK_NAVY, PP_ALIGN.LEFT, 'Arial')
    
    vape_points = [
        "• Электронные сигареты (e-cigarettes)",
        "• Возобновляемое вдыхание аэрозоля",
        "• Основные компоненты: никотин, ароматизаторы, пропиленгликоль, вегетариновое масло",
        "• При нагреве выделяются токсичные вещества: формальдегид, акролеин"
    ]
    y = 1.5
    for point in vape_points:
        add_textbox(slide2, 0.5, y, 12.3, 0.5, point, 14, False, DARK_GRAY,
                    PP_ALIGN.LEFT, 'Calibri')
        y += 0.45
    
    # Red circle placeholder
    add_shape(slide2, 'CIRCLE', 0.5, 5.5, 0.4, 0.4, fill_color=ACCENT_RED)
    
    # ===== Slide 3: Chemicals comparison =====
    slide3 = prs.slides.add_slide(prs.slide_layouts[6])
    add_textbox(slide3, 0.5, 0.3, 12.3, 0.6, "Химические вещества: вейпы vs табак",
                28, True, DARK_NAVY, PP_ALIGN.LEFT, 'Arial')
    
    add_textbox(slide3, 0.5, 1.2, 5, 0.4, "Вейпы", 16, True, ACCENT_RED,
                PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide3, 6.5, 1.2, 5.8, 0.4, "Табачный дым", 16, True, ACCENT_BLUE,
                PP_ALIGN.LEFT, 'Calibri')
    
    vape_chem = [
        "• Никотин",
        "• Пропиленгликоль",
        "• Ароматизаторы (диацетил и др.)",
        "• Формальдегид, ацетальдегид, акролеин",
        "• Всего: ~200–300 компонентов после нагрева"
    ]
    tobacco_chem = [
        "• 7000+ химических веществ",
        "• Минимум 70 известных канцерогенов",
        "• Таран, печенье, оксид углерода",
        "• Канцерогены: минимум 70 известных веществ"
    ]
    
    y = 1.8
    for v, t in zip(vape_chem, tobacco_chem):
        add_textbox(slide3, 0.5, y, 5, 0.35, v, 12, False, DARK_GRAY,
                    PP_ALIGN.LEFT, 'Calibri')
        add_textbox(slide3, 6.5, y, 5.8, 0.35, t, 12, False, DARK_GRAY,
                    PP_ALIGN.LEFT, 'Calibri')
        y += 0.32
    
    # Comparison circles
    add_shape(slide3, 'CIRCLE', 2, 5.5, 0.4, 0.4, fill_color=ACCENT_RED)   # vape
    add_shape(slide3, 'CIRCLE', 2, 5.8, 0.4, 0.4, fill_color=ACCENT_BLUE)   # tobacco
    
    # ===== Slide 4: X-ray comparison =====
    slide4 = prs.slides.add_slide(prs.slide_layouts[6])
    add_textbox(slide4, 0.5, 0.3, 12.3, 0.6, "Рентгенография легких",
                28, True, DARK_NAVY, PP_ALIGN.LEFT, 'Arial')
    
    # Healthy lungs
    add_shape(slide4, 'RECTANGLE', 0.5, 1.3, 3.8, 2.5, fill_color=WHITE, line_color=LIGHT_GRAY)
    add_textbox(slide4, 0.7, 1.4, 3.4, 0.3, "Здоровье", 14, True, DARK_NAVY,
                PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide4, 0.7, 1.8, 3.4, 0.3, "чистые лёгкие", 12, False, DARK_GRAY,
                PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide4, 0.7, 2.2, 3.4, 0.5, "• Инфильтраты нет\n• Четкие контуры\n• Единообразие", 11, False, DARK_GRAY,
                PP_ALIGN.LEFT, 'Calibri')
    
    # Vaper lungs
    add_shape(slide4, 'RECTANGLE', 4.8, 1.3, 3.8, 2.5, fill_color=WHITE, line_color=LIGHT_GRAY)
    add_textbox(slide4, 5.0, 1.4, 3.4, 0.3, "Вейпер", 14, True, ACCENT_RED,
                PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide4, 5.0, 1.8, 3.4, 0.3, "легкие от вейпинга", 12, False, DARK_GRAY,
                PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide4, 5.0, 2.2, 3.4, 0.5, "• Тёмные пятна\n• Воспаление\n• Разрушение ткани", 11, False, DARK_GRAY,
                PP_ALIGN.LEFT, 'Calibri')
    
    # Smoker lungs
    add_shape(slide4, 'RECTANGLE', 9.1, 1.3, 3.8, 2.5, fill_color=WHITE, line_color=LIGHT_GRAY)
    add_textbox(slide4, 9.3, 1.4, 3.4, 0.3, "Курильщик", 14, True, ACCENT_BLUE,
                PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide4, 9.3, 1.8, 3.4, 0.3, "легкие от курения", 12, False, DARK_GRAY,
                PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide4, 9.3, 2.2, 3.4, 0.5, "• Хронический острит\n• Эмфизема\n• Разрушение альвеол", 11, False, DARK_GRAY,
                PP_ALIGN.LEFT, 'Calibri')
    
    add_textbox(slide4, 0.5, 4.2, 12.3, 0.4, "• Здоровье: чистые лёгкие", 13, False, DARK_GRAY,
                PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide4, 0.5, 4.6, 12.3, 0.4, "• Вейперы: тёмные пятна, воспаление", 13, False, ACCENT_RED,
                PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide4, 0.5, 5.0, 12.3, 0.4, "• Курильщики: хронический острит, разрушение", 13, False, ACCENT_BLUE,
                PP_ALIGN.LEFT, 'Calibri')
    
    # ===== Slide 5: Diseases from vaping =====
    slide5 = prs.slides.add_slide(prs.slide_layouts[6])
    add_textbox(slide5, 0.5, 0.3, 12.3, 0.6, "Болезни органов дыхания от вейпов",
                28, True, DARK_NAVY, PP_ALIGN.LEFT, 'Arial')
    
    add_textbox(slide5, 0.5, 1.2, 6, 0.4, "EVALI (E-cigarette or Vaping Product Use-associated Lung Injury)",
                14, True, ACCENT_RED, PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide5, 0.5, 1.6, 6, 0.35, "Симптомы: внезапная одышка, боль в груди, лихорадка",
                12, False, DARK_GRAY, PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide5, 0.5, 2.0, 6, 0.35, "Быстрое прогрессирование, возможен respiratory failure",
                12, False, DARK_GRAY, PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide5, 0.5, 2.4, 6, 0.35, "Основная больная группа: молодежь и молодые взрослые",
                12, False, DARK_GRAY, PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide5, 0.5, 2.8, 6, 0.35, "Статистика США (2020): ~2800 hospitalizations, 68 смертей",
                12, False, DARK_GRAY, PP_ALIGN.LEFT, 'Calibri')
    
    # Warning circle
    add_shape(slide5, 'CIRCLE', 0.5, 4.5, 0.4, 0.4, fill_color=ACCENT_RED)
    
    add_textbox(slide5, 3.5, 1.2, 6, 0.4, "Дополнительные риски от вейпинга:",
                14, True, DARK_NAVY, PP_ALIGN.LEFT, 'Calibri')
    risks = [
        "• Хронический бронхит и постоянный кашель",
        "• Снижение функции легких",
        "• Возможность легочной фиброзы",
        "• Инфекции дыхательных путей (усилен риск)"
    ]
    y = 1.7
    for risk in risks:
        add_textbox(slide5, 3.5, y, 6, 0.3, risk, 12, False, DARK_GRAY,
                    PP_ALIGN.LEFT, 'Calibri')
        y += 0.28
    
    # ===== Slide 6: Diseases from tobacco =====
    slide6 = prs.slides.add_slide(prs.slide_layouts[6])
    add_textbox(slide6, 0.5, 0.3, 12.3, 0.6, "Болезни органов дыхания от курения",
                28, True, DARK_NAVY, PP_ALIGN.LEFT, 'Arial')
    
    diseases = [
        "• Рак легких — причина №1 смертности от рака по миру (85% случаев)",
        "• Хроническая обструктивная порокодыхающая болезнь (ХОБП) — неизлечимое заболевание",
        "• Хронический бронхит — длительный кашель с мокротой",
        "• Эмфизема — разрушение альвеол, нарушение газообмена",
        "• Туберкулез и пневмония — повышенный риск из-за ослабленного иммунитета",
        "• Статистика WHO: ~8 миллионов смертей в год от табачного курения"
    ]
    y = 1.3
    for disease in diseases:
        add_textbox(slide6, 0.5, y, 12.3, 0.35, disease, 13, False, DARK_GRAY,
                    PP_ALIGN.LEFT, 'Calibri')
        y += 0.32
    
    add_shape(slide6, 'CIRCLE', 0.5, 5.5, 0.4, 0.4, fill_color=ACCENT_BLUE)
    
    # ===== Slide 7: Comparative statistics =====
    slide7 = prs.slides.add_slide(prs.slide_layouts[6])
    add_textbox(slide7, 0.5, 0.3, 12.3, 0.6, "Сравнительная статистика",
                28, True, DARK_NAVY, PP_ALIGN.LEFT, 'Arial')
    
    # Vape bar (red)
    add_shape(slide7, 'RECTANGLE', 0.5, 1.5, 5.5, 0.5, fill_color=ACCENT_RED)
    add_textbox(slide7, 0.7, 1.52, 5, 0.45, "Вейпы", 16, True, WHITE,
                PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide7, 0.7, 2.0, 5, 0.3, "~2800 hospitalizations (USA, 2020)", 11, False, WHITE,
                PP_ALIGN.LEFT, 'Calibri')
    
    # Tobacco bar (navy)
    add_shape(slide7, 'RECTANGLE', 0.5, 2.3, 5.5, 0.5, fill_color=ACCENT_BLUE)
    add_textbox(slide7, 0.7, 2.32, 5, 0.45, "Курение", 16, True, WHITE,
                PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide7, 0.7, 2.8, 5, 0.3, "~8 млн смертей в год (WHO)", 11, False, WHITE,
                PP_ALIGN.LEFT, 'Calibri')
    
    add_textbox(slide7, 0.5, 3.5, 12.3, 0.4, "Вейпы: меньше канцерогенов, но риск EVALI и неизвестные долгосрочные эффекты",
                13, False, ACCENT_RED, PP_ALIGN.LEFT, 'Calibri')
    add_textbox(slide7, 0.5, 3.9, 12.3, 0.4, "Курение: 7000+ химикалов, 70+ канцерогенов, высокая зависимость",
                13, False, ACCENT_BLUE, PP_ALIGN.LEFT, 'Calibri')
    
    # ===== Slide 8: Benefits of quitting =====
    slide8 = prs.slides.add_slide(prs.slide_layouts[6])
    add_textbox(slide8, 0.5, 0.3, 12.3, 0.6, "Польза отказа от курения и вейпинга",
                28, True, DARK_NAVY, PP_ALIGN.LEFT, 'Arial')
    
    benefits = [
        "• Через 20 минут: давление возвращается в норму",
        "• Через 12 часов: уровень углерода в крови падает",
        "• Через 3 месяца: функция легких улучшается на 5–10%",
        "• Через 1 год: риск сердечно-сосудистых болезней уменьшается в 2 раза",
        "• Через 5–15 лет: риск рака легких снижается до уровня некурящих"
    ]
    y = 1.5
    for benefit in benefits:
        add_textbox(slide8, 0.5, y, 12.3, 0.35, benefit, 14, False, DARK_GRAY,
                    PP_ALIGN.LEFT, 'Calibri')
        y += 0.35
    
    add_shape(slide8, 'CIRCLE', 0.5, 5.2, 0.4, 0.4, fill_color=GREEN)
    
    # ===== Slide 9: Conclusions =====
    slide9 = prs.slides.add_slide(prs.slide_layouts[6])
    add_textbox(slide9, 0.5, 0.3, 12.3, 0.6, "Выводы и призыв",
                28, True, DARK_NAVY, PP_ALIGN.LEFT, 'Arial')
    
    conclusions = [
        "• Вейпы не безвредны: содержат токсичные вещества, вызывают炎症 и повреждение легких",
        "• Курение — главная причина предотвратимых смертей по миру",
        "• Рентген показывает явные изменения у пользователей вейпов и сигарет",
        "• Отказ от никотина — лучшее решение для здоровья",
        "• Забота о здоровье — основа здорового образа жизни"
    ]
    y = 1.3
    for conclusion in conclusions:
        add_textbox(slide9, 0.5, y, 12.3, 0.35, conclusion, 14, False, DARK_GRAY,
                    PP_ALIGN.LEFT, 'Calibri')
        y += 0.33
    
    add_textbox(slide9, 0.5, 4.5, 12.3, 0.3, "Источники: WHO, CDC, медицинские исследования 2020–2024",
                11, False, SOFT_GRAY, PP_ALIGN.LEFT, 'Calibri')
    
    # Footer bar
    add_shape(slide9, 'RECTANGLE', 0, 7.0, 13.333, 0.5, fill_color=DARK_NAVY)
    add_textbox(slide9, 0.5, 7.05, 12.3, 0.4, "Выбирай здоровье! — брось курить и вейпить",
                14, True, WHITE, PP_ALIGN.LEFT, 'Calibri')
    
    # Save presentation
    output_path = 'vaping_harm_presentation.pptx'
    prs.save(output_path)
    print(f"Презентация успешно создана: {output_path}")
    return output_path

if __name__ == "__main__":
    create_presentation()