<?php
/** Raíz del proyecto HAY en disco (carpeta que contiene config.php, vendor/, php/). */
if (!defined('HAY_ROOT')) {
    define('HAY_ROOT', __DIR__);
}

/** Dominio del correo institucional (@usuario.cncm.edu.mx) */
define('INSTITUTIONAL_EMAIL_DOMAIN', 'cncm.edu.mx');
/** Debe coincidir con SMTP_USER en config.mail.php (cuenta Google Workspace que envía) */
define('APP_FROM_EMAIL', 'noreply@cncm.edu.mx');
require_once __DIR__ . '/php/branding.php';
if (!defined('APP_FROM_NAME')) {
    define('APP_FROM_NAME', APP_DISPLAY_NAME);
}

/** Opcional: clave para php/asistencia_huella_api.php (lector fijo). Vacío = sin clave. */
if (!defined('HAY_HUELLA_API_KEY')) {
    define('HAY_HUELLA_API_KEY', '');
}

/** Rutas SDK DigitalPersona / U.areU 5300 (copiar archivos a js/vendor/digitalpersona/). */
if (!defined('HAY_DP_WEBSDK_JS')) {
    define('HAY_DP_WEBSDK_JS', 'js/vendor/digitalpersona/websdk.client.ui.min.js');
}
if (!defined('HAY_DP_FINGERPRINT_JS')) {
    define('HAY_DP_FINGERPRINT_JS', 'js/vendor/digitalpersona/fingerprint.sdk.min.js');
}
if (!defined('HAY_DP_LITE_CLIENT_URL')) {
    define('HAY_DP_LITE_CLIENT_URL', 'https://digitalpersona.hidglobal.com/lite-client/');
}
/** Opcional: ruta relativa a un .exe local en el servidor (ej. downloads/hid/HID Authentication Device Client.exe). */
if (!defined('HAY_DP_LITE_CLIENT_LOCAL')) {
    define('HAY_DP_LITE_CLIENT_LOCAL', '');
}

/** Raíz URL de la aplicación en el hosting (ej. '/hay/' en subcarpeta). Vacío = detección automática. */
if (!defined('HAY_WEB_ROOT')) {
    define('HAY_WEB_ROOT', '');
}

/** FingerJet — servicio local en PC de recepción (ver config.local.php.example). */
if (!defined('HAY_FINGERJET_ENABLED')) {
    define('HAY_FINGERJET_ENABLED', false);
}
if (!defined('HAY_FINGERJET_MODE')) {
    define('HAY_FINGERJET_MODE', 'auto');
}
if (!defined('HAY_FINGERJET_MATCHER_URL')) {
    define('HAY_FINGERJET_MATCHER_URL', 'http://127.0.0.1:8765');
}
if (!defined('HAY_FINGERJET_MATCHER_KEY')) {
    define('HAY_FINGERJET_MATCHER_KEY', '');
}

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

/** Sesión unificada (misma ruta de guardado que login/dashboard). */
require_once __DIR__ . '/php/session_helper.php';
hay_session_start();

if (is_file(__DIR__ . '/config.mail.php')) {
    require __DIR__ . '/config.mail.php';
} else {
    define('MAIL_DRIVER', 'php');
}

require_once __DIR__ . '/php/db_config_helper.php';
require_once __DIR__ . '/php/hay_schema_migrate.php';
$creds = hay_db_credentials();
$host = $creds['host'];
$db   = $creds['db'];
$user = $creds['user'];
$pass = $creds['pass'];
$charset = 'utf8mb4';

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    /** @var PDO $pdo */
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $GLOBALS['pdo'] = $pdo;
} catch (PDOException $e) {
    die("Error de conexion: " . $e->getMessage());
}

