#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Genera sql/ingles_temario_seed.sql desde TEMARIO COMPLETO .xlsx"""

import re
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path

XLSX = Path(r'c:\Users\CNCM\Downloads\TEMARIO COMPLETO 2025 (2).xlsx')
OUT = Path(__file__).resolve().parent.parent / 'sql' / 'ingles_temario_seed.sql'

NS = {'m': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
HABILIDADES = ('listening', 'reading', 'writing', 'speaking', 'grammar', 'vocabulary')
EXT_NIVELES = {'B2', 'B2+', 'C1', 'C1+'}


def esc(s: str) -> str:
    if s is None:
        return 'NULL'
    s = str(s).replace('\\', '\\\\').replace("'", "''")
    return "'" + s + "'"


def read_shared_strings(z):
    ss = []
    data = z.read('xl/sharedStrings.xml')
    root = ET.fromstring(data)
    for si in root.findall('.//m:si', NS):
        parts = []
        for t in si.findall('.//m:t', NS):
            if t.text:
                parts.append(t.text)
        ss.append(''.join(parts))
    return ss


def read_sheet_rows(z, sheet_path, ss):
    root = ET.fromstring(z.read(sheet_path))
    rows = []
    for row in root.findall('.//m:sheetData/m:row', NS):
        cells = {}
        for c in row.findall('m:c', NS):
            ref = c.get('r', 'A1')
            col = re.match(r'^([A-Z]+)', ref)
            col = col.group(1) if col else 'A'
            t = c.get('t')
            v = c.find('m:v', NS)
            if v is None or v.text is None:
                continue
            val = ss[int(v.text)] if t == 's' else v.text
            cells[col] = val.strip() if val else ''
        if cells:
            rows.append(cells)
    return rows


def parse_level_header(title):
    m = re.match(r'^Level\s+(.+?)\s+(\d+)\s*-\s*(\d+)\s*$', title.strip(), re.I)
    if not m:
        return None
    nivel = m.group(1).strip()
    if re.match(r'^A12\+?$', nivel, re.I):
        nivel = re.sub(r'A12', 'A2', nivel, flags=re.I)
    sem_ini = int(m.group(2))
    parcial = (sem_ini - 1) // 4 + 1
    if parcial < 1 or parcial > 4:
        return None
    return nivel, parcial, int(m.group(3))


def codigo_admin(nivel, parcial):
    if nivel == 'Proyecto final':
        return f'PF-{parcial}'
    if nivel.endswith('+'):
        return f'{nivel}{parcial}'
    return f'{nivel}-{parcial}'


def _apply_cell(current, c, d, vocab_all, grammar_all, skills):
    if c.startswith('Goal'):
        goal = re.sub(r'^Goal:\s*', '', d or '', flags=re.I).strip()
        if goal:
            current['objetivo'] = goal
    elif c.startswith('Vocabulary'):
        current['vocabulario'] = d
        if d:
            vocab_all.append(d)
    elif c.startswith('Grammar'):
        current['gramatica'] = d
        if d:
            grammar_all.append(d)
    elif c.startswith('Listening'):
        current['listening'] = d
        if d:
            skills['listening'].append(d)
    elif c.startswith('Reading'):
        current['reading'] = d
        if d:
            skills['reading'].append(d)
    elif c.startswith('Writing'):
        current['writing'] = d
        if d:
            skills['writing'].append(d)
    elif c.startswith('Speaking'):
        current['speaking'] = d
        if d:
            skills['speaking'].append(d)


def parse_regular_sheet(rows):
    title = rows[0].get('A', '')
    parsed = parse_level_header(title)
    if not parsed:
        return None
    nivel, parcial, sem_fin = parsed
    codigo = codigo_admin(nivel, parcial)

    weeks = []
    current = None
    vocab_all = []
    grammar_all = []
    skills = {h: [] for h in HABILIDADES}

    for r in rows[1:]:
        a = r.get('A', '')
        b = r.get('B', '')
        c = r.get('C', '')
        d = r.get('D', '')

        if re.match(r'^Week\s+\d+', a, re.I):
            if current:
                weeks.append(current)
            wn = int(re.search(r'\d+', a).group())
            current = {
                'semana': wn,
                'leccion': b,
                'objetivo': '',
                'vocabulario': '',
                'gramatica': '',
                'listening': '',
                'reading': '',
                'writing': '',
                'speaking': '',
                'notas': '',
                'es_examen': 0,
                'proyecto_tipo': None,
            }
            _apply_cell(current, c, d, vocab_all, grammar_all, skills)
            if 'exam' in (b + a).lower():
                current['es_examen'] = 1
            continue

        if current is None:
            if 'exam' in b.lower() or 'exam' in a.lower():
                continue
            continue

        _apply_cell(current, c, d, vocab_all, grammar_all, skills)
        if 'exam' in (b + a).lower():
            current['es_examen'] = 1

    if current:
        weeks.append(current)

    objetivo_parcial = ' | '.join(w['objetivo'] for w in weeks if w['objetivo'])[:4000]
    hab = {
        h: '\n'.join(skills[h])[:2000] if skills[h] else ''
        for h in HABILIDADES
    }
    hab['grammar'] = '\n'.join(grammar_all)[:2000] if grammar_all else hab['grammar']
    hab['vocabulary'] = '\n'.join(vocab_all)[:2000] if vocab_all else hab['vocabulary']

    return {
        'codigo': codigo,
        'nivel': nivel,
        'parcial': parcial,
        'es_proyecto_nivel': bool(re.search(r'Project\s+[A-D]', title, re.I) or (
            weeks and all(w.get('leccion', '').startswith('Project') for w in weeks)
        )),
        'objetivo_parcial': objetivo_parcial,
        'habilidades': hab,
        'vocabulario_resumen': '\n'.join(vocab_all)[:3000],
        'gramatica_resumen': '\n'.join(grammar_all)[:3000],
        'weeks': weeks,
    }


def parse_project_level_sheet(rows):
    title = rows[0].get('A', '')
    parsed = parse_level_header(title)
    if not parsed:
        return None
    nivel, parcial, _ = parsed
    codigo = codigo_admin(nivel, parcial)

    weeks = []
    for r in rows[1:]:
        a = r.get('A', '')
        b = r.get('B', '')
        if re.match(r'^week\s+\d+', a, re.I):
            wn = int(re.search(r'\d+', a).group())
            proj = b if b.startswith('Project') else None
            weeks.append({
                'semana': wn,
                'leccion': b,
                'objetivo': '',
                'vocabulario': '',
                'gramatica': '',
                'listening': '',
                'reading': '',
                'writing': '',
                'speaking': '',
                'notas': '',
                'es_examen': 0,
                'proyecto_tipo': proj,
            })

    note = ''
    for r in rows:
        if 'exam evaluates' in (r.get('A', '') + r.get('B', '')).lower():
            note = r.get('A') or r.get('B')

    return {
        'codigo': codigo,
        'nivel': nivel,
        'parcial': parcial,
        'es_proyecto_nivel': True,
        'objetivo_parcial': note or f'Proyecto de nivel {nivel} — semanas de repaso y presentación.',
        'habilidades': {h: '' for h in HABILIDADES},
        'vocabulario_resumen': '',
        'gramatica_resumen': '',
        'weeks': weeks,
    }


def main():
    if not XLSX.is_file():
        raise SystemExit(f'No encontrado: {XLSX}')

    z = zipfile.ZipFile(XLSX)
    ss = read_shared_strings(z)
    sheets = sorted(
        [n for n in z.namelist() if n.startswith('xl/worksheets/sheet') and n.endswith('.xml')],
        key=lambda x: int(re.search(r'sheet(\d+)', x).group(1)),
    )

    fases_ing = []
    fases_ext = []

    for sn in sheets:
        rows = read_sheet_rows(z, sn, ss)
        if not rows:
            continue
        title = rows[0].get('A', '')
        if not title.lower().startswith('level '):
            continue

        is_project = any(
            re.match(r'^week\s+\d+', r.get('A', ''), re.I) and r.get('B', '').startswith('Project')
            for r in rows[1:6]
        )

        data = parse_project_level_sheet(rows) if is_project else parse_regular_sheet(rows)
        if not data:
            continue

        if data['nivel'] in EXT_NIVELES:
            fases_ext.append(data)
        elif data['nivel'] not in ('Proyecto final',):
            fases_ing.append(data)

    # Proyecto final 8 semanas = PF-1 (4 sem) + PF-2 (4 sem) — plantilla vacía editable
    for pf, parcial in [('Proyecto final', 1), ('Proyecto final', 2)]:
        weeks = []
        for w in range(1, 5):
            weeks.append({
                'semana': w,
                'leccion': f'Proyecto final semana {w + (parcial - 1) * 4}',
                'objetivo': '',
                'vocabulario': '',
                'gramatica': '',
                'listening': '',
                'reading': '',
                'writing': '',
                'speaking': '',
                'notas': '',
                'es_examen': 1 if w == 4 else 0,
                'proyecto_tipo': 'investigacion|revista|publicidad',
            })
        fases_ing.append({
            'codigo': codigo_admin('Proyecto final', parcial),
            'nivel': 'Proyecto final',
            'parcial': parcial,
            'es_proyecto_nivel': True,
            'objetivo_parcial': 'Preparación y presentación del proyecto final de especialidad (8 semanas).',
            'habilidades': {h: 'listening, reading, writing, speaking, grammar, vocabulary' for h in HABILIDADES},
            'vocabulario_resumen': '',
            'gramatica_resumen': '',
            'weeks': weeks,
        })

    lines = [
        '-- Temario Inglés generado desde Excel',
        '-- Ejecutar DESPUÉS de tener especialidad ING / ING-EXT y fases con códigos A1-1, etc.',
        'SET NAMES utf8mb4;',
        '',
        'CREATE TABLE IF NOT EXISTS fase_temario_semana (',
        '  id_semana INT UNSIGNED NOT NULL AUTO_INCREMENT,',
        '  id_fase INT UNSIGNED NOT NULL,',
        '  semana TINYINT UNSIGNED NOT NULL COMMENT "1-4 dentro del parcial",',
        '  titulo_leccion VARCHAR(160) NULL,',
        '  objetivo TEXT NULL,',
        '  vocabulario TEXT NULL,',
        '  gramatica TEXT NULL,',
        '  listening TEXT NULL,',
        '  reading TEXT NULL,',
        '  writing TEXT NULL,',
        '  speaking TEXT NULL,',
        '  notas TEXT NULL,',
        '  es_examen TINYINT(1) NOT NULL DEFAULT 0,',
        '  proyecto_tipo VARCHAR(80) NULL,',
        '  PRIMARY KEY (id_semana),',
        '  UNIQUE KEY uq_fase_semana (id_fase, semana),',
        '  KEY idx_fts_fase (id_fase)',
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        '',
    ]

    def subq_fase(clave_esp, codigo):
        return (
            f"(SELECT ef.id_fase FROM especialidad_fases ef "
            f"INNER JOIN especialidades e ON e.id_especialidad = ef.id_especialidad "
            f"WHERE e.clave = {esc(clave_esp)} AND ef.clave_fase = {esc(codigo)} AND ef.activo = 1 LIMIT 1)"
        )

    def emit_updates(fases, clave_esp):
        for f in fases:
            cod = f['codigo']
            idf = subq_fase(clave_esp, cod)
            tipo = 'proyecto_nivel' if f['es_proyecto_nivel'] else 'regular'
            h = f['habilidades']
            lines.append(f'-- Fase {cod} ({clave_esp})')
            lines.append(
                f'UPDATE especialidad_fases SET '
                f'nivel_cefr = {esc(f["nivel"])}, num_parcial = {f["parcial"]}, '
                f'objetivo_parcial = {esc(f["objetivo_parcial"])}, '
                f'tipo_contenido = {esc(tipo)}, '
                f'eval_listening = {esc(h.get("listening") or None)}, '
                f'eval_reading = {esc(h.get("reading") or None)}, '
                f'eval_writing = {esc(h.get("writing") or None)}, '
                f'eval_speaking = {esc(h.get("speaking") or None)}, '
                f'eval_grammar = {esc(h.get("grammar") or None)}, '
                f'eval_vocabulary = {esc(h.get("vocabulary") or None)}, '
                f'vocabulario_resumen = {esc(f["vocabulario_resumen"] or None)}, '
                f'gramatica_resumen = {esc(f["gramatica_resumen"] or None)} '
                f'WHERE id_fase = {idf};'
            )
            for w in f['weeks']:
                lines.append(
                    'INSERT INTO fase_temario_semana (id_fase, semana, titulo_leccion, objetivo, '
                    'vocabulario, gramatica, listening, reading, writing, speaking, notas, es_examen, proyecto_tipo) '
                    f'VALUES ({idf}, {w["semana"]}, {esc(w.get("leccion") or None)}, {esc(w.get("objetivo") or None)}, '
                    f'{esc(w.get("vocabulario") or None)}, {esc(w.get("gramatica") or None)}, '
                    f'{esc(w.get("listening") or None)}, {esc(w.get("reading") or None)}, '
                    f'{esc(w.get("writing") or None)}, {esc(w.get("speaking") or None)}, '
                    f'{esc(w.get("notas") or None)}, {w["es_examen"]}, {esc(w.get("proyecto_tipo") or None)}) '
                    'ON DUPLICATE KEY UPDATE titulo_leccion=VALUES(titulo_leccion), objetivo=VALUES(objetivo), '
                    'vocabulario=VALUES(vocabulario), gramatica=VALUES(gramatica), '
                    'listening=VALUES(listening), reading=VALUES(reading), writing=VALUES(writing), '
                    'speaking=VALUES(speaking), notas=VALUES(notas), es_examen=VALUES(es_examen), '
                    'proyecto_tipo=VALUES(proyecto_tipo);'
                )
            lines.append('')

    lines.append('-- Columnas en especialidad_fases (si no existen, ejecutar nucleo_temario_schema.sql primero)')
    lines.append('')
    emit_updates(fases_ing, 'ING')
    emit_updates(fases_ext, 'ING-EXT')

    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text('\n'.join(lines), encoding='utf-8')
    print(f'OK: {OUT} ({len(fases_ing)} fases ING, {len(fases_ext)} fases EXT)')


if __name__ == '__main__':
    main()
