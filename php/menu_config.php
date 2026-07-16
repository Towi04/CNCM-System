<?php



/**

 * Menú lateral CNCM por flujos de trabajo (consumido por side_nav.php).

 *

 * @return list<array<string, mixed>>

 */

function menu_cncm_secciones(): array

{

    $secciones = [

        [

            'id' => 'principal',

            'titulo' => 'Menú principal',

            'items' => [

                ['staff' => true, 'seccion' => 'inicio_panel', 'icon' => 'fa-home', 'text' => 'Inicio', 'title' => 'Inicio', 'breadcrumb' => 'INICIO'],

                ['cap' => 'menu_preregistro', 'seccion' => 'pre_registro_alumnos', 'icon' => 'fa-bookmark', 'text' => 'Pre-Registro Alumnos', 'title' => 'Pre-Registro Alumnos', 'breadcrumb' => 'PRE-REGISTRO ALUMNOS'],

                ['cap' => 'menu_entrevistas', 'seccion' => 'asesor_entrevistas', 'icon' => 'fa-handshake', 'text' => 'Entrevistas', 'title' => 'Entrevistas', 'breadcrumb' => 'ENTREVISTAS'],

            ],

        ],

        [

            'id' => 'inscripciones',

            'titulo' => 'Inscripciones',

            'caps_any' => ['menu_ubicacion_asesor', 'menu_cert_preregistro', 'menu_grupos_fases', 'menu_asesor_preinicio'],

            'items' => [

                ['cap' => 'menu_ubicacion_asesor', 'seccion' => 'asesor_ubicacion', 'icon' => 'fa-map-signs', 'text' => 'Ubicación', 'title' => 'Examen de ubicación', 'breadcrumb' => 'UBICACIÓN'],

                ['cap' => 'menu_cert_preregistro', 'seccion' => 'cert_preregistro_asesor', 'icon' => 'fa-award', 'text' => 'Cert. Pre-Registro', 'title' => 'Pre-registro certificaciones', 'breadcrumb' => 'CERTIFICACIONES PRE-REGISTRO'],

                ['cap' => 'menu_grupos_fases', 'seccion' => 'asesor_grupos_fases', 'icon' => 'fa-search-location', 'text' => 'Grupos por fase', 'title' => 'Grupos por fase', 'breadcrumb' => 'GRUPOS POR FASE'],

                ['cap' => 'menu_asesor_preinicio', 'seccion' => 'asesor_preinicio_grupos', 'icon' => 'fa-phone-volume', 'text' => 'Contacto pre-inicio', 'title' => 'Contacto pre-inicio de grupos', 'breadcrumb' => 'CONTACTO PRE-INICIO'],

            ],

        ],

        [

            'id' => 'reportes_asesor',

            'titulo' => 'Reportes',

            'caps_any' => ['menu_podio_ventas', 'menu_reporte_inscritos', 'menu_comisiones_consulta'],

            'items' => [

                ['cap' => 'menu_podio_ventas', 'seccion' => 'gerente_podio', 'icon' => 'fa-trophy', 'text' => 'Podio', 'title' => 'Podio de asesores', 'breadcrumb' => 'PODIO ASESORES'],

                ['cap' => 'menu_reporte_inscritos', 'seccion' => 'reporte_inscritos', 'icon' => 'fa-user-check', 'text' => 'Mis inscritos', 'title' => 'Reporte de inscritos', 'breadcrumb' => 'REPORTE INSCRITOS'],

                ['cap' => 'menu_comisiones_consulta', 'seccion' => 'ventas_comisiones_consulta', 'icon' => 'fa-coins', 'text' => 'Mis comisiones', 'title' => 'Mis comisiones', 'breadcrumb' => 'COMISIONES'],

            ],

        ],

        [

            'id' => 'gerencia',

            'titulo' => 'Gerencia ventas',

            'caps_any' => [

                'menu_gerente_dashboard', 'menu_gerente_reportes', 'menu_gerente_pendientes',

                'menu_gerente_perdidos', 'menu_gerente_hay', 'menu_gerente_matriz',

                'menu_gerente_cartas', 'menu_comisiones_admin',

                'menu_gerente_escuelas', 'menu_reporte_escuelas', 'menu_reporte_presentados',

            ],

            'items' => [

                ['cap' => 'menu_gerente_dashboard', 'seccion' => 'gerente_dashboard', 'icon' => 'fa-chart-line', 'text' => 'Panel gerente', 'title' => 'Panel gerente', 'breadcrumb' => 'PANEL GERENTE'],

                ['cap' => 'menu_gerente_reportes', 'seccion' => 'gerente_reportes_captacion', 'icon' => 'fa-chart-pie', 'text' => 'Reportes captación', 'title' => 'Reportes de captación', 'breadcrumb' => 'REPORTES CAPTACIÓN'],

                ['cap' => 'menu_gerente_reportes', 'seccion' => 'gerente_reporte_geografico', 'icon' => 'fa-map-marked-alt', 'text' => 'Reporte geográfico', 'title' => 'Reporte geográfico', 'breadcrumb' => 'REPORTE GEOGRÁFICO'],

                ['cap' => 'menu_gerente_pendientes', 'seccion' => 'gerente_reporte_pendientes', 'icon' => 'fa-tasks', 'text' => 'Pendientes plantel', 'title' => 'Pendientes del plantel', 'breadcrumb' => 'PENDIENTES PLANTEL'],

                ['cap' => 'menu_gerente_perdidos', 'seccion' => 'gerente_reporte_pendientes', 'query' => 'tab=perdidos', 'icon' => 'fa-user-times', 'text' => 'No inscritos', 'title' => 'Motivos de no inscripción', 'breadcrumb' => 'NO INSCRITOS'],

                ['cap' => 'menu_gerente_hay', 'seccion' => 'gerente_hay_portal', 'icon' => 'fa-layer-group', 'text' => 'Evaluación HAY', 'title' => 'Evaluación HAY — gerente', 'breadcrumb' => 'EVALUACIÓN HAY'],

                ['cap' => 'menu_gerente_matriz', 'seccion' => 'gerente_matriz_entrenamiento', 'icon' => 'fa-graduation-cap', 'text' => 'Matriz equipo', 'title' => 'Matriz de entrenamiento del equipo', 'breadcrumb' => 'MATRIZ EQUIPO'],

                ['cap' => 'menu_gerente_cartas', 'seccion' => 'gerente_cartas_nomina', 'icon' => 'fa-envelope-open-text', 'text' => 'Cartas nómina', 'title' => 'Designación cartas', 'breadcrumb' => 'CARTAS NÓMINA'],

                ['cap' => 'menu_gerente_escuelas', 'seccion' => 'gerente_escuelas', 'icon' => 'fa-school', 'text' => 'Escuelas', 'title' => 'Catálogo de escuelas', 'breadcrumb' => 'ESCUELAS'],

                ['cap' => 'menu_reporte_escuelas', 'seccion' => 'reporte_escuelas', 'icon' => 'fa-chart-bar', 'text' => 'Reporte escuelas', 'title' => 'Reporte de escuelas y cartas', 'breadcrumb' => 'REPORTE ESCUELAS'],

                ['cap' => 'menu_reporte_presentados', 'seccion' => 'reporte_presentados', 'icon' => 'fa-user-graduate', 'text' => 'Presentados', 'title' => 'Reporte de presentados', 'breadcrumb' => 'REPORTE PRESENTADOS'],

                ['cap' => 'menu_comisiones_admin', 'seccion' => 'ventas_comisiones_admin', 'icon' => 'fa-sliders-h', 'text' => 'Comisiones ventas', 'title' => 'Comisiones y tabuladores', 'breadcrumb' => 'COMISIONES VENTAS'],

            ],

        ],

        [

            'id' => 'caja',

            'titulo' => 'Caja y cobranza',

            'cap' => 'menu_caja',

            'items' => [

        ['cap' => 'menu_consulta_adeudo', 'seccion' => 'consulta_adeudo', 'icon' => 'fa-calculator', 'text' => 'Consulta de adeudo', 'title' => 'Consulta de adeudo', 'breadcrumb' => 'CONSULTA ADEUDO'],

        ['callback' => 'operativo_piso_puede_ver', 'seccion' => 'piso_operativo', 'icon' => 'fa-concierge-bell', 'text' => 'Piso operativo', 'title' => 'Entrega documentos y cobranza', 'breadcrumb' => 'PISO OPERATIVO'],

        ['cap' => 'menu_punto_venta', 'seccion' => 'punto_venta', 'icon' => 'fa-cash-register', 'text' => 'Punto de venta', 'title' => 'Punto de venta', 'breadcrumb' => 'PUNTO DE VENTA'],

                ['cap' => 'menu_venta_productos', 'seccion' => 'venta_productos', 'icon' => 'fa-shopping-cart', 'text' => 'Venta de productos', 'title' => 'Venta de productos', 'breadcrumb' => 'VENTA DE PRODUCTOS'],

                ['callback' => 'documento_puede_mostrador', 'seccion' => 'documento_mostrador', 'icon' => 'fa-id-card', 'text' => 'Mostrador documentos', 'title' => 'Mostrador de constancias y diplomas', 'breadcrumb' => 'MOSTRADOR DOC'],

                ['callback' => 'cola_facturacion_puede_ver', 'seccion' => 'cola_facturacion', 'icon' => 'fa-file-invoice', 'text' => 'Cola de facturación', 'title' => 'Cola de facturación', 'breadcrumb' => 'COLA FACTURACIÓN'],

                ['cap' => 'menu_asistencia', 'flyout' => 'flyout-asistencia', 'icon' => 'fa-clipboard-check', 'text' => 'Asistencias', 'title' => 'Asistencias', 'breadcrumb' => 'ASISTENCIAS'],

                ['callback' => 'tutor_puede_usar', 'seccion' => 'tutor_chat', 'icon' => 'fa-robot', 'text' => 'Tutor IA', 'title' => 'Tutor Académico Institucional', 'breadcrumb' => 'TUTOR IA'],

                ['cap' => 'menu_calendario_consulta', 'seccion' => 'calendario_consulta', 'icon' => 'fa-calendar', 'text' => 'Calendario institucional', 'title' => 'Calendario institucional', 'breadcrumb' => 'CALENDARIO INSTITUCIONAL'],

                ['cap' => 'menu_reportes', 'seccion' => 'corte_caja', 'icon' => 'fa-coins', 'text' => 'Corte de caja', 'title' => 'Corte de caja diario', 'breadcrumb' => 'CORTE DE CAJA'],

                ['cap' => 'menu_reportes', 'flyout' => 'flyout-reportes', 'icon' => 'fa-chart-bar', 'text' => 'Reportes', 'title' => 'Reportes', 'breadcrumb' => 'REPORTES'],

            ],

        ],

        [

            'id' => 'alumnos',

            'titulo' => 'Alumnos',

            'cap' => 'menu_alumnos',

            'items' => [

                ['cap' => 'menu_alumnos', 'seccion' => 'alumnos', 'icon' => 'fa-user-graduate', 'text' => 'Alumnos', 'title' => 'Alumnos', 'breadcrumb' => 'ALUMNOS'],

            ],

        ],

        [

            'id' => 'academico',

            'titulo' => 'Académico',

            'cap' => 'menu_academico',

            'caps_any' => ['menu_tutor', 'tutor_usar'],

            'items' => [

                ['cap' => 'menu_asistencia', 'flyout' => 'flyout-asistencia', 'icon' => 'fa-clipboard-check', 'text' => 'Asistencias', 'title' => 'Asistencias', 'breadcrumb' => 'ASISTENCIAS'],

                ['cap' => 'menu_grupos', 'flyout' => 'flyout-grupos', 'icon' => 'fa-users', 'text' => 'Grupos', 'title' => 'Grupos', 'breadcrumb' => 'GRUPOS'],

                ['cap' => 'menu_especialidades', 'flyout' => 'flyout-especialidades', 'icon' => 'fa-cogs', 'text' => 'Especialidades', 'title' => 'Especialidades', 'breadcrumb' => 'ESPECIALIDADES'],

                ['cap' => 'menu_certificaciones', 'seccion' => 'certificaciones', 'icon' => 'fa-certificate', 'text' => 'Certificaciones', 'title' => 'Certificaciones', 'breadcrumb' => 'CERTIFICACIONES'],

                ['callback' => 'profesor_portal_es_profesor', 'seccion' => 'profesor_portal', 'icon' => 'fa-chalkboard-teacher', 'text' => 'Mi portal docente', 'title' => 'Mi portal docente', 'breadcrumb' => 'PORTAL DOCENTE'],

                ['callback' => 'academico_alumno_portal_puede', 'seccion' => 'academico_portal_alumno', 'icon' => 'fa-bullhorn', 'text' => 'Avisos y chat alumno', 'title' => 'Avisos y mensajes alumnos', 'breadcrumb' => 'AVISOS ALUMNO'],

                ['callback' => 'asistencia_puede_checada', 'seccion' => 'asistencia_checada', 'icon' => 'fa-fingerprint', 'text' => 'Registrar asistencia', 'title' => 'Registrar asistencia', 'breadcrumb' => 'REGISTRAR ASISTENCIA'],

                ['callback' => 'tutor_puede_usar', 'seccion' => 'tutor_chat', 'icon' => 'fa-robot', 'text' => 'Tutor IA', 'title' => 'Tutor Académico Institucional', 'breadcrumb' => 'TUTOR IA'],

                ['callback' => 'academico_libro_puede_gestionar', 'seccion' => 'academico_libros', 'icon' => 'fa-book', 'text' => 'Libros y materiales', 'title' => 'Libros institucionales', 'breadcrumb' => 'LIBROS CNCM'],

            ],

        ],

        [

            'id' => 'admin',

            'titulo' => 'Administración',

            'cap' => 'menu_admin',

            'items' => [

                ['cap' => 'menu_admin', 'flyout' => 'flyout-admin', 'icon' => 'fa-cog', 'text' => 'Administración', 'title' => 'Administración', 'breadcrumb' => 'ADMINISTRACIÓN'],

            ],

        ],

        [

            'id' => 'desarrollo',

            'titulo' => 'Mi desarrollo',

            'caps_any' => ['menu_mi_evaluacion', 'menu_matriz_entrenamiento'],

            'items' => [

                ['cap' => 'menu_mi_evaluacion', 'seccion' => 'mi_evaluacion', 'icon' => 'fa-user-check', 'text' => 'Mi evaluación', 'title' => 'Mi evaluación', 'breadcrumb' => 'MI EVALUACIÓN'],

                ['cap' => 'menu_matriz_entrenamiento', 'seccion' => 'matriz_entrenamiento', 'icon' => 'fa-graduation-cap', 'text' => 'Matriz de entrenamiento', 'title' => 'Matriz de entrenamiento', 'breadcrumb' => 'MATRIZ'],

            ],

        ],

        [

            'id' => 'otros',

            'titulo' => 'Otros',

            'items' => [

                ['staff' => true, 'seccion' => 'soporte_tecnico', 'icon' => 'fa-headset', 'text' => 'Soporte técnico', 'title' => 'Soporte Técnico', 'breadcrumb' => 'SOPORTE TÉCNICO'],

            ],

        ],

    ];

    return menu_cncm_aplicar_vista_director($secciones);

}



