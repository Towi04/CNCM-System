# Extractor CSV legado → HAY

Exporta grupos activos (y sus alumnos/pagos) desde la BD legado.

**En el servidor (recomendado):** si el dump legado ya está en MariaDB y
`LEGACY_DB_*` está en `config.local.php`, use el Portal → **Migración CSV** →
bloque *Descargar desde BD legado*: pegue las **claves** de grupo (como en el legado) y descargue
`03_grupos` / `04_alumnos` / `05_pagos` (también personal/aulas).

**Opcional local (XAMPP):** esta carpeta `tools/migracion_csv/` sigue sirviendo
si prefiere extraer en su PC. En el Portal también hay **CSV de ejemplo**
(columnas + 2–3 filas) por tipo.

## Requisitos

1. MySQL/MariaDB local con el dump legado restaurado.
2. PHP con extensión `pdo_mysql`.
3. Copiar `config.example.php` → `config.php` y ajustar credenciales.
4. Copiar `grupos_filtro.example.txt` → `grupos_filtro.txt` y listar **claves** de grupos activos.

## Uso

1. Abrir en el navegador: `http://localhost/.../tools/migracion_csv/`
2. Revisar el **diagnóstico** (cobertura de `grupos_dias`, `grupos_materias`, asistencias).
3. Descargar CSVs en orden:
   - `01_personal.csv`
   - `02_aulas.csv` (plantilla; usted pone códigos de aula)
   - `03_grupos_activos.csv` — completar `clave_especialidad`, `aula_codigo`, profesor si falta
   - `04_alumnos.csv`
   - `05_pagos.csv`
4. En HAY (plantel correcto): importar en el mismo orden → **Provisionar correos / Moodle**.

## Claves de especialidad HAY (`clave_especialidad`)

| Clave | Uso |
|-------|-----|
| `ING` | Inglés adulto regular |
| `ING-EXT` | Inglés extensivo |
| `ING-K` | Inglés infantil → crea pareja IK+CK automáticamente |
| `ING-K-EXT` | Extensivo inglés kids |
| `COMP` / `COMP24` / `COMP25` | Informática (plan vigente del plantel) |
| `COMP-EXT` | Extensivo informática |
| `COMP-K` | Solo computación infantil |
| `PREP-AB` | Preparatoria abierta |
| `PREP-ESC` | Preparatoria escolarizada |

Kids: bastan `ING-K` o `infantil=1` (una fila → pareja IK+CK con bloque 2h+2h el mismo día).
También puede **repetir** la misma `clave_legado` en 2 filas (1ª inglés, 2ª cómputo)
con distinta `hora_inicio` / `dias_clase` / `aula_codigo`; HAY asigna el mismo número a la pareja.
Alumnos de ese grupo se inscriben en **ambas** materias (dual); después puede dar de baja individual.

## Convención de días y hora

- `dias_clase`: `0`=domingo … `6`=sábado (PHP `date('w')`). Ejemplos: `6`, `2,4`.
- `hora_inicio`: `7:30`, `8:00`, `11:00`.
- El extractor prellena desde `grupos_dias` si hay datos; si no, infiere del texto `grupos.dias`/`horario`.

## Fecha de inicio y fase

- Por alumno: primera fila en `asistencias` cuyo día de semana esté en `dias_clase`.
- Por grupo: **moda** de esas fechas → `fecha_inicio_estimada`.
- `clave_fase_estimada`: índice aproximado (~4 semanas por parcial). **Revise y corrija** en HAY tras importar (fusiones/ubicación pueden desalinear).

## Columnas importantes por CSV

### 01_personal

`id_usuario_legado`, `nombres`, `apellido_*`, `rol` (HAY: `profesor`|`asesor`|…), `plantel_slug`, `username` (opcional).

### 02_aulas

`plantel_slug`, `codigo` (ej. `A3`), `nombre` opcional. Capacidad/proyector se editan después en HAY.

### 03_grupos

De BD + edición: `clave_especialidad` (obligatoria en adultos; en kids basta `ING-K` o 2 filas repetidas),
`aula_codigo`, `profesor_sugerido` / `profesor_username`, `dias_clase`, `hora_inicio`,
`fecha_inicio_estimada`, `clave_fase_estimada`.

En HAY (Vista previa) el profesor se elige en un **desplegable**; por defecto queda el nombre más
parecido al CSV (sin importar acentos/mayúsculas). Corrija ahí si el match es solo aproximado.

Pareja kids con horarios distintos: duplicar la fila de la misma `clave_legado`
(1ª = inglés, 2ª = cómputo) cambiando `hora_inicio`, `dias_clase` y/o `aula_codigo`.
El id interno de BD (`id_grupo_legado`) es opcional; el enlace operativo es la **clave**.

### 04_alumnos

`numero_control`, nombres, `clave_legado` del grupo ya importado,
`fecha_inicio`, `id_asesor_legado` / `asesor_nombre`.

**No se crean al importar grupos.** Pegue las **mismas claves** con las que importó
`03_grupos`, descargue `04_alumnos.csv` (Portal → Descargar del legado), revise Vista previa
(`grupo_estado`) e Importe. Si una `clave_legado` “no está en el mapa”, esa clave no formó
parte de los grupos importados: vuelva a exportar/importar `03_grupos` incluyendo esa clave.
Kids: un alumno del grupo legado → IK y CK (dual).
Al importar se provisiona cuenta HAY + Google Workspace + Moodle (si faltan), con las mismas
reglas del sistema (número de control / correo institucional).

### 05_pagos

`numero_control`, `fecha`, `monto`, `forma_pago`, `tipo`, `concepto`, `folio`. Colegiatura congelada se define después en HAY.

Para descargar pagos del legado basta pegar (o subir el CSV de alumnos) la columna
`id_alumno_legado`; no es obligatorio el filtro de grupos.

## Checklist operativo por plantel

1. [ ] Alta/import de personal → provisión staff
2. [ ] Import aulas mínimas
3. [ ] Revisar CSV grupos (especialidad, aula, horario, profesor) → import
4. [ ] Import alumnos
5. [ ] Import pagos
6. [ ] Provisionar correos/Moodle alumnos
7. [ ] Revisar cronología, rol de aulas, corregir fases y bloques kids

## Fuera de alcance (por diseño)

Becas, fotos, CFDI, ventas de mostrador, matrícula Moodle por curso de fase, croquis/capacidad de aulas, fusiones históricas.
