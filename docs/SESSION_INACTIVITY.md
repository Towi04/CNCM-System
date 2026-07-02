# Sistema de Logout por Inactividad

## Descripción

Este sistema implementa un monitor de inactividad de sesión que cierra automáticamente la sesión del usuario después de **5 minutos de inactividad** por motivos de seguridad.

## Características

✅ **Monitoreo automático de inactividad** - Detecta la falta de actividad del usuario  
✅ **Advertencia amigable** - Muestra un modal elegante 30 segundos antes de cerrar  
✅ **Extensión de sesión** - El usuario puede extender la sesión clickeando un botón  
✅ **Logout automático** - Si no hay respuesta en 30 segundos, se cierra la sesión  
✅ **Eventos detectados**:
  - Click del mouse
  - Pulsación de tecla
  - Scroll
  - Touch (dispositivos móviles)

## Componentes

### 1. `php/session_inactivity.php`
Endpoint que valida el estado de la sesión y retorna el tiempo restante de actividad.

**Respuesta JSON:**
```json
{
  "status": "ok",
  "remaining_time": 245,
  "inactivity_time": 55,
  "max_inactivity_time": 300
}
```

### 2. `js/session_inactivity.js`
Script cliente que:
- Monitorea actividad del usuario
- Verifica sesión cada 10 segundos
- Muestra advertencia cuando quedan 30 segundos
- Permite extender o cerrar sesión

### 3. `css/session_inactivity.css`
Estilos del modal de advertencia con diseño responsivo y animaciones.

### 4. `php/session_inactivity_helper.php`
Funciones helper para incluir fácilmente los recursos en vistas.

## Configuración

### Tiempos (en `js/session_inactivity.js`)

```javascript
const CONFIG = {
    MAX_INACTIVITY_MS: 5 * 60 * 1000,  // 5 minutos = 300,000 ms
    WARNING_TIME_MS: 30 * 1000,         // 30 segundos antes de expirar
    CHECK_INTERVAL_MS: 10 * 1000,       // Verificar cada 10 segundos
    ACTIVITY_DELAY_MS: 500,             // Debounce para eventos
    CHECK_ENDPOINT: 'php/session_inactivity.php'
};
```

Para cambiar los tiempos, edita estas constantes en `js/session_inactivity.js`:

- **MAX_INACTIVITY_MS**: Tiempo máximo de inactividad antes de cerrar
- **WARNING_TIME_MS**: Tiempo de advertencia antes del cierre
- **CHECK_INTERVAL_MS**: Frecuencia de verificación con el servidor

### Tiempos en PHP (en `php/session_inactivity.php`)

```php
$MAX_INACTIVITY_TIME = 5 * 60; // 300 segundos = 5 minutos
```

Estos valores deben coincidir entre PHP y JavaScript.

## Integración

### En dashboard.php (Ya incluido)

```php
<!-- En el HEAD -->
<link rel="stylesheet" href="<?php echo hay_asset_url('css/session_inactivity.css'); ?>">

<!-- Antes de </body> -->
<script src="<?php echo hay_asset_url('js/session_inactivity.js'); ?>"></script>
```

### En otras vistas

Si deseas agregar el monitor a otras vistas, usa el helper:

**En el HEAD:**
```php
<?php 
require_once __DIR__ . '/php/session_inactivity_helper.php';
session_inactivity_include_css();
?>
```

**Antes de </body>:**
```php
<?php
require_once __DIR__ . '/php/session_inactivity_helper.php';
session_inactivity_include_js();
?>
```

O en una sola línea:
```php
<?php
require_once __DIR__ . '/php/session_inactivity_helper.php';
session_inactivity_include('head');  // Para CSS
session_inactivity_include('footer'); // Para JS
```

## Flujo de ejecución