/** Menú lateral explícito para director de plantel (sin depender de flyouts). */

function menu_cncm_usa_menu_director(): bool

{

    if (!function_exists('rbac_rol_efectivo') || rbac_rol_efectivo() !== 'director') {

        return false;

    }

    if (function_exists('menu_cncm_vista_por_rol') && menu_cncm_vista_por_rol()) {

        return false;

    }



    return true;

}



/** @return array<string, mixed> */

function menu_cncm_seccion_director(): array

{

    return [

        'id' => 'director',

        'titulo' => 'Director / Supervisión',

        'rol' => true,

        'items' => menu_cncm_director_items(),

    ];

}



/**

 * Accesos directos del director (reutilizado en vista por rol del supervisor).

 *

 * @return list<array<string, mixed>>

 */

function menu_cncm_director_items(): array

{

    return [

        ['callback' => 'bandeja_aprobaciones_puede_ver', 'seccion' => 'bandeja_aprobaciones', 'icon' => 'fa-inbox', 'text' => 'Bandeja de aprobaciones', 'title' => 'Bandeja de aprobaciones', 'breadcrumb' => 'BANDEJA APROBACIONES'],

        ['callback' => 'grupo_apertura_puede_gestionar', 'seccion' => 'grupo_apertura', 'icon' => 'fa-door-open', 'text' => 'Apertura de grupos', 'title' => 'Autorizar apertura de grupos', 'breadcrumb' => 'APERTURA GRUPOS'],

        ['callback' => 'planeacion_puede_revisar', 'seccion' => 'planeaciones_revision', 'icon' => 'fa-check-double', 'text' => 'Revisar planeaciones', 'title' => 'Revisar planeaciones', 'breadcrumb' => 'REVISAR PLANEACIONES'],

        ['callback' => 'nomina_puede_gestionar', 'seccion' => 'director_nomina', 'icon' => 'fa-money-check-alt', 'text' => 'Nómina personal', 'title' => 'Nómina del personal', 'breadcrumb' => 'NÓMINA'],

        ['cap' => 'menu_alumnos', 'seccion' => 'alumnos', 'icon' => 'fa-user-graduate', 'text' => 'Alumnos', 'title' => 'Alumnos', 'breadcrumb' => 'ALUMNOS'],

        ['cap' => 'menu_grupos', 'seccion' => 'grupos', 'icon' => 'fa-users', 'text' => 'Grupos', 'title' => 'Ver grupos', 'breadcrumb' => 'GRUPOS'],

        ['callback' => 'supervisor_grupos_historico_puede_ver', 'seccion' => 'supervisor_grupos_historico', 'icon' => 'fa-history', 'text' => 'Carga histórica grupos', 'title' => 'Carga histórica de grupos', 'breadcrumb' => 'CARGA HISTÓRICA'],

        ['cap' => 'menu_consulta_adeudo', 'seccion' => 'consulta_adeudo', 'icon' => 'fa-calculator', 'text' => 'Consulta de adeudo', 'title' => 'Consulta de adeudo', 'breadcrumb' => 'CONSULTA ADEUDO'],

        ['callback' => 'operativo_piso_puede_ver', 'seccion' => 'piso_operativo', 'icon' => 'fa-concierge-bell', 'text' => 'Piso operativo', 'title' => 'Entrega documentos y cobranza', 'breadcrumb' => 'PISO OPERATIVO'],

        ['cap' => 'menu_punto_venta', 'seccion' => 'punto_venta', 'icon' => 'fa-cash-register', 'text' => 'Punto de venta', 'title' => 'Punto de venta', 'breadcrumb' => 'PUNTO DE VENTA'],

        ['cap' => 'menu_reportes', 'seccion' => 'corte_caja', 'icon' => 'fa-coins', 'text' => 'Corte de caja', 'title' => 'Corte de caja diario', 'breadcrumb' => 'CORTE DE CAJA'],

        ['cap' => 'menu_reportes', 'seccion' => 'reporte_vencimientos', 'icon' => 'fa-exclamation-circle', 'text' => 'Cartera vencida', 'title' => 'Reporte de vencimientos', 'breadcrumb' => 'CARTERA VENCIDA'],

        ['cap' => 'menu_reportes', 'seccion' => 'reporte_ventas', 'icon' => 'fa-chart-line', 'text' => 'Reporte de ventas', 'title' => 'Reporte de ventas', 'breadcrumb' => 'REPORTE VENTAS'],

        ['cap' => 'menu_reporte_presentados', 'seccion' => 'reporte_presentados', 'icon' => 'fa-user-graduate', 'text' => 'Presentados', 'title' => 'Reporte de presentados', 'breadcrumb' => 'REPORTE PRESENTADOS'],

        ['cap' => 'menu_reporte_escuelas', 'seccion' => 'reporte_escuelas', 'icon' => 'fa-chart-bar', 'text' => 'Reporte escuelas', 'title' => 'Reporte de escuelas', 'breadcrumb' => 'REPORTE ESCUELAS'],

        ['callback' => 'documento_puede_mostrador', 'seccion' => 'documento_mostrador', 'icon' => 'fa-id-card', 'text' => 'Mostrador documentos', 'title' => 'Mostrador de constancias y diplomas', 'breadcrumb' => 'MOSTRADOR DOC'],

        ['cap' => 'menu_calendario', 'seccion' => 'admin_calendario', 'icon' => 'fa-calendar-week', 'text' => 'Calendarios escolares', 'title' => 'Calendarios escolares', 'breadcrumb' => 'CALENDARIOS ESCOLARES'],

        ['cap' => 'menu_calendario_admin', 'seccion' => 'admin_calendario_admin', 'icon' => 'fa-calendar-check', 'text' => 'Calendario administrativo', 'title' => 'Calendario administrativo', 'breadcrumb' => 'CALENDARIO ADMIN'],

        ['cap' => 'menu_calendario_consulta', 'seccion' => 'calendario_consulta', 'icon' => 'fa-calendar', 'text' => 'Calendario institucional', 'title' => 'Vista combinada calendario', 'breadcrumb' => 'CALENDARIO INSTITUCIONAL'],

        ['callback' => 'acuerdo_escolar_puede_publicar', 'seccion' => 'supervisor_acuerdo_escolar', 'icon' => 'fa-file-signature', 'text' => 'Acuerdo escolar', 'title' => 'Acuerdo escolar versionado', 'breadcrumb' => 'ACUERDO ESCOLAR'],

        ['cap' => 'expediente_requisitos_admin', 'seccion' => 'expediente_requisitos', 'icon' => 'fa-clipboard-list', 'text' => 'Requisitos documentales', 'title' => 'Requisitos documentales', 'breadcrumb' => 'REQ. DOCUMENTALES'],

        ['callback' => 'documento_puede_configurar_plantillas', 'seccion' => 'admin_documento_plantillas', 'icon' => 'fa-file-image', 'text' => 'Plantillas documentos', 'title' => 'Plantillas constancias y diplomas', 'breadcrumb' => 'PLANTILLAS DOC'],

        ['callback' => 'rol_aula_puede_ver', 'seccion' => 'rol_aulas_consulta', 'icon' => 'fa-th-large', 'text' => 'Rol de aulas', 'title' => 'Consulta rol de aulas', 'breadcrumb' => 'ROL DE AULAS'],

        ['callback' => 'aula_puede_gestionar', 'seccion' => 'admin_mapa_plantel', 'icon' => 'fa-door-open', 'text' => 'Catálogo de aulas', 'title' => 'Catálogo de aulas', 'breadcrumb' => 'CATÁLOGO AULAS'],

        ['cap' => 'admin_usuarios', 'seccion' => 'ver_usuarios', 'icon' => 'fa-users-cog', 'text' => 'Usuarios', 'title' => 'Ver usuarios', 'breadcrumb' => 'USUARIOS'],

        ['cap' => 'menu_admin', 'flyout' => 'flyout-admin', 'icon' => 'fa-ellipsis-h', 'text' => 'Más administración', 'title' => 'Catálogo, productos y configuración', 'breadcrumb' => 'ADMINISTRACIÓN'],

    ];

}



