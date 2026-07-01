# Purga de datos operativos

Herramienta para vaciar datos de operación, pruebas e importación del sistema viejo, **sin tocar la configuración base**.

## Qué se conserva

- Planteles, aulas, roles y permisos
- Usuarios del personal real (no `demo.*`, no cuentas de alumnos)
- Catálogo de especialidades y productos (salvo opción LEG_*)
- Rúbrica HAY, niveles, capacitaciones configuradas
- Bancos de preguntas de exámenes
- Calendario escolar, banners de marketing (config)

## Qué se elimina (purga completa)

- Alumnos, grupos, pre-registros, pagos, asistencias
- Certificaciones, exámenes generados, evaluaciones HAY realizadas
- Entrevistas, comisiones movimiento, cortes de caja
- Mapas de importación legacy (`hay_legacy_map`, `hay_legacy_import_log`)
- Usuarios alumno y cuentas `demo.*`

## Cómo ejecutar

### Web (solo supervisor)

```
/hay/php/purge_datos_operativos_run.php
```

Escriba `BORRAR DATOS` para confirmar.

### CLI

```bash
php scripts/purge_datos_operativos.php --confirm=BORRAR DATOS
php scripts/purge_datos_operativos.php --confirm=BORRAR DATOS --solo-demo
php scripts/purge_datos_operativos.php --confirm=BORRAR DATOS --legacy-catalogo
```

## Opciones

| Opción | Efecto |
|--------|--------|
| (ninguna) | Purga **completa**: alumnos, grupos, asistencias, productos, pre-registros, mapas legacy, etc. |
| **Solo datos de prueba** | **Únicamente** seed demo — si la marca, **no** se borra el resto |
| `--legacy-catalogo` | Además borra especialidades/productos/grupos con prefijo LEG |

## Recomendación

1. Respaldar la base de datos antes de purgar.
2. Ejecutar como supervisor.
3. Si hubo importación legacy defectuosa, marque **También borrar catálogos LEG_***.
4. Tras la purga, verifique planteles y roles; cree personal y alumnos desde cero.
