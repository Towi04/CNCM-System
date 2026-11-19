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

Esto es el **catálogo de especialidad**, no la clave del grupo (IS1, CK3…).
HAY genera la clave de grupo al importar. Puede usar la clave larga o el atajo:

| Atajo | Clave catálogo | Uso |
|-------|----------------|-----|
| `I` | `ING` | Inglés adulto |
| `C` | `COMP` (o COMP25/24) | Informática adulto |
| `IK` | `ING-K` | Inglés infantil |
| `CK` | `COMP-K` | Computación infantil |
| `PA` | `PREP-AB` | Preparatoria abierta |
| `PE` | `PREP-ESC` | Preparatoria escolarizada |
| `EI` | `ING-EXT` | Extensivo inglés |
| `EC` | `COMP-EXT` | Extensivo informática |

También valen `ING`, `ING-K`, `COMP`, `COMP-K`, etc. Fechas: `YYYY-MM-DD` o Excel MX `DD/MM/YYYY`.

Kids: `infantil=1` + `IK`/`CK` (o `ING-K`), o la misma `clave_legado` en 2 filas
(1ª inglés, 2ª cómputo) con distinto horario/aula. HAY crea la pareja `IK{n}+CK{n}`.
Alumnos de ese grupo se inscriben en **ambas** materias (dual).

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

Columna `infantil`: `0` = adulto, `1` = kids (se respeta en vista previa e import).
Pareja kids con horarios distintos: `infantil=1` (o especialidad `ING-K`/`COMP-K`) y, si aplica,
duplicar la misma `clave_legado` (1ª = inglés, 2ª = cómputo) cambiando `hora_inicio` /
`dias_clase` / `aula_codigo`. Con `infantil=0` **no** se crea pareja kids aunque la clave se repita.
El id interno de BD (`id_grupo_legado`) es opcional; el enlace operativo es la **clave**.

### 04_alumnos

`numero_control`, nombres, `clave_legado` del grupo ya importado,
`fecha_inicio`, `id_asesor_legado` / `asesor_nombre`.

**No se crean al importar grupos.** Pegue las **mismas claves** con las que importó
`03_grupos`, descargue `04_alumnos.csv` (Portal → Descargar del legado), revise Vista previa
(`grupo_estado`) e Importe. Si una `clave_legado` “no está en el mapa”, esa clave no formó
parte de los grupos importados: vuelva a exportar/importar `03_grupos` incluyendo esa clave.
Kids: un alumno del grupo legado → IK y CK (dual).

El `numero_control` del legado se **respeta tal cual** en HAY (no se regenera).
Kids dual = **una** ficha HAY con vínculo a IK y CK (no dos alumnos). El contador de mapa
puede listar ~3 claves por alumno (`nc:…`, NC plano, `id_alumno_legado`).

Vista previa marca alertas (grupo faltante, teléfono >30 chars, sin NC, NC duplicado, etc.)
y las pone primero; use «Solo alertas» para filtrar. Teléfonos largos se truncan al importar.

**Importación en dos pasos:** por defecto Importar solo crea la ficha HAY + vínculo a grupo
(sin Google Workspace ni Moodle). Cuando confirme que los alumnos están correctos, use
«Re-provisionar mapeados» para crear usuario HAY + Google + Moodle (NC como usuario /
correo institucional).

### 05_pagos

`numero_control`, `fecha`, `monto`, `forma_pago`, `tipo`, `concepto`, `folio`. Colegiatura congelada se define después en HAY.

**Recomendado:** pegue `numero_control` (o suba el CSV de alumnos / marque «usar
alumnos ya mapeados») y pulse **Migrar pagos del legado**. El servidor consulta
el legado e importa a HAY por lotes — sin descargar/subir el CSV enorme de pagos.
Los ya migrados se omiten por `id_pago_legado`. El cobrador («Recibió») viene de
`id_recibio` del legado vía el mapa de personal/usuarios (no del operador que importa);
al re-ejecutar se corrige el cobrador de pagos ya mapeados.

Alternativa: descargar CSV de pagos e Importar. Prefijo `id:123` solo si necesita
el id interno legado.

## Fecha de inicio

`fecha_inicio_estimada` / `fecha_inicio_legado` deben venir del legado (`YYYY-MM-DD` o `DD/MM/YYYY`).
El import **ya no inventa la fecha de hoy** si falta o no parsea: la fila falla con error.
Grupos con fecha ≤ hoy quedan en `iniciado`; futuros en `programado` (la vista de apertura
solo marca *pendiente de autorización* cuando el inicio está cerca).

Kids: 2 filas con la **misma** `clave_legado` (una `IK`/`ING-K`, otra `CK`/`COMP-K`) → **1** pareja
HAY (`IK{n}` + `CK{n}` = 2 grupos). Si solo sube la fila CK sin su IK, se omite (no se duplica).
Una sola fila `ING-K` aún parte 2h+2h. Al comparar totales: adultos 1:1; cada pareja kids = 2 grupos HAY.

## Reintentar grupos mal importados

En el Portal → Migración CSV → **Revertir grupos migrados** (plantel activo):

1. Confirme con la palabra `REVERTIR`.
2. Opcional: también borrar alumnos/pagos creados por la migración.
3. Se eliminan los grupos del mapa, se desvinculan alumnos y se **reconstruye**
   el contador de claves (`grupo_clave_secuencia`) según lo que quede en el plantel
   (si no queda ninguno, las nuevas claves vuelven a empezar en 1).
4. Vuelva a descargar/importar `03_grupos` con las claves legado.

### Purga total desde terminal (todos los grupos del plantel)

Si la tabla no estaba vacía o quedaron grupos fuera del mapa:

```bash
php scripts/purge_grupos_plantel.php --plantel=guerrero --confirm="BORRAR GRUPOS" --todos
# opcional: --borrar-alumnos
```

Luego reimporte `03_grupos` y revise la vista **Grupos** (paginada: buscar / estado / 25–100 por página).

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