/** Inserta bloque director y oculta el ítem flyout suelto de Administración. */

function menu_cncm_aplicar_vista_director(array $secciones): array

{

    if (!menu_cncm_usa_menu_director()) {

        return $secciones;

    }

    $out = [];

    foreach ($secciones as $sec) {

        if (($sec['id'] ?? '') === 'admin') {

            continue;

        }

        $out[] = $sec;

        if (($sec['id'] ?? '') === 'principal') {

            $out[] = menu_cncm_seccion_director();

        }

    }



    return $out;

}



/** Menú compacto por rol operativo (profesor, coordinador, recepción). */

function menu_cncm_usa_menu_compacto(): bool

{

    if (menu_cncm_vista_por_rol() || menu_cncm_usa_menu_director()) {

        return false;

    }

    if (function_exists('rbac_esta_simulando_rol') && rbac_esta_simulando_rol()) {

        return false;

    }



    return in_array(rbac_rol_efectivo(), ['profesor', 'coordinador', 'admin'], true);

}



/** @return list<array<string, mixed>> */

function menu_cncm_secciones_compactas(): array

{

    $rol = rbac_rol_efectivo();

    $soporte = [

        'id' => 'otros',

        'titulo' => 'Otros',

        'items' => [

            ['staff' => true, 'seccion' => 'soporte_tecnico', 'icon' => 'fa-headset', 'text' => 'Soporte técnico', 'title' => 'Soporte Técnico', 'breadcrumb' => 'SOPORTE TÉCNICO'],

        ],

    ];

    $desarrollo = [

        'id' => 'desarrollo',

        'titulo' => 'Mi desarrollo',

        'items' => [

            ['cap' => 'menu_mi_evaluacion', 'seccion' => 'mi_evaluacion', 'icon' => 'fa-user-check', 'text' => 'Mi evaluación', 'title' => 'Mi evaluación', 'breadcrumb' => 'MI EVALUACIÓN'],

            ['cap' => 'menu_matriz_entrenamiento', 'seccion' => 'matriz_entrenamiento', 'icon' => 'fa-graduation-cap', 'text' => 'Matriz de entrenamiento', 'title' => 'Matriz de entrenamiento', 'breadcrumb' => 'MATRIZ'],

            ['staff' => true, 'seccion' => 'mi_expediente_documentos', 'icon' => 'fa-folder-open', 'text' => 'Mi expediente', 'title' => 'Mis documentos', 'breadcrumb' => 'MI EXPEDIENTE'],

        ],

    ];



    if ($rol === 'profesor') {

        return [

            [

                'id' => 'profesor',

                'titulo' => 'Docente',

                'rol' => true,

                'items' => [

                    ['staff' => true, 'seccion' => 'inicio_panel', 'icon' => 'fa-home', 'text' => 'Inicio', 'title' => 'Inicio', 'breadcrumb' => 'INICIO'],

                    ['callback' => 'profesor_portal_es_profesor', 'seccion' => 'profesor_portal', 'icon' => 'fa-chalkboard-teacher', 'text' => 'Mi portal docente', 'title' => 'Mi portal docente', 'breadcrumb' => 'PORTAL DOCENTE'],

                    ['cap' => 'menu_asistencia', 'flyout' => 'flyout-asistencia', 'icon' => 'fa-clipboard-check', 'text' => 'Asistencias', 'title' => 'Asistencias', 'breadcrumb' => 'ASISTENCIAS'],

                    ['cap' => 'menu_grupos', 'flyout' => 'flyout-grupos-profesor', 'icon' => 'fa-users', 'text' => 'Grupos', 'title' => 'Grupos', 'breadcrumb' => 'GRUPOS'],

                    ['cap' => 'menu_asesorias', 'seccion' => 'asesorias', 'icon' => 'fa-calendar-alt', 'text' => 'Disponibilidad asesorías', 'title' => 'Disponibilidad asesorías', 'breadcrumb' => 'DISPONIBILIDAD ASESORÍAS'],
                    ['cap' => 'asesoria_calendario', 'seccion' => 'asesoria_calendario', 'icon' => 'fa-calendar-check', 'text' => 'Mis asesorías', 'title' => 'Mis asesorías agendadas', 'breadcrumb' => 'MIS ASESORÍAS'],

                    ['cap' => 'menu_grupos', 'seccion' => 'planeaciones', 'icon' => 'fa-file-alt', 'text' => 'Planeaciones', 'title' => 'Planeaciones', 'breadcrumb' => 'PLANEACIONES'],

                    ['callback' => 'asistencia_puede_checada', 'seccion' => 'asistencia_checada', 'icon' => 'fa-fingerprint', 'text' => 'Registrar asistencia', 'title' => 'Registrar asistencia', 'breadcrumb' => 'REGISTRAR ASISTENCIA'],

                    ['cap' => 'menu_examenes', 'flyout' => 'flyout-examenes', 'icon' => 'fa-file-alt', 'text' => 'Exámenes', 'title' => 'Generar exámenes', 'breadcrumb' => 'EXÁMENES'],

                    ['callback' => 'tutor_puede_usar', 'seccion' => 'tutor_chat', 'icon' => 'fa-robot', 'text' => 'Tutor IA', 'title' => 'Tutor Académico Institucional', 'breadcrumb' => 'TUTOR IA'],

                    ['seccion' => 'profesor_360_mis_resultados', 'icon' => 'fa-chart-pie', 'text' => 'Mi evaluación 360', 'title' => 'Evaluación 360', 'breadcrumb' => 'EVAL 360'],

                ],

            ],

            $desarrollo,

            $soporte,

        ];

    }



    if ($rol === 'coordinador') {

        return [

            [

                'id' => 'coordinador',

                'titulo' => 'Coordinación académica',

                'rol' => true,

                'items' => [

                    ['staff' => true, 'seccion' => 'inicio_panel', 'icon' => 'fa-home', 'text' => 'Inicio', 'title' => 'Inicio', 'breadcrumb' => 'INICIO'],

                    ['cap' => 'menu_alumnos', 'seccion' => 'alumnos', 'icon' => 'fa-user-graduate', 'text' => 'Alumnos', 'title' => 'Alumnos', 'breadcrumb' => 'ALUMNOS'],

                    ['cap' => 'menu_grupos', 'flyout' => 'flyout-grupos', 'icon' => 'fa-users-cog', 'text' => 'Grupos y planeación', 'title' => 'Grupos', 'breadcrumb' => 'GRUPOS'],

                    ['cap' => 'menu_especialidades', 'flyout' => 'flyout-especialidades', 'icon' => 'fa-cogs', 'text' => 'Especialidades', 'title' => 'Especialidades', 'breadcrumb' => 'ESPECIALIDADES'],

                    ['cap' => 'menu_asistencia', 'flyout' => 'flyout-asistencia', 'icon' => 'fa-clipboard-check', 'text' => 'Asistencias', 'title' => 'Asistencias', 'breadcrumb' => 'ASISTENCIAS'],

                    ['cap' => 'menu_examenes', 'flyout' => 'flyout-coordinador-academico', 'icon' => 'fa-file-signature', 'text' => 'Exámenes y evaluación', 'title' => 'Exámenes y evaluación', 'breadcrumb' => 'EXÁMENES Y EVALUACIÓN'],

                    ['callback' => 'planeacion_puede_revisar', 'seccion' => 'planeaciones_revision', 'icon' => 'fa-check-double', 'text' => 'Revisar planeaciones', 'title' => 'Revisar planeaciones', 'breadcrumb' => 'REVISAR PLANEACIONES'],

                    ['cap' => 'asesoria_agendar', 'seccion' => 'asesoria_agendar', 'icon' => 'fa-user-clock', 'text' => 'Agendar asesoría', 'title' => 'Agendar asesoría', 'breadcrumb' => 'AGENDAR ASESORÍA'],
                    ['cap' => 'asesoria_agenda_dia', 'seccion' => 'asesoria_agenda_dia', 'icon' => 'fa-calendar-day', 'text' => 'Agenda asesorías', 'title' => 'Agenda asesorías', 'breadcrumb' => 'AGENDA ASESORÍAS'],
                    ['cap' => 'asesoria_calendario', 'seccion' => 'asesoria_calendario', 'icon' => 'fa-calendar-week', 'text' => 'Calendario asesorías', 'title' => 'Calendario asesorías', 'breadcrumb' => 'CALENDARIO ASESORÍAS'],
                    ['cap' => 'asesoria_tabulador', 'seccion' => 'asesoria_tabulador_admin', 'icon' => 'fa-sliders-h', 'text' => 'Tabulador asesorías', 'title' => 'Tabulador asesorías', 'breadcrumb' => 'TABULADOR ASESORÍAS'],

                    ['cap' => 'menu_grupos', 'seccion' => 'cronologia_grupos', 'icon' => 'fa-table', 'text' => 'Cronología de fases', 'title' => 'Cronología de fases', 'breadcrumb' => 'CRONOLOGÍA GRUPOS'],

                    ['callback' => 'aula_puede_gestionar', 'seccion' => 'admin_mapa_plantel', 'icon' => 'fa-door-open', 'text' => 'Catálogo de aulas', 'title' => 'Catálogo de aulas', 'breadcrumb' => 'CATÁLOGO AULAS'],

                    ['callback' => 'rol_aula_puede_gestionar', 'seccion' => 'rol_aulas_coordinador', 'icon' => 'fa-th-large', 'text' => 'Rol de aulas', 'title' => 'Rol de aulas', 'breadcrumb' => 'ROL DE AULAS'],

                    ['cap' => 'menu_grupos', 'seccion' => 'grupo_fusion_plan', 'icon' => 'fa-object-group', 'text' => 'Planificación de fusiones', 'title' => 'Planificación de fusiones', 'breadcrumb' => 'FUSIONES GRUPOS'],

                    ['callback' => 'academico_alumno_portal_puede', 'seccion' => 'academico_portal_alumno', 'icon' => 'fa-bullhorn', 'text' => 'Avisos y chat alumno', 'title' => 'Avisos y mensajes alumnos', 'breadcrumb' => 'AVISOS ALUMNO'],

                    ['callback' => 'tutor_puede_usar', 'seccion' => 'tutor_chat', 'icon' => 'fa-robot', 'text' => 'Tutor IA', 'title' => 'Tutor Académico', 'breadcrumb' => 'TUTOR IA'],

                    ['cap' => 'menu_especialidades', 'seccion' => 'moodle_nivel_admin', 'icon' => 'fa-graduation-cap', 'text' => 'Moodle por nivel', 'title' => 'Moodle por nivel', 'breadcrumb' => 'MOODLE NIVEL'],

                    ['cap' => 'reporte_academico_ver', 'seccion' => 'reporte_resumen_academico', 'icon' => 'fa-chart-line', 'text' => 'Reporte académico', 'title' => 'Resumen académico', 'breadcrumb' => 'RESUMEN ACADÉMICO'],

                    ['callback' => 'documento_puede_gestionar_diplomas', 'seccion' => 'coordinador_diplomas', 'icon' => 'fa-award', 'text' => 'Diplomas por grupo', 'title' => 'Diplomas por grupo', 'breadcrumb' => 'DIPLOMAS'],

                    ['callback' => 'docente_prospecto_puede_gestionar', 'seccion' => 'docente_prospectos', 'icon' => 'fa-user-plus', 'text' => 'Reclutamiento docente', 'title' => 'Reclutamiento docente', 'breadcrumb' => 'RECLUTAMIENTO DOCENTE'],

                    ['cap' => 'expediente_consultar', 'seccion' => 'expediente_consulta', 'icon' => 'fa-folder-open', 'text' => 'Expedientes documentales', 'title' => 'Consulta de expedientes', 'breadcrumb' => 'EXPEDIENTES'],

                    ['callback' => 'profesor_360_puede_gestionar', 'seccion' => 'profesor_360_ciclos', 'icon' => 'fa-sync-alt', 'text' => 'Ciclos eval. 360', 'title' => 'Ciclos evaluación 360', 'breadcrumb' => 'CICLOS 360'],

                    ['callback' => 'profesor_eval_puede_gestionar', 'seccion' => 'calificar_usuario', 'icon' => 'fa-star-half-alt', 'text' => 'Eval. 360 profesores', 'title' => 'Evaluación 360 profesores', 'breadcrumb' => 'EVAL 360'],

                    ['cap' => 'menu_calendario_consulta', 'seccion' => 'calendario_consulta', 'icon' => 'fa-calendar', 'text' => 'Calendario institucional', 'title' => 'Calendario institucional', 'breadcrumb' => 'CALENDARIO INSTITUCIONAL'],

                ],

            ],

            $desarrollo,

            $soporte,

        ];

    }



    // Recepción / caja (rol admin)

    return [

        [

            'id' => 'recepcion',

            'titulo' => 'Recepción / Caja',

            'rol' => true,

            'items' => [

                ['staff' => true, 'seccion' => 'inicio_panel', 'icon' => 'fa-home', 'text' => 'Inicio', 'title' => 'Inicio', 'breadcrumb' => 'INICIO'],

                ['cap' => 'menu_alumnos', 'seccion' => 'alumnos', 'icon' => 'fa-user-graduate', 'text' => 'Alumnos', 'title' => 'Alumnos', 'breadcrumb' => 'ALUMNOS'],

                ['callback' => 'operativo_piso_puede_ver', 'seccion' => 'piso_operativo', 'icon' => 'fa-concierge-bell', 'text' => 'Piso operativo', 'title' => 'Entrega documentos y cobranza', 'breadcrumb' => 'PISO OPERATIVO'],

                ['cap' => 'menu_consulta_adeudo', 'seccion' => 'consulta_adeudo', 'icon' => 'fa-calculator', 'text' => 'Consulta de adeudo', 'title' => 'Consulta de adeudo', 'breadcrumb' => 'CONSULTA ADEUDO'],

                ['cap' => 'menu_punto_venta', 'seccion' => 'punto_venta', 'icon' => 'fa-cash-register', 'text' => 'Punto de venta', 'title' => 'Punto de venta', 'breadcrumb' => 'PUNTO DE VENTA'],

                ['cap' => 'menu_venta_productos', 'seccion' => 'venta_productos', 'icon' => 'fa-shopping-cart', 'text' => 'Venta de productos', 'title' => 'Venta de productos', 'breadcrumb' => 'VENTA DE PRODUCTOS'],

                ['callback' => 'documento_puede_marcar_pagada', 'seccion' => 'constancia_recepcion', 'icon' => 'fa-file-certificate', 'text' => 'Constancias pendientes', 'title' => 'Constancias pendientes de pago', 'breadcrumb' => 'CONSTANCIAS'],

                ['callback' => 'documento_puede_mostrador', 'seccion' => 'documento_mostrador', 'icon' => 'fa-id-card', 'text' => 'Mostrador documentos', 'title' => 'Mostrador de constancias y diplomas', 'breadcrumb' => 'MOSTRADOR DOC'],

                ['callback' => 'cola_facturacion_puede_ver', 'seccion' => 'cola_facturacion', 'icon' => 'fa-file-invoice', 'text' => 'Cola de facturación', 'title' => 'Cola de facturación', 'breadcrumb' => 'COLA FACTURACIÓN'],

                ['cap' => 'menu_asistencia', 'flyout' => 'flyout-asistencia', 'icon' => 'fa-clipboard-check', 'text' => 'Asistencias', 'title' => 'Asistencias', 'breadcrumb' => 'ASISTENCIAS'],

                ['callback' => 'tutor_puede_usar', 'seccion' => 'tutor_chat', 'icon' => 'fa-robot', 'text' => 'Tutor IA', 'title' => 'Tutor Académico Institucional', 'breadcrumb' => 'TUTOR IA'],

                ['cap' => 'menu_calendario_consulta', 'seccion' => 'calendario_consulta', 'icon' => 'fa-calendar', 'text' => 'Calendario institucional', 'title' => 'Calendario institucional', 'breadcrumb' => 'CALENDARIO INSTITUCIONAL'],

                ['cap' => 'menu_reportes', 'seccion' => 'corte_caja', 'icon' => 'fa-coins', 'text' => 'Corte de caja', 'title' => 'Corte de caja diario', 'breadcrumb' => 'CORTE DE CAJA'],

                ['cap' => 'menu_reportes', 'flyout' => 'flyout-reportes', 'icon' => 'fa-chart-bar', 'text' => 'Reportes financieros', 'title' => 'Reportes financieros', 'breadcrumb' => 'REPORTES'],

                ['cap' => 'menu_certificaciones', 'seccion' => 'certificaciones', 'icon' => 'fa-certificate', 'text' => 'Certificaciones', 'title' => 'Certificaciones', 'breadcrumb' => 'CERTIFICACIONES'],

                ['cap' => 'asesoria_agendar', 'seccion' => 'asesoria_agendar', 'icon' => 'fa-user-clock', 'text' => 'Agendar asesoría', 'title' => 'Agendar asesoría', 'breadcrumb' => 'AGENDAR ASESORÍA'],
                ['cap' => 'asesoria_agenda_dia', 'seccion' => 'asesoria_agenda_dia', 'icon' => 'fa-calendar-day', 'text' => 'Agenda del día', 'title' => 'Agenda asesorías', 'breadcrumb' => 'AGENDA ASESORÍAS'],
                ['cap' => 'asesoria_calendario', 'seccion' => 'asesoria_calendario', 'icon' => 'fa-calendar-week', 'text' => 'Calendario asesorías', 'title' => 'Calendario asesorías', 'breadcrumb' => 'CALENDARIO ASESORÍAS'],

                ['callback' => 'curso_personalizado_puede_gestionar', 'seccion' => 'curso_personalizado_admin', 'icon' => 'fa-file-contract', 'text' => 'Cursos personalizados', 'title' => 'Cursos personalizados', 'breadcrumb' => 'CURSOS PERSONALIZADOS'],

                ['callback' => 'rol_aula_puede_ver', 'seccion' => 'rol_aulas_consulta', 'icon' => 'fa-door-closed', 'text' => 'Rol de aulas', 'title' => 'Consulta rol de aulas', 'breadcrumb' => 'ROL DE AULAS'],

                ['cap' => 'admin_usuarios', 'seccion' => 'ver_usuarios', 'icon' => 'fa-users', 'text' => 'Personal', 'title' => 'Consulta de personal', 'breadcrumb' => 'PERSONAL'],

                ['cap' => 'expediente_consultar', 'seccion' => 'expediente_consulta', 'icon' => 'fa-folder-open', 'text' => 'Expedientes documentales', 'title' => 'Consulta de expedientes', 'breadcrumb' => 'EXPEDIENTES'],

            ],

        ],

        $desarrollo,

        $soporte,

    ];

}



