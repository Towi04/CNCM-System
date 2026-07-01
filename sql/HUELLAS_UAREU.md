# Huellas digitales — U.areU 5300 + rondín recepción

## Resumen

| Escenario | Cómo checa el alumno |
|-----------|----------------------|
| **Normal** | Huella en lector U.areU 5300 en recepción |
| **Sin huella / lector falla / entró directo al salón** | Recepción registra en **Rondín de asistencia** con **número de control** |

La huella es **opcional** al inscribir: si el alumno no quiere registrarla, recepción marca su asistencia manualmente en el rondín.

---

## Hardware: U.areU 5300

- Modelo: **U.areU 5300** (Part 50019-001-101, I.T.E. E254976)
- Conexión: USB a PC Windows en recepción
- Driver: incluido con **HID Authentication Device Client** (Lite Client)

---

## Instalación en la PC de recepción (Windows)

### 1. Driver y Lite Client

1. [HID Authentication Device Client](https://digitalpersona.hidglobal.com/lite-client/) — servicio Windows + driver
2. Conecte el U.areU 5300 por USB
3. Verifique en Administrador de dispositivos que aparece como lector HID/DigitalPersona
4. El Lite Client debe quedar **ejecutándose** (bandeja de Windows)

### 2. Archivos JavaScript para el navegador

| Archivo | De dónde sacarlo |
|---------|------------------|
| `websdk.client.ui.min.js` | [Sample WebSdk de HID](https://github.com/hidglobal/digitalpersona-sample-angularjs/tree/master/src/modules/WebSdk) |
| `fingerprint.sdk.min.js` | [unpkg @digitalpersona/fingerprint](https://unpkg.com/@digitalpersona/fingerprint@1.0.0/dist/fingerprint.sdk.min.js) |

Ver `js/vendor/digitalpersona/README.md`.

### 3. Navegador

- Chrome o Edge (recomendado)
- Lite Client en ejecución

---

## Flujo en HAY

### Después de inscribir un alumno

1. Inscripción al grupo → ticket de pago (si aplica)
2. Pantalla «Registrar huella digital» (se puede **omitir**)
3. Si captura: 2 lecturas en U.areU; ID en lector = **número de control** (ej. 10016)

### Checada en recepción (con lector)

Terminal **Checada con huella** — el navegador captura la huella con el SDK y la identifica contra las plantillas enroladas en HAY (`alumno_huellas`).

Flujo:
1. Lite Client en ejecución + U.areU conectado
2. La pantalla inicia captura automática al detectar el lector
3. Al colocar el dedo: «Huella detectada. Identificando…»
4. Si coincide → asistencia registrada con datos del alumno
5. Si no coincide → mensaje «Huella no reconocida» con enlace al Rondín

API alternativa (lector fijo / puente local):

```
POST php/asistencia_huella_api.php
codigo_huella=10016
plantel_id=1
```

El **User ID** enrolado en el lector debe coincidir con `codigo_huella` en HAY (normalmente = número de control).

### Rondín — sin huella

1. Menú → **Rondín de asistencia** (`asistencia_faltantes`)
2. Lista solo alumnos que **no checaron** hoy, agrupados por salón/grupo
3. Recepción recorre salones y pulsa **Presente** o ingresa **número de control**
4. Origen del registro: `recepcion`

---

## Configuración opcional (`config.local.php`)

```php
define('HAY_HUELLA_API_KEY', 'clave-secreta-para-lector');
define('HAY_DP_WEBSDK_JS', 'js/vendor/digitalpersona/websdk.client.ui.min.js');
define('HAY_DP_FINGERPRINT_JS', 'js/vendor/digitalpersona/fingerprint.sdk.min.js');
```

---

## Base de datos

- `alumnos.codigo_huella` — ID interno en el lector (normalmente = número de control)
- `alumnos.huella_registrada` — 1 si se capturó con U.areU
- `alumno_huellas` — templates (formato intermediate) para respaldo
- `asistencias.origen` — `huella`, `recepcion`, `movil` (histórico)

---

## Relacionado

- **[FingerJet / SDK comercial (planteles grandes)](HUELLAS_FINGERJET.md)** — identificación 1:N con ~600 alumnos

| Problema | Acción |
|----------|--------|
| «Lector no disponible» | Lite Client en ejecución; verificar `.js` en vendor; Ctrl+F5 |
| StartAcquisition: 80070057 | El lector está ocupado. Salga de «Checada con huella» antes de registrar huella, o espere unos segundos |
| Huella no reconocida al checar | Vuelva a capturar con **3 lecturas del mismo dedo** en Inscripción → Registrar huella |
| Solo guardó ID sin captura | El botón «Guardar ID sin capturar» no basta para checada; debe usar las 3 lecturas |
| Huella no checa | Verificar ID en lector = número de control en ficha |
| Alumno sin huella | Rondín de asistencia con número de control |
| ID duplicado | Cada alumno debe tener ID único por plantel |