require_once __DIR__ . '/php/encoding_helper.php';
require_once __DIR__ . '/php/rbac_jerarquia_helper.php';
require_once __DIR__ . '/php/rbac_helper.php';
require_once __DIR__ . '/php/rbac_db_helper.php';
require_once __DIR__ . '/php/plantel_helper.php';
require_once __DIR__ . '/php/avatar_helper.php';
require_once __DIR__ . '/php/catalog_helper.php';
require_once __DIR__ . '/php/preregistro_helper.php';
require_once __DIR__ . '/php/alumno_helper.php';
require_once __DIR__ . '/php/alumno_perfil_helper.php';
require_once __DIR__ . '/php/academico_libro_helper.php';
require_once __DIR__ . '/php/moodle_material_helper.php';
require_once __DIR__ . '/php/asistencia_helper.php';
require_once __DIR__ . '/php/huella_helper.php';
require_once __DIR__ . '/php/huella_matcher_helper.php';
require_once __DIR__ . '/php/pago_helper.php';
require_once __DIR__ . '/php/pago_supervisor_helper.php';
require_once __DIR__ . '/php/fase_helper.php';
require_once __DIR__ . '/php/notificaciones_helper.php';
require_once __DIR__ . '/php/operativo_panel_helper.php';
require_once __DIR__ . '/php/colegiatura_combo_helper.php';
require_once __DIR__ . '/php/alumno_tarifa_supervisor_helper.php';
require_once __DIR__ . '/php/grupo_clave_helper.php';
require_once __DIR__ . '/php/grupo_docente_helper.php';
require_once __DIR__ . '/php/grupo_plan_helper.php';
require_once __DIR__ . '/php/academico_helper.php';
require_once __DIR__ . '/php/calificaciones_helper.php';
require_once __DIR__ . '/php/ubicacion_helper.php';
require_once __DIR__ . '/php/inscripcion_flow_helper.php';
require_once __DIR__ . '/php/calendario_helper.php';
require_once __DIR__ . '/php/grupo_avance_helper.php';
require_once __DIR__ . '/php/grupo_apertura_helper.php';
require_once __DIR__ . '/php/gemini_helper.php';
require_once __DIR__ . '/php/openrouter_helper.php';
require_once __DIR__ . '/php/ai_helper.php';
require_once __DIR__ . '/php/tutor_helper.php';
require_once __DIR__ . '/php/planeacion_helper.php';
require_once __DIR__ . '/php/planeacion_prompt_helper.php';
require_once __DIR__ . '/php/asesoria_helper.php';
require_once __DIR__ . '/php/asesoria_tabulador_helper.php';
require_once __DIR__ . '/php/asesoria_credito_helper.php';
require_once __DIR__ . '/php/asesoria_agenda_helper.php';
require_once __DIR__ . '/php/asesoria_moodle_helper.php';
require_once __DIR__ . '/php/asesoria_nomina_helper.php';
require_once __DIR__ . '/php/moodle_inscripcion_helper.php';
require_once __DIR__ . '/php/graduacion_helper.php';
require_once __DIR__ . '/php/profesor_portal_helper.php';
require_once __DIR__ . '/php/docente_prospecto_helper.php';
require_once __DIR__ . '/php/expediente_documental_helper.php';
require_once __DIR__ . '/php/profesor_eval_helper.php';
require_once __DIR__ . '/php/profesor_360_helper.php';
require_once __DIR__ . '/php/hay_eval_helper.php';
require_once __DIR__ . '/php/hay_area_multi_helper.php';
require_once __DIR__ . '/php/reporte_academico_helper.php';
require_once __DIR__ . '/php/reporte_semanal_helper.php';
require_once __DIR__ . '/php/reporte_financiero_helper.php';
require_once __DIR__ . '/php/reporte_cartera_helper.php';
require_once __DIR__ . '/php/nomina_helper.php';
require_once __DIR__ . '/php/suplencia_helper.php';
require_once __DIR__ . '/php/documento_helper.php';
require_once __DIR__ . '/php/venta_producto_helper.php';
require_once __DIR__ . '/php/certificacion_campos_helper.php';
require_once __DIR__ . '/php/asesor_helper.php';
require_once __DIR__ . '/php/comision_cert_helper.php';
require_once __DIR__ . '/php/referido_helper.php';
require_once __DIR__ . '/php/reporte_inscritos_helper.php';
require_once __DIR__ . '/php/reporte_presentados_helper.php';
require_once __DIR__ . '/php/grupo_preinicio_helper.php';
require_once __DIR__ . '/php/escuelas_helper.php';
require_once __DIR__ . '/php/ventas_comision_helper.php';
require_once __DIR__ . '/php/certificacion_helper.php';
require_once __DIR__ . '/php/aula_helper.php';
require_once __DIR__ . '/php/rol_aula_helper.php';
require_once __DIR__ . '/php/cronologia_helper.php';
require_once __DIR__ . '/php/grupo_fusion_helper.php';
require_once __DIR__ . '/php/alumno_grupo_acciones_helper.php';
require_once __DIR__ . '/php/auth_helpers.php';
require_once __DIR__ . '/php/upload_security_helper.php';
require_once __DIR__ . '/php/login_security_helper.php';
require_once __DIR__ . '/php/usuario_suspension_helper.php';
require_once __DIR__ . '/php/usuario_helper.php';
require_once __DIR__ . '/php/google_helper.php';
require_once __DIR__ . '/php/moodle_helper.php';
require_once __DIR__ . '/php/moodle_fase_helper.php';
require_once __DIR__ . '/php/cuenta_externa_helper.php';
require_once __DIR__ . '/php/cuenta_alumno_helper.php';
require_once __DIR__ . '/php/cuenta_digital_helper.php';
require_once __DIR__ . '/php/tour_helper.php';
require_once __DIR__ . '/php/gerente_helper.php';
require_once __DIR__ . '/php/marketing_helper.php';
require_once __DIR__ . '/php/alumno_portal_helper.php';
require_once __DIR__ . '/php/soporte_helper.php';
require_once __DIR__ . '/php/legacy_import_helper.php';
require_once __DIR__ . '/php/operativo_cncm_helper.php';
require_once __DIR__ . '/php/plan_version_helper.php';
require_once __DIR__ . '/php/acuerdo_escolar_helper.php';
require_once __DIR__ . '/php/supervisor_grupos_historico_helper.php';
require_once __DIR__ . '/php/curso_personalizado_helper.php';
require_once __DIR__ . '/php/inscripcion_protocolo_helper.php';
require_once __DIR__ . '/php/bandeja_aprobaciones_helper.php';
require_once __DIR__ . '/php/cola_facturacion_helper.php';
require_once __DIR__ . '/php/operativo_piso_helper.php';
require_once __DIR__ . '/php/menu_config.php';
require_once __DIR__ . '/php/rbac_view_guard.php';

