import zipfile
import xml.etree.ElementTree as ET
import sys

path = sys.argv[1] if len(sys.argv) > 1 else r"c:\Users\CNCM\Desktop\HAY Tovar SYSTEM v3.0 FINAL.xlsm"
NS = {"m": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}


def col_letter(cell_ref: str) -> str:
    return "".join(c for c in cell_ref if c.isalpha())


def parse_sheet(z, name: str, ss: list) -> list:
    root = ET.fromstring(z.read(name))
    rows = []
    for row in root.findall(".//m:sheetData/m:row", NS):
        r_idx = row.get("r")
        cells = {}
        for c in row.findall("m:c", NS):
            ref = c.get("r", "")
            v = c.find("m:v", NS)
            is_elem = c.find("m:is", NS)
            val = ""
            if v is not None and v.text:
                val = v.text
            elif is_elem is not None:
                tnode = is_elem.find(".//m:t", NS)
                if tnode is not None and tnode.text:
                    val = tnode.text
            if c.get("t") == "s" and val.isdigit() and ss:
                val = ss[int(val)]
            cells[col_letter(ref)] = val
        if cells:
            rows.append((r_idx, cells))
    return rows


with zipfile.ZipFile(path) as z:
    wb = ET.fromstring(z.read("xl/workbook.xml"))
    for s in wb.findall(".//m:sheet", NS):
        print("SHEET:", s.get("name"))

    ss = []
    if "xl/sharedStrings.xml" in z.namelist():
        sroot = ET.fromstring(z.read("xl/sharedStrings.xml"))
        for si in sroot.findall(".//m:si", NS):
            parts = []
            for t in si.findall(".//m:t", NS):
                if t.text:
                    parts.append(t.text)
            ss.append("".join(parts))
        print("strings:", len(ss))
        for i, t in enumerate(ss):
            print(f"  [{i}] {t}")

    for i in range(1, 10):
        sheet = f"xl/worksheets/sheet{i}.xml"
        if sheet not in z.namelist():
            continue
        print(f"\n=== sheet {i} ===")
        rows = parse_sheet(z, sheet, ss)
        for r_idx, cells in rows:
            cols = sorted(cells.keys(), key=lambda x: (len(x), x))
            line = " | ".join(f"{c}={cells[c][:120]}" for c in cols if str(cells[c]).strip())
            if line:
                print("R" + str(r_idx) + ":", line)
