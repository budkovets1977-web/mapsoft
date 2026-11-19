# -*- coding: utf-8 -*-
"""Structural QA v2 (stdlib only): rels graph, media, 16:9, docProps untouched."""
import re
import zipfile
import xml.dom.minidom as MD

P = r"C:\Users\Admin\Desktop\vaping_harm_presentation_belarus_v2.pptx"
TPL = r"C:\Users\Admin\AppData\Local\Programs\Python\Python315\Lib\site-packages\pptx\templates\default.pptx"

zf = zipfile.ZipFile(P)
names = zf.namelist()
ok = True


def chk(cond, label):
    global ok
    print(("PASS" if cond else "FAIL"), "-", label)
    if not cond:
        ok = False


# --- all XML/Rels well-formed ---
bad_xml = []
for n in names:
    if n.endswith(".xml") or n.endswith(".rels"):
        try:
            MD.parseString(zf.read(n))
        except Exception:
            bad_xml.append(n)
chk(not bad_xml, f"all XML parts well-formed {bad_xml[:3] or ''}")

slides = sorted([n for n in names if re.fullmatch(r"ppt/slides/slide\d+\.xml", n)],
                key=lambda s: int(re.search(r"(\d+)", s).group(1)))
chk(len(slides) == 9, "9 slides present")

pres = zf.read("ppt/presentation.xml").decode("utf-8")
chk('cx="12192000" cy="6858000"' in pres and 'type="screen16x9"' in pres, "slide size 16:9")
chk("notesMasterIdLst" not in pres, "no dangling notesMaster ref")
ids = re.findall(r'<p:sldId id="(\d+)" r:id="(rId\d+)"/>', pres)
chk(len(ids) == 9, "9 sldId entries")

rels = zf.read("ppt/_rels/presentation.xml.rels").decode("utf-8")
rmap = dict(re.findall(r'Id="([^"]+)"[^>]*Target="([^"]+)"', rels))
chk(all(rid in rmap for _, rid in ids), "sldIds resolve")
chk(all(rmap.get(rid, "").startswith("slides/slide") for _, rid in ids), "all targets are slides")
chk(all(f"ppt/{rmap[rid]}" in names for _, rid in ids), "target parts exist")
chk('<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>' in rels.replace("\n", ""),
    "master rel intact")

# --- images ---
media = [n for n in names if n.startswith("ppt/media/")]
chk(len(media) == 5, f"5 media parts embedded ({len(media)})")
ct = zf.read("[Content_Types].xml").decode("utf-8")
chk('Extension="jpeg"' in ct, "jpeg Default present")
chk(any(n.endswith(".png") for n in media) == ('Extension="png"' in ct), "png Default iff png media used")
img_rels_ok = True
missing_media = []
for s in slides:
    n = s.split("/")[-1]
    relp = f"ppt/slides/_rels/{n}.rels"
    data = zf.read(relp).decode("utf-8")
    m = re.search(r'Target="\.\./slideLayouts/(slideLayout\d+\.xml)"', data)
    if not m or f"ppt/slideLayouts/{m.group(1)}" not in names:
        img_rels_ok = False
    for rid, tgt in re.findall(r'Id="(rId\d+)"[^>]*Type="[^"]*/image"[^>]*Target="/?ppt/(media/[^"]+)"', data):
        rels_body = zf.read(s).decode("utf-8")
        if f'r:embed="{rid}"' not in rels_body:
            img_rels_ok = False
        if f"ppt/{tgt}" not in names:
            missing_media.append(tgt)
chk(img_rels_ok, "layout + image rels consistent")
chk(not missing_media, f"media targets exist {missing_media[:3] or ''}")

# docProps untouched vs template
tpl_zf = zipfile.ZipFile(TPL)
same = True
for dp in ["docProps/app.xml", "docProps/core.xml"]:
    if tpl_zf.read(dp) != zf.read(dp):
        same = False
chk(same, "docProps identical to template (repair-fix)")

# text probes
txt_all = "".join(zf.read(s).decode("utf-8") for s in slides)
for probe in ["Выбирай здоровье", "EVALI", "7000+", "Wikimedia Commons", "35–40 тыс"]:
    chk(probe in txt_all, f"content probe: {probe}")

print()
print("RESULT:", "ALL OK" if ok else "PROBLEMS FOUND")
