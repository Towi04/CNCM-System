# Fase 2 — Académico (piloto Inglés)



## Estado actual (mayo 2026)



| Bloque | Estado | Notas |

|--------|--------|--------|

| Plan de estudio en BD | **Hecho** | `esp_fases`, `fase_temario_semana`, seed inglés |

| Calendarios (3 + admin + consulta) | **Hecho** | Fase 1 extendida |

| Plan mensual coordinación | **Hecho** | `grupo_plan` |

| Posición grupo / sesiones lectivas | **Hecho** | `academico_posicion_grupo` + calendario |

| Captura calificaciones por parcial | **Hecho** | `grupo_calificaciones` → Grupos → icono clipboard |

| Rúbrica por grupo/parcial | **Hecho** | Ponderación 100 %; 6 criterios obligatorios |

| Ubicación (placement) | **Hecho** | `ubicacion_coordinacion`, validación al inscribir grupo |

| Alertas riesgo académico | **Hecho** | `en_riesgo_academico`, `academico_riesgo`, notificaciones |

| Avance automático de grupo | **Hecho** | `grupo_avance_log`, auto al abrir Grupos + cron CLI |

| Portal profesor (horario, permisos, docs) | **Hecho** | `profesor_portal`, permisos con revisión gerencia |

| Graduación / parciales obligatorios | **Hecho** | `graduacion_helper.php` + API consulta |

| Moodle por nivel | **Pendiente** | Solo documentado |
| Evaluación 360 profesores | **Hecho** | Ver `sql/FASE3_EVALUACION_PROFESOR.md` |
| Reclutamiento docente + bolsa | **Hecho** | Prospectos, show class, DISC |
| Alertas graduación automáticas | **Hecho** | Coordinación decide |



## Avance automático de parcial



- Cada **4 sesiones lectivas** (según calendario del modelo del grupo) el sistema puede avanzar `id_fase_actual`.

- Se registra en `grupo_avance_log` (fase anterior, nueva, semanas lectivas, automático/manual).

- Al avanzar: alumnos sin calificación **≥ 6** en el parcial cerrado → `en_riesgo_academico = 1`.

- **Grupos** → botón sincronizar / avance manual (coordinación).

- Cron opcional: `php scripts/grupo_avance_cron.php [id_plantel]`



## Riesgo académico



- Pantalla: **Especialidades → Riesgo académico** (`academico_riesgo`).

- Coordinador registra nota y si el alumno aceptó cambio de grupo → `omitir_alerta_riesgo`.

- Al capturar calificación aprobatoria en el grupo, se limpia la bandera de riesgo.



## Portal docente



- Menú **Mi portal docente** (rol profesor): horario de grupos, accesos a calificaciones/asistencia/planeación.

- Solicitud de **permiso** → tabla `profesor_permiso_solicitud` → revisión en **Administración → Permisos de profesores**.



## Graduación



- API: `php/graduacion_api.php?id_alumno=&id_especialidad=` devuelve parciales pendientes o sin aprobar.

- Solo parciales `tipo_contenido = regular` cuentan para validación inicial.



## Fase 1 — pendiente menor



- Retención asistencia → puntos HAY.

- Push ADMS/ZKTeco (cuando haya lector).



## Pantalla calificaciones



**Grupos → icono calificaciones** → `grupo_calificaciones`



- Parcial (`id_fase`), ponderación por criterio (suma 100 %).

- Lista de alumnos del grupo; notas 1–10 por Listening, Reading, etc.

- Promedio y aprobado automático (≥ 6).

- Coordinador puede editar igual que el profesor.

