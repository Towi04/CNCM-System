# Fase 3 — Evaluación 360 de profesores

## Estado (mayo 2026)

| Bloque | Estado |
|--------|--------|
| Lista de profesores por periodo | **Hecho** |
| Métricas automáticas (retención, asistencia, puntualidad, calificaciones) | **Hecho** |
| Criterios manuales (coordinación) | **Hecho** |
| Cierre de periodo (borrador / cerrado) | **Hecho** |
| Vinculación puntos HAY globales | **Pendiente** |
| Rúbrica Excel completa (todas las columnas del XLSM) | **Pendiente** |
| Vista profesor (solo lectura de su evaluación) | **Hecho** | Mi portal docente |
| Rúbrica Excel completa (Know-how, etc.) | **Hecho** | 6 bloques, máximos matriz HAY |

## Acceso

**Administración → Evaluación 360 profesores** (`calificar_usuario`)

Roles: gerente, supervisor, admin.

## Periodo

Evaluación **mensual** (año + mes). Cada profesor tiene una fila única por plantel y periodo.

## Métricas automáticas

Calculadas al abrir o con «Recalcular métricas»:

| Criterio | Fuente en HAY |
|----------|----------------|
| Retención | Alumnos activos en grupos del profesor vs total en esos grupos |
| Asistencia alumnos | Registros `asistencias` del mes en sus grupos |
| Puntualidad | `asistencia_personal` (llegada ≤ 08:10) |
| Entrega calificaciones | `alumno_fase_calificacion` vs alumnos activos en parcial actual |

El sistema sugiere puntos según bandas inspiradas en la matriz HAY (retención ≥95% → 100 pts, etc.). Coordinación puede ajustar antes de cerrar.

## Criterios manuales

MCERL, certificación, Moodle, planeaciones, fusiones, juntas, supervisión, eval. 4.º mes, eval. de clase (0 a máximo por rubro).

## Nivel global (puntos totales)

| Puntos | Nivel |
|--------|--------|
| ≥ 850 | Excelente |
| ≥ 700 | Muy bueno |
| ≥ 550 | Bueno |
| ≥ 400 | Regular |
| &lt; 400 | Mejorable |

Máximo teórico del periodo: calculado dinámicamente (~**5 900** pts según matriz C2 del Excel).

## Punto de venta (layout anterior)

Pantalla **Punto de venta** con dos selectores (alumno / preregistro), tabla de pagos pendientes y panel «Recibir abono» (ver `views/punto_venta.php`).

## Archivos

- `php/profesor_eval_helper.php`
- `php/profesor_eval_api.php`
- `views/calificar_usuario.php`
- `views/profesor_evaluacion.php`
- `css/profesor_eval.css`
- Tabla: `profesor_eval_periodo`

## Motor HAY genérico (2026)

Nuevo módulo paralelo: ver [HAY_SISTEMA.md](HAY_SISTEMA.md). La rúbrica de profesor inglés puede importarse a `hay_area` y evaluarse desde **Evaluar personal** sin depender del PHP hardcodeado.

## Próximo

1. Migrar periodos `profesor_eval_periodo` al motor genérico.
2. Exportar resumen a reportes / gráficas por plantel.
3. Integrar retención histórica por grupo (no solo snapshot actual).
