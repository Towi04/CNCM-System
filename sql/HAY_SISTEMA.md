# Sistema HAY genérico

## Resumen

Motor configurable por **área de trabajo** (ej. Profesor Inglés, Asesor ventas). Supervisión define rubros, aspectos, opciones con puntaje, niveles salariales y matriz de capacitación.

## Tablas

| Tabla | Uso |
|-------|-----|
| `hay_area` | Áreas (Profesor Inglés, etc.) |
| `hay_area_rol` | Roles RBAC asignados al área |
| `hay_area_usuario` | Override área por usuario |
| `hay_rubro` | Know-how, Accountability, Problem Solving, Environment |
| `hay_aspecto` | Criterio evaluable (ej. Nivel MCERL) |
| `hay_opcion` | Opción con puntos (origen: manual, moodle, sistema) |
| `hay_config_version` | Snapshot publicado de rúbrica |
| `hay_eval_periodo` | Evaluación mensual por colaborador |
| `hay_eval_respuesta` | Opción elegida por aspecto |
| `hay_nivel_cargo` | 5 niveles / rangos de puntos / sueldo base |
| `hay_capacitacion` | Curso obligatorio o extra mensual |
| `hay_capacitacion_cumplimiento` | Marcado manual por jefe |

## Flujo mensual

1. Supervisor configura rúbrica en **Configurar evaluación HAY** y publica.
2. **Evaluar personal**: elige área, año, mes; califica cada aspecto (select de opciones).
3. Cierra periodo → asigna nivel según `hay_nivel_cargo`.
4. Colaborador ve resultado en **Mi evaluación**.
5. **Matriz de entrenamiento**: jefe marca capacitaciones completadas.

## Archivos

- `php/hay_eval_helper.php` — esquema y lógica
- `php/hay_eval_config_api.php` — CRUD configuración
- `php/hay_eval_api.php` — evaluación y matriz
- `views/hay_config_rubrica.php` — UI configuración
- `views/hay_evaluacion_admin.php` / `hay_evaluacion_form.php`
- `views/mi_evaluacion.php` / `matriz_entrenamiento.php` / `hay_matriz_admin.php`
- `css/hay_eval.css`

## Semilla (solo Profesor Inglés)

La plantilla se importa desde `scripts/hay_xlsm_dump.txt`, hoja **Opciones a evaluar** (sheet 3): aspectos con opciones y puntos reales del Excel (MCERL B1/C2, certificaciones, Windows/Word, planeaciones, etc.). **No** usar este dump para otras áreas (asesor, recepción).

- UI: **Importar Profesor Inglés (Excel)** en configuración; opción *Reimportar* si el área ya existe (solo sin evaluaciones guardadas).
- CLI: `php php/hay_eval_seed_cli.php` o `php php/hay_eval_seed_cli.php --forzar`
- Parser: `php/hay_xlsm_parser.php`

## Moodle (Fase 4)

Opciones con `origen=moodle` y `moodle_course_id`. API `sync_moodle` en `hay_eval_api.php`. Funciones en `moodle_helper.php`.

## Convivencia

Evaluación 360 legacy (`profesor_eval_periodo`) sigue activa hasta migración completa.
