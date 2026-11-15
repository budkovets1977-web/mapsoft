import sys
print("Step 1: imports")
try:
    from pptx import Presentation
    print("Step 2: pptx imported")
except Exception as e:
    print(f"FAIL: {e}")
    sys.exit(1)

try:
    prs = Presentation()
    print("Step 3: Presentation created")
except Exception as e:
    print(f"FAIL: {e}")
    sys.exit(1)

try:
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    print("Step 4: Slide added")
except Exception as e:
    print(f"FAIL: {e}")
    sys.exit(1)

try:
    prs.save('test_debug.pptx')
    print("Step 5: File saved")
except Exception as e:
    print(f"FAIL: {e}")
    sys.exit(1)

print("SUCCESS")