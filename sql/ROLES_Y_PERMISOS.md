# Roles y permisos (HAY / CNCM)

## Jerarquía (de menor a mayor alcance)

| Nivel | Rol | Alcance |
|-------|-----|---------|
| 1 | **asesor** | Ventas y captación: pre-registro, entrevistas, grupos por fase, sus inscritos, consulta de comisiones propias. |
| 2 | **gerente** | Todo lo del asesor + administración comercial: comisiones, reportes de ventas, cartas, descuentos gerente. |
| 2 | **coordinador** | Todo lo del asesor + académico: alumnos, grupos, fases, asistencias, calificaciones, planeaciones, autorizaciones de inscripción. |
| 3 | **admin** (recepción) | Coordinador + caja: punto de venta, adeudos, certificaciones, reportes de efectivo, cobros. |
| 4 | **director** | Recepción + gerente + administración del plantel (usuarios, catálogo, calendarios). **Sin** configuraciones exclusivas de supervisión. |
| 5 | **supervisor** | Acceso total + tarifas de catálogo, planteles, roles, configuración HAY. |

### Roles especiales

| Rol | Alcance |
|-----|---------|
| **profesor** | Su información personal, sus grupos y alumnos, calificaciones, planeaciones, asistencia de sus grupos. |
| **alumno** | Solo su información: calificaciones, pagos, perfil. |

### Personal HAY

Todos los roles de **personal** (no alumnos) ven **Mi evaluación** y **Matriz de entrenamiento**.

## Solo supervisión general

Estas capacidades **no** las tiene el director; solo `supervisor`:

- `catalogo_editar_costos` — editar tarifas en catálogo
- `admin_planteles` — administrar sedes
- `admin_roles` — crear/editar roles del sistema
- `hay_eval_configurar` — configurar rúbrica HAY
- `seed_datos` — semillas de prueba
- `cartas_excepcion_minimo` — excepciones en promoción cartas

## Alcance por plantel

| Rol | Sedes por defecto |
|-----|-------------------|
| **supervisor** | Todas las sedes (selector activo) |
| **director** | Solo su plantel asignado |
| **gerente** | Todas las sedes (ventas multi-sede) |
| **asesor**, **admin** (recepción), **coordinador** | Solo su plantel; pueden recibir **sedes temporales** al cubrir otro puesto |

### Sedes temporales (apoyo en otro plantel)

1. **Administración → Ver usuarios → Editar usuario**
2. Sección **Sedes temporales de apoyo** (solo asesor, recepción, coordinador)
3. Agregar sede + fecha de vigencia opcional

API: `php/usuario_planteles_api.php`

## Privilegios por persona (temporal)

Cuando un coordinador apoya en recepción, o se necesita un permiso puntual:

1. **Administración → Ver usuarios → Editar usuario**
2. Sección **Privilegios individuales**
3. Marcar **+** (otorgar) o **−** (denegar) por vista
4. Opcional: **fecha de vigencia** para que expire solo

API: `php/usuario_privilegios_api.php`

## Roles personalizados

**Administración → Roles y privilegios** (solo supervisión):

- Crear rol nuevo (ej. `coordinador_caja_temp`)
- Elegir vistas del catálogo
- Asignar el rol al usuario en **Editar usuario**

## Archivos técnicos

| Archivo | Función |
|---------|---------|
| `php/rbac_jerarquia_helper.php` | Capas de privilegios por nivel |
| `php/rbac_db_helper.php` | BD: roles, role_privilegios, usuario_privilegios |
| `php/rbac_helper.php` | `rbac_cap()` en tiempo de ejecución |
| `php/menu_config.php` | Menú lateral por flujos |
| `views/admin_roles.php` | UI roles (supervisor) |

## Migración automática

1. Subir código actualizado al servidor.
2. Entrar al dashboard **una vez** (aplica `sql/migrations/006_production_roles_schema.sql` y repara RBAC).
3. O ejecutar en servidor: `php scripts/run_schema_migrate.php`
4. Cerrar sesión y volver a entrar.

Tras la migración 006, el DDL en runtime queda desactivado (`schema_ddl_runtime=0`); las tablas nuevas se agregan solo vía archivos en `sql/migrations/`.