rbac_reparar_sesion_desde_bd();

hay_utf8_init($pdo);

/** Incrementar al agregar migraciones en hay_bootstrap_schema (fuerza una pasada completa). */
if (!defined('HAY_SCHEMA_VERSION')) {
    define('HAY_SCHEMA_VERSION', 8);
}

/**
 * Migraciones de tablas/columnas. No debe bloquear el login si una falla en hosting.
 */
function hay_bootstrap_schema(PDO $pdo): void
{
    static $enEstaPeticion = false;
    if ($enEstaPeticion) {
        return;
    }
    $forzarRun = defined('HAY_RUN_SCHEMA_BOOTSTRAP') && HAY_RUN_SCHEMA_BOOTSTRAP === true;
    if (defined('HAY_SKIP_SCHEMA_BOOTSTRAP') && HAY_SKIP_SCHEMA_BOOTSTRAP === true && !$forzarRun) {
        return;
    }

    hay_meta_ensure_table($pdo);

    $enEstaPeticion = true;

    // Migraciones SQL (007, 008, 009…) tienen marca propia; no depender de schema_bootstrap_version.
    hay_schema_aplicar_migraciones($pdo);
    if (function_exists('rbac_db_asegurar_jerarquia_roles')) {
        try {
            rbac_db_asegurar_jerarquia_roles($pdo);
        } catch (Throwable $e) {
            error_log('HAY rbac jerarquia sync: ' . $e->getMessage());
        }
    } elseif (function_exists('rbac_db_sincronizar_jerarquia_roles')) {
        try {
            rbac_db_sincronizar_jerarquia_roles($pdo);
        } catch (Throwable $e) {
            error_log('HAY rbac jerarquia sync: ' . $e->getMessage());
        }
    }

    $version = (string) HAY_SCHEMA_VERSION;
    $forzar = defined('HAY_FORCE_SCHEMA_BOOTSTRAP') && HAY_FORCE_SCHEMA_BOOTSTRAP === true;
    if (!$forzar && hay_meta_get($pdo, 'schema_bootstrap_version') === $version) {
        $enEstaPeticion = false;
        return;
    }

    if (function_exists('rbac_db_reparar_roles_sistema')) {
        try {
            rbac_db_reparar_roles_sistema($pdo);
        } catch (Throwable $e) {
            error_log('HAY schema [rbac_repair]: ' . $e->getMessage());
        }
    }

    if (!hay_schema_ddl_habilitado($pdo)) {
        hay_meta_set($pdo, 'schema_bootstrap_version', $version);
        $enEstaPeticion = false;
        return;
    }

    $pasos = [
        'plantel' => static function (PDO $pdo): void { plantel_ensure_schema($pdo); },
        'catalog' => static function (PDO $pdo): void {
            catalog_ensure_schema($pdo);
            if (function_exists('operativo_cncm_ensure_schema')) {
                operativo_cncm_ensure_schema($pdo);
            }
        },
        'preregistro' => static function (PDO $pdo): void { preregistro_ensure_schema($pdo); },
        'alumno' => static function (PDO $pdo): void { alumno_ensure_schema($pdo); },
        'asistencia' => static function (PDO $pdo): void {
            asistencia_ensure_schema($pdo);
            huella_ensure_schema($pdo);
            if (function_exists('huella_fingerjet_ensure_schema')) {
                huella_fingerjet_ensure_schema($pdo);
            }
        },
        'pago' => static function (PDO $pdo): void { pago_ensure_schema($pdo); },
        'fase' => static function (PDO $pdo): void { fase_ensure_schema($pdo); },
        'academico' => static function (PDO $pdo): void {
            academico_ensure_schema($pdo);
            profesor_portal_ensure_schema($pdo);
            graduacion_ensure_schema($pdo);
            docente_prospecto_ensure_schema($pdo);
            expediente_documental_ensure_schema($pdo);
            profesor_eval_ensure_schema($pdo);
            profesor_360_ensure_schema($pdo);
            hay_eval_ensure_schema($pdo);
            reporte_semanal_ensure_schema($pdo);
            if (function_exists('grupo_plan_ensure_schema')) {
                grupo_plan_ensure_schema($pdo);
            }
            if (function_exists('grupo_docente_ensure_schema')) {
                grupo_docente_ensure_schema($pdo);
            }
            if (function_exists('grupo_fusion_ensure_schema')) {
                grupo_fusion_ensure_schema($pdo);
            }
            if (function_exists('tutor_ensure_schema')) {
                tutor_ensure_schema($pdo);
            }
            if (function_exists('aula_ensure_schema')) {
                aula_ensure_schema($pdo);
            }
            if (function_exists('rol_aula_ensure_schema')) {
                rol_aula_ensure_schema($pdo);
            }
            if (function_exists('asesoria_ensure_schema')) {
                asesoria_ensure_schema($pdo);
            }
        },
        'calendario' => static function (PDO $pdo): void {
            if (function_exists('calendario_migrate_schema')) {
                calendario_migrate_schema($pdo);
            }
        },
        'combo' => static function (PDO $pdo): void { combo_ensure_schema($pdo); },
        'certificacion' => static function (PDO $pdo): void { certificacion_ensure_schema($pdo); },
        'asesor' => static function (PDO $pdo): void {
            asesor_ensure_schema($pdo);
            comision_cert_ensure_schema($pdo);
            referido_ensure_schema($pdo);
            ventas_comision_ensure_schema($pdo);
            if (function_exists('grupo_preinicio_ensure_schema')) {
                grupo_preinicio_ensure_schema($pdo);
            }
            if (function_exists('escuelas_ensure_schema')) {
                escuelas_ensure_schema($pdo);
            }
        },
        'usuario' => static function (PDO $pdo): void {
            usuario_ensure_schema($pdo);
            user_avatar_ensure_schema($pdo);
            rbac_db_ensure_schema($pdo);
            if (function_exists('login_security_ensure_schema')) {
                login_security_ensure_schema($pdo);
            }
            if (function_exists('usuario_suspension_ensure_schema')) {
                usuario_suspension_ensure_schema($pdo);
            }
            if (function_exists('hay_upload_asegurar_arbol')) {
                hay_upload_asegurar_arbol();
            }
            if (function_exists('soporte_ensure_schema')) {
                soporte_ensure_schema($pdo);
            }
        },
        'legacy_import' => static function (PDO $pdo): void {
            if (function_exists('legacy_import_ensure_schema')) {
                legacy_import_ensure_schema($pdo);
            }
        },
    ];

    foreach ($pasos as $nombre => $fn) {
        try {
            $fn($pdo);
        } catch (Throwable $e) {
            error_log('HAY schema [' . $nombre . ']: ' . $e->getMessage());
        }
    }

    hay_meta_set($pdo, 'schema_bootstrap_version', $version);
    hay_schema_deshabilitar_ddl_runtime($pdo);
    $enEstaPeticion = false;
}

