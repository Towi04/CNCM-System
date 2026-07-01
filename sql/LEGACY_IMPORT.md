# Importación desde sistema legado (Laravel / `cncm/`)

HAY es la fuente de verdad: **no** se modifica la lógica de negocio del sistema nuevo; solo se cargan datos históricos desde la base restaurada del sistema anterior.

## Requisitos

1. Restaurar `cncm/base de datos.dump` en MySQL como base **separada** (recomendado: `cncm_legacy`).
2. En `config.local.php` definir conexión al legado:

```php
define('LEGACY_DB_HOST', 'localhost');
define('LEGACY_DB_NAME', 'cncm_legacy');
define('LEGACY_DB_USER', 'root');
define('LEGACY_DB_PASS', '');
```

3. Tener la BD de HAY con esquema actual (`hay_bootstrap_schema` al entrar al dashboard o importador).

## Criterio legado → HAY

| Legado | HAY |
|--------|-----|
| `sucursales` | `planteles` (por nombre/slug) |
| `users` + rol Spatie | `usuarios` |
| `especialidades` | `especialidades` (clave `LEG_{id}` si no hay match) |
| `productos` | `productos` |
| `grupos` | `grupos` (clave conservada o `LEG-G{id}`) |
| `alumnos` con `status` NULL o `Pre-Registro` | `preregistros` (`estado` = `pendiente`) |
| `alumnos` con `status` = `Alumno` | `alumnos` |
| `alumnos_grupos` (pivot `Inscrito`) | `alumno_grupos` |
| `alumnos_especialidades` | `alumno_especialidades` |
| `pagos` + `abonos` (no borrados) | `alumno_pagos` (histórico) |

Registros con `deleted_at` en legado se omiten.

## Equivalencias (evitar duplicados)

Menú **Administración → Equivalencias legado → HAY** (`legacy_mapeo`):

- Asocie cada sucursal legado con el plantel HAY correcto (Salamanca, Celaya, etc.).
- Asocie cada especialidad legado con la especialidad HAY existente.
- **Omitir** evita importar ese registro; **Crear nuevo** solo si realmente no existe en HAY.

Después reimporte solo las fases que falten (p. ej. **pagos**).

## Ejecutar importación

Desde la raíz del proyecto (PHP CLI):

```bash
php php/legacy_import_run.php
php php/legacy_import_run.php --fase=planteles
php php/legacy_import_run.php --fase=alumnos --dry-run
php php/legacy_import_run.php --reset-map
```

Fases (en orden): `planteles`, `especialidades`, `usuarios`, `productos`, `grupos`, `preregistros`, `alumnos`, `alumno_grupos`, `alumno_especialidades`, `pagos`, o `all`.

## Mapeo de IDs

La tabla `hay_legacy_map` guarda `entidad`, `id_legacy`, `id_hay` para no duplicar al re-ejecutar una fase.

## Archivos (fotos, documentos)

La importación **no** copia archivos automáticamente. Rutas legado suelen estar en `storage/app/public` del Laravel. Copiar manualmente a:

- `uploads/preregistros/fotos/`
- `uploads/alumnos/fotos/`

y actualizar rutas en BD si hace falta (fase futura).

## Qué no se importa (por diseño HAY)

- Materias / `grupos_materias` (modelo académico distinto)
- CFDI / Factura.com (folios se pueden conservar en notas de pago)
- Permisos Spatie granulares (solo rol principal → `usuarios.rol`)
- Certificaciones, Moodle, exámenes HAY (solo datos nativos HAY)

## Pruebas

Usar una copia de la BD HAY (`cncmedum_hay_test`) antes de importar en producción.
