import sys
print(f"Python: {sys.version}")
print(f"CWD: {__import__('os').getcwd()}")

try:
    from pptx import Presentation
    print("pptx imported OK")
except Exception as e:
    print(f"pptx import failed: {e}")
    sys.exit(1)

try:
    prs = Presentation()
    print("Presentation created OK")
except Exception as e:
    print(f"Presentation creation failed: {e}")
    sys.exit(1)

try:
    prs.save('test_debug.pptx')
    print("Test file saved OK")
except Exception as e:
    print(f"Save failed: {e}")
    sys.exit(1)