/** Vista del supervisor: menú agrupado por rol (sin simular otro rol). */

function menu_cncm_vista_por_rol(): bool

{

    if (function_exists('rbac_esta_simulando_rol') && rbac_esta_simulando_rol()) {

        return false;

    }

    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {

        return true;

    }

    return function_exists('rbac_es_supervisor') && rbac_es_supervisor();

}



/** Menú lateral para supervisora: secciones por rol del sistema. */

function menu_cncm_secciones_por_rol(): array

{

    return [

        [

            'id' => 'general',

            'titulo' => 'General',

            'items' => [

                ['staff' => true, 'seccion' => 'inicio_panel', 'icon' => 'fa-home', 'text' => 'Inicio', 'title' => 'Inicio', 'breadcrumb' => 'INICIO'],

                ['seccion' => 'mi_evaluacion', 'icon' => 'fa-user-check', 'text' => 'Mi evaluación', 'title' => 'Mi evaluación', 'breadcrumb' => 'MI EVALUACIÓN'],

                ['seccion' => 'matriz_entrenamiento', 'icon' => 'fa-graduation-cap', 'text' => 'Matriz de entrenamiento', 'title' => 'Matriz de entrenamiento', 'breadcrumb' => 'MATRIZ'],

                ['seccion' => 'reporte_pagos_anulados', 'icon' => 'fa-ban', 'text' => 'Pagos anulados', 'title' => 'Reporte de pagos anulados', 'breadcrumb' => 'PAGOS ANULADOS'],

                ['seccion' => 'supervisor_acuerdo_escolar', 'icon' => 'fa-file-signature', 'text' => 'Acuerdo escolar', 'title' => 'Acuerdo escolar', 'breadcrumb' => 'ACUERDO ESCOLAR'],

                ['seccion' => 'supervisor_grupos_historico', 'icon' => 'fa-history', 'text' => 'Carga histórica grupos', 'title' => 'Carga histórica de grupos', 'breadcrumb' => 'CARGA HISTÓRICA'],

            ],

        ],

        [

            'id' => 'asesor',

            'titulo' => 'Asesor',

            'rol' => true,

            'items' => [

                ['seccion' => 'pre_registro_alumnos', 'icon' => 'fa-bookmark', 'text' => 'Pre-registro alumnos', 'title' => 'Pre-Registro Alumnos', 'breadcrumb' => 'PRE-REGISTRO ALUMNOS'],

                ['seccion' => 'asesor_entrevistas', 'icon' => 'fa-handshake', 'text' => 'Entrevistas', 'title' => 'Entrevistas', 'breadcrumb' => 'ENTREVISTAS'],

                ['seccion' => 'asesor_ubicacion', 'icon' => 'fa-map-signs', 'text' => 'Examen de ubicación', 'title' => 'Examen de ubicación', 'breadcrumb' => 'UBICACIÓN'],

                ['seccion' => 'cert_preregistro_asesor', 'icon' => 'fa-award', 'text' => 'Cert. pre-registro', 'title' => 'Pre-registro certificaciones', 'breadcrumb' => 'CERTIFICACIONES PRE-REGISTRO'],

                ['seccion' => 'asesor_grupos_fases', 'icon' => 'fa-search-location', 'text' => 'Grupos por fase', 'title' => 'Grupos por fase', 'breadcrumb' => 'GRUPOS POR FASE'],

                ['seccion' => 'asesor_preinicio_grupos', 'icon' => 'fa-phone-volume', 'text' => 'Contacto pre-inicio', 'title' => 'Contacto pre-inicio', 'breadcrumb' => 'CONTACTO PRE-INICIO'],

                ['seccion' => 'gerente_podio', 'icon' => 'fa-trophy', 'text' => 'Podio de asesores', 'title' => 'Podio de asesores', 'breadcrumb' => 'PODIO ASESORES'],

                ['seccion' => 'reporte_inscritos', 'icon' => 'fa-user-check', 'text' => 'Reporte de inscritos', 'title' => 'Reporte de inscritos', 'breadcrumb' => 'REPORTE INSCRITOS'],

                ['seccion' => 'ventas_comisiones_consulta', 'icon' => 'fa-coins', 'text' => 'Mis comisiones', 'title' => 'Mis comisiones', 'breadcrumb' => 'COMISIONES'],

                ['seccion' => 'calendario_consulta', 'icon' => 'fa-calendar', 'text' => 'Calendario institucional', 'title' => 'Vista combinada calendario', 'breadcrumb' => 'CALENDARIO INSTITUCIONAL'],

            ],

        ],

        [

            'id' => 'gerente',

            'titulo' => 'Gerente de ventas',

            'rol' => true,

            'items' => [

                ['seccion' => 'gerente_dashboard', 'icon' => 'fa-chart-line', 'text' => 'Panel gerente', 'title' => 'Panel gerente', 'breadcrumb' => 'PANEL GERENTE'],

                ['seccion' => 'gerente_reportes_captacion', 'icon' => 'fa-chart-pie', 'text' => 'Reportes captación', 'title' => 'Reportes de captación', 'breadcrumb' => 'REPORTES CAPTACIÓN'],

                ['seccion' => 'gerente_reporte_geografico', 'icon' => 'fa-map-marked-alt', 'text' => 'Reporte geográfico', 'title' => 'Reporte geográfico', 'breadcrumb' => 'REPORTE GEOGRÁFICO'],

                ['seccion' => 'gerente_reporte_pendientes', 'icon' => 'fa-tasks', 'text' => 'Pendientes del plantel', 'title' => 'Pendientes del plantel', 'breadcrumb' => 'PENDIENTES PLANTEL'],

                ['seccion' => 'gerente_reporte_pendientes', 'query' => 'tab=perdidos', 'icon' => 'fa-user-times', 'text' => 'No inscritos', 'title' => 'Motivos de no inscripción', 'breadcrumb' => 'NO INSCRITOS'],

                ['seccion' => 'gerente_hay_portal', 'icon' => 'fa-layer-group', 'text' => 'Evaluación HAY (equipo)', 'title' => 'Evaluación HAY — gerente', 'breadcrumb' => 'EVALUACIÓN HAY'],

                ['seccion' => 'gerente_matriz_entrenamiento', 'icon' => 'fa-graduation-cap', 'text' => 'Matriz del equipo', 'title' => 'Matriz de entrenamiento del equipo', 'breadcrumb' => 'MATRIZ EQUIPO'],

                ['seccion' => 'gerente_cartas_nomina', 'icon' => 'fa-envelope-open-text', 'text' => 'Cartas nómina', 'title' => 'Designación cartas', 'breadcrumb' => 'CARTAS NÓMINA'],

                ['seccion' => 'gerente_escuelas', 'icon' => 'fa-school', 'text' => 'Escuelas', 'title' => 'Catálogo de escuelas', 'breadcrumb' => 'ESCUELAS'],

                ['seccion' => 'reporte_escuelas', 'icon' => 'fa-chart-bar', 'text' => 'Reporte escuelas', 'title' => 'Reporte de escuelas', 'breadcrumb' => 'REPORTE ESCUELAS'],

                ['seccion' => 'reporte_presentados', 'icon' => 'fa-user-graduate', 'text' => 'Presentados', 'title' => 'Reporte de presentados', 'breadcrumb' => 'REPORTE PRESENTADOS'],

                ['seccion' => 'ventas_comisiones_admin', 'icon' => 'fa-sliders-h', 'text' => 'Comisiones y tabuladores', 'title' => 'Comisiones y tabuladores', 'breadcrumb' => 'COMISIONES VENTAS'],

            ],

        ],

        [

            'id' => 'recepcion',

            'titulo' => 'Recepción / Caja',

            'rol' => true,

            'items' => [

                ['callback' => 'operativo_piso_puede_ver', 'seccion' => 'piso_operativo', 'icon' => 'fa-concierge-bell', 'text' => 'Piso operativo', 'title' => 'Entrega documentos y cobranza', 'breadcrumb' => 'PISO OPERATIVO'],

                ['callback' => 'cola_facturacion_puede_ver', 'seccion' => 'cola_facturacion', 'icon' => 'fa-file-invoice', 'text' => 'Cola de facturación', 'title' => 'Cola de facturación', 'breadcrumb' => 'COLA FACTURACIÓN'],

                ['seccion' => 'consulta_adeudo', 'icon' => 'fa-calculator', 'text' => 'Consulta de adeudo', 'title' => 'Consulta de adeudo', 'breadcrumb' => 'CONSULTA ADEUDO'],

                ['seccion' => 'punto_venta', 'icon' => 'fa-cash-register', 'text' => 'Punto de venta', 'title' => 'Punto de venta', 'breadcrumb' => 'PUNTO DE VENTA'],

                ['seccion' => 'venta_productos', 'icon' => 'fa-shopping-cart', 'text' => 'Venta de productos', 'title' => 'Venta de productos', 'breadcrumb' => 'VENTA DE PRODUCTOS'],

                ['callback' => 'documento_puede_marcar_pagada', 'seccion' => 'constancia_recepcion', 'icon' => 'fa-file-certificate', 'text' => 'Constancias pendientes', 'title' => 'Constancias pendientes de pago', 'breadcrumb' => 'CONSTANCIAS'],

                ['callback' => 'documento_puede_mostrador', 'seccion' => 'documento_mostrador', 'icon' => 'fa-id-card', 'text' => 'Mostrador documentos', 'title' => 'Mostrador de constancias y diplomas', 'breadcrumb' => 'MOSTRADOR DOC'],

                ['seccion' => 'corte_caja', 'icon' => 'fa-coins', 'text' => 'Corte de caja', 'title' => 'Corte de caja diario', 'breadcrumb' => 'CORTE DE CAJA'],

                ['seccion' => 'certificaciones', 'icon' => 'fa-certificate', 'text' => 'Certificaciones', 'title' => 'Certificaciones', 'breadcrumb' => 'CERTIFICACIONES'],

                ['flyout' => 'flyout-reportes', 'icon' => 'fa-chart-bar', 'text' => 'Reportes financieros', 'title' => 'Reportes', 'breadcrumb' => 'REPORTES'],

                ['callback' => 'rol_aula_puede_ver', 'seccion' => 'rol_aulas_consulta', 'icon' => 'fa-door-closed', 'text' => 'Rol de aulas', 'title' => 'Consulta rol de aulas', 'breadcrumb' => 'ROL DE AULAS'],

                ['flyout' => 'flyout-asistencia', 'icon' => 'fa-clipboard-check', 'text' => 'Asistencias', 'title' => 'Asistencias', 'breadcrumb' => 'ASISTENCIAS'],

                ['callback' => 'tutor_puede_usar', 'seccion' => 'tutor_chat', 'icon' => 'fa-robot', 'text' => 'Tutor IA', 'title' => 'Tutor Académico Institucional', 'breadcrumb' => 'TUTOR IA'],

                ['seccion' => 'calendario_consulta', 'icon' => 'fa-calendar', 'text' => 'Calendario institucional', 'title' => 'Calendario institucional', 'breadcrumb' => 'CALENDARIO INSTITUCIONAL'],

                ['callback' => 'curso_personalizado_puede_gestionar', 'seccion' => 'curso_personalizado_admin', 'icon' => 'fa-file-contract', 'text' => 'Cursos personalizados', 'title' => 'Cursos personalizados', 'breadcrumb' => 'CURSOS PERSONALIZADOS'],

            ],

        ],

        [

            'id' => 'profesor',

            'titulo' => 'Profesor',

            'rol' => true,

            'items' => [

                ['flyout' => 'flyout-asistencia', 'icon' => 'fa-clipboard-check', 'text' => 'Asistencias', 'title' => 'Asistencias', 'breadcrumb' => 'ASISTENCIAS'],

                ['flyout' => 'flyout-grupos-profesor', 'icon' => 'fa-users', 'text' => 'Grupos', 'title' => 'Grupos', 'breadcrumb' => 'GRUPOS'],

                ['seccion' => 'profesor_portal', 'icon' => 'fa-chalkboard-teacher', 'text' => 'Mi portal docente', 'title' => 'Mi portal docente', 'breadcrumb' => 'PORTAL DOCENTE'],

                ['seccion' => 'asesorias', 'icon' => 'fa-calendar-alt', 'text' => 'Disponibilidad asesorías', 'title' => 'Disponibilidad semanal', 'breadcrumb' => 'DISPONIBILIDAD ASESORÍAS'],

                ['cap' => 'asesoria_calendario', 'seccion' => 'asesoria_calendario', 'icon' => 'fa-calendar-check', 'text' => 'Mis asesorías', 'title' => 'Mis asesorías agendadas', 'breadcrumb' => 'MIS ASESORÍAS'],

                ['seccion' => 'planeaciones', 'icon' => 'fa-file-alt', 'text' => 'Planeaciones', 'title' => 'Planeaciones', 'breadcrumb' => 'PLANEACIONES'],

                ['seccion' => 'asistencia_checada', 'icon' => 'fa-fingerprint', 'text' => 'Registrar asistencia', 'title' => 'Registrar asistencia', 'breadcrumb' => 'REGISTRAR ASISTENCIA'],

            ],

        ],

        [

            'id' => 'coordinador',

            'titulo' => 'Coordinador',

            'rol' => true,

            'items' => [

                ['seccion' => 'alumnos', 'icon' => 'fa-user-graduate', 'text' => 'Alumnos', 'title' => 'Alumnos', 'breadcrumb' => 'ALUMNOS'],

                ['flyout' => 'flyout-grupos', 'icon' => 'fa-users-cog', 'text' => 'Grupos y planeación', 'title' => 'Grupos', 'breadcrumb' => 'GRUPOS'],

                ['flyout' => 'flyout-especialidades', 'icon' => 'fa-cogs', 'text' => 'Especialidades', 'title' => 'Especialidades', 'breadcrumb' => 'ESPECIALIDADES'],

                ['flyout' => 'flyout-asistencia', 'icon' => 'fa-clipboard-check', 'text' => 'Asistencias', 'title' => 'Asistencias', 'breadcrumb' => 'ASISTENCIAS'],

                ['flyout' => 'flyout-coordinador-academico', 'icon' => 'fa-file-signature', 'text' => 'Exámenes y evaluación', 'title' => 'Exámenes y evaluación', 'breadcrumb' => 'EXÁMENES Y EVALUACIÓN'],

                ['seccion' => 'planeaciones_revision', 'icon' => 'fa-check-double', 'text' => 'Revisar planeaciones', 'title' => 'Revisar planeaciones', 'breadcrumb' => 'REVISAR PLANEACIONES'],

                ['seccion' => 'cronologia_grupos', 'icon' => 'fa-table', 'text' => 'Cronología de fases', 'title' => 'Cronología de fases', 'breadcrumb' => 'CRONOLOGÍA GRUPOS'],

                ['callback' => 'aula_puede_gestionar', 'seccion' => 'admin_mapa_plantel', 'icon' => 'fa-door-open', 'text' => 'Catálogo de aulas', 'title' => 'Catálogo de aulas', 'breadcrumb' => 'CATÁLOGO AULAS'],

                ['callback' => 'rol_aula_puede_gestionar', 'seccion' => 'rol_aulas_coordinador', 'icon' => 'fa-th-large', 'text' => 'Rol de aulas', 'title' => 'Rol de aulas', 'breadcrumb' => 'ROL DE AULAS'],

                ['seccion' => 'grupo_fusion_plan', 'icon' => 'fa-object-group', 'text' => 'Planificación de fusiones', 'title' => 'Planificación de fusiones', 'breadcrumb' => 'FUSIONES GRUPOS'],

                ['callback' => 'tutor_puede_usar', 'seccion' => 'tutor_chat', 'icon' => 'fa-robot', 'text' => 'Tutor IA', 'title' => 'Tutor Académico', 'breadcrumb' => 'TUTOR IA'],

                ['seccion' => 'moodle_nivel_admin', 'icon' => 'fa-graduation-cap', 'text' => 'Moodle por nivel', 'title' => 'Moodle por nivel', 'breadcrumb' => 'MOODLE NIVEL'],

                ['seccion' => 'reporte_resumen_academico', 'icon' => 'fa-chart-line', 'text' => 'Reporte académico', 'title' => 'Resumen académico', 'breadcrumb' => 'RESUMEN ACADÉMICO'],

                ['callback' => 'documento_puede_gestionar_diplomas', 'seccion' => 'coordinador_diplomas', 'icon' => 'fa-award', 'text' => 'Diplomas por grupo', 'title' => 'Diplomas por grupo', 'breadcrumb' => 'DIPLOMAS'],

                ['seccion' => 'academico_portal_alumno', 'icon' => 'fa-bullhorn', 'text' => 'Avisos y chat alumno', 'title' => 'Avisos y mensajes alumnos', 'breadcrumb' => 'AVISOS ALUMNO'],

            ],

        ],

        [

            'id' => 'director',

            'titulo' => 'Director / Supervisión',

            'rol' => true,

            'items' => menu_cncm_director_items(),

        ],

        [

            'id' => 'soporte',

            'titulo' => 'Soporte',

            'items' => [

                ['staff' => true, 'seccion' => 'soporte_tecnico', 'icon' => 'fa-headset', 'text' => 'Soporte técnico', 'title' => 'Soporte Técnico', 'breadcrumb' => 'SOPORTE TÉCNICO'],

            ],

        ],

    ];

}



