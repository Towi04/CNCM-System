# Tutor Académico Institucional — Documentación técnica

Módulo de tutor virtual con IA (OpenRouter) integrado al sistema HAY CNCM. Prioriza el **temario institucional** antes que el conocimiento general del modelo.

---

## Estructura de carpetas

```
hay_system/
├── css/tutor.css                    # UI del chat
├── js/tutor.js                      # Cliente del chat
├── docs/TUTOR_ACADEMICO.md          # Este documento
├── sql/migrations/
│   ├── 018_tutor_academico_schema.sql
│   └── 019_grupos_tutor.sql         # grupos.id_tutor
├── php/
│   ├── tutor_helper.php             # Bootstrap, permisos, CSRF
│   ├── tutor_api.php                # API REST panel HAY
│   ├── tutor_moodle_api.php         # API REST Moodle / iframe
│   └── tutor/
│       ├── TutorRepository.php
│       ├── ConversationRepository.php
│       ├── MessageRepository.php
│       ├── AiLogRepository.php
│       ├── AcademicContextRetriever.php   # RAG básico (keyword)
│       ├── MaterialContextRetriever.php   # Libros, workbook, Moodle indexados
│       ├── AIService.php                  # OpenRouter + logs
│       ├── TutorAccess.php                # Acceso por rol y grupo
│       ├── TutorSeeder.php                # Tutores iniciales
│       └── TutorService.php               # Orquestación
└── views/tutor_chat.php             # Interfaz principal
```

---

## Base de datos

| Tabla | Descripción |
|-------|-------------|
| `tutor_tutores` | Catálogo de tutores (nombre, especialidad, system prompt) |
| `tutor_conversaciones` | Sesiones por usuario |
| `tutor_mensajes` | Mensajes user/assistant |
| `tutor_ia_logs` | Auditoría de prompts, respuestas, tokens y costo |
| `grupos.id_tutor` | Tutor asignado al crear el grupo (según especialidad) |

**Contenido académico reutilizado (existente):**

- `especialidades` — programas / cursos
- `especialidad_fases` — parciales
- `fase_temario_semana` — lecciones semanales
- `alumnos.perfil_*` — gustos del alumno (onboarding)
- `academico_material` — libros, workbook y Moodle indexados para RAG

Ver **docs/TUTOR_MATERIALES_Y_RAG.md** para importar PDFs y Moodle.

La migración `018_tutor_academico_schema.sql` se aplica automáticamente vía `hay_schema_aplicar_migraciones()` y `tutor_ensure_schema()`.

---

## Tutores precargados

| Tutor | Especialidad | Uso |
|-------|--------------|-----|
| Teacher Emma | `ingles` | Inglés adulto / CEFR |
| Tech Mentor | `computacion` | Computación |
| Academic Coach | `preparatoria` | Preparatoria |
| Assistant | `general` | Apoyo HAY / transversal |

Kids y futuras especialidades se cubren en RAG filtrando `especialidades.modalidad = 'kids'` cuando el tutor o la pregunta lo requieren.

---

## Configuración OpenRouter

En `config.local.php`:

```php
define('HAY_AI_PROVIDER', 'openrouter');
define('OPENROUTER_API_KEY', 'sk-or-v1-...');
define('OPENROUTER_MODEL', 'openai/gpt-4o');

/** Opcional: modelo dedicado al tutor */
define('OPENROUTER_TUTOR_MODEL', 'openai/gpt-4o');
define('OPENROUTER_TUTOR_MAX_TOKENS', 1800);

/** API Moodle (iframe / servicios externos) */
define('TUTOR_MOODLE_API_KEY', 'clave-larga-secreta-para-moodle');
```

Prueba de conexión:

```bash
php php/ai_test_conexion.php
```

---

## Instalación

1. Subir archivos del módulo al servidor.
2. Configurar `OPENROUTER_API_KEY` en `config.local.php`.
3. Entrar al panel HAY (como admin) para ejecutar migraciones, o forzar una vez:
   ```php
   define('HAY_RUN_SCHEMA_BOOTSTRAP', true);
   ```
   en el login / `dashboard.php`, luego quitar la línea.
4. Entrar al panel HAY (como admin) para ejecutar migraciones, o forzar una vez:
   ```php
   define('HAY_RUN_SCHEMA_BOOTSTRAP', true);
   ```
   en el login / `dashboard.php`, luego quitar la línea.
5. Abrir **Académico → Tutor IA** (staff), **Mi portal → Tutor IA** (alumno), o el ítem en el flyout **Grupos** (profesor/coordinador).

**Iframe Moodle (futuro):**

```
https://cncm.edu.mx/hay/views/tutor_chat.php?embed=1
```

(Requiere sesión HAY activa; la API Moodle usa `tutor_moodle_api.php`.)

---

## Flujo RAG básico

```
Pregunta del usuario
    ↓
AcademicContextRetriever::buscar()
    → Detecta intención (temario/semana/fase/inglés…)
    → Extrae número de semana ("primera semana" → 1)
    → Contexto del alumno (grupo + fase actual) si aplica
    → Consulta fase_temario_semana, especialidad_fases, especialidades
    ↓
AIService::construirPrompt() — system + temario CNCM + reglas estrictas
    ↓
hay_openrouter_chat() → OpenRouter
```

**Fuentes de datos:** `especialidades`, `especialidad_fases` (objetivo, temas), `fase_temario_semana` (vocabulario, gramática, listening, etc.).

