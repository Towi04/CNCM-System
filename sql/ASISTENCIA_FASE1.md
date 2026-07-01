# Asistencia — Fase 1 (huella, rondín, puntualidad)

## Arquitectura

| Canal | Quién | Alumnos | Personal (profesores) |
|--------|--------|---------|------------------------|
| **Terminal huella** | Lector U.areU en recepción | Sí (automático) | Sí (entrada/salida) |
| **Rondín recepción** | Recepción | Sí (número de control) | **No** |
| **Lista recepción** | Recepción + profesor (aula) | Manual faltantes | — |
| **Manual dirección** | `gerente`, `supervisor` | — | Corrección / sin lector |

### Flujo alumnos

1. **Checada con huella** — terminal en recepción; polling de eventos del lector.
2. **Rondín** — pantalla `asistencia_faltantes`: solo quienes **no checaron**; recepción confirma presencia sin interrumpir la clase.
3. Registro por **número de control** o botón **Presente** por alumno.

### `codigo_huella`

- ID interno del lector (normalmente = número de control del alumno).
- No se usa PIN manual en la terminal de checada.

## URLs

| Uso | URL |
|-----|-----|
| Panel asistencias | `dashboard.php` → Asistencias |
| Terminal huella | sección `asistencia_checada` |
| Rondín | sección `asistencia_faltantes` |
| Registros / corregir | sección `asistencia_registros` |
| Puntualidad HAY | sección `asistencia_puntualidad` |
| API lector fijo | `php/asistencia_huella_api.php` |
| API rondín | `php/asistencia_registros_api.php` → `registrar_recepcion` |

### Lector fijo (POST)

```
codigo_huella=12345
plantel_id=1
fecha_hora=2025-05-20 08:05:00   (opcional)
api_key=...                       (opcional, ver config)
```

### Rondín recepción (POST)

```
accion=registrar_recepcion
numero_control=10042
fecha=2025-05-20
```

O bien `id_alumno` + `id_grupo` opcional.

## Puntualidad (HAY Excel)

Compara `asistencia_personal.hora_llegada` vs primera clase del día (`grupo_horarios`).

| Código | Etiqueta Excel |
|--------|----------------|
| `10_min_antes` | 10 min antes |
| `a_la_hora` | A la hora |
| `5_min_tarde` | 5 min tarde |
| `mas_5_tarde` | Más de 5 min tarde |
| `sin_registro` | Sin checada |

## Permisos (RBAC)

| Capacidad | Roles |
|-----------|--------|
| `asistencia_checada` | recepción, admin, etc. |
| `asistencia_lista_grupo` | admin, gerente, profesor, supervisor |
| `asistencia_puntualidad` | admin, gerente, supervisor |
| `asistencia_eliminar_registro` | dirección |
| `asistencia_personal_manual` | gerente, supervisor |

## Configurar ID en lector (interfaz web)

| Dónde | Quién puede editar |
|--------|-------------------|
| **Alumno** → Información / enrolamiento huella | Recepción, dirección |
| **Usuario** → Editar usuario | Dirección / admin usuarios |
| **Mi perfil** | Cada empleado su propio ID (entrada en lector fijo) |

## Próximo (Fase 2)

- Retención por grupo/profesor.
- Enlace automático a puntos HAY en evaluación del periodo.
