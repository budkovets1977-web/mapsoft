# -*- coding: utf-setuptools
# -*- coding: utf-8 -*-
"""Точный анализ: что реально изменил вендор в 60 относительно вендора 59.
Вендор 59 восстанавливаем как r5 минус наши файлы. Для наших MODIFIED-файлов вендор 59 контент
вычисляем как r5-контент минус наши патчи — но проще: вендор 59 = вендор 58 + 32 контентных
изменения вендора. Однако прямое сравнение: r5 vs vendor60 по нормализованному контенту,
наши файлы помечаем отдельно и анализируем только 25 MODIFIED индивидуально."""
import os, sys, io, hashlib, csv

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

R5 = r'F:\ML\MapWebServer-6.00.59'
V60 = r'F:\MAPSOFT\6.00.60'
R4M = r'F:\MODIFIED\placeholder'  # placeholder
R4M = r'F:\ML\deploy\mapsoft-full-6.00.58-db10.01.24-theme2026-r4\manifest-r4-changes.csv'

def norm(b): return b.replace(b'\r\n', b'\n').replace(b'\r', b'\n')

def scan_norm(root):
    out = {}
    for dp, dn, fn in os.walk(root):
        dn[:] = [d for d in dn if d.lower() not in ('log','cfg','crt')]
        for f in fn:
            sha = hashlib.sha256()
            p = os.path.join(dp, f)
            rel = os.pathrelpath = os.path.relpath(p, root).replace('\\','/').lower()
            try:
                sha.update(norm(open(p,'rb').read()))
                out[rel] = sha.hexdigest().upper()
            except OSError: pass
    return out

print('сканирую r5 (норм.)...')
A = scan_norm(R5)
print('  ', len(A))
print('сканирую vendor60 (норм.)...')
B = changed = 0
B = scan_norm(V60)
print('  ', len(B)