function menu_cncm_item_visible(array $item): bool

{

    if (menu_cncm_vista_por_rol()) {

        if (!empty($item['staff'])) {

            return !empty($_SESSION['user_id']);

        }



        return true;

    }

    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {

        return true;

    }

    if (!empty($item['staff'])) {

        return !empty($_SESSION['user_id']) && rbac_rol_efectivo() !== 'alumno';

    }

    if (!empty($item['callback']) && is_string($item['callback']) && function_exists($item['callback'])) {

        return (bool) call_user_func($item['callback']);

    }

    if (!empty($item['cap']) && function_exists('rbac_cap')) {

        return rbac_cap((string) $item['cap']);

    }



    return false;

}



function menu_cncm_seccion_visible(array $sec): bool

{

    if (menu_cncm_vista_por_rol()) {

        return true;

    }

    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {

        return true;

    }

    if (!empty($sec['cap']) && function_exists('rbac_cap') && rbac_cap((string) $sec['cap'])) {

        return true;

    }

    if (!empty($sec['caps_any']) && is_array($sec['caps_any']) && function_exists('rbac_cap')) {

        foreach ($sec['caps_any'] as $c) {

            if (rbac_cap((string) $c)) {

                return true;

            }

        }

    }

    foreach ($sec['items'] ?? [] as $it) {

        if (menu_cncm_item_visible($it)) {

            return true;

        }

    }



    return false;

}



