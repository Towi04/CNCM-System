# Auditoría de base de datos HAY (2026-06)

Revisión orientada a **estabilidad**, **seguridad** y **redundancia**. No implica migrar todo de inmediato; prioriza decisiones conscientes.

## Resumen ejecutivo

| Área | Estado | Acción recomendada |
|------|--------|-------------------|
| Número de tablas (~120+) | Alto pero manejable para ERP académico | No fusionar a ciegas; documentar dominios |
| Migraciones `ensure_schema` en PHP | Flexible, riesgo de drift entre entornos | Mantener migraciones SQL numeradas como fuente de verdad |
| Duplicidad prospectos | Media | Consolidar `prospectos_profesor` → `docente_prospecto` cuando el legacy no se use |
| Profesor por grupo | Baja | `grupos.id_profesor` + `grupo_docente` es intencional (compatibilidad) |
| Área HAY vs especialidad | Media | `hay_area.alias_especialidad` es el puente; evitar tercera copia en `usuarios.id_hay_area` salvo cache |
| Seguridad login | Mejorado | Bloqueo por intentos + auditoría `usuario_login_intento` |
| Uploads | Mejorado | Helper central + `.htaccess` en árbol `uploads/` |

---

## Dominios (agrupación lógica)

1. **Identidad y acceso** — `usuarios`, `roles`, `rbac_*`, `password_resets`, `usuario_login_intento`, `usuario_plantel`
2. **Planteles y catálogo** — `planteles`, `especialidades`, `especialidad_fases`, `ubicacion_examenes`, `plantel_aulas`
3. **Alumnos e inscripción** — `alumnos`, `preregistros`, `alumno_grupos`, `inscripcion_*`, `pago_*`
4. **Grupos y académico** — `grupos`, `grupo_*`, `planeaciones`, `asistencias`, `calificaciones_*`
5. **HAY / nómina / evaluación** — `hay_*`, `personal_pago_config`, `nomina_*`, `profesor_360_*`, `expediente_*`
6. **Integraciones** — Moodle (columnas en usuarios/alumnos), Google (cuenta digital), legacy import
7. **Tutor / RAG / material** — `tutor_*`, `academico_material*`, embeddings
8. **Exámenes inglés (legacy)** — `exam_*`, `examenes`, bancos CSV — dominio separado del piloto calificaciones

Esta separación explica por qué hay muchas tablas: son **subdominios** con ciclos de vida distintos, no un diseño monolítico fallido.

---

## Posible redundancia (evaluar antes de fusionar)

### 1. `prospectos_profesor` vs `docente_prospecto`

- `usuario_helper` aún crea `prospectos_profesor` (legacy).
- El flujo nuevo usa `docente_prospecto` + `docente_prospecto_area`.
- **Recomendación:** dejar de escribir en `prospectos_profesor`; migrar filas históricas y deprecar tabla en una migración futura.

### 2. `usuarios.id_hay_area` vs `hay_area_usuario`

- Con multi-área, la fuente de verdad es `hay_area_usuario`.
- `id_hay_area` en `usuarios` sirve como área principal / cache.
- **Recomendación:** no eliminar aún; siempre leer áreas con `hay_eval_areas_usuario()`.

### 3. `grupos.id_profesor` vs `grupo_docente`

- Titular en `grupos`; asignaciones por materia en `grupo_docente`.
- **Recomendación:** mantener ambos; sincronizar titular al guardar docentes.

### 4. Calificaciones: `calificaciones_parcial` vs módulo `exam_*`

- Dos modelos (piloto por fase vs exámenes fusionados inglés).
- **Recomendación:** no unificar sin replantear producto; documentar cuál usa cada especialidad.

### 5. Documentos: `documento_*`, `expediente_*`, `certificacion_*`

- Propósitos distintos (diplomas/plantillas vs expediente laboral vs certificación SEP).
- **Recomendación:** compartir solo el helper de upload (`upload_security_helper.php`), no la tabla.

---

## Riesgos de estabilidad

1. **`plantel_ensure_column` en runtime** — Si dos requests ejecutan ALTER simultáneos, puede haber contención. Las migraciones SQL (`041`, `042`, …) reducen esto.
2. **FKs ausentes en tablas nuevas** — Muchas relaciones son lógicas (`id_grupo`, `id_usuario`) sin FOREIGN KEY. Mejora integridad referencial en tablas críticas nuevas; en legacy, añadir FKs con cuidado (datos huérfanos).
3. **Tabla `usuario_login_intento`** — Crecerá; programar purga (>90 días) en cron o script de mantenimiento.
4. **Uploads en `uploads/` bajo webroot** — Mitigado con `.htaccess`; ideal a largo plazo: servir vía PHP (`stream`) o bucket fuera de public_html.

---

## Seguridad (checklist aplicado / pendiente)

| Control | Estado |
|---------|--------|
| Contraseñas `password_hash` | OK |
| Recuperación por correo institucional | OK (`olvido_password.php`) |
| Bloqueo por intentos fallidos | OK (042 + `login_security_helper`) |
| Desbloqueo por coordinación/recepción | OK (`ver_usuarios` + API) |
| Validación MIME + magic bytes en uploads | OK (helper central) |
| Re-codificación de imágenes | OK (GD) |
| Bloqueo ejecución PHP en uploads | OK (`.htaccess`) |
| CSRF en formularios legacy | Parcial — revisar endpoints POST críticos |
| Prepared statements PDO | OK en código revisado |
| Secrets en repo | Verificar que `config.local.php` / `config.mail.php` no estén en git |

---

## ¿Conviene reducir tablas?

**No recomendado un “big merge”** sin congelar funcionalidad. Sí recomendado:

1. **Deprecar** tablas legacy no escritas (`prospectos_profesor` cuando aplique).
2. **Vistas SQL** para reportes que hoy hacen JOIN repetidos (nómina, grupos+docentes).
3. **Convención:** toda tabla nueva → migración numerada + FK opcional + índices explícitos.
4. **Inventario:** ejecutar `SHOW TABLES` en producción y marcar “activa / legacy / solo lectura”.

---

## Constantes de login (config.local.php opcional)

```php
define('LOGIN_MAX_INTENTOS', 5);
define('LOGIN_BLOQUEO_MINUTOS', 30);
define('LOGIN_IP_MAX_INTENTOS', 25);
define('LOGIN_IP_VENTANA_MINUTOS', 15);
```

---

## Próximos pasos sugeridos (prioridad)

1. Ejecutar migración `042_login_seguridad.sql` (o bootstrap usuario).
2. Purga programada de `usuario_login_intento`.
3. Plan de retiro de `prospectos_profesor`.
4. FK en `grupo_docente(id_grupo)`, `expediente_entrega`, etc.
5. Servir PDFs sensibles solo vía `*_stream.php` autenticado (expediente ya tiene stream).
