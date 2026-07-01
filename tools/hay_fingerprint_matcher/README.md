# HayFingerprintMatcher — FingerJet local (PC recepción)

Servicio Windows que corre **en la misma PC** donde está el lector U.areU 5300. Hace identificación **1:N** con el motor **FingerJet** del SDK comercial HID (soporta cientos de huellas por plantel).

HAY (servidor Linux/PHP) **no puede** ejecutar FingerJet directamente. Este servicio:

1. Sincroniza la galería de huellas desde HAY cada N minutos
2. Recibe la muestra capturada por el navegador (`POST /identify`)
3. Devuelve `codigo_huella` si hay coincidencia
4. El navegador registra la asistencia en HAY

```
Navegador (checada) → http://127.0.0.1:8765/identify
                   ← { codigo_huella }
Navegador → HAY php/asistencia_checada_api.php (registrar)
HayFingerprintMatcher ← sync → HAY php/huella_matcher_api.php (gallery)
```

---

## Requisitos

| Componente | Dónde |
|------------|--------|
| Windows 10/11 64 bits | PC de recepción |
| .NET 8 Runtime | [dotnet.microsoft.com](https://dotnet.microsoft.com/download/dotnet/8.0) |
| HID Lite Client | Ya instalado para el lector |
| **U.are.U SDK Windows** (FingerJet) | [sdk.hidglobal.com](https://sdk.hidglobal.com/) — licencia comercial HID |

---

## Paso 1 — Obtener el SDK de HID

1. Registrarse en [HID Developer Portal](https://sdk.hidglobal.com/)
2. Descargar **DigitalPersona SDK for Windows** (incluye FingerJet + DPUruNet)
3. Instalar en la PC de recepción (incluye runtime redistribuible)

Sin el SDK el servicio arranca en modo **heurístico** (igual que HAY en PHP) — no es fiable con ~600 alumnos.

---

## Paso 2 — Configurar HAY (servidor)

1. Copie `config.local.php.example` → `config.local.php` en la raíz de HAY
2. Ajuste:

```php
define('HAY_FINGERJET_ENABLED', true);
define('HAY_FINGERJET_MODE', 'required');  // plantel grande
define('HAY_FINGERJET_MATCHER_URL', 'http://127.0.0.1:8765');
define('HAY_FINGERJET_MATCHER_KEY', 'su-clave-secreta-larga');
```

3. Suba `config.local.php` al hosting (no lo suba a git público)

---

## Paso 3 — Configurar este servicio

Edite `appsettings.json`:

```json
{
  "Hay": {
    "BaseUrl": "https://cncmedum.edu.mx/hay",
    "MatcherKey": "misma-clave-que-config.local.php",
    "IdPlantel": 1,
    "GallerySyncMinutes": 5
  }
}
```

`IdPlantel`: 1=Salamanca, 2=Celaya, etc. (una instancia del servicio por PC/plantel).

---

## Paso 4 — Compilar y ejecutar

```powershell
cd tools\hay_fingerprint_matcher
dotnet restore
dotnet run
```

Pruebe: [http://127.0.0.1:8765/health](http://127.0.0.1:8765/health)

Debe mostrar `gallery_count` > 0 tras sincronizar.

### Instalar como servicio Windows (opcional)

Use NSSM o `sc create` para que inicie con Windows:

```powershell
dotnet publish -c Release -o C:\HAY\FingerprintMatcher
# Registrar C:\HAY\FingerprintMatcher\HayFingerprintMatcher.exe como servicio
```

---

## Paso 5 — Activar FingerJet en el código

1. En `HayFingerprintMatcher.csproj`, descomente la referencia a `DPUruNet.dll`
2. Defina constante de compilación `FINGERJET_SDK`
3. Complete `FingerJetEngine.Identify()` con API DPUruNet (ver documentación SDK, sección 1:N Identify)

Documentación SDK: carpeta `Docs` del instalador U.are.U SDK.

---

## API local

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/health` | Estado SDK + tamaño galería |
| POST | `/identify` | `{ "sample": "...", "id_plantel": 1 }` |
| POST | `/sync` | Forzar sincronización galería |

---

## Planteles múltiples

- **Una PC por plantel** → una instancia con `IdPlantel` distinto
- Misma clave `MatcherKey` en todas (o claves distintas si prefiere)

---

## Seguridad

- El servicio escucha solo en `127.0.0.1` (localhost)
- La clave `MatcherKey` protege la API de galería en el servidor HAY
- No exponga el puerto 8765 a la red

---

## Solución de problemas

| Síntoma | Acción |
|---------|--------|
| `gallery_count: 0` | Verifique MatcherKey, IdPlantel y que hay huellas enroladas |
| `sdk: false` | Instale U.are.U SDK y recompile con FINGERJET_SDK |
| Checada dice matcher no responde | Ejecute `dotnet run` en la PC de recepción |
| Identifica mal (<100 alumnos) | Modo heurístico; instale SDK |
| Identifica mal (>100 alumnos) | Obligatorio SDK FingerJet |
