# Reporte semanal de asistencia / plantilla

## Acceso

Menú **Reportes → Reporte semanal asistencia** (`reporte_semanal`).

Roles: gerente, supervisor, admin (`reporte_academico_puede_ver`).

## Semanas

- **Domingo–sábado**, semanas **1–52** por año calendario.
- Semana 1 = semana que contiene el **1 de enero** (domingo de esa semana).
- Al abrir la vista se carga **la semana actual** automáticamente.

## Vistas

| Modo | Descripción |
|------|-------------|
| Semana | Una semana (por defecto la actual) |
| Rango | De semana X a semana Y |
| Mes | Todas las semanas del mes en curso |
| Año | Semanas 1–52 |

## Columnas por grupo

| Col | Significado |
|-----|-------------|
| **A** | Alumnos activos al cierre de la semana anterior |
| **I** | Inicios (primera vez en el grupo) |
| **R** | Reingresos (faltó semana anterior, asistió esta) |
| **+C** | Cambio positivo de horario (entró desde otro grupo) |
| **B** | Baja (sin asistencia en la semana) |
| **−C** | Cambio negativo (salió a otro grupo) |
| **FC** | Fin de curso (manual, coordinación/dirección) |
| **T** | A + I + R − B − FC |

Los cambios **+C / −C** se registran pero **no alteran** el total único del plantel.

## Deserción / crecimiento

```
Deserción = alumnos únicos al cierre − alumnos únicos al inicio del periodo
```

Valor **positivo** = el plantel creció.

## Detección automática

Al generar el reporte el sistema sincroniza movimientos desde:

- `alumno_grupos` (inicios, bajas, cambios de grupo)
- `asistencias` (bajas por falta total, reingresos)

**FC** y ajustes de **B** se pueden registrar manualmente vía API (`marcar_movimiento`).

## Acciones en interfaz

| Dónde | Acción |
|-------|--------|
| **Alumno → Información** | Cambio de horario, Fin de curso, Baja temporal/definitiva |
| **Rondín de asistencia** | Baja temporal/definitiva por alumno faltante |
| **Automático** | B en reporte si no hubo asistencia en la semana (al cargar faltantes o generar reporte) |

Los cambios de grupo desactivan solo otros grupos de la **misma especialidad** (+C/−C).

## Archivos

| Archivo | Función |
|---------|---------|
| `php/reporte_semanal_helper.php` | Cálculos y semanas |
| `php/reporte_semanal_api.php` | API JSON |
| `views/reporte_semanal.php` | Vista |
| `js/reporte_semanal.js` | UI |
| Tabla `reporte_semanal_movimiento` | Movimientos I/R/C/B/FC |
