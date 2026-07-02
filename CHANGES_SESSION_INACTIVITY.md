# ✅ Sistema de Logout por Inactividad - Implementado

## Resumen de cambios

### 📁 Archivos Creados

| Archivo | Descripción |
|---------|-------------|
| `php/session_inactivity.php` | Endpoint que verifica estado de sesión (5 min inactividad) |
| `js/session_inactivity.js` | Monitor cliente que detecta inactividad y muestra advertencia |
| `css/session_inactivity.css` | Estilos del modal de advertencia responsivo |
| `php/session_inactivity_helper.php` | Funciones helper para incluir monitor en otras vistas |
| `docs/SESSION_INACTIVITY.md` | Documentación completa del sistema |

### 📝 Archivos Modificados

| Archivo | Cambios |
|---------|---------|
| `dashboard.php` | ✅ Agregado CSS y JS del monitor en líneas 86 y 136 |

---

## 🔄 Flujo de funcionamiento

```
┌─────────────────────────────────────────────────────────┐
│  USUARIO INICIA SESIÓN → dashboard.php carga            │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│  Se cargan CSS y JS del monitor de inactividad          │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│  Monitor inicia: escucha click, tecla, scroll, touch    │
│  Verifica sesión cada 10 segundos con servidor         │
└──────────────────┬──────────────────────────────────────┘
                   │
        ┌──────────┴────────────┐
        │                       │
        ▼ (sin actividad)       ▼ (con actividad)
   ┌─────────────────┐    ┌──────────────────┐
   │ 5 MINUTOS SIN   │    │ Resetea contador │
   │ ACTIVIDAD       │    │ Sesión continúa  │
   └────────┬────────┘    └──────────────────┘
            │
            ▼
   ┌─────────────────────────────┐
   │ ⚠️  MODAL DE ADVERTENCIA    │
   │ "¿Sigues ahí?"              │
   │ Contador: 30 segundos       │
   │                             │
   │ [✅ Seguir] [❌ Cerrar]    │
   └──────┬──────────┬───────────┘
          │          │
    ┌─────▼──┐  ┌────▼─────┐
    │ CLICK  │  │ NO RESP. │
    │        │  │          │
    ▼        ▼  ▼          ▼
   ✅ Extiende ❌ Cierra sesión
   Sesión     Redirige a login
```

---

## ⚙️ Configuración

### Tiempos (en segundos)

| Concepto | Tiempo | Archivo |
|----------|--------|---------|
| **Máxima inactividad** | 5 minutos (300 seg) | `php/session_inactivity.php` L11 |
| **Advertencia** | 30 segundos antes | `js/session_inactivity.js` L18 |
| **Verificación servidor** | Cada 10 segundos | `js/session_inactivity.js` L19 |

### Para cambiar tiempos

**Si quieres 10 minutos en lugar de 5:**

1. **PHP** (`php/session_inactivity.php` línea 11):
```php
$MAX_INACTIVITY_TIME = 10 * 60; // cambiar de 5*60 a 10*60
```

2. **JavaScript** (`js/session_inactivity.js` línea 16):
```javascript
MAX_INACTIVITY_MS: 10 * 60 * 1000, // cambiar de 5*60*1000 a 10*60*1000
```

---

## 🎨 Modal de Advertencia

El modal que se muestra cuando faltan 30 segundos tiene:

```
┌──────────────────────────────────────┐
│  ⚠️  ¡Sesión a punto de expirar!    │  ← Header rojo
├──────────────────────────────────────┤
│                                      │
│  Tu sesión expirará en:              │
│           30 segundos                │  ← Contador grande
│                                      │
│  Si no respondes, la sesión se       │
│  cerrará automáticamente por         │
│  seguridad.                          │
│                                      │
│  [✅ Sí, seguir activo]              │  ← Verde (acción)
│  [❌ Cerrar sesión]                  │  ← Gris (alternativa)
│                                      │
└──────────────────────────────────────┘
```

### Características del modal:
- ✅ Responsive (funciona en móvil)
- ✅ Animaciones suaves (fade + slide)
- ✅ Icono Font Awesome
- ✅ Contador regresivo automático
- ✅ Botones intuitivos

---

## 🔒 Seguridad

- ✅ Validación en **servidor PHP** (no confiar solo en cliente)
- ✅ Cookies de sesión **httponly** (no accesibles desde JS)
- ✅ Cierre automático si no hay respuesta
- ✅ Monitorea múltiples eventos: click, tecla, scroll, touch
- ✅ Debounce para evitar actualizaciones excesivas

---

## 🧪 Pruebas

### Verificar que funcione:

1. **Inicia sesión** en el dashboard
2. **No hagas nada** durante 4 minutos y 30 segundos
3. Debería aparecer un **modal de advertencia**
4. El contador debería llegar a 0
5. Si no haces click, deberías ser **redirigido a login**

### En la consola (F12 → Console):

```javascript
// Ver objeto del monitor
window.SessionInactivityMonitor

// Forzar cierre
SessionInactivityMonitor.forceLogout()

// Extender manualmente
SessionInactivityMonitor.extendSession()
```

---

## 📱 Integración en otras vistas

Si necesitas agregar el monitor a otra página:

```php
<?php
require_once __DIR__ . '/php/session_inactivity_helper.php';
?>
<!DOCTYPE html>
<html>
<head>
    <?php session_inactivity_include_css(); ?>
    <!-- Otros CSS -->
</head>
<body>
    <!-- Contenido -->
    
    <?php session_inactivity_include_js(); ?>
</body>
</html>
```

---

## 📊 Respuesta de la API

El endpoint `php/session_inactivity.php` responde con:

```json
{
  "status": "ok",
  "remaining_time": 245,
  "inactivity_time": 55,
  "max_inactivity_time": 300
}
```

O si la sesión expiró:

```json
{
  "status": "session_expired",
  "message": "Su sesión ha expirado por inactividad",
  "inactivity_time": 305
}
```

---

## 🔍 Archivos de referencia

- 📄 **Documentación completa**: `docs/SESSION_INACTIVITY.md`
- 🛠️ **Code**: `php/session_inactivity.php`, `js/session_inactivity.js`, `css/session_inactivity.css`
- 🎯 **Integración**: Ver líneas 86 y 136 en `dashboard.php`

---

## ✨ Características implementadas

✅ **Cierre automático** después de 5 minutos  
✅ **Advertencia amigable** 30 segundos antes  
✅ **Opción de extender** sesión  
✅ **Validación servidor** (seguridad)  
✅ **Detección eventos**: click, tecla, scroll, touch  
✅ **Responsive design**  
✅ **Animaciones suaves**  
✅ **Mensajes en español**  

---

**Implementación completada ✨**