**Prioridad:** Si existe [LECCIÓN SEMANAL OFICIAL] en el contexto, el tutor debe usar ese contenido y no sustituirlo por un programa genérico.

---

## API REST — Panel HAY (`php/tutor_api.php`)

Autenticación: sesión PHP (`$_SESSION['user_id']`).  
CSRF obligatorio en POST (`csrf` o header `X-Tutor-CSRF`).

| Acción | Método | Parámetros | Respuesta |
|--------|--------|------------|-----------|
| `csrf` | GET | — | `{ status, csrf }` |
| `tutores` | GET | `especialidad?` | `{ status, tutores[], ia_configurada, solo_un_tutor }` |
| `conversaciones` | GET | — | `{ status, conversaciones[] }` |
| `conversacion` | GET | `id_conversacion` | `{ status, conversacion, mensajes[] }` |
| `crear` | POST | `id_tutor`, `csrf` | `{ status, id_conversacion, tutor }` |
| `mensaje` | POST | `id_conversacion`, `mensaje`, `csrf` | `{ status, respuesta, model, tokens }` |

Ejemplo enviar mensaje:

```javascript
const fd = new FormData();
fd.append('action', 'mensaje');
fd.append('csrf', token);
fd.append('id_conversacion', '12');
fd.append('mensaje', '¿Qué vocabulario veo en la semana 2 del parcial F1?');
const r = await fetch('php/tutor_api.php', { method: 'POST', body: fd, credentials: 'include' });
```

---

## API REST — Moodle (`php/tutor_moodle_api.php`)

Autenticación:

```
Authorization: Bearer {TUTOR_MOODLE_API_KEY}
```

Incluir `id_usuario` (ID en tabla `usuarios` de HAY) en body JSON, POST o header `X-Tutor-User-Id`.

| Acción | Descripción |
|--------|-------------|
| `auth` | Valida clave + usuario |
| `tutores` | Lista tutores |
| `crear` | `{ id_tutor, id_usuario }` |
| `mensaje` | `{ id_conversacion, id_usuario, mensaje }` |
| `historial` | GET `id_conversacion` + `id_usuario` |

Ejemplo cURL:

```bash
curl -X POST "https://cncm.edu.mx/hay/php/tutor_moodle_api.php?action=mensaje" \
  -H "Authorization: Bearer SU_CLAVE_MOODLE" \
  -H "Content-Type: application/json" \
  -d '{"id_usuario":42,"id_conversacion":5,"mensaje":"Explícame present simple"}'
```

---

## Seguridad

- **Prepared statements** en todos los repositorios.
- **CSRF** en API del panel (`tutor_csrf_validate`).
- **XSS**: mensajes de usuario con `strip_tags`; respuestas assistant renderizadas con Markdown (marked.js) — el servidor no ejecuta HTML del usuario.
- **Sesión** obligatoria en `tutor_api.php`.
- **API Moodle** con clave dedicada (`TUTOR_MOODLE_API_KEY`).
- **Sanitización** `tutor_sanitize_text()` — máx. 4000 caracteres.
- **Logs IA** en `tutor_ia_logs` para auditoría.

---

## Acceso por rol y grupo

Al dar de alta un grupo (`grupo_save.php`, grupos infantiles en `grupo_clave_helper.php`) o inscribir un alumno, se asigna automáticamente `grupos.id_tutor` según la especialidad del grupo.

| Rol | Tutores visibles |
|-----|------------------|
| **Alumno** | Solo el tutor del grupo(s) activo(s) en los que está inscrito |
| **Profesor** | Tutores de las especialidades de los grupos que imparte |
| **Supervisor / admin / director / coordinador** | Todos |
| **Asesor, gerente, recepción, caja** | Solo **Assistant** (`general`) |

La API valida el acceso en `crear` y `mensaje`. Un alumno de informática no puede abrir conversación con el tutor de inglés si su grupo no lo tiene asignado.

Clase principal: `HayTutor\TutorAccess`.

---

## Menú lateral

- **Alumno:** Mi portal → **Tutor IA** (si tiene grupo activo con tutor asignado).
- **Profesor / coordinador:** flyout **Grupos** → **Tutor IA**.
- **Staff académico:** sección **Académico** → **Tutor IA**.

La visibilidad usa el callback `tutor_puede_usar()` (no depende de caps RBAC sincronizados en BD).

---

## RBAC

| Privilegio | Descripción |
|------------|-------------|
| `menu_tutor` | Legacy: ítem de menú (opcional si se usa callback) |
| `tutor_usar` | Legacy: usar el chat |
| `tutor_administrar` | Futuro: editar tutores (admin) |

El acceso efectivo lo determina `TutorAccess` + `tutor_puede_usar()`.

---

## Evolución futura

- Embeddings + búsqueda vectorial (pgvector / Pinecone).
- Sincronización bidireccional con cursos Moodle.
- Voz (Web Speech API / TTS) y avatar.
- Panel admin para editar tutores e instrucciones.
- Tutor dedicado Kids con prompt infantil.

---

## Clase AIService — métodos

| Método | Función |
|--------|---------|
| `enviarMensaje()` | Llama OpenRouter con system + contexto + historial |
| `recuperarContexto()` | RAG keyword sobre temario |
| `construirPrompt()` | Arma array `messages` |
| `calcularTokens()` | Estimación tokens |
| `estimarCosto()` | Costo aproximado USD |
| `registrarLog()` | Insert en `tutor_ia_logs` |

Cambiar modelo: `OPENROUTER_TUTOR_MODEL` o `OPENROUTER_MODEL` en config.
