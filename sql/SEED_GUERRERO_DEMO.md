# Seed demo — plantel Guerrero

Genera datos falsos **solo en Guerrero** para que dirección, recepción, asesor, coordinación y profesores puedan recorrer el sistema con información realista.

## Ejecutar

```bash
php scripts/seed_guerrero_demo.php
```

O en el navegador (gerente / supervisor / admin):

`php/seed_guerrero_demo_run.php?confirm=1`

**Contraseña de todos los usuarios demo:** `1234`

Etiqueta idempotente: `seed_guerrero_demo_2026` (no duplica grupos ni preregistros si ya existen).

## Qué crea

| Área | Contenido |
|------|-----------|
| **Aulas** | A1–A5 + LAB1 (laboratorio de cómputo) |
| **Personal** | `demo.g.deysi` (dirección), `demo.g.mejia` (asesor), `demo.g.karla` (recepción), coordinadores y 5 profesores |
| **Grupos** | Varios de ING / COMP / PREP con `fecha_inicio` de hace 3–8 meses, horarios distintos, estado iniciado |
| **Alumnos** | ~5–6 por grupo; pagos desde la fecha de alta; login = número de control |
| **Asistencias** | Histórico por días de clase del grupo (~87% presentismo) |
| **Calificaciones** | Rúbrica por cada fase cursada (no solo la actual) |
| **Preregistros** | 15 prospectos con edad, municipio, colonia, CP, grado de estudios, medio de entero y estados variados |
| **Entrevistas** | Vinculadas a preregistros (reportes de asesor) |
| **Cortes de caja** | Cuenta A y B en días hábiles recientes |
| **Reporte semanal** | Sincronización de las últimas semanas |
| **Rol de aulas** | Generado y publicado del mes actual |
| **Checador** | `asistencia_personal` de personal demo |

## Relación con seeds anteriores

1. Opcional: `php scripts/seed_datos_prueba.php` (todos los planteles, base ligera).
2. Opcional: `php scripts/seed_datos_operativos.php` (operativo sobre grupos `seed_prueba_2025`).
3. **Este script** es independiente y más completo para Guerrero (`seed_guerrero_demo_2026`).

Si ya limpió la BD operativa y solo quiere Guerrero, ejecute únicamente este seed.

## Qué revisar después

1. **Cronología de grupos** — fases coherentes con `fecha_inicio`.
2. **Rol de aulas** — mural del mes publicado.
3. **Punto de venta / estado de cuenta** — pagos históricos.
4. **Asistencias / rondín** — historial por grupo.
5. **Calificaciones** — parciales de fases anteriores.
6. **Pre-registro y demográficos** — variedad de medios, municipios y edades.
7. **Entrevistas / podio asesor** — captación.
8. **Corte de caja y reporte semanal** — movimientos recientes.