/**
 * Por defecto NO migrar en cada request (login y APIs ligeros).
 * dashboard.php define HAY_RUN_SCHEMA_BOOTSTRAP antes de cargar este archivo.
 * login_process.php define HAY_SKIP_SCHEMA_BOOTSTRAP (true).
 *
 * NO usar HAY_FORCE_SCHEMA_BOOTSTRAP al final de este archivo: ya habría corrido el bootstrap.
 * En config.local.php: define('HAY_RUN_SCHEMA_BOOTSTRAP', true); solo para forzar migración una vez.
 */
if (!defined('HAY_SKIP_SCHEMA_BOOTSTRAP')) {
    define('HAY_SKIP_SCHEMA_BOOTSTRAP', true);
}
$hayEjecutarBootstrap = (defined('HAY_RUN_SCHEMA_BOOTSTRAP') && HAY_RUN_SCHEMA_BOOTSTRAP === true)
    || (HAY_SKIP_SCHEMA_BOOTSTRAP !== true);
if ($hayEjecutarBootstrap) {
    hay_bootstrap_schema($pdo);
} elseif (!empty($_SESSION['user_id'])) {
    // Migraciones SQL pendientes aunque el bootstrap completo ya corrió (010, etc.).
    hay_schema_aplicar_migraciones($pdo);
    if (function_exists('rbac_db_asegurar_jerarquia_roles')) {
        try {
            rbac_db_asegurar_jerarquia_roles($pdo);
        } catch (Throwable $e) {
            error_log('HAY rbac jerarquia sync (request): ' . $e->getMessage());
        }
    }
    if (function_exists('rbac_db_reparar_supervisor')) {
        try {
            rbac_db_reparar_supervisor($pdo);
        } catch (Throwable $e) {
            error_log('HAY rbac supervisor repair: ' . $e->getMessage());
        }
    }
}

if (function_exists('hay_request_debe_liberar_sesion') && hay_request_debe_liberar_sesion()) {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
    hay_session_release_lock();
}