/** @param list<array<string, mixed>> $secciones */

function menu_cncm_emitir_items(array $secciones, bool $ignorarVisibilidad = false): int

{

    $emitidos = 0;

    foreach ($secciones as $sec) {

        if (!$ignorarVisibilidad && !menu_cncm_seccion_visible($sec)) {

            continue;

        }

        $titleCls = 'section-title';

        if (!empty($sec['rol'])) {

            $titleCls .= ' section-title--rol';

        }

        echo '<li class="' . $titleCls . '">' . htmlspecialchars((string) ($sec['titulo'] ?? '')) . '</li>';

        foreach ($sec['items'] ?? [] as $it) {

            if (!$ignorarVisibilidad && !menu_cncm_item_visible($it)) {

                continue;

            }

            $flyout = $it['flyout'] ?? '';

            $seccion = $it['seccion'] ?? '';

            $cls = 'nav-item';

            if ($flyout !== '') {

                $cls .= ' has-flyout';

            }

            echo '<li class="' . $cls . '"';

            if ($flyout !== '') {

                echo ' data-flyout="' . htmlspecialchars($flyout) . '"';

            } elseif ($seccion !== '') {

                echo ' data-seccion="' . htmlspecialchars($seccion) . '"';

            }

            echo ' data-title="' . htmlspecialchars($it['title'] ?? '') . '"';

            echo ' data-breadcrumb="' . htmlspecialchars($it['breadcrumb'] ?? '') . '"';

            if (!empty($it['query'])) {

                echo ' data-query="' . htmlspecialchars((string) $it['query']) . '"';

            }

            echo '>';

            echo '<i class="fas ' . htmlspecialchars($it['icon'] ?? 'fa-circle') . '"></i>';

            echo '<span class="nav-text">' . htmlspecialchars($it['text'] ?? '') . '</span>';

            if ($flyout !== '') {

                echo '<i class="fas fa-chevron-down arrow"></i>';

            }

            echo '</li>';

            $emitidos++;

        }

    }



    return $emitidos;

}



