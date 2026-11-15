import sys
print("Step 1: imports")

try:
    import pptx
    print(f"pptx module found: {pptx.__file__}")
except Exception as e:
    print(f"FAIL importing pptx: {e}")

try:
    from pptx import Presentation
    print("Presentation imported OK")
except Exception as e:
    print(f"FAIL importing Presentation: {e}")
    import traceback
    traceback.print_exc()