# Piloto Inglés (ING) — reglas de negocio acordadas

Documento de referencia para desarrollo. Alcance: **Inglés regular (ING)**; extensivo después.

## Inscripción y trayectoria

- Al inscribirse en un **grupo**, el alumno cursa **todas las fases** de esa especialidad en orden (A1-1 → … → graduación).
- Puede estar inscrito en **otra especialidad** a la vez; colegiatura con **descuento combo** (ya existe `admin_colegiatura_combos`).
- Puede **terminar** una especialidad e inscribirse a otra.
- Debe tener **calificación en todos los parciales** (incluidos fusiones administrativas) para **graduarse**.
- **Graduación**: a los 2 años puede solicitar graduarse con el nivel alcanzado, o continuar hasta completar todo el programa.

## Grupo y avance

- El **grupo avanza junto** de parcial en parcial (automático cada 4 semanas lectivas).
- **Semana lectiva** = desde `fecha_inicio` del grupo, **excluyendo 3 vacaciones anuales**:
  1. Primera semana de enero
  2. Semana mayor (Lunes Santo–Sábado Santo; **no** incluye Domingo de Ramos)
  3. Última semana de diciembre
- Por defecto el alumno inicia en **A1-1**; con **examen de ubicación** puede entrar a grupo avanzado.

## Reprobación y riesgo académico

- Calificación mínima aprobatoria: **6** (escala **1–10**).
- Si reprueba, el coordinador orienta (horario, clases extra, cambio de grupo). El alumno **puede** seguir con su grupo aunque no tenga nivel.
- El sistema debe **alertar al coordinador** sobre alumnos que avanzan sin nivel aprobado.
- El coordinador puede dejar **nota en el perfil** (aceptó / rechazó cambio de grupo).
- Si el alumno **regresa** a fase anterior, la **nueva calificación sustituye** la anterior.

## Calificaciones

- Registro **por parcial** (`id_fase`).
- Captura principal: **profesor** del grupo.
- **Coordinador** puede capturar/editar (asesoría, examen tardío, revisión a padres).
- Criterios **obligatorios** (escuela): Listening, Reading, Writing, Speaking, Grammar, Vocabulary.
- Criterios **opcionales** (libertad de cátedra): libro, plataforma, asistencia, participación, tareas, proyectos, otros.
- El profesor define **ponderaciones** por grupo y por parcial (suma 100%).

## Ubicación (placement)

1. Ventas: pre-registro  
2. Recepción: apartado / pago; si aplica ubicación → **Examen de ubicación** en ficha alumno  
3. Coordinación: **Especialidades → Examen de ubicación** — nivel detectado + **grupos autorizados**  
4. Recepción solo inscribe en grupos **autorizados** (el sistema bloquea otros)  
5. Al inscribir → **notificación al profesor** (marca ubicación si aplica)

## Moodle

- Un curso por **nivel** (A1, A1+, A2, …).
- 12 unidades/lecciones por curso ≈ temas semanales; **últimas 4 semanas** = proyectos del parcial.

## Libros

- Aún **no** en inventario de productos (fase posterior).

## Cambios manuales

- Coordinador y recepción pueden **cambiar de grupo** al alumno cuando sea necesario.

## Calendarios escolar y administrativo

Tres calendarios lectivos independientes (ver `sql/CALENDARIO_ESCOLAR.md`):

| Modelo | Quién edita | Reglas clave |
|--------|-------------|--------------|
| **regular** | Supervisora, gerente | Asuetos con recuperación; vacación sábado |
| **prepa escolarizada (PE)** | Supervisora, coordinador prepa | Sin recuperación; vacaciones entre cuatrimestres |
| **prepa abierta (PA)** | Supervisora, coordinador prepa | Igual que escolarizada |

- **Calendario institucional** (`calendario_consulta`): un solo mes con casillas REG / PE / PA / ADM; profesores solo lectura según grupos que imparten.
- Pantalla **Editar calendarios escolares** (`admin_calendario`): pestañas por modelo, publicación por año.
- Coordinador prepa: usuario con departamento **Preparatoria** (solo calendarios PE/PA).
- **Calendario administrativo** (`admin_calendario_admin`): juntas, capacitaciones; audiencia por rol/departamento; notificación al publicar.
- **Recepción / consulta de adeudo**: usa el calendario del grupo del alumno (PE/PA/regular).
- Parciales: `academico_sesiones_lectivas_desde()` con el modelo del grupo.

## Claves de grupo

| Parte | Código |
|-------|--------|
| Inglés | I |
| Kids | K |
| Computación | C |
| Prepa abierta | PA |
| Prepa escolarizada | PE |
| Sábados / Dom / Mat / Vespertino | S, D, M, V |
| Extensivo | prefijo **E** (ej. EIS212, EK120) |
| Personalizado | **PER-NOMBRE** (PER-TOEFL, PER-Excel) |

Ejemplos: `IS102`, `CD350`, `K221`, `PA36`, `PE15`.

- La clave **no cambia** al avanzar de fase.
- **Fusiones**: no se codifican con asteriscos en la clave. En pantalla: clave + insignias `Fusión ×n` y `↑` adelanto / `↓` atraso. Si fusionan en la misma fase, solo cuenta la fusión sin flecha.
- Consecutivo automático por prefijo (`IS` → 101, 102…).

## Plan de parciales (compresión antes de fusión)

- Solo **coordinación** ve el plan mensual (`grupo_plan`).
- Por cada **mes**: elige el **parcial que se registra al alumno** (historial normal, sin mensaje de adelanto).
- Puede marcar **varios parciales cuyo temario imparte** ese mes (compresión interna).
- **Nota / temas a retomar**: para cuando termine el mes intensivo, saber qué repasar.
- Alerta **«Retomar»** en lista de grupos si hay pendientes.
- El **alumno** no ve que vio dos parciales en un mes; solo su parcial registrado en secuencia.
