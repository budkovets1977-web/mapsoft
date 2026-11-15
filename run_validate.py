import subprocess, sys
proc = subprocess.run([sys.executable, r"C:\Users\Admin\.claude\skills\pptx\scripts\office\validate.py",
                       r"F:\ML\minskline.by\vaping_harm_presentation_belarus.pptx"],
                      capture_output=True, text=True, timeout=110)
print("returncode:", proc.returncode)
outh = proc.stdout or ""
errh = proc.stderr or ""
OUT_ENC = "cp866" if sys.platform == "win32" else "utf-8"
print("STDOUT:", outh[:4000])
print("STDERR:", errh[:4000])