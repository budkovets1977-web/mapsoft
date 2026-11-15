@echo off
cd F:\ML\minskline.by
python -c "
from pptx import Presentation
from pptx.util import Inches, Pt
from pptx.enum.text import PP_ALIGN
from pptx.dml.color import RGBColor

prs = Presentation()
prs.slide_width = Inches(13.333)
prs.slide_height = Inches(7.5)

DARK_NAVY = RGBColor(30, 39, 97)
ACCENT_RED = RGBColor(249, 97, 103)
ACCENT_BLUE = RGBColor(47, 60, 126)
WHITE = RGBColor(255, 255, 255)
LIGHT_GRAY = RGBColor(211, 211, 211)
DARK_GRAY = RGBColor(51, 51, 51)
GREEN = RGBColor(76, 175, 80)

slide1 = prs.slides.add_slide(prs.slide_layouts[6])
background = slide1.background
fill = background.fill
fill.solid()
fill.fore_color.rgb = WHITE

txBox = slide1.shapes.add_textbox(Inches(1), Inches(1.5), Inches(11), Inches(1.5))
tf = txBox.text_frame
p = tf.paragraphs[0]
p.text = 'Test slide'
p.font.size = Pt(20)
p.font.color.rgb = DARK_NAVY

prs.save('vaping_harm_presentation.pptx')
print('SUCCESS: Presentation created')
"