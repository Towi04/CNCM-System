/**
 * Monitor de inactividad de sesión
 * Detecta inactividad del usuario y muestra una advertencia antes de cerrar la sesión
 * 
 * Características:
 * - Monitorea actividad del usuario (click, movimiento del mouse, tecla)
 * - Envía verificación de sesión cada 10 segundos
 * - Muestra advertencia cuando faltan 30 segundos para expirar
 * - Permite extender la sesión si el usuario responde
 * - Cierra automáticamente si no hay respuesta
 */

(function() {
    const CONFIG = {
        // Tiempos en milisegundos
        MAX_INACTIVITY_MS: 5 * 60 * 1000, // 5 minutos = 300,000 ms
        WARNING_TIME_MS: 30 * 1000,        // 30 segundos antes de expirar
        CHECK_INTERVAL_MS: 10 * 1000,      // Verificar cada 10 segundos
        ACTIVITY_DELAY_MS: 500,            // Debounce para eventos de actividad
        CHECK_ENDPOINT: 'php/session_inactivity.php'
    };

    let lastActivityTime = Date.now();
    let checkIntervalId = null;
    let warningShownTime = null;
    let isWarningVisible = false;
    let activityTimeout = null;

    /**
     * Registra una actividad del usuario
     */
    function recordActivity() {
        clearTimeout(activityTimeout);
        
        activityTimeout = setTimeout(() => {
            lastActivityTime = Date.now();
            
            // Ocultar advertencia si estaba visible
            if (isWarningVisible) {
                hideWarning();
            }
        }, CONFIG.ACTIVITY_DELAY_MS);
    }

    /**
     * Añade listeners para detectar actividad
     */
    function attachActivityListeners() {
        const events = ['mousedown', 'keydown', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, recordActivity, { passive: true });
        });
    }

    /**
     * Elimina listeners de actividad
     */
    function detachActivityListeners() {
        const events = ['mousedown', 'keydown', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.removeEventListener(event, recordActivity);
        });
    }

    /**
     * Verifica el estado de la sesión con el servidor
     */
    async function checkSessionStatus() {
        try {
            const response = await fetch(CONFIG.CHECK_ENDPOINT, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                if (response.status === 401) {
                    // Sesión expirada
                    handleSessionExpired();
                    return;
                }
                return;
            }

            const data = await response.json();

            if (data.status === 'session_expired') {
                handleSessionExpired();
                return;
            }

            if (data.status === 'ok') {
                const remainingTime = data.remaining_time;
                
                // Si quedan 30 segundos o menos, mostrar advertencia
                if (remainingTime <= CONFIG.WARNING_TIME_MS / 1000 && !isWarningVisible) {
                    showWarning(remainingTime);
                }
            }
        } catch (error) {
            console.warn('Error verificando sesión:', error);
        }
    }

    /**
     * Muestra la advertencia de sesión a punto de expirar
     */
    function showWarning(remainingSeconds) {
        isWarningVisible = true;
        warningShownTime = Date.now();

        // Crear modal si no existe
        let modal = document.getElementById('session-warning-modal');
        if (!modal) {
            modal = createWarningModal();
            document.body.appendChild(modal);
        }

        // Mostrar modal
        modal.style.display = 'flex';
        modal.classList.add('active');

        // Actualizar contador cada segundo
        const updateCounter = () => {
            const elapsed = Math.floor((Date.now() - warningShownTime) / 1000);
            const remaining = 30 - elapsed;
            
            const counterEl = document.getElementById('session-warning-countdown');
            if (counterEl) {
                counterEl.textContent = remaining;
            }

            // Si se agota el tiempo, cerrar sesión
            if (remaining <= 0) {
                forceLogout();
            }
        };

        // Actualizar inmediatamente
        updateCounter();
        
        // Actualizar cada segundo
        const counterId = setInterval(() => {
            if (!isWarningVisible) {
                clearInterval(counterId);
            } else {
                updateCounter();
            }
        }, 1000);
    }

    /**
     * Oculta la advertencia de sesión
     */
    function hideWarning() {
        isWarningVisible = false;
        const modal = document.getElementById('session-warning-modal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
        }
    }

    /**
     * Extiende la sesión
     */
    async function extendSession() {
        try {
            const response = await fetch(CONFIG.CHECK_ENDPOINT, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                if (data.status === 'ok') {
                    lastActivityTime = Date.now();
                    hideWarning();
                    console.log('Sesión extendida');
                }
            }
        } catch (error) {
            console.warn('Error extendiendo sesión:', error);
        }
    }

    /**
     * Cierra la sesión forzadamente
     */
    async function forceLogout() {
        detachActivityListeners();
        clearInterval(checkIntervalId);
        
        // Hacer logout en el servidor
        try {
            await fetch('index.php?salir=1', {
                method: 'GET',
                credentials: 'same-origin'
            });
        } catch (error) {
            console.warn('Error cerrando sesión:', error);
        }

        // Redirigir a login
        window.location.href = 'index.php?sesion=1';
    }

    /**
     * Maneja la expiración de sesión
     */
    function handleSessionExpired() {
        detachActivityListeners();
        clearInterval(checkIntervalId);
        hideWarning();
        
        // Redirigir a login con mensaje de sesión expirada
        window.location.href = 'index.php?sesion=1';
    }

    /**
     * Crea el modal de advertencia
     */
    function createWarningModal() {
        const modal = document.createElement('div');
        modal.id = 'session-warning-modal';
        modal.className = 'session-warning-modal';
        modal.innerHTML = `
            <div class="session-warning-content">
                <div class="session-warning-header">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2>¡Sesión a punto de expirar!</h2>
                </div>
                <div class="session-warning-body">
                    <p>Tu sesión expirará por inactividad en:</p>
                    <div class="session-warning-countdown">
                        <span id="session-warning-countdown" class="countdown-number">30</span>
                        <span class="countdown-label">segundos</span>
                    </div>
                    <p class="warning-message">Si no respondes, tu sesión se cerrará automáticamente por seguridad.</p>
                </div>
                <div class="session-warning-actions">
                    <button id="session-extend-btn" class="btn btn-primary" type="button">
                        <i class="fas fa-clock"></i> Sí, seguir activo
                    </button>
                    <button id="session-logout-btn" class="btn btn-secondary" type="button">
                        <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                    </button>
                </div>
            </div>
        `;

        // Event listeners para botones
        modal.querySelector('#session-extend-btn').addEventListener('click', () => {
            extendSession();
        });

        modal.querySelector('#session-logout-btn').addEventListener('click', () => {
            forceLogout();
        });

        return modal;
    }

    /**
     * Inicia el monitor de inactividad
     */
    function init() {
        attachActivityListeners();
        
        // Verificar sesión cada 10 segundos
        checkIntervalId = setInterval(() => {
            checkSessionStatus();
        }, CONFIG.CHECK_INTERVAL_MS);

        console.log('Monitor de inactividad iniciado');
    }

    /**
     * Detiene el monitor de inactividad
     */
    function destroy() {
        detachActivityListeners();
        clearInterval(checkIntervalId);
        clearTimeout(activityTimeout);
    }

    // Exponer funciones globales
    window.SessionInactivityMonitor = {
        init: init,
        destroy: destroy,
        extendSession: extendSession,
        forceLogout: forceLogout
    };

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
