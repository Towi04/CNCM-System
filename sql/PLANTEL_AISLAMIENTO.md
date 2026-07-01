# Aislamiento por plantel (sede)

Cada usuario trabaja sobre **un plantel activo** en sesión (selector arriba a la derecha).

## Regla

Al elegir **Plantel Salamanca**, solo debe verse:

- Alumnos con `alumnos.id_plantel` = Salamanca
- Grupos con `grupos.id_plantel` = Salamanca
- Personal con `usuarios.id_plantel` = Salamanca

Lo mismo para Celaya, Guerrero y Fuentes.

## Quién puede cambiar de plantel

| Rol | Cambiar sede en el selector |
|-----|----------------------------|
| Admin, gerente, supervisor | Sí (todas las sedes activas) |
| Asesor, profesor | No (solo su plantel asignado en el usuario) |

Al cambiar de plantel, la pantalla actual se **recarga** con datos de la nueva sede.

## Datos de alumnos

Cada alumno debe tener `id_plantel` coherente con su grupo. Al guardar el esquema de planteles, el sistema intenta corregir alumnos cuyo plantel no coincide con el del grupo.

## Módulos filtrados

- Alumnos, grupos, personal (usuarios), pre-registros
- Asistencia, calificaciones, punto de venta, consulta de adeudo
- Evaluación 360 profesores, reclutamiento docente, alertas de graduación
- Alta/edición de personal: el plantel queda fijado a la sede activa (salvo admin global)

## Verificación

1. Entrar como `demo.s.laura` (Salamanca), contraseña `1234`.
2. Alumnos / Grupos / Usuarios → solo registros de Salamanca.
3. Cambiar a Celaya en el selector (gerente) → solo datos de Celaya.
4. Entrar como `demo.s.sarahi` (asesor Salamanca) → el selector muestra solo Salamanca (bloqueado).
5. Intentar abrir un alumno de otro plantel por URL (`alumno_detalle?id=…`) → mensaje «no encontrado».
