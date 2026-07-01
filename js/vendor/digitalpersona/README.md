# SDK HID DigitalPersona (U.areU 5300)

## ¿Qué es cada cosa?

| Componente | Qué hace | Dónde se obtiene |
|------------|----------|------------------|
| **HID Authentication Device Client** (Lite Client) | Programa/servicio Windows que habla con el lector USB | [digitalpersona.hidglobal.com/lite-client](https://digitalpersona.hidglobal.com/lite-client/) |
| **WebSdk** (`websdk.client.ui.min.js` o `websdk/index.js`) | Biblioteca del navegador que se conecta al Lite Client | Sample oficial HID (no viene solo con el Lite Client) |
| **fingerprint.sdk.min.js** | API para capturar huellas desde la página web | npm `@digitalpersona/fingerprint` |

El **Lite Client ya instalado** cubre driver + servicio. Los **.js** son archivos aparte que debe tener el servidor web de HAY.

---

## Nota de HID: «no importes WebSdk en tu JS»

Esa advertencia **no** dice «no copies archivos al servidor».

Dice que **WebSdk debe cargarse con etiqueta `<script src="...">` en el HTML**, **antes** de su código de aplicación. El error común es hacer esto en un proyecto con webpack/vite:

```javascript
import WebSdk from 'WebSdk'; // ❌ incorrecto
```

En HAY lo correcto es (como en `views/alumno_huella_enroll.php`):

```html
<script src="js/vendor/digitalpersona/websdk.client.ui.min.js"></script>
<script src="js/vendor/digitalpersona/fingerprint.sdk.min.js"></script>
<script src="js/huella_uareu.js"></script>
```

Copiar los `.js` a `js/vendor/digitalpersona/` **sí es lo correcto**; lo que no debe hacer es empaquetarlos con `import`.

---

## Archivos en esta carpeta

| Archivo | Origen |
|---------|--------|
| `websdk.client.ui.min.js` | [Sample WebSdk](https://github.com/hidglobal/digitalpersona-sample-angularjs/tree/master/src/modules/WebSdk) → `index.js` (mismo contenido; puede renombrarse) |
| `websdk/index.js` | Copia alternativa del WebSdk del sample |
| `fingerprint.sdk.min.js` | [unpkg @digitalpersona/fingerprint](https://unpkg.com/@digitalpersona/fingerprint@1.0.0/dist/fingerprint.sdk.min.js) |

### Descarga manual (si faltan archivos)

**WebSdk** — descargue `index.js` desde GitHub y guárdelo como `websdk.client.ui.min.js`:

https://raw.githubusercontent.com/hidglobal/digitalpersona-sample-angularjs/master/src/modules/WebSdk/index.js

**Fingerprint SDK**:

https://unpkg.com/@digitalpersona/fingerprint@1.0.0/dist/fingerprint.sdk.min.js

---

## Verificación en la PC de recepción

1. Instale el **HID Authentication Device Client** (botón en HAY → Asistencias o Terminal de checada).
2. U.areU 5300 conectado por USB.
3. Chrome o Edge.
4. Cliente HID en ejecución (bandeja de Windows).

**Descarga:** enlace oficial en el sistema, o copia local opcional en `downloads/hid/` (ver `downloads/hid/README.md`).

Sin estos archivos, HAY permite **guardar el ID en lector** (`codigo_huella`) sin captura; recepción registra asistencia en **Rondín** con número de control.

Documentación: https://hidglobal.github.io/digitalpersona-devices/
