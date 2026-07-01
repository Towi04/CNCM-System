# Calendarios escolar y administrativo

## Tres calendarios lectivos

| Modelo | Grupos | Reglas |
|--------|--------|--------|
| **regular** | Inglés, cómputo, kids (IS, CD, K, …) | Asuetos entre semana con **fecha de recuperación**; vacación **sábado** recorre semana |
| **prepa_escolarizada** | PE… | Asuetos **sin recuperación**; **vacaciones entre cuatrimestres** |
| **prepa_abierta** | PA… | Igual criterio que escolarizada |

Cada modelo se publica por año de forma independiente.

## Permisos

| Rol | Calendarios que puede editar |
|-----|------------------------------|
| **Supervisora** | Los tres lectivos + administrativo |
| **Gerente** (depto. inglés/cómputo/admin) | Solo **regular** |
| **Coordinador prepa** (usuario con departamento **Preparatoria**) | **prepa_escolarizada** y **prepa_abierta** |
| **Gerente / admin** (según configuración) | **Calendario administrativo** (juntas, capacitaciones) |

Asigne departamento **Preparatoria** al coordinador de prepa en **Usuarios**.

## Pantallas

- **Calendario institucional** (`calendario_consulta`): vista **combinada** con casillas por área (REG / PE / PA / ADM). Solo lectura para profesores; dirección usa la misma vista para comparar áreas.
- **Administración → Editar calendarios escolares**: pestañas por modelo, grid mensual, publicar.
- **Administración → Calendario administrativo**: eventos con audiencia (rol, departamento o todo el personal). Al **publicar**, se envían notificaciones (`notificacion_usuario`) visibles en el panel de inicio.

### Profesores (solo lectura)

Las capas visibles se calculan por **grupos asignados** (`grupos.id_profesor`):

| Imparte en | Ve en consulta |
|------------|----------------|
| Solo prepa (PE) | Prepa escolarizada y/o abierta + administrativo (si aplica audiencia) |
| Solo inglés/cómputo | Regular + administrativo |
| Prepa e inglés/cómputo | Todas las capas que correspondan + administrativo |

Eventos **administrativos**: el maestro solo ve los publicados donde su usuario está en la **audiencia** (rol, departamento, todos o usuario concreto).

## Tipos de día (lectivo)

| Tipo | Clases | Plantel |
|------|--------|---------|
| **cierre_plantel** | No | Cerrado |
| **sin_clase_abierto** | No | Abierto (cobranza) |
| **asueto** | No ese día | Abierto; recuperación solo en **regular** |
| **vacacion_sabado** | No (solo S) | Solo calendario **regular** |
| **vacacion_cuatrimestre** | No | Calendarios **prepa** |

## Continuidad de fases

`academico_sesiones_lectivas_desde()` usa el modelo del grupo (`PE` → escolarizada, `PA` → abierta, resto → regular).

## Migración

Al cargar la app, `calendario_migrate_schema()` añade columna `modelo` y tablas de eventos administrativos. Los días existentes quedan en `modelo = regular`.
