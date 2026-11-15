import subprocess, sys
result = subprocess.run([sys.executable, '-c', 'import pptx; print("OK")'], capture_output=True, text=True, timeout=30)
print('STDOUT:', result.stdout)
print('STDERR:', result.stderr)
print('Return code:', result.returncode)