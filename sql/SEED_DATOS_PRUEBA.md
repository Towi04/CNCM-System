# Datos de prueba (demostración)

Genera usuarios, profesores, 3 grupos de inglés y 5 alumnos por grupo en cada plantel.

## Ejecutar

Desde la raíz del proyecto (local o servidor con PHP CLI):

```bash
php scripts/seed_datos_prueba.php
```

O en Windows:

```powershell
php c:\ruta\hay_system\scripts\seed_datos_prueba.php
```

**Contraseña de todos los usuarios de prueba:** `1234`

## Usuarios creados

| Plantel | Usuario | Rol | Persona |
|---------|---------|-----|---------|
| Guerrero | `demo.g.deysi` | Dirección | Deysi Guerrero |
| Guerrero | `demo.g.mejia` | Ventas | Roberto Mejía |
| Guerrero | `demo.g.sharoom` | Coord. inglés | Sharoom López |
| Guerrero | `demo.g.manuel` | Coord. compu/prep | Manuel Ríos |
| Guerrero | `demo.g.karla` | Recepción | Karla Núñez |
| Fuentes | `demo.f.victor` | Dirección | Víctor Fuentes |
| Fuentes | `demo.f.lucia` | Ventas | Lucía Morales |
| Fuentes | `demo.f.jenny` | Recepción | Jenny Ruiz |
| Salamanca | `demo.s.laura` | Dirección | Laura Samudio |
| Salamanca | `demo.s.sarahi` | Ventas | Sarahi Delgado |
| Salamanca | `demo.s.janette` | Recepción | Janette Vega |
| Salamanca | `demo.s.arturo` | Coord. (todo) | Arturo Mendoza |
| Celaya | `demo.c.lorena` | Dirección | Lorena Castillo |
| Celaya | `demo.c.alejandro` | Ventas | Alejandro Torres |
| Celaya | `demo.c.brenda` | Recepción | Brenda Herrera |
| Celaya | `demo.c.carlos` | Coord. prepa | Carlos Prepa |
| Celaya | `demo.c.jairo` | Coord. inglés | Jairo Inglés |
| Celaya | `demo.c.ivan` | Coord. compu | Iván Computación |

**Coordinadores compartidos Guerrero/Fuentes:** Sharoom y Manuel están en plantel Guerrero; en Fuentes cambien sede con el selector superior.

**Profesores:** `demo.{g|f|s|c}.prof.pedro`, `pablo`, `penelope`/`patricia`/`paula`/`pamela`

Correo institucional: `usuario@cncm.edu.mx` (ej. `demo.g.deysi@cncm.edu.mx`)

## Alumnos

- 3 grupos ING (sábados) por plantel, 5 alumnos c/u.
- Nombres con inicial del plantel: **G**uerrero, **F**uentes, **S**alamanca, **C**elaya.
- Login alumno: **número de control** · contraseña **1234**.

El script es **idempotente** para usuarios (no duplica si ya existen). Grupos demo se omiten si ya hay 3 con etiqueta `seed_prueba_2025`.

---

## Paso 2 — Datos operativos (pagos, asistencia, calificaciones)

Después del seed base, ejecute:

```bash
php scripts/seed_datos_operativos.php
```

O en el navegador (como admin/gerente):

`php/seed_datos_operativos_run.php?confirm=1`

### Qué agrega

| Dato | Detalle |
|------|---------|
| **Pagos** | Inscripción + colegiaturas de los últimos **6 meses** (algunos alumnos con adeudo del mes actual o abono parcial) |
| **Asistencias** | ~24 sábados por grupo (~88% presentismo) |
| **Calificaciones** | Parcial actual del grupo, rúbrica por defecto; algunos en riesgo académico |
| **Preregistros** | 3 prospectos demo por plantel (uno con apartado) |
| **Evaluación 360** | Evaluación **cerrada** del mes anterior para cada profesor de grupo demo |
| **Asistencia profesor** | Checadas de los últimos 30 días (profesores demo) |

Etiqueta en conceptos: `seed_operativo_2025` (no duplica si ya existen pagos con esa etiqueta).

### Qué probar después

1. **Punto de venta** — buscar alumno `#` control (ej. grupo Guerrero) → ver pagos pendientes y cobrar abono.
2. **Asistencias** — lista por grupo con registros previos.
3. **Grupos → calificaciones** — notas ya capturadas.
4. **Especialidades → Riesgo académico** — alumnos marcados.
5. **Pre-registro** — prospectos demo.
6. **Evaluación 360** / **Mi portal docente** (profesor).
7. **Reportes → Resumen académico por grupo**.