/** Menú mínimo si falla la renderización normal (supervisor / acceso total). */

function menu_cncm_render_emergencia(): void

{

    $items = [

        ['seccion' => 'inicio_panel', 'icon' => 'fa-home', 'text' => 'Inicio', 'title' => 'Inicio', 'breadcrumb' => 'INICIO'],

        ['seccion' => 'ver_usuarios', 'icon' => 'fa-users-cog', 'text' => 'Usuarios', 'title' => 'Usuarios', 'breadcrumb' => 'USUARIOS'],

        ['seccion' => 'admin_roles', 'icon' => 'fa-shield-alt', 'text' => 'Roles y permisos', 'title' => 'Roles y permisos', 'breadcrumb' => 'ROLES Y PERMISOS'],

        ['seccion' => 'admin_planteles', 'icon' => 'fa-building', 'text' => 'Planteles', 'title' => 'Planteles', 'breadcrumb' => 'PLANTELES'],

        ['seccion' => 'alumnos', 'icon' => 'fa-user-graduate', 'text' => 'Alumnos', 'title' => 'Alumnos', 'breadcrumb' => 'ALUMNOS'],

        ['seccion' => 'pre_registro_alumnos', 'icon' => 'fa-bookmark', 'text' => 'Pre-registro', 'title' => 'Pre-registro alumnos', 'breadcrumb' => 'PRE-REGISTRO'],

        ['seccion' => 'consulta_adeudo', 'icon' => 'fa-calculator', 'text' => 'Consulta de adeudo', 'title' => 'Consulta de adeudo', 'breadcrumb' => 'CONSULTA ADEUDO'],

        ['seccion' => 'punto_venta', 'icon' => 'fa-cash-register', 'text' => 'Punto de venta', 'title' => 'Punto de venta', 'breadcrumb' => 'PUNTO DE VENTA'],

        ['flyout' => 'flyout-admin', 'icon' => 'fa-cog', 'text' => 'Administración', 'title' => 'Administración', 'breadcrumb' => 'ADMINISTRACIÓN'],

        ['seccion' => 'soporte_tecnico', 'icon' => 'fa-headset', 'text' => 'Soporte técnico', 'title' => 'Soporte', 'breadcrumb' => 'SOPORTE'],

    ];

    menu_cncm_emitir_items([['titulo' => 'Accesos principales', 'items' => $items]], true);

}



