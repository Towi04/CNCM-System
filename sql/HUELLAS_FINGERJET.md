# FingerJet — SDK comercial HID (planteles grandes)

Para planteles con **~600 alumnos activos** la comparación heurística en PHP **no es suficiente**. Se requiere el motor **FingerJet** del **U.are.U SDK Windows** de HID Global.

## Arquitectura

```
┌──────────────────── PC recepción (Windows) ────────────────────┐
│  U.areU 5300 → Lite Client → Navegador (HAY checada)          │
│       ↓ muestra                                                │
│  HayFingerprintMatcher :8765  ← FingerJet 1:N                  │
│       ↑ galería (sync cada 5 min)                              │
└───────┼────────────────────────────────────────────────────────┘
        │ HTTPS + MatcherKey
        ▼
┌──────────────────── Servidor HAY (PHP/MySQL) ──────────────────┐
│  huella_matcher_api.php → galería por plantel                  │
│  alumno_huellas (muestras + template_fmd FingerJet)            │
│  asistencia_checada_api.php → registra asistencia              │
└────────────────────────────────────────────────────────────────┘
```

## Qué NO se puede hacer

- Instalar FingerJet solo en el **servidor web Linux** — el SDK es Windows nativo
- Comparar huellas con precisión solo con JavaScript del navegador — HID no lo permite
- Descargar el SDK desde este repositorio — requiere **licencia/registro HID**

## Qué ya está listo en HAY

| Pieza | Archivo |
|-------|---------|
| Configuración | `config.local.php.example` |
| API galería | `php/huella_matcher_api.php` |
| Helper | `php/huella_matcher_helper.php` |
| Checada usa matcher local | `js/asistencia_checada.js` |
| Servicio Windows | `tools/hay_fingerprint_matcher/` |
| Columna FMD | `alumno_huellas.template_fmd` |

## Pasos para ponerlo en producción

### 1. Licencia SDK HID

1. [sdk.hidglobal.com](https://sdk.hidglobal.com/) → DigitalPersona SDK for Windows
2. Instalar en **cada PC de recepción** con lector U.areU

### 2. Servidor HAY

```bash
cp config.local.php.example config.local.php
# Editar claves y HAY_FINGERJET_MODE=required
```

### 3. PC recepción

Ver guía completa: [tools/hay_fingerprint_matcher/README.md](../tools/hay_fingerprint_matcher/README.md)

```powershell
cd tools\hay_fingerprint_matcher
dotnet run
```

### 4. Verificar

- `http://127.0.0.1:8765/health` → `gallery_count` > 0
- Checada con huella → «Identificando con FingerJet…»

### 5. Completar integración SDK (desarrollo)

En `Program.cs`, implementar `Identify()` con **DPUruNet** tras referenciar el DLL del SDK.

## Modos (`HAY_FINGERJET_MODE`)

| Modo | Uso |
|------|-----|
| `off` | Desactivado (solo heurística PHP) |
| `auto` | Matcher local primero; respaldo heurístico |
| `required` | **Recomendado ≥300 alumnos** — solo matcher local |

## Costos / consideraciones

- SDK HID: consultar precio con distribuidor HID / Crossmatch
- Una instancia del servicio por PC de recepción
- Re-enrolar alumnos existentes tras activar FMD mejora precisión (opcional; matcher puede usar muestras actuales)

## Relacionado

- [HUELLAS_UAREU.md](HUELLAS_UAREU.md) — Lite Client, enrolamiento, rondín
