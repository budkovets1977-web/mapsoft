# -*- coding: utf-8 -*-
"""Structural QA of the built pptx (stdlib only)."""
import re
import sys
import zipfile
import xml.dom.minidom as MD

P = r"C:\Users\Admin\Desktop\vaping_harm_presentation_belarus.pptx"

zf = zipfile.ZipFile(P)
names = zf.namelist()
ok = True


def chk(cond, label):
    global ok
    print(("PASS" if cond else "FAIL"), "-", label)
    if not cond:
        ok = False


# every xml parses
bad_xml = []
for n in names:
    if n.endswith(".xml") or n.endswith(".rels"):
        try:
            MD = zf.read(n)
            MD and __import__("xml.dom.minidom", fromlist=["x"]).parseString(MD)
        except Exception:
            bad_xml.append(n)
chk(not bad_xml, f"all {len([n for n in names if n.endswith(('.xml', '.rels'))])} xml parts well-formed")

slides = sorted(n for n in names if re.fullmatch(r"ppt/slides/slide\d+\.xml", n))
chk(len(slides) == 9, "9 slide parts present")

# presentation.xml vs rels
pres = zf.read("ppt/presentation.xml").decode("utf-8")
ids = re.findall(r'<p:sldId id="(\d+)" r:id="(rId\d+)"/>', pres)
chk(len(ids) == 9, f"sldIdLst has 9 entries (got {len(ids)})")

rels = zf.read("ppt/_rels/presentation.xml.rels").decode("utf-8")
rmap = dict(re.findall(r'Id="([^"]+)"[^>]*Target="([^"]+)"', rels))
missing = [i for _, rid in ids if rid not in rmap]
chk(not missing, f"sldId rIds resolve in rels {missing or ''}")
missfiles = [t for _, rid in ids for t in [rmap.get(rid, "?")]
             if not re.fullmatch(r"slides/slide\d+\.xml", t)]
chk(not missfiles, f"all targets point to slide parts {set(missfiles) or ''}")
missing_parts = [f"ppt/{rmap[rid]}" for _, rid in ids if f"ppt/{rmap[rid]}" not in names]
chk(not missing_parts, f"slide parts exist in zip {missing_parts or ''}")

# every slide rels -> layout exists
bad_layout = []
for s in slides:
    relp = f"ppt/slides/_rels/{s.split('/')[-1]}.rels"
    data = zf.read(relp).decode("utf-8")
    m = re.search(r'Target="\.\./slideLayouts/(slideLayout\d+\.xml)"', data)
    if not m or f"ppt/slideLayouts/{m.group(1)}" not in names:
        bad_layout.append(s)
chk(not bad_layout, "each slide's layout rel target exists")

# content types
ct = zf.read("[Content_Types].xml").decode("utf-8")
ov = set(re.findall(r'PartName="/ppt/slides/slide(\d+)\.xml"', ct))
chk(ov == {str(i) for i in range(1, 10)}, f"Content_Types overrides cover 9 slides {sorted(ov)}")

# master/theme relations intact in presentation rels
kinds = re.findall(r'Type="[^"]*relationships/(slideMaster1?|theme)"', rels)
chk("slideMaster1" in rels, "slideMaster relationship present")
chk("theme" in rels, "theme relationship present")

# stray other slots (app.xml/core.xml/content types for layouts/master)
chk(any(n.startswith("docProps/") for n in names), "docProps present")
chk("ppt/theme/theme1.xml" in names, "theme part present")
chk("ppt/slideMasters/slideMaster1.xml" in names, "slide master present")

# text smoke-tests
txt_all = "".join(zf.read(s).decode("utf-8") for s in slides)
for probe in ["Выбирай здоровье", "EVALI", "7000+", "Минздрава", "Рентген"]:
    chk(probe in txt_all if 'txt_all' in {} else probe in txt_all, f"content probe: {probe}")

print()
print("RESULT:", "ALL OK" if ok else "PROBLEMS FOUND")