/** Renderiza ítems de menú configurados (nav-item). */

function menu_cncm_render_items(): void

{

    if (menu_cncm_usa_menu_compacto()) {

        $secciones = menu_cncm_secciones_compactas();

    } else {

        $secciones = menu_cncm_vista_por_rol() ? menu_cncm_secciones_por_rol() : menu_cncm_secciones();

    }

    $emitidos = menu_cncm_emitir_items($secciones);

    if ($emitidos > 0 || empty($_SESSION['user_id'])) {

        return;

    }

    $esStaff = function_exists('rbac_rol_efectivo') && rbac_rol_efectivo() !== 'alumno';

    $esSuper = (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total())

        || (function_exists('rbac_es_supervisor') && rbac_es_supervisor());

    if ($esStaff && $esSuper) {

        error_log('HAY menu vacío para supervisor id=' . (int) $_SESSION['user_id'] . '; usando menú de respaldo.');

        if (function_exists('rbac_supervisor_aplicar_sesion')) {

            rbac_supervisor_aplicar_sesion();

        }

        menu_cncm_emitir_items(menu_cncm_secciones_por_rol(), true);

    }

}



/** Envuelve la renderización del menú con reparación de sesión y fallback. */

function menu_cncm_render_items_safe(): void

{

    if (function_exists('rbac_supervisor_aplicar_sesion')) {

        rbac_supervisor_aplicar_sesion();

    }

    try {

        if (!function_exists('menu_cncm_render_items')) {

            menu_cncm_render_emergencia();

            return;

        }

        ob_start();

        menu_cncm_render_items();

        $html = (string) ob_get_clean();

        if ($html !== '' && substr_count($html, 'nav-item') > 0) {

            echo $html;

            return;

        }

        error_log('HAY menu_cncm_render_items_safe: HTML vacío para user_id=' . (int) ($_SESSION['user_id'] ?? 0));

        menu_cncm_render_emergencia();

    } catch (Throwable $e) {

        error_log('HAY menu_cncm_render_items_safe: ' . $e->getMessage());

        menu_cncm_render_emergencia();

    }

}



/** Flyout especialidades dinámico desde catálogo. */

function menu_cncm_flyout_especialidades_items(PDO $pdo): array

{

    $items = [

        ['seccion' => 'esp_fases', 'title' => 'Fases por especialidad', 'breadcrumb' => 'FASES ESPECIALIDAD', 'text' => 'Fases por especialidad'],

        ['seccion' => 'planeacion_prompts', 'title' => 'Plantillas IA planeación', 'breadcrumb' => 'PLANTILLAS IA PLANEACIÓN', 'text' => 'Plantillas IA planeación'],

    ];

    $st = $pdo->query(

        "SELECT id_especialidad, clave, nombre FROM especialidades WHERE activo = 1 AND visible = 1 ORDER BY orden, nombre LIMIT 20"

    );

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $e) {

        $items[] = [

            'seccion' => 'esp_fases',

            'query' => 'id_especialidad=' . (int) $e['id_especialidad'],

            'title' => $e['nombre'] . ' — fases',

            'breadcrumb' => 'FASES ' . strtoupper($e['clave']),

            'text' => $e['nombre'],

        ];

    }

    if (function_exists('ubicacion_puede_evaluar') && ubicacion_puede_evaluar()) {

        $items[] = ['seccion' => 'ubicacion_coordinacion', 'title' => 'Examen de ubicación', 'breadcrumb' => 'UBICACIÓN', 'text' => 'Examen de ubicación'];

        $items[] = ['seccion' => 'admin_ubicacion_examenes', 'title' => 'Catálogo exámenes ubicación', 'breadcrumb' => 'EXÁMENES UBICACIÓN', 'text' => 'Exámenes Moodle (ubicación)'];

    }

    if (function_exists('inscripcion_protocolo_puede_autorizar') && inscripcion_protocolo_puede_autorizar()) {

        $items[] = ['seccion' => 'inscripcion_autorizaciones', 'title' => 'Autorizaciones inscripción', 'breadcrumb' => 'AUTORIZACIONES', 'text' => 'Autorizaciones inscripción'];

    }

    if (function_exists('moodle_nivel_puede_administrar') && moodle_nivel_puede_administrar()) {

        $items[] = ['seccion' => 'moodle_nivel_admin', 'title' => 'Moodle por nivel', 'breadcrumb' => 'MOODLE NIVEL', 'text' => 'Moodle por nivel'];

    }



    return $items;

}