```
1. Usuario inicia sesión → dashboard.php carga
   ↓
2. Se cargan CSS y JS de inactividad
   ↓
3. Monitor JS inicia y escucha eventos de actividad
   ↓
4. Cada 10 segundos: Verifica sesión con servidor
   ↓
5. Si no hay actividad en 5 minutos:
   ├─ Muestra modal: "¿Sigues ahí?"
   ├─ Cuenta regresiva: 30 segundos
   │
   ├─ Si hace click en "Sí, seguir activo":
   │  └─ Extiende sesión (resetea contador)
   │
   └─ Si expira (30 seg sin respuesta):
      └─ Cierra sesión y redirige a login
```

## Modal de Advertencia

El modal muestra:
- ⚠️ Ícono de advertencia
- Título: "¡Sesión a punto de expirar!"
- Contador regresivo: 30 segundos
- Botón verde: "Sí, seguir activo" - Extiende la sesión
- Botón gris: "Cerrar sesión" - Cierra inmediatamente

### Estilos

El modal tiene:
- Fondo oscuro con overlay
- Animaciones suaves (fade + slide)
- Responsive (funciona en móvil)
- Gradientes y sombras profesionales
- Colores: rojo en header (peligro), verde en botón de acción

## Notas de Seguridad

⚠️ **Importante:**
- El monitoreo se realiza tanto en cliente (JS) como en servidor (PHP)
- La sesión PHP se valida en cada verificación
- Si se destruye manualmente la sesión, el JS redirecciona a login
- Las cookies de sesión son httponly (no accesibles desde JS)

## Personalizaciones

### Cambiar tiempo de inactividad a 10 minutos

1. **PHP** (`php/session_inactivity.php`):
```php
$MAX_INACTIVITY_TIME = 10 * 60; // 600 segundos
```

2. **JavaScript** (`js/session_inactivity.js`):
```javascript
MAX_INACTIVITY_MS: 10 * 60 * 1000, // 600,000 ms
```

### Cambiar tiempo de advertencia a 1 minuto

En `js/session_inactivity.js`:
```javascript
WARNING_TIME_MS: 60 * 1000, // 60 segundos en lugar de 30
```

### Personalizar estilos del modal

Edita `css/session_inactivity.css`:
- Colores: Busca `#f5365c` (rojo), `#4CAF50` (verde)
- Fuente: Busca `font-family:`
- Animaciones: Busca `@keyframes`

## Pruebas

### Simular inactividad

1. Abre la consola: F12 → Console
2. Ejecuta:
```javascript
// Forzar mostrar advertencia
SessionInactivityMonitor.forceLogout();
```

3. O modifica el tiempo en la consola:
```javascript
// Acelerar verificación (para pruebas)
// Modifica CHECK_INTERVAL_MS a 1 segundo (1000 ms)
```

### Verificar estado de sesión

```bash
curl http://tu-app.local/php/session_inactivity.php
```

## Troubleshooting

### El modal no aparece
- Verifica que `session_inactivity.js` esté cargado
- Abre la consola (F12) y busca errores
- Verifica que `session_inactivity.php` sea accesible

### La sesión no se cierra
- Verifica que `php/logout.php` sea accesible
- Revisa que la sesión PHP sea válida
- Comprueba que `hay_session_destroy_completa()` funcione

### El contador regresivo no cambia
- Verifica en la consola: `window.SessionInactivityMonitor`
- Comprueba que el archivo CSS esté cargado
- Revisa que Font Awesome esté disponible (para el ícono)

## Archivos creados

```
/workspaces/CNCM-System/
├── php/
│   ├── session_inactivity.php          ← Endpoint de verificación
│   └── session_inactivity_helper.php   ← Funciones helper
├── js/
│   └── session_inactivity.js           ← Monitor cliente
├── css/
│   └── session_inactivity.css          ← Estilos
└── dashboard.php                        ← Modificado (incluye monitor)
```

## Version

Versión: 1.0
Fecha: 2026-01-15

## Soporte

Para problemas o sugerencias, revisa:
1. Consola del navegador (F12 → Console)
2. Logs del servidor PHP
3. Headers HTTP de respuesta del endpoint
