-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 03-07-2026 a las 18:12:58
-- Versión del servidor: 10.6.25-MariaDB-cll-lve-log
-- Versión de PHP: 8.4.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `cncmedum_hay_system`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `academico_libro`
--

CREATE TABLE `academico_libro` (
  `id_libro` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `tipo` enum('studentbook','workbook','libro_profesor','guia_profesor') NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `academico_libro_version`
--

CREATE TABLE `academico_libro_version` (
  `id_version` int(10) UNSIGNED NOT NULL,
  `id_libro` int(10) UNSIGNED NOT NULL,
  `etiqueta` varchar(40) NOT NULL,
  `ruta_pdf` varchar(500) NOT NULL,
  `num_paginas` smallint(5) UNSIGNED DEFAULT NULL,
  `pagina_inicio_workbook` smallint(5) UNSIGNED DEFAULT NULL,
  `hash_sha256` char(64) DEFAULT NULL,
  `activo_alumno` tinyint(1) NOT NULL DEFAULT 0,
  `activo_rag` tinyint(1) NOT NULL DEFAULT 0,
  `estado_indexacion` enum('pendiente','procesando','listo','error') NOT NULL DEFAULT 'pendiente',
  `error_indexacion` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `academico_material`
--

CREATE TABLE `academico_material` (
  `id_material` int(10) UNSIGNED NOT NULL,
  `tipo` enum('libro_alumno','libro_profesor','workbook','studentbook','guia_profesor','moodle_actividad','pdf_fragmento','otro') NOT NULL DEFAULT 'otro',
  `id_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `id_fase` int(10) UNSIGNED DEFAULT NULL,
  `semana` tinyint(3) UNSIGNED DEFAULT NULL,
  `pagina_inicio` smallint(5) UNSIGNED DEFAULT NULL,
  `pagina_fin` smallint(5) UNSIGNED DEFAULT NULL,
  `titulo` varchar(220) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `contenido_texto` mediumtext DEFAULT NULL,
  `ruta_archivo` varchar(500) DEFAULT NULL,
  `moodle_course_id` int(10) UNSIGNED DEFAULT NULL,
  `moodle_cm_id` int(10) UNSIGNED DEFAULT NULL,
  `moodle_url` varchar(500) DEFAULT NULL,
  `etiquetas` varchar(500) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `id_libro` int(10) UNSIGNED DEFAULT NULL,
  `id_version` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `academico_material_embedding`
--

CREATE TABLE `academico_material_embedding` (
  `id_embedding` bigint(20) UNSIGNED NOT NULL,
  `id_material` int(10) UNSIGNED NOT NULL,
  `id_version` int(10) UNSIGNED NOT NULL,
  `modelo` varchar(80) NOT NULL,
  `embedding_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`embedding_json`)),
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acuerdo_escolar_version`
--

CREATE TABLE `acuerdo_escolar_version` (
  `id_acuerdo_version` int(10) UNSIGNED NOT NULL,
  `version_label` varchar(40) NOT NULL,
  `contenido` mediumtext NOT NULL,
  `vigente_desde` date DEFAULT NULL,
  `activo_para_nuevos` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `id_usuario` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos`
--

CREATE TABLE `alumnos` (
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `numero_control` varchar(12) DEFAULT NULL,
  `nombres` varchar(120) DEFAULT NULL,
  `apellido_paterno` varchar(80) DEFAULT NULL,
  `apellido_materno` varchar(80) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `codigo_huella` varchar(40) DEFAULT NULL,
  `huella_registrada` tinyint(1) NOT NULL DEFAULT 0,
  `huella_registrada_en` datetime DEFAULT NULL,
  `huella_dispositivo` varchar(60) DEFAULT NULL,
  `estado` enum('activo','baja','graduado') NOT NULL DEFAULT 'activo',
  `forma_pago` enum('mensual','semanal') NOT NULL DEFAULT 'mensual',
  `id_usuario_asesor` int(10) UNSIGNED DEFAULT NULL,
  `id_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `id_escuela_origen` int(10) UNSIGNED DEFAULT NULL,
  `id_preregistro` int(10) UNSIGNED DEFAULT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `moodle_user_id` int(10) UNSIGNED DEFAULT NULL,
  `perfil_gustos` text DEFAULT NULL,
  `perfil_intereses_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`perfil_intereses_json`)),
  `perfil_completado` tinyint(1) NOT NULL DEFAULT 0,
  `perfil_completado_en` datetime DEFAULT NULL,
  `acuerdo_pendiente_version` int(10) UNSIGNED DEFAULT NULL,
  `inscripcion_global_pagada` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_inscripcion_global` date DEFAULT NULL,
  `inscripcion_vigente_hasta` date DEFAULT NULL,
  `fecha_baja_temporal` date DEFAULT NULL,
  `inscripcion_kids_modo` varchar(20) NOT NULL DEFAULT '',
  `id_regla_colegiatura_pref` int(10) UNSIGNED DEFAULT NULL,
  `motivo_baja_temporal` varchar(255) DEFAULT NULL,
  `pagos_programados` smallint(5) UNSIGNED DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `edad` smallint(5) UNSIGNED DEFAULT NULL,
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `id_grupo` int(10) UNSIGNED DEFAULT NULL,
  `nombre` varchar(120) NOT NULL,
  `apellido` varchar(120) NOT NULL,
  `matricula` varchar(60) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_alta` date NOT NULL DEFAULT curdate(),
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_acuerdo_aceptacion`
--

CREATE TABLE `alumno_acuerdo_aceptacion` (
  `id_aceptacion` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_acuerdo_version` int(10) UNSIGNED NOT NULL,
  `fecha_aceptacion` datetime NOT NULL DEFAULT current_timestamp(),
  `ip` varchar(45) DEFAULT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_adeudo_condonacion`
--

CREATE TABLE `alumno_adeudo_condonacion` (
  `id_condonacion` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_alumno_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `id_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `monto_condonado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `adeudo_antes` decimal(12,2) NOT NULL DEFAULT 0.00,
  `detalle_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detalle_json`)),
  `motivo` varchar(255) NOT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_aviso`
--

CREATE TABLE `alumno_aviso` (
  `id_aviso` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL = todo el plantel',
  `titulo` varchar(160) NOT NULL,
  `mensaje` text NOT NULL,
  `id_usuario_autor` int(10) UNSIGNED DEFAULT NULL,
  `autor_nombre` varchar(120) DEFAULT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_becas`
--

CREATE TABLE `alumno_becas` (
  `id_beca` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_alumno_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `aplicar_a` enum('inscripcion','colegiatura','ambos') NOT NULL DEFAULT 'colegiatura',
  `tipo` enum('porcentaje','monto_fijo') NOT NULL DEFAULT 'porcentaje',
  `valor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `motivo` varchar(255) NOT NULL,
  `id_autoriza` int(10) UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_calificaciones_fase`
--

CREATE TABLE `alumno_calificaciones_fase` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_fase` int(10) UNSIGNED NOT NULL,
  `calificacion` decimal(5,2) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_calificacion_parcial`
--

CREATE TABLE `alumno_calificacion_parcial` (
  `id_calificacion` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_fase` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED DEFAULT NULL,
  `notas_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`notas_json`)),
  `promedio` decimal(4,2) DEFAULT NULL,
  `aprobado` tinyint(1) DEFAULT NULL,
  `capturado_por` int(10) UNSIGNED DEFAULT NULL,
  `editado_por` int(10) UNSIGNED DEFAULT NULL,
  `observaciones` varchar(500) DEFAULT NULL,
  `capturado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_chat_mensaje`
--

CREATE TABLE `alumno_chat_mensaje` (
  `id_mensaje` int(10) UNSIGNED NOT NULL,
  `id_sala` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `id_alumno` int(10) UNSIGNED DEFAULT NULL,
  `autor_nombre` varchar(120) NOT NULL,
  `mensaje` text NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_chat_sala`
--

CREATE TABLE `alumno_chat_sala` (
  `id_sala` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `tipo` enum('grupo','recepcion','coordinacion') NOT NULL,
  `id_grupo` int(10) UNSIGNED DEFAULT NULL,
  `nombre` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_documento`
--

CREATE TABLE `alumno_documento` (
  `id_documento` int(10) UNSIGNED NOT NULL,
  `tipo` enum('constancia','diploma') NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED DEFAULT NULL,
  `id_plantilla` int(10) UNSIGNED NOT NULL,
  `id_producto` int(10) UNSIGNED DEFAULT NULL,
  `id_pago` int(10) UNSIGNED DEFAULT NULL,
  `folio` varchar(32) NOT NULL,
  `token_verificacion` char(32) NOT NULL,
  `campos_opciones` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`campos_opciones`)),
  `campos_extra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`campos_extra`)),
  `estado` enum('pendiente_pago','pagada','expirada','cancelada') NOT NULL DEFAULT 'pendiente_pago',
  `vigente_hasta` date DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `solicitado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `pagado_en` datetime DEFAULT NULL,
  `pagado_por` int(10) UNSIGNED DEFAULT NULL,
  `generado_en` datetime DEFAULT NULL,
  `entregado_en` datetime DEFAULT NULL,
  `entregado_por` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_documentos`
--

CREATE TABLE `alumno_documentos` (
  `id_documento` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `tipo` varchar(60) NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `ruta` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_especialidades`
--

CREATE TABLE `alumno_especialidades` (
  `id_alumno_especialidad` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `forma_pago` enum('mensual','semanal') NOT NULL DEFAULT 'mensual',
  `fecha_inscripcion` date NOT NULL,
  `costo_inscripcion` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_inscripcion_referencia` decimal(12,2) DEFAULT NULL,
  `costo_inscripcion_apoyo` decimal(12,2) DEFAULT NULL,
  `costo_mensualidad` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_mensualidad_referencia` decimal(12,2) DEFAULT NULL,
  `costo_mensualidad_apoyo` decimal(12,2) DEFAULT NULL,
  `costo_pronto_pago` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_pronto_pago_referencia` decimal(12,2) DEFAULT NULL,
  `costo_pronto_pago_apoyo` decimal(12,2) DEFAULT NULL,
  `costo_semanal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_semanal_referencia` decimal(12,2) DEFAULT NULL,
  `costo_semanal_apoyo` decimal(12,2) DEFAULT NULL,
  `usa_tarifa_cartas` tinyint(1) NOT NULL DEFAULT 0,
  `duracion_meses` smallint(5) UNSIGNED NOT NULL DEFAULT 12,
  `duracion_semanas` smallint(5) UNSIGNED DEFAULT NULL,
  `inscripcion_cubierta` tinyint(1) NOT NULL DEFAULT 0,
  `cuatrimestre_actual` varchar(10) DEFAULT NULL,
  `colegiatura_meses_pausa` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `colegiatura_meses_extension` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `colegiatura_pausa_desde` date DEFAULT NULL,
  `id_regla_combo` int(10) UNSIGNED DEFAULT NULL,
  `base_costo_inscripcion` decimal(12,2) DEFAULT NULL,
  `base_costo_mensualidad` decimal(12,2) DEFAULT NULL,
  `base_costo_pronto_pago` decimal(12,2) DEFAULT NULL,
  `base_costo_semanal` decimal(12,2) DEFAULT NULL,
  `override_supervisor` tinyint(1) NOT NULL DEFAULT 0,
  `override_vigente_hasta` date DEFAULT NULL,
  `override_motivo` varchar(255) DEFAULT NULL,
  `override_id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `override_actualizado` datetime DEFAULT NULL,
  `override_resto_curso` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_grupos`
--

CREATE TABLE `alumno_grupos` (
  `id_alumno_grupo` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `id_fase_entrada` int(10) UNSIGNED DEFAULT NULL,
  `ubicacion_examen` tinyint(1) NOT NULL DEFAULT 0,
  `en_riesgo_academico` tinyint(1) NOT NULL DEFAULT 0,
  `omitir_alerta_riesgo` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_inicio` date NOT NULL DEFAULT curdate(),
  `fecha_baja` date DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_huellas`
--

CREATE TABLE `alumno_huellas` (
  `id_huella` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `codigo_huella` varchar(40) NOT NULL,
  `dedo` varchar(24) NOT NULL DEFAULT 'indice_derecho',
  `formato` varchar(30) NOT NULL DEFAULT 'intermediate',
  `template_data` mediumtext NOT NULL,
  `template_fmd` mediumtext DEFAULT NULL,
  `fmd_formato` varchar(30) DEFAULT NULL,
  `dispositivo` varchar(60) NOT NULL DEFAULT 'uareu_5300',
  `calidad` tinyint(3) UNSIGNED DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `id_usuario_registro` int(10) UNSIGNED DEFAULT NULL,
  `registrado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_moodle_curso`
--

CREATE TABLE `alumno_moodle_curso` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `id_fase` int(10) UNSIGNED DEFAULT NULL,
  `moodle_course_id` int(10) UNSIGNED NOT NULL,
  `moodle_user_id` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_notas`
--

CREATE TABLE `alumno_notas` (
  `id_nota` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `nota` text NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_nota_coordinacion`
--

CREATE TABLE `alumno_nota_coordinacion` (
  `id_nota` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `tipo` enum('orientacion_grupo','ubicacion','riesgo_academico','general') NOT NULL DEFAULT 'general',
  `nota` text NOT NULL,
  `alumno_acepto_cambio` tinyint(1) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_pagos`
--

CREATE TABLE `alumno_pagos` (
  `id_pago` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `id_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `tipo` enum('inscripcion','mensualidad','semanal','abono','producto','otro') NOT NULL DEFAULT 'abono',
  `id_producto` int(10) UNSIGNED DEFAULT NULL,
  `id_solicitud_cert` int(10) UNSIGNED DEFAULT NULL COMMENT 'Certificación cobrada en PV',
  `id_alumno_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `periodo_ref` varchar(20) DEFAULT NULL,
  `aplico_pronto_pago` tinyint(1) NOT NULL DEFAULT 0,
  `id_beca` int(10) UNSIGNED DEFAULT NULL,
  `id_promocion` int(10) UNSIGNED DEFAULT NULL,
  `monto_descuento` decimal(12,2) NOT NULL DEFAULT 0.00,
  `motivo_descuento` varchar(255) DEFAULT NULL,
  `id_autoriza` int(10) UNSIGNED DEFAULT NULL,
  `folio` varchar(20) DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monto_referencia` decimal(12,2) DEFAULT NULL,
  `monto_apoyo` decimal(12,2) DEFAULT NULL,
  `etiqueta_apoyo` varchar(120) DEFAULT NULL,
  `cobro_precio_lista` tinyint(1) NOT NULL DEFAULT 0,
  `origen_cartas` tinyint(1) NOT NULL DEFAULT 0,
  `comision_asesor_manual` decimal(12,2) DEFAULT NULL,
  `comision_gerente_sobre` decimal(12,2) DEFAULT NULL,
  `excluir_tabulador` tinyint(1) NOT NULL DEFAULT 0,
  `id_autoriza_director` int(10) UNSIGNED DEFAULT NULL,
  `forma_pago` varchar(40) DEFAULT NULL,
  `medio_pago` enum('efectivo','tarjeta_debito','tarjeta_credito','transferencia') DEFAULT NULL,
  `cuenta_contable` char(1) DEFAULT NULL COMMENT 'A=tarjeta/transfer/factura B=efectivo sin factura',
  `concepto` text DEFAULT NULL,
  `cliente_nombre` varchar(160) DEFAULT NULL COMMENT 'Comprador si no es alumno',
  `cubrio` text DEFAULT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_pago` date DEFAULT NULL,
  `estado` enum('activo','anulado') NOT NULL DEFAULT 'activo',
  `anulado_en` datetime DEFAULT NULL,
  `anulado_por` int(10) UNSIGNED DEFAULT NULL,
  `anulado_motivo` varchar(500) DEFAULT NULL,
  `id_pago_reemplazo` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_pago_movimiento`
--

CREATE TABLE `alumno_pago_movimiento` (
  `id_mov` int(10) UNSIGNED NOT NULL,
  `id_pago` int(10) UNSIGNED NOT NULL,
  `id_pago_nuevo` int(10) UNSIGNED DEFAULT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `tipo` enum('anular','editar_monto','editar_concepto') NOT NULL,
  `snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`snapshot_json`)),
  `motivo` varchar(500) NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_plan_asignado`
--

CREATE TABLE `alumno_plan_asignado` (
  `id_asignacion` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `id_plan_version` int(10) UNSIGNED NOT NULL,
  `fecha_asignacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_tarifa_override_hist`
--

CREATE TABLE `alumno_tarifa_override_hist` (
  `id_hist` int(10) UNSIGNED NOT NULL,
  `id_alumno_especialidad` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `accion` enum('aplicar','restaurar','vencer','condonar') NOT NULL,
  `costo_inscripcion` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_mensualidad` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_pronto_pago` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_semanal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `base_inscripcion` decimal(12,2) DEFAULT NULL,
  `base_mensualidad` decimal(12,2) DEFAULT NULL,
  `base_pronto_pago` decimal(12,2) DEFAULT NULL,
  `base_semanal` decimal(12,2) DEFAULT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_ubicacion`
--

CREATE TABLE `alumno_ubicacion` (
  `id_ubicacion` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `id_examen_ubicacion` int(10) UNSIGNED DEFAULT NULL,
  `evaluado_por` int(10) UNSIGNED DEFAULT NULL,
  `fecha_evaluacion` date NOT NULL,
  `nivel_detectado` varchar(20) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `moodle_inscrito` tinyint(1) NOT NULL DEFAULT 0,
  `estado` enum('pendiente','autorizado','rechazado','usado') NOT NULL DEFAULT 'pendiente',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno_ubicacion_grupos`
--

CREATE TABLE `alumno_ubicacion_grupos` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_ubicacion` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asesoria_cita`
--

CREATE TABLE `asesoria_cita` (
  `id_cita` bigint(20) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` tinyint(3) UNSIGNED NOT NULL,
  `id_profesor` int(10) UNSIGNED NOT NULL,
  `materia_clave` varchar(48) NOT NULL DEFAULT '',
  `id_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `tema` varchar(200) NOT NULL DEFAULT '',
  `tipo` enum('falta_gratis','pagada_materia','pagada_cross','regularizacion','kids','kids_dual') NOT NULL DEFAULT 'pagada_materia',
  `estado` enum('agendada','confirmada','impartida','np','cancelada_a_tiempo','reagendada','cancelada') NOT NULL DEFAULT 'agendada',
  `max_alumnos` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `mismo_tema` tinyint(1) NOT NULL DEFAULT 1,
  `moodle_verificado` tinyint(1) NOT NULL DEFAULT 0,
  `costo_total_alumnos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cancelada_en` datetime DEFAULT NULL,
  `motivo_cancelacion` varchar(500) DEFAULT NULL,
  `confirmada_recepcion_en` datetime DEFAULT NULL,
  `id_usuario_agenda` int(10) UNSIGNED DEFAULT NULL,
  `id_autorizacion_mismo_dia` int(10) UNSIGNED DEFAULT NULL,
  `notas_internas` varchar(500) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asesoria_cita_alumno`
--

CREATE TABLE `asesoria_cita_alumno` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_cita` bigint(20) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED DEFAULT NULL,
  `costo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `id_credito` bigint(20) UNSIGNED DEFAULT NULL,
  `id_pago` int(10) UNSIGNED DEFAULT NULL,
  `asistio` tinyint(1) DEFAULT NULL,
  `estado_cobro` varchar(24) NOT NULL DEFAULT 'pendiente',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asesoria_credito`
--

CREATE TABLE `asesoria_credito` (
  `id_credito` bigint(20) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `origen` varchar(32) NOT NULL,
  `horas_otorgadas` decimal(4,2) NOT NULL DEFAULT 1.00,
  `horas_usadas` decimal(4,2) NOT NULL DEFAULT 0.00,
  `solo_individual` tinyint(1) NOT NULL DEFAULT 0,
  `vence_en` date DEFAULT NULL,
  `id_grupo` int(10) UNSIGNED DEFAULT NULL,
  `semana_falta` date DEFAULT NULL,
  `notas` varchar(500) DEFAULT NULL,
  `id_usuario_otorga` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asesoria_disp`
--

CREATE TABLE `asesoria_disp` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `id_profesor` int(11) NOT NULL,
  `anio` smallint(5) UNSIGNED NOT NULL,
  `semana` tinyint(3) UNSIGNED NOT NULL,
  `dow` tinyint(3) UNSIGNED NOT NULL,
  `hora` tinyint(3) UNSIGNED NOT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asesoria_pago_profesor`
--

CREATE TABLE `asesoria_pago_profesor` (
  `id_pago` bigint(20) UNSIGNED NOT NULL,
  `id_cita` bigint(20) UNSIGNED NOT NULL,
  `id_profesor` int(10) UNSIGNED NOT NULL,
  `concepto` varchar(200) NOT NULL,
  `importe` decimal(12,2) NOT NULL,
  `id_nomina_linea` int(10) UNSIGNED DEFAULT NULL,
  `liquidado` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asesoria_tabulador`
--

CREATE TABLE `asesoria_tabulador` (
  `id_tabulador` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `clave` varchar(48) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `monto_alumno` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monto_profesor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vigente_desde` date DEFAULT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asesor_cartas_periodo`
--

CREATE TABLE `asesor_cartas_periodo` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_usuario_asesor` int(10) UNSIGNED NOT NULL,
  `periodo_mes` varchar(10) NOT NULL COMMENT 'YYYY-Www semana ISO o YYYY-MM legado',
  `notas` varchar(255) DEFAULT NULL,
  `registrado_por` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asesor_entrevistas`
--

CREATE TABLE `asesor_entrevistas` (
  `id_entrevista` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_usuario_asesor` int(10) UNSIGNED NOT NULL COMMENT 'Asesor al que se contabiliza',
  `id_usuario_registra` int(10) UNSIGNED NOT NULL COMMENT 'Quién capturó (asesor o gerente)',
  `origen` enum('propia','registrada_supervisor') NOT NULL DEFAULT 'propia',
  `nombres` varchar(120) NOT NULL,
  `apellido_paterno` varchar(80) DEFAULT NULL,
  `apellido_materno` varchar(80) DEFAULT NULL,
  `telefono` varchar(40) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` enum('contacto','preregistro','inscrito','descartado') NOT NULL DEFAULT 'contacto',
  `id_preregistro` int(10) UNSIGNED DEFAULT NULL,
  `id_alumno` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencias`
--

CREATE TABLE `asistencias` (
  `id_asistencia` bigint(20) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `anio` smallint(5) UNSIGNED NOT NULL,
  `semana` tinyint(3) UNSIGNED NOT NULL,
  `presente` tinyint(1) NOT NULL DEFAULT 1,
  `origen` enum('huella','recepcion') NOT NULL DEFAULT 'recepcion',
  `hora_llegada` time DEFAULT NULL,
  `id_usuario_registro` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencia_falta_seguimiento`
--

CREATE TABLE `asistencia_falta_seguimiento` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `estado_contacto` varchar(30) NOT NULL DEFAULT 'pendiente',
  `observacion` text DEFAULT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencia_personal`
--

CREATE TABLE `asistencia_personal` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `hora_llegada` time NOT NULL,
  `hora_salida` time DEFAULT NULL,
  `origen` enum('huella','manual') NOT NULL DEFAULT 'huella',
  `id_usuario_registro` int(10) UNSIGNED DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `aula_especialidades`
--

CREATE TABLE `aula_especialidades` (
  `id_aula` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `permitido` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `aula_fotos`
--

CREATE TABLE `aula_fotos` (
  `id_foto` int(10) UNSIGNED NOT NULL,
  `id_aula` int(10) UNSIGNED NOT NULL,
  `orden` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `ruta` varchar(255) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calendario_dia_lectivo`
--

CREATE TABLE `calendario_dia_lectivo` (
  `id` int(10) UNSIGNED NOT NULL,
  `anio` smallint(5) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `tipo` varchar(40) NOT NULL DEFAULT 'sin_clase_abierto',
  `aplica_a` enum('todos','sabado','domingo','entre_semana') NOT NULL DEFAULT 'todos',
  `etiqueta` varchar(120) DEFAULT NULL,
  `plantel_abierto` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_recuperacion` date DEFAULT NULL,
  `modelo` varchar(32) NOT NULL DEFAULT 'regular',
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calendario_escolar_anio`
--

CREATE TABLE `calendario_escolar_anio` (
  `anio` smallint(5) UNSIGNED NOT NULL,
  `modelo` varchar(32) NOT NULL DEFAULT 'regular',
  `publicado` tinyint(1) NOT NULL DEFAULT 0,
  `notas` text DEFAULT NULL,
  `actualizado_por` int(10) UNSIGNED DEFAULT NULL,
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calendario_evento_admin`
--

CREATE TABLE `calendario_evento_admin` (
  `id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(160) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` varchar(40) NOT NULL DEFAULT 'evento',
  `fecha` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `lugar` varchar(160) DEFAULT NULL,
  `publicado` tinyint(1) NOT NULL DEFAULT 0,
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `creado_por` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calendario_evento_audiencia`
--

CREATE TABLE `calendario_evento_audiencia` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_evento` int(10) UNSIGNED NOT NULL,
  `tipo_audiencia` enum('todos','rol','departamento','usuario') NOT NULL,
  `valor` varchar(80) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `certificacion_accesos`
--

CREATE TABLE `certificacion_accesos` (
  `id_acceso` int(10) UNSIGNED NOT NULL,
  `id_solicitud` int(10) UNSIGNED NOT NULL,
  `vigente` tinyint(1) NOT NULL DEFAULT 1,
  `usuario` varchar(120) DEFAULT NULL,
  `password_acceso` varchar(120) DEFAULT NULL,
  `institution_id` varchar(80) DEFAULT NULL,
  `id_examen_alumno` varchar(80) DEFAULT NULL,
  `clave_dia` varchar(80) DEFAULT NULL,
  `url_examen` varchar(500) DEFAULT NULL,
  `url_software` varchar(500) DEFAULT NULL,
  `url_zoom` varchar(500) DEFAULT NULL,
  `clave_grupo` varchar(80) DEFAULT NULL,
  `voucher` varchar(120) DEFAULT NULL,
  `codigo_curso` varchar(120) DEFAULT NULL,
  `sede_direccion` text DEFAULT NULL,
  `contacto_supervisor` varchar(200) DEFAULT NULL,
  `contacto_nombre` varchar(120) DEFAULT NULL,
  `notas_entrega` text DEFAULT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `certificacion_campo_catalogo`
--

CREATE TABLE `certificacion_campo_catalogo` (
  `clave` varchar(60) NOT NULL,
  `etiqueta` varchar(120) NOT NULL,
  `tipo` enum('text','date','time','email','phone','bool') NOT NULL DEFAULT 'text',
  `categoria` enum('alumno','examen','acceso_supervisor','linguaskill','fiscal') NOT NULL DEFAULT 'alumno',
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `certificacion_comision_historial`
--

CREATE TABLE `certificacion_comision_historial` (
  `id_historial` int(10) UNSIGNED NOT NULL,
  `id_solicitud` int(10) UNSIGNED NOT NULL,
  `precio_cobrado` decimal(12,2) DEFAULT NULL,
  `comision_asesor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `comision_gerente` decimal(12,2) NOT NULL DEFAULT 0.00,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `certificacion_documentos`
--

CREATE TABLE `certificacion_documentos` (
  `id_documento` int(10) UNSIGNED NOT NULL,
  `id_solicitud` int(10) UNSIGNED NOT NULL,
  `tipo` varchar(40) NOT NULL,
  `nombre_original` varchar(200) DEFAULT NULL,
  `ruta` varchar(255) NOT NULL,
  `validado` tinyint(1) NOT NULL DEFAULT 0,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `certificacion_reagendamientos`
--

CREATE TABLE `certificacion_reagendamientos` (
  `id_reagendamiento` int(10) UNSIGNED NOT NULL,
  `id_solicitud` int(10) UNSIGNED NOT NULL,
  `id_acceso_anterior` int(10) UNSIGNED DEFAULT NULL,
  `fecha_anterior` date DEFAULT NULL,
  `hora_anterior` time DEFAULT NULL,
  `fecha_nueva` date DEFAULT NULL,
  `hora_nueva` time DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `credenciales_nuevas` tinyint(1) NOT NULL DEFAULT 1,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `certificacion_solicitudes`
--

CREATE TABLE `certificacion_solicitudes` (
  `id_solicitud` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_producto` int(10) UNSIGNED NOT NULL,
  `id_usuario_registro` int(10) UNSIGNED DEFAULT NULL,
  `id_usuario_asesor` int(10) UNSIGNED DEFAULT NULL,
  `id_pago` int(10) UNSIGNED DEFAULT NULL,
  `estado` enum('pre_registro','documentos_pendientes','pendiente_confirmacion','pendiente_credenciales','lista_para_examen','en_proceso','completada','cancelada','reagendamiento') NOT NULL DEFAULT 'pre_registro',
  `fecha_examen` date DEFAULT NULL,
  `fecha_solicitada` date DEFAULT NULL,
  `hora_solicitada` time DEFAULT NULL,
  `fecha_confirmada` date DEFAULT NULL,
  `hora_confirmada` time DEFAULT NULL,
  `fecha_confirmada_en` datetime DEFAULT NULL,
  `id_supervisor_confirma` int(10) UNSIGNED DEFAULT NULL,
  `sede_direccion` text DEFAULT NULL,
  `reagendamientos` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `motivo_reagendamiento` text DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `precio_cobrado` decimal(12,2) DEFAULT NULL COMMENT 'Precio al registrar (histórico)',
  `comision_asesor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `comision_gerente` decimal(12,2) NOT NULL DEFAULT 0.00,
  `datos_formulario` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Valores capturados según plantilla del producto' CHECK (json_valid(`datos_formulario`)),
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `corte_caja`
--

CREATE TABLE `corte_caja` (
  `id_corte` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `cuenta` char(1) NOT NULL DEFAULT 'B',
  `ingreso_sistema` decimal(12,2) NOT NULL DEFAULT 0.00,
  `retiros` decimal(12,2) NOT NULL DEFAULT 0.00,
  `comprobantes` decimal(12,2) NOT NULL DEFAULT 0.00,
  `efectivo_contado` decimal(12,2) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `curso_personalizado`
--

CREATE TABLE `curso_personalizado` (
  `id_curso` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(160) NOT NULL,
  `duracion_semanas` smallint(5) UNSIGNED DEFAULT NULL,
  `costo_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `num_pagos` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `id_especialidad_ref` int(10) UNSIGNED DEFAULT NULL,
  `id_grupo` int(10) UNSIGNED DEFAULT NULL,
  `temario_json` text DEFAULT NULL,
  `estado` enum('activo','completado','cancelado') NOT NULL DEFAULT 'activo',
  `id_usuario_registro` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `curso_personalizado_pago`
--

CREATE TABLE `curso_personalizado_pago` (
  `id_pago_prog` int(10) UNSIGNED NOT NULL,
  `id_curso` int(10) UNSIGNED NOT NULL,
  `numero` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `monto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fecha_programada` date DEFAULT NULL,
  `pagado` tinyint(1) NOT NULL DEFAULT 0,
  `id_pago_alumno` int(10) UNSIGNED DEFAULT NULL,
  `pagado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disc_cod`
--

CREATE TABLE `disc_cod` (
  `codigo` char(4) NOT NULL,
  `pat_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disc_pat`
--

CREATE TABLE `disc_pat` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(80) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `descp` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disc_res`
--

CREATE TABLE `disc_res` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `1+` int(10) UNSIGNED DEFAULT NULL,
  `2+` int(10) UNSIGNED DEFAULT NULL,
  `3+` int(10) UNSIGNED DEFAULT NULL,
  `4+` int(10) UNSIGNED DEFAULT NULL,
  `5+` int(10) UNSIGNED DEFAULT NULL,
  `6+` int(10) UNSIGNED DEFAULT NULL,
  `7+` int(10) UNSIGNED DEFAULT NULL,
  `8+` int(10) UNSIGNED DEFAULT NULL,
  `9+` int(10) UNSIGNED DEFAULT NULL,
  `10+` int(10) UNSIGNED DEFAULT NULL,
  `11+` int(10) UNSIGNED DEFAULT NULL,
  `12+` int(10) UNSIGNED DEFAULT NULL,
  `13+` int(10) UNSIGNED DEFAULT NULL,
  `14+` int(10) UNSIGNED DEFAULT NULL,
  `15+` int(10) UNSIGNED DEFAULT NULL,
  `16+` int(10) UNSIGNED DEFAULT NULL,
  `17+` int(10) UNSIGNED DEFAULT NULL,
  `18+` int(10) UNSIGNED DEFAULT NULL,
  `19+` int(10) UNSIGNED DEFAULT NULL,
  `20+` int(10) UNSIGNED DEFAULT NULL,
  `21+` int(10) UNSIGNED DEFAULT NULL,
  `22+` int(10) UNSIGNED DEFAULT NULL,
  `23+` int(10) UNSIGNED DEFAULT NULL,
  `24+` int(10) UNSIGNED DEFAULT NULL,
  `25+` int(10) UNSIGNED DEFAULT NULL,
  `26+` int(10) UNSIGNED DEFAULT NULL,
  `27+` int(10) UNSIGNED DEFAULT NULL,
  `28+` int(10) UNSIGNED DEFAULT NULL,
  `1-` int(10) UNSIGNED DEFAULT NULL,
  `2-` int(10) UNSIGNED DEFAULT NULL,
  `3-` int(10) UNSIGNED DEFAULT NULL,
  `4-` int(10) UNSIGNED DEFAULT NULL,
  `5-` int(10) UNSIGNED DEFAULT NULL,
  `6-` int(10) UNSIGNED DEFAULT NULL,
  `7-` int(10) UNSIGNED DEFAULT NULL,
  `8-` int(10) UNSIGNED DEFAULT NULL,
  `9-` int(10) UNSIGNED DEFAULT NULL,
  `10-` int(10) UNSIGNED DEFAULT NULL,
  `11-` int(10) UNSIGNED DEFAULT NULL,
  `12-` int(10) UNSIGNED DEFAULT NULL,
  `13-` int(10) UNSIGNED DEFAULT NULL,
  `14-` int(10) UNSIGNED DEFAULT NULL,
  `15-` int(10) UNSIGNED DEFAULT NULL,
  `16-` int(10) UNSIGNED DEFAULT NULL,
  `17-` int(10) UNSIGNED DEFAULT NULL,
  `18-` int(10) UNSIGNED DEFAULT NULL,
  `19-` int(10) UNSIGNED DEFAULT NULL,
  `20-` int(10) UNSIGNED DEFAULT NULL,
  `21-` int(10) UNSIGNED DEFAULT NULL,
  `22-` int(10) UNSIGNED DEFAULT NULL,
  `23-` int(10) UNSIGNED DEFAULT NULL,
  `24-` int(10) UNSIGNED DEFAULT NULL,
  `25-` int(10) UNSIGNED DEFAULT NULL,
  `26-` int(10) UNSIGNED DEFAULT NULL,
  `27-` int(10) UNSIGNED DEFAULT NULL,
  `28-` int(10) UNSIGNED DEFAULT NULL,
  `D+` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `I+` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `S+` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `C+` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `D-` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `I-` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `S-` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `C-` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `D` smallint(6) NOT NULL DEFAULT 0,
  `I` smallint(6) NOT NULL DEFAULT 0,
  `S` smallint(6) NOT NULL DEFAULT 0,
  `C` smallint(6) NOT NULL DEFAULT 0,
  `codigo` char(4) DEFAULT NULL,
  `pat_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disc_words`
--

CREATE TABLE `disc_words` (
  `id` int(10) UNSIGNED NOT NULL,
  `sec` tinyint(3) UNSIGNED NOT NULL,
  `ord` tinyint(3) UNSIGNED NOT NULL,
  `word` varchar(120) NOT NULL,
  `defn` text NOT NULL,
  `mas` enum('D','I','S','C','N') NOT NULL,
  `menos` enum('D','I','S','C','N') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docente_prospecto`
--

CREATE TABLE `docente_prospecto` (
  `id_prospecto` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `estado` enum('nuevo','clase_muestra_agendada','evaluado','apto_disc','disc_completo','contratado','no_contratado','bolsa') NOT NULL DEFAULT 'nuevo',
  `nombres` varchar(120) NOT NULL,
  `apellido_paterno` varchar(80) NOT NULL,
  `apellido_materno` varchar(80) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `email_google` varchar(160) DEFAULT NULL,
  `id_hay_area` int(10) UNSIGNED DEFAULT NULL,
  `curp` varchar(20) DEFAULT NULL,
  `especialidad` varchar(120) DEFAULT NULL,
  `id_rubrica` int(10) UNSIGNED DEFAULT NULL,
  `disponibilidad` varchar(120) DEFAULT NULL,
  `fecha_clase_muestra` datetime DEFAULT NULL,
  `puntaje_showclass` decimal(5,2) DEFAULT NULL,
  `showclass_aprobado` tinyint(1) NOT NULL DEFAULT 0,
  `disc_resultado_id` int(10) UNSIGNED DEFAULT NULL,
  `decision_final` enum('pendiente','contratar','no_contratar','bolsa') NOT NULL DEFAULT 'pendiente',
  `motivo_no_contratacion` text DEFAULT NULL,
  `categoria_no_contratacion` varchar(60) DEFAULT NULL,
  `recontactar_en` date DEFAULT NULL,
  `segunda_oportunidad` tinyint(1) NOT NULL DEFAULT 0,
  `id_usuario_registro` int(10) UNSIGNED DEFAULT NULL,
  `id_usuario_candidato` int(10) UNSIGNED DEFAULT NULL,
  `id_usuario_profesor` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docente_prospecto_area`
--

CREATE TABLE `docente_prospecto_area` (
  `id_prospecto` int(10) UNSIGNED NOT NULL,
  `id_area` int(10) UNSIGNED NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docente_prospecto_evento`
--

CREATE TABLE `docente_prospecto_evento` (
  `id_evento` int(10) UNSIGNED NOT NULL,
  `id_prospecto` int(10) UNSIGNED NOT NULL,
  `tipo` enum('nota','agenda','disc','decision','seguimiento') NOT NULL,
  `detalle` text NOT NULL,
  `fecha_evento` datetime DEFAULT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docente_rubrica_area`
--

CREATE TABLE `docente_rubrica_area` (
  `id_rubrica` int(10) UNSIGNED NOT NULL,
  `clave` varchar(40) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `tipo` enum('showclass','nivel','360_alumno','360_coordinador','360_auto','360_adjunto') NOT NULL DEFAULT 'showclass',
  `id_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docente_rubrica_criterio`
--

CREATE TABLE `docente_rubrica_criterio` (
  `id_criterio` int(10) UNSIGNED NOT NULL,
  `id_rubrica` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(60) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `maximo` smallint(5) UNSIGNED NOT NULL DEFAULT 10,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docente_showclass_eval`
--

CREATE TABLE `docente_showclass_eval` (
  `id_eval` int(10) UNSIGNED NOT NULL,
  `id_prospecto` int(10) UNSIGNED NOT NULL,
  `puntaje_total` decimal(5,2) NOT NULL DEFAULT 0.00,
  `aprobada` tinyint(1) NOT NULL DEFAULT 0,
  `rubrica_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`rubrica_json`)),
  `comentarios` text DEFAULT NULL,
  `evaluado_por` int(10) UNSIGNED DEFAULT NULL,
  `evaluado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documento_plantilla`
--

CREATE TABLE `documento_plantilla` (
  `id_plantilla` int(10) UNSIGNED NOT NULL,
  `tipo` enum('constancia','diploma') NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `fondo_path` varchar(255) DEFAULT NULL,
  `ancho_mm` decimal(6,2) NOT NULL DEFAULT 215.90,
  `alto_mm` decimal(6,2) NOT NULL DEFAULT 279.40,
  `campos_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`campos_json`)),
  `firma_path` varchar(255) DEFAULT NULL,
  `vigencia_dias` smallint(5) UNSIGNED NOT NULL DEFAULT 90,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `en_audios`
--

CREATE TABLE `en_audios` (
  `id` int(10) UNSIGNED NOT NULL,
  `fase` varchar(80) NOT NULL,
  `id_audio` varchar(40) NOT NULL,
  `nombre_audio` varchar(200) DEFAULT NULL,
  `link_audio` varchar(500) NOT NULL,
  `script_audio` mediumtext DEFAULT NULL,
  `id_fusion` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `en_gramatica`
--

CREATE TABLE `en_gramatica` (
  `id` int(10) UNSIGNED NOT NULL,
  `fase` varchar(80) NOT NULL,
  `pregunta` text NOT NULL,
  `opcion_a` varchar(500) NOT NULL,
  `opcion_b` varchar(500) NOT NULL,
  `opcion_c` varchar(500) NOT NULL,
  `opcion_d` varchar(500) NOT NULL,
  `respuesta` char(1) NOT NULL,
  `id_fusion` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `en_lecturas`
--

CREATE TABLE `en_lecturas` (
  `id` int(10) UNSIGNED NOT NULL,
  `fase` varchar(80) NOT NULL,
  `id_lectura` varchar(40) NOT NULL,
  `nombre_lectura` varchar(200) DEFAULT NULL,
  `lectura` mediumtext NOT NULL,
  `id_fusion` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `en_listening`
--

CREATE TABLE `en_listening` (
  `id` int(10) UNSIGNED NOT NULL,
  `fase` varchar(80) NOT NULL,
  `id_audio` varchar(40) NOT NULL,
  `pregunta` text NOT NULL,
  `opcion_a` varchar(500) NOT NULL,
  `opcion_b` varchar(500) NOT NULL,
  `opcion_c` varchar(500) NOT NULL,
  `opcion_d` varchar(500) NOT NULL,
  `respuesta` char(1) NOT NULL,
  `id_fusion` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `en_reading`
--

CREATE TABLE `en_reading` (
  `id` int(10) UNSIGNED NOT NULL,
  `fase` varchar(80) NOT NULL,
  `id_lectura` varchar(40) NOT NULL,
  `pregunta` text NOT NULL,
  `opcion_a` varchar(500) NOT NULL,
  `opcion_b` varchar(500) NOT NULL,
  `opcion_c` varchar(500) NOT NULL,
  `opcion_d` varchar(500) NOT NULL,
  `respuesta` char(1) NOT NULL,
  `id_fusion` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `en_speaking`
--

CREATE TABLE `en_speaking` (
  `id` int(10) UNSIGNED NOT NULL,
  `fase` varchar(80) NOT NULL,
  `pregunta` text NOT NULL,
  `id_fusion` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `en_vocabulario`
--

CREATE TABLE `en_vocabulario` (
  `id` int(10) UNSIGNED NOT NULL,
  `fase` varchar(80) NOT NULL,
  `pregunta` text NOT NULL,
  `opcion_a` varchar(500) NOT NULL,
  `opcion_b` varchar(500) NOT NULL,
  `opcion_c` varchar(500) NOT NULL,
  `opcion_d` varchar(500) NOT NULL,
  `respuesta` char(1) NOT NULL,
  `id_fusion` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `en_writing`
--

CREATE TABLE `en_writing` (
  `id` int(10) UNSIGNED NOT NULL,
  `fase` varchar(80) NOT NULL,
  `pregunta` text NOT NULL,
  `id_fusion` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `escuelas_externas`
--

CREATE TABLE `escuelas_externas` (
  `id_escuela` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `colonia` varchar(120) DEFAULT NULL,
  `municipio` varchar(120) DEFAULT NULL,
  `contacto_nombre` varchar(160) DEFAULT NULL,
  `contacto_telefono` varchar(30) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `escuela_visita`
--

CREATE TABLE `escuela_visita` (
  `id_visita` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_escuela` int(10) UNSIGNED NOT NULL,
  `id_usuario_asesor` int(10) UNSIGNED NOT NULL,
  `fecha_visita` date NOT NULL,
  `cartas_entregadas` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `notas` varchar(500) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especialidades`
--

CREATE TABLE `especialidades` (
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `clave` varchar(30) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `prompt_planeacion` mediumtext DEFAULT NULL COMMENT 'Plantilla IA planeación; placeholders <<Tema>>, <<Nivel>>, etc.',
  `modalidad` enum('regular','kids','prep_abierta','prep_escolarizada','extensivo') NOT NULL DEFAULT 'regular',
  `duracion_fase_semanas` smallint(5) UNSIGNED NOT NULL DEFAULT 4,
  `inscripcion_por_cuatrimestre` tinyint(1) NOT NULL DEFAULT 0,
  `parciales_por_cuatrimestre` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `edad_min` tinyint(3) UNSIGNED DEFAULT NULL,
  `edad_max` tinyint(3) UNSIGNED DEFAULT NULL,
  `costo_cuatrimestre` decimal(12,2) DEFAULT NULL,
  `costo_anual` decimal(12,2) DEFAULT NULL,
  `costo_inscripcion` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_inscripcion_referencia` decimal(12,2) DEFAULT NULL,
  `costo_inscripcion_apoyo` decimal(12,2) DEFAULT NULL,
  `costo_mensualidad` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_mensualidad_referencia` decimal(12,2) DEFAULT NULL,
  `costo_mensualidad_apoyo` decimal(12,2) DEFAULT NULL,
  `costo_pronto_pago` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_pronto_pago_referencia` decimal(12,2) DEFAULT NULL,
  `costo_pronto_pago_apoyo` decimal(12,2) DEFAULT NULL,
  `costo_semanal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_semanal_referencia` decimal(12,2) DEFAULT NULL,
  `costo_semanal_apoyo` decimal(12,2) DEFAULT NULL,
  `descuento_adelanto_4meses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `descuento_adelanto_12meses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `referido_tipo` enum('semana_colegiatura','monto_fijo','inscripcion_fija') NOT NULL DEFAULT 'semana_colegiatura',
  `referido_valor` decimal(12,2) DEFAULT NULL COMMENT 'Monto fijo o % según tipo; NULL=usa costo semanal',
  `ventas_comision_asesor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ventas_comision_gerente` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ventas_comision_asesor_pct` decimal(5,2) DEFAULT NULL,
  `ventas_comision_gerente_pct` decimal(5,2) DEFAULT NULL,
  `ventas_cuenta_tabulador` tinyint(1) NOT NULL DEFAULT 1,
  `ventas_tipo_comision` enum('fija','pct_inscripcion','personalizado_pct') NOT NULL DEFAULT 'fija',
  `es_plantilla_personalizado` tinyint(1) NOT NULL DEFAULT 0,
  `duracion_meses` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `duracion_semanas` smallint(5) UNSIGNED DEFAULT NULL,
  `es_fija` tinyint(1) NOT NULL DEFAULT 0,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `inscripcion_abierta` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_inicio_venta` date DEFAULT NULL,
  `fecha_fin_venta` date DEFAULT NULL,
  `fecha_apertura_prevista` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `asesoria_requiere_moodle` tinyint(1) NOT NULL DEFAULT 1,
  `asesoria_costo_default` decimal(12,2) DEFAULT NULL,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especialidad_fases`
--

CREATE TABLE `especialidad_fases` (
  `id_fase` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `id_plan_version` int(10) UNSIGNED DEFAULT NULL,
  `nombre_fase` varchar(80) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `temas` text DEFAULT NULL,
  `practicas_sugeridas` text DEFAULT NULL,
  `asesoria` text DEFAULT NULL,
  `objetivo_parcial` text DEFAULT NULL,
  `tipo_contenido` enum('regular','proyecto_nivel','proyecto_final') NOT NULL DEFAULT 'regular',
  `eval_listening` text DEFAULT NULL,
  `eval_reading` text DEFAULT NULL,
  `eval_writing` text DEFAULT NULL,
  `eval_speaking` text DEFAULT NULL,
  `eval_grammar` text DEFAULT NULL,
  `eval_vocabulary` text DEFAULT NULL,
  `vocabulario_resumen` text DEFAULT NULL,
  `gramatica_resumen` text DEFAULT NULL,
  `eval_criterios_json` text DEFAULT NULL,
  `duracion_semanas` smallint(5) UNSIGNED DEFAULT NULL,
  `clave_fase` varchar(40) DEFAULT NULL,
  `nivel_cefr` varchar(20) DEFAULT NULL,
  `num_parcial` tinyint(3) UNSIGNED DEFAULT NULL,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `moodle_course_id` int(10) UNSIGNED DEFAULT NULL,
  `moodle_shortname` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especialidad_tarifa_cartas`
--

CREATE TABLE `especialidad_tarifa_cartas` (
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `costo_inscripcion_ref` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_inscripcion_apoyo` decimal(12,2) NOT NULL DEFAULT 450.00,
  `costo_mensualidad_ref` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_mensualidad_apoyo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especialidad_tarifa_historial`
--

CREATE TABLE `especialidad_tarifa_historial` (
  `id_hist` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `tarifa_anterior` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`tarifa_anterior`)),
  `tarifa_nueva` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`tarifa_nueva`)),
  `alumnos_con_tarifa_congelada` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `exam_calificaciones`
--

CREATE TABLE `exam_calificaciones` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_examen` varchar(20) NOT NULL,
  `fase` varchar(80) NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED DEFAULT NULL,
  `numero_control` varchar(10) NOT NULL,
  `correctas_mc` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `max_mc` smallint(5) UNSIGNED NOT NULL DEFAULT 41,
  `calificacion_mc` decimal(5,2) NOT NULL DEFAULT 0.00,
  `calificacion_writing` decimal(5,2) NOT NULL DEFAULT 0.00,
  `calificacion_speaking` decimal(5,2) NOT NULL DEFAULT 0.00,
  `calificacion_final` decimal(5,2) NOT NULL DEFAULT 0.00,
  `respuestas_mc` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`respuestas_mc`)),
  `rubrica_writing` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rubrica_writing`)),
  `rubrica_speaking` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rubrica_speaking`)),
  `escaneado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `id_profesor` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `exam_fusiones`
--

CREATE TABLE `exam_fusiones` (
  `id_fusion` int(10) UNSIGNED NOT NULL,
  `area` varchar(40) NOT NULL DEFAULT 'ingles',
  `nombre` varchar(160) NOT NULL,
  `id_profesor` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `exam_fusion_fases`
--

CREATE TABLE `exam_fusion_fases` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_fusion` int(10) UNSIGNED NOT NULL,
  `fase` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `exam_generados`
--

CREATE TABLE `exam_generados` (
  `id_examen` varchar(20) NOT NULL,
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `area` varchar(40) NOT NULL DEFAULT 'ingles',
  `tipo` enum('fase','nivel','fusion') NOT NULL,
  `nombre_examen` varchar(200) NOT NULL,
  `fases_usadas` varchar(500) NOT NULL,
  `id_fusion` int(10) UNSIGNED DEFAULT NULL,
  `id_profesor` int(11) DEFAULT NULL,
  `audio_link` varchar(500) DEFAULT NULL,
  `pdf_path` varchar(300) NOT NULL,
  `csv_path` varchar(300) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `exam_generado_preguntas`
--

CREATE TABLE `exam_generado_preguntas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_examen` varchar(20) NOT NULL,
  `numero` tinyint(3) UNSIGNED NOT NULL,
  `seccion` varchar(30) NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'opcion_multiple',
  `pregunta` text NOT NULL,
  `opcion_a` varchar(500) DEFAULT NULL,
  `opcion_b` varchar(500) DEFAULT NULL,
  `opcion_c` varchar(500) DEFAULT NULL,
  `opcion_d` varchar(500) DEFAULT NULL,
  `respuesta` varchar(10) DEFAULT NULL,
  `contexto` mediumtext DEFAULT NULL,
  `id_audio` varchar(40) DEFAULT NULL,
  `id_lectura` varchar(40) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `exam_plantel_config`
--

CREATE TABLE `exam_plantel_config` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `digitos_control` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `peso_mc` decimal(5,2) NOT NULL DEFAULT 70.00,
  `peso_writing` decimal(5,2) NOT NULL DEFAULT 15.00,
  `peso_speaking` decimal(5,2) NOT NULL DEFAULT 15.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expediente_entrega`
--

CREATE TABLE `expediente_entrega` (
  `id_entrega` int(10) UNSIGNED NOT NULL,
  `id_requisito` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `tipo_entidad` enum('usuario','alumno','prospecto') NOT NULL,
  `id_entidad` int(10) UNSIGNED NOT NULL,
  `id_hay_area` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=general; >0 certificación por área',
  `ruta` varchar(255) DEFAULT NULL,
  `nombre_original` varchar(200) DEFAULT NULL,
  `estado` enum('pendiente','aprobado','rechazado','exento') NOT NULL DEFAULT 'pendiente',
  `puntaje` decimal(6,2) DEFAULT NULL,
  `origen_puntaje` enum('documento','moodle','manual') DEFAULT NULL,
  `comentario_rechazo` text DEFAULT NULL,
  `moodle_inscrito` tinyint(1) NOT NULL DEFAULT 0,
  `id_usuario_subio` int(10) UNSIGNED DEFAULT NULL,
  `id_usuario_evaluo` int(10) UNSIGNED DEFAULT NULL,
  `evaluado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expediente_requisito`
--

CREATE TABLE `expediente_requisito` (
  `id_requisito` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `clave` varchar(40) NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `categoria` enum('general','candidato_profesor','profesor','alumno_sep','personal') NOT NULL DEFAULT 'general',
  `roles_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`roles_json`)),
  `obligatorio` tinyint(1) NOT NULL DEFAULT 1,
  `tipo_verificacion` enum('documento','certificacion','examen_moodle') NOT NULL DEFAULT 'documento',
  `moodle_course_id` int(10) UNSIGNED DEFAULT NULL,
  `umbral_aprobacion` decimal(5,2) DEFAULT 70.00,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 100,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fase_temario_semana`
--

CREATE TABLE `fase_temario_semana` (
  `id_semana` int(10) UNSIGNED NOT NULL,
  `id_fase` int(10) UNSIGNED NOT NULL,
  `semana` tinyint(3) UNSIGNED NOT NULL COMMENT '1-4 dentro del parcial',
  `titulo_leccion` varchar(160) DEFAULT NULL,
  `objetivo` text DEFAULT NULL,
  `vocabulario` text DEFAULT NULL,
  `gramatica` text DEFAULT NULL,
  `listening` text DEFAULT NULL,
  `reading` text DEFAULT NULL,
  `writing` text DEFAULT NULL,
  `speaking` text DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `es_examen` tinyint(1) NOT NULL DEFAULT 0,
  `proyecto_tipo` varchar(80) DEFAULT NULL COMMENT 'Project A, investigacion, etc.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `graduacion_alerta`
--

CREATE TABLE `graduacion_alerta` (
  `id_alerta` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `id_fase_actual` int(10) UNSIGNED DEFAULT NULL,
  `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `motivo_decision` varchar(500) DEFAULT NULL,
  `decidido_por` int(10) UNSIGNED DEFAULT NULL,
  `fecha_alerta` date NOT NULL,
  `fecha_fin_estimada` date DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupos`
--

CREATE TABLE `grupos` (
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `clave` varchar(50) NOT NULL,
  `clave_anterior` varchar(50) DEFAULT NULL,
  `clave_actualizada_en` datetime DEFAULT NULL,
  `clave_actualizada_por` int(10) UNSIGNED DEFAULT NULL,
  `codigo_area` varchar(4) DEFAULT NULL,
  `codigo_horario` char(1) DEFAULT NULL COMMENT 'S,D,M,V',
  `es_extensivo` tinyint(1) NOT NULL DEFAULT 0,
  `es_personalizado` tinyint(1) NOT NULL DEFAULT 0,
  `personalizado_temas` text DEFAULT NULL COMMENT 'JSON temas/fases personalizado',
  `personalizado_descripcion` varchar(200) DEFAULT NULL,
  `personalizado_costo_acordado` decimal(10,2) DEFAULT NULL,
  `numero_secuencial` smallint(5) UNSIGNED DEFAULT NULL,
  `fusiones_total` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `fusion_desfase` enum('ninguno','adelanto','atraso') NOT NULL DEFAULT 'ninguno',
  `id_grupo_pareja_infantil` int(10) UNSIGNED DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `estado_apertura` enum('programado','pendiente_autorizacion','autorizado','iniciado') NOT NULL DEFAULT 'programado',
  `min_alumnos` smallint(5) UNSIGNED DEFAULT NULL,
  `dias_preaviso` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `id_autoriza_apertura` int(10) UNSIGNED DEFAULT NULL,
  `autorizado_en` datetime DEFAULT NULL,
  `pospuestos` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `id_profesor` int(10) UNSIGNED DEFAULT NULL,
  `aula` varchar(60) DEFAULT NULL,
  `id_aula` int(10) UNSIGNED DEFAULT NULL,
  `id_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `id_tutor` int(10) UNSIGNED DEFAULT NULL,
  `id_fase_actual` int(10) UNSIGNED DEFAULT NULL,
  `moodle_nivel` varchar(20) DEFAULT NULL,
  `horario_texto` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_apertura_log`
--

CREATE TABLE `grupo_apertura_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `accion` enum('pendiente','autorizado','pospuesto') NOT NULL,
  `fecha_anterior` date DEFAULT NULL,
  `fecha_nueva` date DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `pagos_remapeados` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_avance_log`
--

CREATE TABLE `grupo_avance_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `id_fase_anterior` int(10) UNSIGNED DEFAULT NULL,
  `id_fase_nueva` int(10) UNSIGNED NOT NULL,
  `semanas_lectivas` int(10) UNSIGNED NOT NULL,
  `avanzado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `automatico` tinyint(1) NOT NULL DEFAULT 1,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_clave_historial`
--

CREATE TABLE `grupo_clave_historial` (
  `id_historial` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `clave_anterior` varchar(50) NOT NULL,
  `clave_nueva` varchar(50) NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_clave_secuencia`
--

CREATE TABLE `grupo_clave_secuencia` (
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `prefijo` varchar(24) NOT NULL,
  `ultimo_numero` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_docente`
--

CREATE TABLE `grupo_docente` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `id_profesor` int(10) UNSIGNED NOT NULL,
  `materia_clave` varchar(80) NOT NULL DEFAULT '',
  `materia_nombre` varchar(160) DEFAULT NULL,
  `es_titular` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_fusion_alumno`
--

CREATE TABLE `grupo_fusion_alumno` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_fusion_plan` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_grupo_procedencia` int(10) UNSIGNED NOT NULL,
  `id_grupo_graduacion` int(10) UNSIGNED DEFAULT NULL COMMENT 'Grupo origen para alertas/graduación',
  `debe_retomar` tinyint(1) NOT NULL DEFAULT 0,
  `separado` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_fusion_log`
--

CREATE TABLE `grupo_fusion_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_grupo_resultante` int(10) UNSIGNED NOT NULL,
  `id_grupo_origen` int(10) UNSIGNED NOT NULL,
  `clave_grupo_origen` varchar(50) NOT NULL,
  `desfase` enum('ninguno','adelanto','atraso') NOT NULL DEFAULT 'ninguno',
  `misma_fase` tinyint(1) NOT NULL DEFAULT 1,
  `notas` text DEFAULT NULL,
  `fusionado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `id_usuario` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_fusion_pendiente_fase`
--

CREATE TABLE `grupo_fusion_pendiente_fase` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_fusion_plan` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL COMMENT 'Grupo que debe impartir/retomar la fase',
  `id_fase` int(10) UNSIGNED NOT NULL,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `estado` enum('pendiente','impartida','completada') NOT NULL DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_fusion_plan`
--

CREATE TABLE `grupo_fusion_plan` (
  `id_fusion_plan` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `id_grupo_a` int(10) UNSIGNED NOT NULL,
  `id_grupo_b` int(10) UNSIGNED NOT NULL,
  `id_grupo_resultante` int(10) UNSIGNED NOT NULL COMMENT 'Grupo que conserva clave y recibe alumnos',
  `id_grupo_origen` int(10) UNSIGNED NOT NULL COMMENT 'Grupo absorbido',
  `id_grupo_atrasado` int(10) UNSIGNED NOT NULL,
  `id_grupo_adelantado` int(10) UNSIGNED NOT NULL,
  `id_fase_destino` int(10) UNSIGNED NOT NULL,
  `fases_pendientes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fases_pendientes_json`)),
  `simulacion_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`simulacion_json`)),
  `estado` enum('borrador','planificada','activa','separada','completada','cancelada') NOT NULL DEFAULT 'borrador',
  `tipo` enum('simple','kids_dual') NOT NULL DEFAULT 'simple',
  `id_plan_vinculado` int(10) UNSIGNED DEFAULT NULL COMMENT 'Plan pareja (dual kids)',
  `fecha_prevista` date DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `id_fusion_log` int(10) UNSIGNED DEFAULT NULL,
  `id_usuario_crea` int(10) UNSIGNED DEFAULT NULL,
  `confirmado_en` datetime DEFAULT NULL,
  `activado_en` datetime DEFAULT NULL,
  `separado_en` datetime DEFAULT NULL,
  `cancelado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_horarios`
--

CREATE TABLE `grupo_horarios` (
  `id_horario` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `dia_semana` tinyint(3) UNSIGNED NOT NULL COMMENT '0=Dom..6=Sab, 1=Lun en convencion alternativa usamos PHP w',
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_plan_periodo`
--

CREATE TABLE `grupo_plan_periodo` (
  `id_plan` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `anio` smallint(5) UNSIGNED NOT NULL,
  `mes` tinyint(3) UNSIGNED NOT NULL COMMENT '1-12',
  `id_fase_registro` int(10) UNSIGNED NOT NULL COMMENT 'Parcial que se registra al alumno',
  `fases_temario_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Parciales cuyo temario se imparte' CHECK (json_valid(`fases_temario_json`)),
  `nota_coordinador` text DEFAULT NULL,
  `temas_retomar` text DEFAULT NULL,
  `pendiente_retomar` tinyint(1) NOT NULL DEFAULT 0,
  `cerrado` tinyint(1) NOT NULL DEFAULT 0,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_preinicio_contacto`
--

CREATE TABLE `grupo_preinicio_contacto` (
  `id_contacto` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `contactado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_contacto` datetime DEFAULT NULL,
  `medio` enum('telefono','whatsapp','presencial','correo','otro') DEFAULT NULL,
  `notas` varchar(500) DEFAULT NULL,
  `id_usuario_registro` int(10) UNSIGNED NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_rubrica_parcial`
--

CREATE TABLE `grupo_rubrica_parcial` (
  `id_rubrica` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `id_fase` int(10) UNSIGNED NOT NULL,
  `criterios_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`criterios_json`)),
  `actualizado_por` int(10) UNSIGNED DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_suplencia`
--

CREATE TABLE `grupo_suplencia` (
  `id_suplencia` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_profesor_titular` int(10) UNSIGNED NOT NULL,
  `id_profesor_suplente` int(10) UNSIGNED DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `motivo` enum('enfermedad','evento_institucional','apoyo_evento','otro') NOT NULL DEFAULT 'enfermedad',
  `regla_pago` enum('solo_suplente','ambos','solo_titular_apoyo') NOT NULL DEFAULT 'solo_suplente',
  `pago_titular_concepto` varchar(160) DEFAULT NULL,
  `pago_titular_monto` decimal(12,2) DEFAULT NULL,
  `pago_titular_horas` decimal(8,2) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_app_meta`
--

CREATE TABLE `hay_app_meta` (
  `clave` varchar(64) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_area`
--

CREATE TABLE `hay_area` (
  `id_area` int(10) UNSIGNED NOT NULL,
  `clave` varchar(40) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `moodle_course_examen_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Curso Moodle examen conocimientos candidatos/profesor',
  `alias_especialidad` varchar(255) DEFAULT NULL COMMENT 'Aliases separados por coma para mapear especialidad',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_area_rol`
--

CREATE TABLE `hay_area_rol` (
  `id_area` int(10) UNSIGNED NOT NULL,
  `rol_clave` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_area_usuario`
--

CREATE TABLE `hay_area_usuario` (
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_area` int(10) UNSIGNED NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_aspecto`
--

CREATE TABLE `hay_aspecto` (
  `id_aspecto` int(10) UNSIGNED NOT NULL,
  `id_rubro` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(60) NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `origen_default` enum('manual','moodle','sistema') NOT NULL DEFAULT 'manual',
  `regla_auto` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`regla_auto`)),
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_capacitacion`
--

CREATE TABLE `hay_capacitacion` (
  `id_capacitacion` int(10) UNSIGNED NOT NULL,
  `id_area` int(10) UNSIGNED NOT NULL,
  `id_nivel_min` tinyint(3) UNSIGNED DEFAULT NULL,
  `nombre` varchar(160) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `tipo` enum('obligatoria_nivel','mensual_extra') NOT NULL DEFAULT 'obligatoria_nivel',
  `obligatoria` tinyint(1) NOT NULL DEFAULT 1,
  `moodle_course_id` int(10) UNSIGNED DEFAULT NULL,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_capacitacion_cumplimiento`
--

CREATE TABLE `hay_capacitacion_cumplimiento` (
  `id_cumplimiento` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_capacitacion` int(10) UNSIGNED NOT NULL,
  `periodo` char(7) NOT NULL,
  `completada` tinyint(1) NOT NULL DEFAULT 0,
  `marcado_por` int(10) UNSIGNED DEFAULT NULL,
  `marcado_en` datetime DEFAULT NULL,
  `notas` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_config_version`
--

CREATE TABLE `hay_config_version` (
  `id_version` int(10) UNSIGNED NOT NULL,
  `id_area` int(10) UNSIGNED NOT NULL,
  `numero` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `publicada` tinyint(1) NOT NULL DEFAULT 0,
  `vigente_desde` date DEFAULT NULL,
  `snapshot_json` longtext DEFAULT NULL,
  `publicada_en` datetime DEFAULT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_eval_periodo`
--

CREATE TABLE `hay_eval_periodo` (
  `id_eval` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_area` int(10) UNSIGNED NOT NULL,
  `anio` smallint(5) UNSIGNED NOT NULL,
  `mes` tinyint(3) UNSIGNED NOT NULL,
  `estado` enum('borrador','cerrado') NOT NULL DEFAULT 'borrador',
  `puntos_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `id_nivel_resultado` int(10) UNSIGNED DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `evaluado_por` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_eval_respuesta`
--

CREATE TABLE `hay_eval_respuesta` (
  `id_respuesta` int(10) UNSIGNED NOT NULL,
  `id_eval` int(10) UNSIGNED NOT NULL,
  `id_aspecto` int(10) UNSIGNED NOT NULL,
  `id_opcion` int(10) UNSIGNED DEFAULT NULL,
  `puntos_aplicados` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `es_automatico` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_legacy_equivalence`
--

CREATE TABLE `hay_legacy_equivalence` (
  `entidad` varchar(32) NOT NULL,
  `id_legacy` bigint(20) UNSIGNED NOT NULL,
  `id_hay` int(10) UNSIGNED DEFAULT NULL,
  `modo` enum('usar','omitir','crear') NOT NULL DEFAULT 'usar',
  `notas` varchar(255) DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_legacy_import_log`
--

CREATE TABLE `hay_legacy_import_log` (
  `id_log` int(10) UNSIGNED NOT NULL,
  `fase` varchar(40) NOT NULL,
  `nivel` enum('info','warn','error') NOT NULL DEFAULT 'info',
  `mensaje` text NOT NULL,
  `id_legacy` bigint(20) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_legacy_map`
--

CREATE TABLE `hay_legacy_map` (
  `entidad` varchar(32) NOT NULL,
  `id_legacy` bigint(20) UNSIGNED NOT NULL,
  `id_hay` int(10) UNSIGNED NOT NULL,
  `notas` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_nivel_cargo`
--

CREATE TABLE `hay_nivel_cargo` (
  `id_nivel` int(10) UNSIGNED NOT NULL,
  `id_area` int(10) UNSIGNED NOT NULL,
  `numero` tinyint(3) UNSIGNED NOT NULL,
  `nombre_display` varchar(80) NOT NULL,
  `puntos_min` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `puntos_max` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sueldo_base` decimal(12,2) DEFAULT NULL,
  `notas_comision` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_opcion`
--

CREATE TABLE `hay_opcion` (
  `id_opcion` int(10) UNSIGNED NOT NULL,
  `id_aspecto` int(10) UNSIGNED NOT NULL,
  `etiqueta` varchar(200) NOT NULL,
  `puntos` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `origen` enum('manual','moodle','sistema') NOT NULL DEFAULT 'manual',
  `moodle_course_id` int(10) UNSIGNED DEFAULT NULL,
  `moodle_activity_id` int(10) UNSIGNED DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hay_rubro`
--

CREATE TABLE `hay_rubro` (
  `id_rubro` int(10) UNSIGNED NOT NULL,
  `id_area` int(10) UNSIGNED NOT NULL,
  `clave` varchar(40) NOT NULL,
  `titulo` varchar(120) NOT NULL,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `huella_codigos`
--

CREATE TABLE `huella_codigos` (
  `id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('alumno','usuario') NOT NULL,
  `id_referencia` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `codigo_huella` varchar(40) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `huella_eventos`
--

CREATE TABLE `huella_eventos` (
  `id_evento` bigint(20) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `codigo_huella` varchar(40) NOT NULL,
  `canal` enum('lector_fijo','movil','prueba') NOT NULL DEFAULT 'lector_fijo',
  `id_usuario_operador` int(10) UNSIGNED DEFAULT NULL,
  `tipo` enum('alumno','personal','desconocido') NOT NULL DEFAULT 'desconocido',
  `id_referencia` int(10) UNSIGNED DEFAULT NULL,
  `procesado` tinyint(1) NOT NULL DEFAULT 0,
  `mensaje` varchar(255) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripcion_autorizacion`
--

CREATE TABLE `inscripcion_autorizacion` (
  `id_auth` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED DEFAULT NULL,
  `id_preregistro` int(10) UNSIGNED DEFAULT NULL,
  `id_grupo` int(10) UNSIGNED DEFAULT NULL,
  `id_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `tipo` enum('edad','ubicacion','ambos') NOT NULL DEFAULT 'edad',
  `estado` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `motivo` text DEFAULT NULL,
  `id_solicita` int(10) UNSIGNED NOT NULL,
  `id_autoriza` int(10) UNSIGNED DEFAULT NULL,
  `autorizado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripcion_cartas_campana`
--

CREATE TABLE `inscripcion_cartas_campana` (
  `id_campana` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `minimo_inscripcion` decimal(12,2) NOT NULL DEFAULT 450.00,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `id_gerente_definio` int(10) UNSIGNED DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripcion_cartas_reparto`
--

CREATE TABLE `inscripcion_cartas_reparto` (
  `id_reparto` int(10) UNSIGNED NOT NULL,
  `id_pago` int(10) UNSIGNED NOT NULL,
  `id_asesor` int(10) UNSIGNED NOT NULL,
  `rol` enum('repartidor','cierre') NOT NULL,
  `monto_comision` decimal(12,2) NOT NULL DEFAULT 0.00,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripcion_referidos`
--

CREATE TABLE `inscripcion_referidos` (
  `id_referido` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_alumno_inscrito` int(10) UNSIGNED NOT NULL,
  `id_alumno_referidor` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED DEFAULT NULL,
  `id_pago_inscripcion` int(10) UNSIGNED DEFAULT NULL,
  `id_pago_beneficio` int(10) UNSIGNED DEFAULT NULL,
  `monto_beneficio` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tipo_beneficio` varchar(40) NOT NULL DEFAULT 'semana_colegiatura',
  `id_usuario_registro` int(10) UNSIGNED DEFAULT NULL,
  `firma_referidor_at` datetime DEFAULT NULL,
  `ticket_copia_impresa` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `marketing_banner`
--

CREATE TABLE `marketing_banner` (
  `id_banner` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(160) NOT NULL,
  `imagen_url` varchar(500) DEFAULT NULL,
  `enlace_url` varchar(500) DEFAULT NULL,
  `texto_alt` varchar(200) DEFAULT NULL,
  `audiencia` enum('alumno','todos','staff') NOT NULL DEFAULT 'alumno',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `vigente_desde` date DEFAULT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nomina_ajuste_log`
--

CREATE TABLE `nomina_ajuste_log` (
  `id_log` int(10) UNSIGNED NOT NULL,
  `id_liquidacion` int(10) UNSIGNED NOT NULL,
  `id_linea` int(10) UNSIGNED DEFAULT NULL,
  `id_usuario_afectado` int(10) UNSIGNED NOT NULL,
  `accion` enum('agregar','editar','eliminar') NOT NULL,
  `concepto_antes` varchar(255) DEFAULT NULL,
  `importe_antes` decimal(12,2) DEFAULT NULL,
  `concepto_despues` varchar(255) DEFAULT NULL,
  `importe_despues` decimal(12,2) DEFAULT NULL,
  `observacion` text NOT NULL,
  `id_usuario_editor` int(10) UNSIGNED NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nomina_linea`
--

CREATE TABLE `nomina_linea` (
  `id_linea` int(10) UNSIGNED NOT NULL,
  `id_liquidacion` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `rol` varchar(40) DEFAULT NULL,
  `area_nombre` varchar(80) DEFAULT NULL,
  `nivel_nombre` varchar(80) DEFAULT NULL,
  `tipo_pago` varchar(40) DEFAULT NULL,
  `concepto` varchar(255) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 1.00,
  `tarifa` decimal(12,2) NOT NULL DEFAULT 0.00,
  `importe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `es_manual` tinyint(1) NOT NULL DEFAULT 0,
  `observacion_interna` text DEFAULT NULL,
  `origen` varchar(30) NOT NULL DEFAULT 'calculado',
  `detalle_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detalle_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nomina_liquidacion`
--

CREATE TABLE `nomina_liquidacion` (
  `id_liquidacion` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `tipo_periodo` enum('semana','quincena','mes') NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `etiqueta` varchar(120) DEFAULT NULL,
  `estado` enum('borrador','cerrada') NOT NULL DEFAULT 'borrador',
  `total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `creado_por` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificacion_panel_oculta`
--

CREATE TABLE `notificacion_panel_oculta` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `clave` varchar(80) NOT NULL,
  `estado` enum('leida','archivada') NOT NULL DEFAULT 'leida',
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificacion_usuario`
--

CREATE TABLE `notificacion_usuario` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `tipo` varchar(60) NOT NULL,
  `titulo` varchar(160) NOT NULL,
  `mensaje` text NOT NULL,
  `enlace_seccion` varchar(80) DEFAULT NULL,
  `enlace_params` varchar(255) DEFAULT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `archivada` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `patrones`
--

CREATE TABLE `patrones` (
  `codigo` char(4) NOT NULL,
  `patron_slug` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal_pago_config`
--

CREATE TABLE `personal_pago_config` (
  `id_config` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `alcance` enum('principal','docencia') NOT NULL DEFAULT 'principal',
  `tipo_pago` enum('fijo_quincena','fijo_mes','por_hora','tabulador_asesor','nivel_hay') NOT NULL DEFAULT 'fijo_quincena',
  `monto_fijo` decimal(12,2) DEFAULT NULL,
  `tarifa_hora` decimal(12,2) DEFAULT NULL,
  `id_hay_nivel` int(10) UNSIGNED DEFAULT NULL,
  `id_hay_area` int(10) UNSIGNED DEFAULT NULL,
  `notas` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planeaciones`
--

CREATE TABLE `planeaciones` (
  `id_planeacion` bigint(20) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `id_profesor` int(11) DEFAULT NULL,
  `id_fase` int(10) UNSIGNED DEFAULT NULL,
  `fecha` date NOT NULL,
  `anio` smallint(5) UNSIGNED NOT NULL,
  `semana` tinyint(3) UNSIGNED NOT NULL,
  `titulo` varchar(160) NOT NULL,
  `contenido` mediumtext NOT NULL,
  `estado` enum('borrador','enviada','revisada','observada') NOT NULL DEFAULT 'enviada',
  `nota_revision` text DEFAULT NULL,
  `id_revisor` int(10) UNSIGNED DEFAULT NULL,
  `revisado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planeacion_observacion`
--

CREATE TABLE `planeacion_observacion` (
  `id_obs` bigint(20) UNSIGNED NOT NULL,
  `id_planeacion` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `autor_rol` varchar(20) NOT NULL,
  `comentario` text NOT NULL,
  `es_reenvio` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planteles`
--

CREATE TABLE `planteles` (
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `slug` varchar(40) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `razon_social` varchar(160) NOT NULL DEFAULT 'GRUPO EDUCATIVO CNCM',
  `direccion` varchar(255) DEFAULT NULL,
  `rfc` varchar(20) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `email_contacto` varchar(120) DEFAULT 'corporativo@cncm.com.mx',
  `logo_url` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plantel_aulas`
--

CREATE TABLE `plantel_aulas` (
  `id_aula` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(40) NOT NULL,
  `nombre` varchar(120) DEFAULT NULL,
  `piso` varchar(30) DEFAULT NULL,
  `capacidad` int(10) UNSIGNED NOT NULL DEFAULT 20,
  `tiene_pizarron` tinyint(1) NOT NULL DEFAULT 1,
  `tiene_proyector` tinyint(1) NOT NULL DEFAULT 0,
  `tiene_tv` tinyint(1) NOT NULL DEFAULT 0,
  `tiene_pc` tinyint(1) NOT NULL DEFAULT 0,
  `tipo_aula` varchar(30) NOT NULL DEFAULT 'aula',
  `capacidad_flexible` tinyint(1) NOT NULL DEFAULT 0,
  `todas_especialidades` tinyint(1) NOT NULL DEFAULT 1,
  `es_laboratorio` tinyint(1) NOT NULL DEFAULT 0,
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plan_estudio_version`
--

CREATE TABLE `plan_estudio_version` (
  `id_plan_version` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `version_label` varchar(40) NOT NULL,
  `vigente_desde` date DEFAULT NULL,
  `activo_para_nuevos` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `id_usuario` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `preregistros`
--

CREATE TABLE `preregistros` (
  `id_preregistro` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_usuario_registro` int(10) UNSIGNED NOT NULL,
  `id_usuario_asesor` int(10) UNSIGNED DEFAULT NULL COMMENT 'Asesor que recibe comisión',
  `id_entrevista_origen` int(10) UNSIGNED DEFAULT NULL,
  `comision_cncm` tinyint(1) NOT NULL DEFAULT 0,
  `id_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `id_alumno_vinculado` int(10) UNSIGNED DEFAULT NULL,
  `estado` enum('activo','pendiente','perdido','inscrito') NOT NULL DEFAULT 'activo',
  `categoria_perdido` varchar(40) DEFAULT NULL,
  `motivo_perdido` text DEFAULT NULL,
  `categoria_pendiente` varchar(40) DEFAULT NULL,
  `motivo_pendiente` text DEFAULT NULL,
  `fecha_recordatorio` date DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `nombres` varchar(120) NOT NULL,
  `apellido_paterno` varchar(80) NOT NULL,
  `apellido_materno` varchar(80) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `edad` tinyint(3) UNSIGNED DEFAULT NULL,
  `medio_entero` enum('redes_sociales','publicidad','cartas','pasando','recomendado','crm','cita_crm','otro') NOT NULL DEFAULT 'otro',
  `medio_entero_otro` varchar(120) DEFAULT NULL,
  `id_escuela_origen` int(10) UNSIGNED DEFAULT NULL,
  `domicilio` varchar(200) DEFAULT NULL,
  `colonia` varchar(120) DEFAULT NULL,
  `municipio` varchar(120) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `telefono2` varchar(30) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `codigo_postal` varchar(10) DEFAULT NULL,
  `ocupacion` varchar(120) DEFAULT NULL,
  `grado_estudios` enum('primaria','secundaria','preparatoria','universidad','otros') DEFAULT NULL,
  `padre_tutor` varchar(160) DEFAULT NULL,
  `objetivo_inscripcion` text DEFAULT NULL,
  `enfermedad_cronica` tinyint(1) NOT NULL DEFAULT 0,
  `enfermedad_detalle` varchar(200) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `tiene_apartado` tinyint(1) NOT NULL DEFAULT 0,
  `monto_apartado` decimal(12,2) DEFAULT NULL,
  `folio_apartado` varchar(30) DEFAULT NULL,
  `fecha_apartado` datetime DEFAULT NULL,
  `forma_pago_apartado` varchar(40) DEFAULT NULL,
  `requiere_factura` tinyint(1) NOT NULL DEFAULT 0,
  `factura_rfc` varchar(20) DEFAULT NULL,
  `factura_curp` varchar(22) DEFAULT NULL,
  `factura_telefono` varchar(30) DEFAULT NULL,
  `factura_razon_social` varchar(200) DEFAULT NULL,
  `factura_correo` varchar(160) DEFAULT NULL,
  `factura_domicilio_fiscal` varchar(255) DEFAULT NULL,
  `factura_constancia_path` varchar(255) DEFAULT NULL,
  `factura_datos_pendientes` tinyint(1) NOT NULL DEFAULT 0,
  `edad_requiere_autorizacion` tinyint(1) NOT NULL DEFAULT 0,
  `espera_apertura_curso` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_compromiso_contacto` date DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `fecha_estado` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `preregistro_alertas`
--

CREATE TABLE `preregistro_alertas` (
  `id_alerta` int(10) UNSIGNED NOT NULL,
  `id_preregistro` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `tipo` enum('curso_no_abierto','curso_abierto_seguimiento','factura_incompleta','general') NOT NULL,
  `mensaje` varchar(500) NOT NULL,
  `fecha_programada` date DEFAULT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `resuelta` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id_producto` int(10) UNSIGNED NOT NULL,
  `clave` varchar(40) NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(12,2) NOT NULL DEFAULT 0.00,
  `clave_sat` varchar(20) NOT NULL DEFAULT '01010101',
  `unidad_sat` varchar(10) NOT NULL DEFAULT 'H87',
  `gratis_profesor` tinyint(1) NOT NULL DEFAULT 0,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `descontinuado` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `es_constancia` tinyint(1) NOT NULL DEFAULT 0,
  `stock_minimo` int(10) UNSIGNED NOT NULL DEFAULT 5,
  `controla_inventario` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=sin control de existencia (servicios)',
  `es_certificacion` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=aparece en módulo certificaciones',
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_certificacion`
--

CREATE TABLE `producto_certificacion` (
  `id_producto` int(10) UNSIGNED NOT NULL,
  `organismo` varchar(120) DEFAULT NULL,
  `familia` varchar(40) NOT NULL DEFAULT 'certiport' COMMENT 'Plantilla: cambridge_online, toefl, etc.',
  `protocolo` text DEFAULT NULL COMMENT 'Pasos para presentar el examen',
  `reglamento_texto` text DEFAULT NULL,
  `reglamento_pdf` varchar(255) DEFAULT NULL,
  `requiere_reglamento_firmado` tinyint(1) NOT NULL DEFAULT 0,
  `software_nombre` varchar(160) DEFAULT NULL,
  `software_url` varchar(500) DEFAULT NULL,
  `software_instrucciones` text DEFAULT NULL,
  `documentos_requeridos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Lista de tipos: INE, CURP, reglamento_firmado, etc.' CHECK (json_valid(`documentos_requeridos`)),
  `notas_asesor` text DEFAULT NULL,
  `comision_asesor_default` decimal(12,2) NOT NULL DEFAULT 0.00,
  `comision_gerente_default` decimal(12,2) NOT NULL DEFAULT 0.00,
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_certificacion_campo`
--

CREATE TABLE `producto_certificacion_campo` (
  `id_producto` int(10) UNSIGNED NOT NULL,
  `clave_campo` varchar(60) NOT NULL,
  `obligatorio` tinyint(1) NOT NULL DEFAULT 0,
  `llenado_por` enum('asesor','alumno','supervisor') NOT NULL DEFAULT 'asesor',
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_inventario`
--

CREATE TABLE `producto_inventario` (
  `id_inventario` int(10) UNSIGNED NOT NULL,
  `id_producto` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `existencia` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` int(10) UNSIGNED DEFAULT NULL,
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_movimientos`
--

CREATE TABLE `producto_movimientos` (
  `id_movimiento` int(10) UNSIGNED NOT NULL,
  `id_producto` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `tipo` enum('entrada','merma','ajuste','salida') NOT NULL,
  `cantidad` int(10) UNSIGNED NOT NULL,
  `notas` text DEFAULT NULL,
  `estado` enum('pendiente','aplicado','cancelado') NOT NULL DEFAULT 'pendiente',
  `id_usuario_registro` int(10) UNSIGNED DEFAULT NULL,
  `id_usuario_confirma` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `confirmado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesor_360_ciclo`
--

CREATE TABLE `profesor_360_ciclo` (
  `id_ciclo` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `anio` smallint(5) UNSIGNED NOT NULL,
  `mes` tinyint(3) UNSIGNED NOT NULL,
  `titulo` varchar(120) DEFAULT NULL,
  `inicio_alumno` datetime DEFAULT NULL,
  `fin_alumno` datetime DEFAULT NULL,
  `inicio_adjunto` datetime DEFAULT NULL,
  `fin_adjunto` datetime DEFAULT NULL,
  `inicio_auto` datetime DEFAULT NULL,
  `fin_auto` datetime DEFAULT NULL,
  `inicio_coord` datetime DEFAULT NULL,
  `fin_coord` datetime DEFAULT NULL,
  `estado` enum('borrador','abierto','cerrado','publicado') NOT NULL DEFAULT 'borrador',
  `publicado_en` datetime DEFAULT NULL,
  `id_usuario_creador` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesor_360_eval`
--

CREATE TABLE `profesor_360_eval` (
  `id_eval` int(10) UNSIGNED NOT NULL,
  `id_ciclo` int(10) UNSIGNED NOT NULL,
  `id_profesor` int(10) UNSIGNED NOT NULL,
  `id_evaluador` int(10) UNSIGNED NOT NULL,
  `tipo` enum('alumno','coordinador','auto','adjunto') NOT NULL,
  `id_grupo` int(10) UNSIGNED DEFAULT NULL,
  `id_alumno` int(10) UNSIGNED DEFAULT NULL,
  `estado` enum('borrador','cerrado') NOT NULL DEFAULT 'borrador',
  `puntaje_total` decimal(8,2) NOT NULL DEFAULT 0.00,
  `puntaje_max` decimal(8,2) NOT NULL DEFAULT 0.00,
  `rubrica_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`rubrica_json`)),
  `observaciones` text DEFAULT NULL,
  `anonimo` tinyint(1) NOT NULL DEFAULT 1,
  `cerrado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesor_360_participante`
--

CREATE TABLE `profesor_360_participante` (
  `id_participante` int(10) UNSIGNED NOT NULL,
  `id_ciclo` int(10) UNSIGNED NOT NULL,
  `id_profesor` int(10) UNSIGNED NOT NULL,
  `id_hay_area` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `id_adjunto` int(10) UNSIGNED DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesor_asesoria_materia`
--

CREATE TABLE `profesor_asesoria_materia` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `materia_clave` varchar(48) NOT NULL DEFAULT '',
  `materia_nombre` varchar(120) DEFAULT NULL,
  `nivel` varchar(24) NOT NULL DEFAULT 'general',
  `puede_kids_dual` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesor_eval_periodo`
--

CREATE TABLE `profesor_eval_periodo` (
  `id_eval` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `anio` smallint(5) UNSIGNED NOT NULL,
  `mes` tinyint(3) UNSIGNED NOT NULL,
  `estado` enum('borrador','cerrado') NOT NULL DEFAULT 'borrador',
  `metricas_auto` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`metricas_auto`)),
  `criterios_manual` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`criterios_manual`)),
  `puntos_auto` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `puntos_manual` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `puntos_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `nivel` varchar(20) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `evaluado_por` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesor_permiso_solicitud`
--

CREATE TABLE `profesor_permiso_solicitud` (
  `id_solicitud` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `motivo` text NOT NULL,
  `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `revisado_por` int(10) UNSIGNED DEFAULT NULL,
  `comentario_revision` varchar(500) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `promociones_descuento`
--

CREATE TABLE `promociones_descuento` (
  `id_promocion` int(10) UNSIGNED NOT NULL,
  `clave` varchar(40) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `aplicar_a` enum('inscripcion','colegiatura','ambos') NOT NULL DEFAULT 'colegiatura',
  `tipo` enum('porcentaje','monto_fijo') NOT NULL DEFAULT 'porcentaje',
  `valor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `requiere_motivo` tinyint(1) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prospectos_profesor`
--

CREATE TABLE `prospectos_profesor` (
  `id_prospecto` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_usuario_registro` int(10) UNSIGNED NOT NULL,
  `estado` enum('entrevista','evaluacion','contratado','rechazado','contactar_despues') NOT NULL DEFAULT 'entrevista',
  `nombres` varchar(120) NOT NULL,
  `apellido_paterno` varchar(80) NOT NULL,
  `apellido_materno` varchar(80) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `email_personal` varchar(160) DEFAULT NULL,
  `especialidad` varchar(120) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `motivo_no_contratacion` text DEFAULT NULL,
  `id_usuario_final` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reglas_colegiatura_combo`
--

CREATE TABLE `reglas_colegiatura_combo` (
  `id_regla` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `claves_combo` varchar(255) NOT NULL COMMENT 'Claves ordenadas CSV ej. COMP-K,ING-K',
  `min_especialidades` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `motivo` varchar(255) DEFAULT NULL,
  `tipo` varchar(24) NOT NULL DEFAULT 'combinacion',
  `categoria_promo` varchar(40) DEFAULT NULL,
  `id_autoriza` int(10) UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `regla_combo_tarifa`
--

CREATE TABLE `regla_combo_tarifa` (
  `id_tarifa` int(10) UNSIGNED NOT NULL,
  `id_regla` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `costo_inscripcion` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_inscripcion_referencia` decimal(12,2) DEFAULT NULL,
  `costo_inscripcion_apoyo` decimal(12,2) DEFAULT NULL,
  `costo_mensualidad` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_pronto_pago` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_semanal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_anual` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reporte_semanal_movimiento`
--

CREATE TABLE `reporte_semanal_movimiento` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_alumno` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `id_grupo_otro` int(10) UNSIGNED DEFAULT NULL,
  `tipo` enum('I','R','C_POS','C_NEG','B','FC') NOT NULL,
  `fecha` date NOT NULL,
  `anio` smallint(5) UNSIGNED NOT NULL,
  `semana` tinyint(3) UNSIGNED NOT NULL,
  `nota` varchar(500) DEFAULT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `origen` enum('auto','manual') NOT NULL DEFAULT 'auto',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(10) UNSIGNED NOT NULL,
  `clave` varchar(40) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `acceso_total` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=todos los privilegios',
  `alcance_planteles` varchar(20) NOT NULL DEFAULT 'solo_usuario' COMMENT 'solo_usuario|lista|todos',
  `departamento_default` varchar(40) DEFAULT NULL,
  `es_sistema` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `role_planteles`
--

CREATE TABLE `role_planteles` (
  `id_rol` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `role_privilegios`
--

CREATE TABLE `role_privilegios` (
  `id_rol` int(10) UNSIGNED NOT NULL,
  `privilegio` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_aulas_asignacion`
--

CREATE TABLE `rol_aulas_asignacion` (
  `id_asignacion` int(10) UNSIGNED NOT NULL,
  `id_publicacion` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `id_aula` int(10) UNSIGNED DEFAULT NULL,
  `cupo_grupo` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `cupo_aula` int(10) UNSIGNED DEFAULT NULL,
  `es_manual` tinyint(1) NOT NULL DEFAULT 0,
  `notas` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_aulas_publicacion`
--

CREATE TABLE `rol_aulas_publicacion` (
  `id_publicacion` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `anio` smallint(5) UNSIGNED NOT NULL,
  `mes` tinyint(3) UNSIGNED NOT NULL,
  `estado` enum('borrador','publicado') NOT NULL DEFAULT 'borrador',
  `notas` text DEFAULT NULL,
  `creado_por` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `publicado_por` int(10) UNSIGNED DEFAULT NULL,
  `publicado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `soporte_reporte`
--

CREATE TABLE `soporte_reporte` (
  `id_reporte` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `tipo` enum('error','sugerencia') NOT NULL DEFAULT 'error',
  `mensaje` text NOT NULL,
  `adjuntos_json` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tutor_conversaciones`
--

CREATE TABLE `tutor_conversaciones` (
  `id_conversacion` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_tutor` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(200) DEFAULT NULL,
  `id_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `id_fase` int(10) UNSIGNED DEFAULT NULL,
  `origen` varchar(32) NOT NULL DEFAULT 'hay',
  `archivada` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = archivada, oculta del listado activo',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tutor_ia_logs`
--

CREATE TABLE `tutor_ia_logs` (
  `id_log` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_conversacion` int(10) UNSIGNED DEFAULT NULL,
  `id_tutor` int(10) UNSIGNED DEFAULT NULL,
  `prompt_enviado` mediumtext NOT NULL,
  `respuesta_recibida` mediumtext DEFAULT NULL,
  `modelo` varchar(80) NOT NULL,
  `tokens_prompt` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `tokens_respuesta` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `tokens_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `costo_estimado` decimal(10,6) NOT NULL DEFAULT 0.000000,
  `http_code` smallint(5) UNSIGNED DEFAULT NULL,
  `provider` varchar(32) NOT NULL DEFAULT 'openrouter',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tutor_mensajes`
--

CREATE TABLE `tutor_mensajes` (
  `id_mensaje` int(10) UNSIGNED NOT NULL,
  `id_conversacion` int(10) UNSIGNED NOT NULL,
  `role` enum('system','user','assistant') NOT NULL,
  `mensaje` mediumtext NOT NULL,
  `tokens` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tutor_tutores`
--

CREATE TABLE `tutor_tutores` (
  `id_tutor` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `especialidad` varchar(40) NOT NULL DEFAULT 'general',
  `instrucciones` text NOT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ubicacion_examen`
--

CREATE TABLE `ubicacion_examen` (
  `id_examen` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `id_fase` int(10) UNSIGNED DEFAULT NULL COMMENT 'Opcional: examen según fase destino (ej. Excel avanzado)',
  `nombre` varchar(160) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `moodle_course_id` int(10) UNSIGNED NOT NULL,
  `moodle_shortname` varchar(80) DEFAULT NULL,
  `moodle_idnumber` varchar(80) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `codigo_huella` varchar(40) DEFAULT NULL,
  `huella_registrada` tinyint(1) NOT NULL DEFAULT 0,
  `huella_registrada_en` datetime DEFAULT NULL,
  `huella_dispositivo` varchar(60) DEFAULT NULL,
  `id_plantel` int(10) UNSIGNED DEFAULT NULL,
  `id_alumno` int(10) UNSIGNED DEFAULT NULL,
  `moodle_user_id` int(10) UNSIGNED DEFAULT NULL,
  `debe_cambiar_password` tinyint(1) NOT NULL DEFAULT 0,
  `suspendido` tinyint(1) NOT NULL DEFAULT 0,
  `login_fallidos` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `login_bloqueado_hasta` datetime DEFAULT NULL,
  `ultimo_acceso` datetime DEFAULT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('supervisor','director','gerente','coordinador','admin','asesor','profesor','alumno') NOT NULL DEFAULT 'profesor',
  `id_hay_area` int(10) UNSIGNED DEFAULT NULL,
  `id_rol` int(10) UNSIGNED DEFAULT NULL,
  `permisos_personalizados` tinyint(1) NOT NULL DEFAULT 0,
  `departamento` enum('ingles','computacion','administrativo','ventas','otro') NOT NULL,
  `avatar` varchar(255) DEFAULT 'default_avatar.png',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_huellas`
--

CREATE TABLE `usuario_huellas` (
  `id_huella` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `codigo_huella` varchar(40) NOT NULL,
  `dedo` varchar(24) NOT NULL DEFAULT 'indice_derecho',
  `formato` varchar(30) NOT NULL DEFAULT 'intermediate',
  `template_data` mediumtext NOT NULL,
  `dispositivo` varchar(60) NOT NULL DEFAULT 'uareu_5300',
  `calidad` tinyint(3) UNSIGNED DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `id_usuario_registro` int(10) UNSIGNED DEFAULT NULL,
  `registrado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_login_intento`
--

CREATE TABLE `usuario_login_intento` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `username_intento` varchar(120) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `exito` tinyint(1) NOT NULL DEFAULT 0,
  `motivo` varchar(80) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_planteles`
--

CREATE TABLE `usuario_planteles` (
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `id_usuario_otorga` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_privilegios`
--

CREATE TABLE `usuario_privilegios` (
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `privilegio` varchar(64) NOT NULL,
  `tipo` enum('otorgar','denegar') NOT NULL DEFAULT 'otorgar',
  `vigente_hasta` date DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `id_usuario_otorga` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_tour`
--

CREATE TABLE `usuario_tour` (
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `tour_key` varchar(80) NOT NULL,
  `completado` tinyint(1) NOT NULL DEFAULT 0,
  `completado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas_movimiento`
--

CREATE TABLE `ventas_movimiento` (
  `id_movimiento` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_usuario_asesor` int(10) UNSIGNED NOT NULL,
  `tipo` enum('inscripcion','certificacion','personalizado') NOT NULL,
  `id_alumno` int(10) UNSIGNED DEFAULT NULL,
  `id_especialidad` int(10) UNSIGNED DEFAULT NULL,
  `id_grupo` int(10) UNSIGNED DEFAULT NULL,
  `id_preregistro` int(10) UNSIGNED DEFAULT NULL,
  `id_pago` int(10) UNSIGNED DEFAULT NULL,
  `id_solicitud_cert` int(10) UNSIGNED DEFAULT NULL,
  `monto_base` decimal(12,2) NOT NULL DEFAULT 0.00,
  `comision_asesor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `comision_gerente` decimal(12,2) NOT NULL DEFAULT 0.00,
  `comision_gerente_sobre` decimal(12,2) DEFAULT NULL,
  `cuenta_tabulador` tinyint(1) NOT NULL DEFAULT 1,
  `origen_cartas` tinyint(1) NOT NULL DEFAULT 0,
  `excluir_tabulador` tinyint(1) NOT NULL DEFAULT 0,
  `regla_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`regla_snapshot`)),
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas_override`
--

CREATE TABLE `ventas_override` (
  `id_override` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `id_usuario_asesor` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL = todos los asesores',
  `fecha_desde` date NOT NULL,
  `fecha_hasta` date NOT NULL,
  `periodo` enum('dia','semana','mes') NOT NULL DEFAULT 'semana',
  `afecta` enum('sueldo_base','solo_comisiones','ambos') NOT NULL DEFAULT 'sueldo_base',
  `id_tabulador` int(10) UNSIGNED DEFAULT NULL COMMENT 'Tabulador temporal de reemplazo',
  `motivo` text DEFAULT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas_regla_especialidad_hist`
--

CREATE TABLE `ventas_regla_especialidad_hist` (
  `id_hist` int(10) UNSIGNED NOT NULL,
  `id_especialidad` int(10) UNSIGNED NOT NULL,
  `ventas_comision_asesor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ventas_comision_gerente` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ventas_comision_asesor_pct` decimal(5,2) DEFAULT NULL,
  `ventas_comision_gerente_pct` decimal(5,2) DEFAULT NULL,
  `ventas_cuenta_tabulador` tinyint(1) NOT NULL DEFAULT 1,
  `ventas_tipo_comision` varchar(20) NOT NULL DEFAULT 'fija',
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas_tabulador`
--

CREATE TABLE `ventas_tabulador` (
  `id_tabulador` int(10) UNSIGNED NOT NULL,
  `id_plantel` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `periodo` enum('dia','semana','mes') NOT NULL DEFAULT 'semana',
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `notas` text DEFAULT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas_tabulador_tramo`
--

CREATE TABLE `ventas_tabulador_tramo` (
  `id_tramo` int(10) UNSIGNED NOT NULL,
  `id_tabulador` int(10) UNSIGNED NOT NULL,
  `min_inscripciones` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `max_inscripciones` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'NULL = sin tope',
  `monto_sueldo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `academico_libro`
--
ALTER TABLE `academico_libro`
  ADD PRIMARY KEY (`id_libro`),
  ADD UNIQUE KEY `uq_libro_esp_tipo` (`id_especialidad`,`tipo`),
  ADD KEY `idx_libro_esp` (`id_especialidad`,`activo`);

--
-- Indices de la tabla `academico_libro_version`
--
ALTER TABLE `academico_libro_version`
  ADD PRIMARY KEY (`id_version`),
  ADD KEY `idx_ver_libro` (`id_libro`),
  ADD KEY `idx_ver_rag` (`activo_rag`,`estado_indexacion`);

--
-- Indices de la tabla `academico_material`
--
ALTER TABLE `academico_material`
  ADD PRIMARY KEY (`id_material`),
  ADD KEY `idx_mat_esp` (`id_especialidad`,`activo`),
  ADD KEY `idx_mat_fase_sem` (`id_fase`,`semana`),
  ADD KEY `idx_mat_tipo` (`tipo`,`activo`),
  ADD KEY `idx_mat_pagina` (`pagina_inicio`,`pagina_fin`);

--
-- Indices de la tabla `academico_material_embedding`
--
ALTER TABLE `academico_material_embedding`
  ADD PRIMARY KEY (`id_embedding`),
  ADD UNIQUE KEY `uq_mat_model` (`id_material`,`modelo`),
  ADD KEY `idx_emb_version` (`id_version`);

--
-- Indices de la tabla `acuerdo_escolar_version`
--
ALTER TABLE `acuerdo_escolar_version`
  ADD PRIMARY KEY (`id_acuerdo_version`),
  ADD KEY `idx_aev_activo` (`activo_para_nuevos`,`id_acuerdo_version`);

--
-- Indices de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD PRIMARY KEY (`id_alumno`),
  ADD UNIQUE KEY `uq_alumnos_matricula` (`matricula`),
  ADD KEY `idx_alumnos_grupo` (`id_grupo`),
  ADD KEY `idx_alumnos_activo` (`activo`);

--
-- Indices de la tabla `alumno_acuerdo_aceptacion`
--
ALTER TABLE `alumno_acuerdo_aceptacion`
  ADD PRIMARY KEY (`id_aceptacion`),
  ADD UNIQUE KEY `uq_aaa_alumno_version` (`id_alumno`,`id_acuerdo_version`),
  ADD KEY `idx_aaa_version` (`id_acuerdo_version`);

--
-- Indices de la tabla `alumno_adeudo_condonacion`
--
ALTER TABLE `alumno_adeudo_condonacion`
  ADD PRIMARY KEY (`id_condonacion`),
  ADD KEY `idx_cond_alumno` (`id_alumno`),
  ADD KEY `idx_cond_ae` (`id_alumno_especialidad`);

--
-- Indices de la tabla `alumno_aviso`
--
ALTER TABLE `alumno_aviso`
  ADD PRIMARY KEY (`id_aviso`),
  ADD KEY `idx_aviso_plantel` (`id_plantel`,`activo`,`creado_en`),
  ADD KEY `idx_aviso_grupo` (`id_grupo`);

--
-- Indices de la tabla `alumno_becas`
--
ALTER TABLE `alumno_becas`
  ADD PRIMARY KEY (`id_beca`),
  ADD KEY `idx_beca_alumno` (`id_alumno`);

--
-- Indices de la tabla `alumno_calificaciones_fase`
--
ALTER TABLE `alumno_calificaciones_fase`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_alumno_fase` (`id_alumno`,`id_fase`);

--
-- Indices de la tabla `alumno_calificacion_parcial`
--
ALTER TABLE `alumno_calificacion_parcial`
  ADD PRIMARY KEY (`id_calificacion`),
  ADD UNIQUE KEY `uq_alumno_fase_cal` (`id_alumno`,`id_fase`),
  ADD KEY `idx_cal_fase` (`id_fase`);

--
-- Indices de la tabla `alumno_chat_mensaje`
--
ALTER TABLE `alumno_chat_mensaje`
  ADD PRIMARY KEY (`id_mensaje`),
  ADD KEY `idx_chat_sala_fecha` (`id_sala`,`creado_en`);

--
-- Indices de la tabla `alumno_chat_sala`
--
ALTER TABLE `alumno_chat_sala`
  ADD PRIMARY KEY (`id_sala`),
  ADD UNIQUE KEY `uq_chat_sala` (`id_plantel`,`tipo`,`id_grupo`);

--
-- Indices de la tabla `alumno_documento`
--
ALTER TABLE `alumno_documento`
  ADD PRIMARY KEY (`id_documento`),
  ADD UNIQUE KEY `uq_ad_folio` (`folio`),
  ADD UNIQUE KEY `uq_ad_token` (`token_verificacion`),
  ADD KEY `idx_ad_alumno` (`id_alumno`),
  ADD KEY `idx_ad_estado` (`estado`),
  ADD KEY `idx_ad_grupo` (`id_grupo`);

--
-- Indices de la tabla `alumno_documentos`
--
ALTER TABLE `alumno_documentos`
  ADD PRIMARY KEY (`id_documento`);

--
-- Indices de la tabla `alumno_especialidades`
--
ALTER TABLE `alumno_especialidades`
  ADD PRIMARY KEY (`id_alumno_especialidad`),
  ADD UNIQUE KEY `uq_alumno_esp` (`id_alumno`,`id_especialidad`),
  ADD KEY `idx_ae_alumno` (`id_alumno`);

--
-- Indices de la tabla `alumno_grupos`
--
ALTER TABLE `alumno_grupos`
  ADD PRIMARY KEY (`id_alumno_grupo`),
  ADD UNIQUE KEY `uq_alumno_grupo` (`id_alumno`,`id_grupo`),
  ADD KEY `idx_ag_alumno` (`id_alumno`),
  ADD KEY `idx_ag_grupo` (`id_grupo`);

--
-- Indices de la tabla `alumno_huellas`
--
ALTER TABLE `alumno_huellas`
  ADD PRIMARY KEY (`id_huella`),
  ADD KEY `idx_ah_alumno` (`id_alumno`),
  ADD KEY `idx_ah_codigo` (`codigo_huella`,`id_plantel`);

--
-- Indices de la tabla `alumno_moodle_curso`
--
ALTER TABLE `alumno_moodle_curso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_amc_alumno_esp_curso` (`id_alumno`,`id_especialidad`,`moodle_course_id`),
  ADD KEY `idx_amc_alumno` (`id_alumno`);

--
-- Indices de la tabla `alumno_notas`
--
ALTER TABLE `alumno_notas`
  ADD PRIMARY KEY (`id_nota`);

--
-- Indices de la tabla `alumno_nota_coordinacion`
--
ALTER TABLE `alumno_nota_coordinacion`
  ADD PRIMARY KEY (`id_nota`),
  ADD KEY `idx_anc_alumno` (`id_alumno`);

--
-- Indices de la tabla `alumno_pagos`
--
ALTER TABLE `alumno_pagos`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `idx_pago_alumno` (`id_alumno`);

--
-- Indices de la tabla `alumno_pago_movimiento`
--
ALTER TABLE `alumno_pago_movimiento`
  ADD PRIMARY KEY (`id_mov`),
  ADD KEY `idx_apm_pago` (`id_pago`),
  ADD KEY `idx_apm_alumno` (`id_alumno`),
  ADD KEY `idx_apm_fecha` (`creado_en`);

--
-- Indices de la tabla `alumno_plan_asignado`
--
ALTER TABLE `alumno_plan_asignado`
  ADD PRIMARY KEY (`id_asignacion`),
  ADD UNIQUE KEY `uq_apa_alumno_esp` (`id_alumno`,`id_especialidad`),
  ADD KEY `idx_apa_plan` (`id_plan_version`);

--
-- Indices de la tabla `alumno_tarifa_override_hist`
--
ALTER TABLE `alumno_tarifa_override_hist`
  ADD PRIMARY KEY (`id_hist`),
  ADD KEY `idx_tarifa_hist_alumno` (`id_alumno`),
  ADD KEY `idx_tarifa_hist_ae` (`id_alumno_especialidad`);

--
-- Indices de la tabla `alumno_ubicacion`
--
ALTER TABLE `alumno_ubicacion`
  ADD PRIMARY KEY (`id_ubicacion`),
  ADD KEY `idx_ub_alumno` (`id_alumno`);

--
-- Indices de la tabla `alumno_ubicacion_grupos`
--
ALTER TABLE `alumno_ubicacion_grupos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ub_grupo` (`id_ubicacion`,`id_grupo`);

--
-- Indices de la tabla `asesoria_cita`
--
ALTER TABLE `asesoria_cita`
  ADD PRIMARY KEY (`id_cita`),
  ADD KEY `idx_ase_cita_fecha` (`id_plantel`,`fecha`,`estado`),
  ADD KEY `idx_ase_cita_prof` (`id_profesor`,`fecha`);

--
-- Indices de la tabla `asesoria_cita_alumno`
--
ALTER TABLE `asesoria_cita_alumno`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cita_alumno` (`id_cita`,`id_alumno`),
  ADD KEY `idx_ase_ca_alum` (`id_alumno`);

--
-- Indices de la tabla `asesoria_credito`
--
ALTER TABLE `asesoria_credito`
  ADD PRIMARY KEY (`id_credito`),
  ADD KEY `idx_ase_cred_alum` (`id_alumno`,`id_plantel`);

--
-- Indices de la tabla `asesoria_disp`
--
ALTER TABLE `asesoria_disp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_disp` (`id_profesor`,`anio`,`semana`,`dow`,`hora`),
  ADD KEY `idx_disp_prof_sem` (`id_profesor`,`anio`,`semana`);

--
-- Indices de la tabla `asesoria_pago_profesor`
--
ALTER TABLE `asesoria_pago_profesor`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `idx_ase_pago_prof` (`id_profesor`,`liquidado`);

--
-- Indices de la tabla `asesoria_tabulador`
--
ALTER TABLE `asesoria_tabulador`
  ADD PRIMARY KEY (`id_tabulador`),
  ADD KEY `idx_ase_tab_clave` (`clave`,`id_plantel`,`activo`);

--
-- Indices de la tabla `asesor_cartas_periodo`
--
ALTER TABLE `asesor_cartas_periodo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cartas_asesor_mes` (`id_plantel`,`id_usuario_asesor`,`periodo_mes`);

--
-- Indices de la tabla `asesor_entrevistas`
--
ALTER TABLE `asesor_entrevistas`
  ADD PRIMARY KEY (`id_entrevista`),
  ADD KEY `idx_ent_plantel_asesor` (`id_plantel`,`id_usuario_asesor`,`creado_en`),
  ADD KEY `idx_ent_estado` (`estado`),
  ADD KEY `idx_ent_prereg` (`id_preregistro`);

--
-- Indices de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD PRIMARY KEY (`id_asistencia`),
  ADD UNIQUE KEY `uq_asistencia` (`id_alumno`,`fecha`),
  ADD KEY `idx_asist_grupo_fecha` (`id_grupo`,`fecha`),
  ADD KEY `idx_asist_grupo_anio_sem` (`id_grupo`,`anio`,`semana`);

--
-- Indices de la tabla `asistencia_falta_seguimiento`
--
ALTER TABLE `asistencia_falta_seguimiento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_falta_dia` (`id_alumno`,`id_grupo`,`fecha`),
  ADD KEY `idx_falta_fecha_plantel` (`fecha`,`id_plantel`);

--
-- Indices de la tabla `asistencia_personal`
--
ALTER TABLE `asistencia_personal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_personal_fecha` (`id_usuario`,`fecha`);

--
-- Indices de la tabla `aula_especialidades`
--
ALTER TABLE `aula_especialidades`
  ADD PRIMARY KEY (`id_aula`,`id_especialidad`),
  ADD KEY `idx_ae_esp` (`id_especialidad`);

--
-- Indices de la tabla `aula_fotos`
--
ALTER TABLE `aula_fotos`
  ADD PRIMARY KEY (`id_foto`),
  ADD KEY `idx_af_aula` (`id_aula`);

--
-- Indices de la tabla `calendario_dia_lectivo`
--
ALTER TABLE `calendario_dia_lectivo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cal_fecha_plantel_modelo` (`fecha`,`id_plantel`,`modelo`),
  ADD KEY `idx_cal_anio` (`anio`);

--
-- Indices de la tabla `calendario_escolar_anio`
--
ALTER TABLE `calendario_escolar_anio`
  ADD PRIMARY KEY (`anio`,`modelo`);

--
-- Indices de la tabla `calendario_evento_admin`
--
ALTER TABLE `calendario_evento_admin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cev_fecha` (`fecha`),
  ADD KEY `idx_cev_pub` (`publicado`);

--
-- Indices de la tabla `calendario_evento_audiencia`
--
ALTER TABLE `calendario_evento_audiencia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cea_evento` (`id_evento`);

--
-- Indices de la tabla `certificacion_accesos`
--
ALTER TABLE `certificacion_accesos`
  ADD PRIMARY KEY (`id_acceso`),
  ADD KEY `idx_cert_acc_sol` (`id_solicitud`,`vigente`);

--
-- Indices de la tabla `certificacion_campo_catalogo`
--
ALTER TABLE `certificacion_campo_catalogo`
  ADD PRIMARY KEY (`clave`);

--
-- Indices de la tabla `certificacion_comision_historial`
--
ALTER TABLE `certificacion_comision_historial`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `idx_cert_com_hist_sol` (`id_solicitud`,`creado_en`);

--
-- Indices de la tabla `certificacion_documentos`
--
ALTER TABLE `certificacion_documentos`
  ADD PRIMARY KEY (`id_documento`),
  ADD UNIQUE KEY `uq_cert_doc_tipo` (`id_solicitud`,`tipo`),
  ADD KEY `idx_cert_doc_solicitud` (`id_solicitud`);

--
-- Indices de la tabla `certificacion_reagendamientos`
--
ALTER TABLE `certificacion_reagendamientos`
  ADD PRIMARY KEY (`id_reagendamiento`),
  ADD KEY `idx_cert_reag_sol` (`id_solicitud`);

--
-- Indices de la tabla `certificacion_solicitudes`
--
ALTER TABLE `certificacion_solicitudes`
  ADD PRIMARY KEY (`id_solicitud`),
  ADD KEY `idx_cert_sol_plantel` (`id_plantel`,`estado`),
  ADD KEY `idx_cert_sol_alumno` (`id_alumno`),
  ADD KEY `idx_cert_sol_producto` (`id_producto`);

--
-- Indices de la tabla `corte_caja`
--
ALTER TABLE `corte_caja`
  ADD PRIMARY KEY (`id_corte`),
  ADD KEY `idx_corte_plantel_fecha` (`id_plantel`,`fecha`);

--
-- Indices de la tabla `curso_personalizado`
--
ALTER TABLE `curso_personalizado`
  ADD PRIMARY KEY (`id_curso`),
  ADD KEY `idx_cp_alumno` (`id_alumno`),
  ADD KEY `idx_cp_plantel` (`id_plantel`,`estado`);

--
-- Indices de la tabla `curso_personalizado_pago`
--
ALTER TABLE `curso_personalizado_pago`
  ADD PRIMARY KEY (`id_pago_prog`),
  ADD UNIQUE KEY `uq_cpp_curso_num` (`id_curso`,`numero`),
  ADD KEY `idx_cpp_curso` (`id_curso`,`pagado`);

--
-- Indices de la tabla `disc_cod`
--
ALTER TABLE `disc_cod`
  ADD PRIMARY KEY (`codigo`),
  ADD KEY `idx_pat` (`pat_id`);

--
-- Indices de la tabla `disc_pat`
--
ALTER TABLE `disc_pat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slug` (`slug`),
  ADD UNIQUE KEY `uq_nombre` (`nombre`);

--
-- Indices de la tabla `disc_res`
--
ALTER TABLE `disc_res`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_fecha` (`user_id`,`creado_en`),
  ADD KEY `fk_disc_res_pat` (`pat_id`),
  ADD KEY `fk_res_1p` (`1+`),
  ADD KEY `fk_res_2p` (`2+`),
  ADD KEY `fk_res_3p` (`3+`),
  ADD KEY `fk_res_4p` (`4+`),
  ADD KEY `fk_res_5p` (`5+`),
  ADD KEY `fk_res_6p` (`6+`),
  ADD KEY `fk_res_7p` (`7+`),
  ADD KEY `fk_res_8p` (`8+`),
  ADD KEY `fk_res_9p` (`9+`),
  ADD KEY `fk_res_10p` (`10+`),
  ADD KEY `fk_res_11p` (`11+`),
  ADD KEY `fk_res_12p` (`12+`),
  ADD KEY `fk_res_13p` (`13+`),
  ADD KEY `fk_res_14p` (`14+`),
  ADD KEY `fk_res_15p` (`15+`),
  ADD KEY `fk_res_16p` (`16+`),
  ADD KEY `fk_res_17p` (`17+`),
  ADD KEY `fk_res_18p` (`18+`),
  ADD KEY `fk_res_19p` (`19+`),
  ADD KEY `fk_res_20p` (`20+`),
  ADD KEY `fk_res_21p` (`21+`),
  ADD KEY `fk_res_22p` (`22+`),
  ADD KEY `fk_res_23p` (`23+`),
  ADD KEY `fk_res_24p` (`24+`),
  ADD KEY `fk_res_25p` (`25+`),
  ADD KEY `fk_res_26p` (`26+`),
  ADD KEY `fk_res_27p` (`27+`),
  ADD KEY `fk_res_28p` (`28+`),
  ADD KEY `fk_res_1m` (`1-`),
  ADD KEY `fk_res_2m` (`2-`),
  ADD KEY `fk_res_3m` (`3-`),
  ADD KEY `fk_res_4m` (`4-`),
  ADD KEY `fk_res_5m` (`5-`),
  ADD KEY `fk_res_6m` (`6-`),
  ADD KEY `fk_res_7m` (`7-`),
  ADD KEY `fk_res_8m` (`8-`),
  ADD KEY `fk_res_9m` (`9-`),
  ADD KEY `fk_res_10m` (`10-`),
  ADD KEY `fk_res_11m` (`11-`),
  ADD KEY `fk_res_12m` (`12-`),
  ADD KEY `fk_res_13m` (`13-`),
  ADD KEY `fk_res_14m` (`14-`),
  ADD KEY `fk_res_15m` (`15-`),
  ADD KEY `fk_res_16m` (`16-`),
  ADD KEY `fk_res_17m` (`17-`),
  ADD KEY `fk_res_18m` (`18-`),
  ADD KEY `fk_res_19m` (`19-`),
  ADD KEY `fk_res_20m` (`20-`),
  ADD KEY `fk_res_21m` (`21-`),
  ADD KEY `fk_res_22m` (`22-`),
  ADD KEY `fk_res_23m` (`23-`),
  ADD KEY `fk_res_24m` (`24-`),
  ADD KEY `fk_res_25m` (`25-`),
  ADD KEY `fk_res_26m` (`26-`),
  ADD KEY `fk_res_27m` (`27-`),
  ADD KEY `fk_res_28m` (`28-`);

--
-- Indices de la tabla `disc_words`
--
ALTER TABLE `disc_words`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sec_ord` (`sec`,`ord`),
  ADD KEY `idx_sec` (`sec`);

--
-- Indices de la tabla `docente_prospecto`
--
ALTER TABLE `docente_prospecto`
  ADD PRIMARY KEY (`id_prospecto`),
  ADD KEY `idx_dp_plantel_estado` (`id_plantel`,`estado`),
  ADD KEY `idx_dp_contacto` (`email`,`telefono`,`curp`);

--
-- Indices de la tabla `docente_prospecto_area`
--
ALTER TABLE `docente_prospecto_area`
  ADD PRIMARY KEY (`id_prospecto`,`id_area`),
  ADD KEY `idx_dpa_area` (`id_area`);

--
-- Indices de la tabla `docente_prospecto_evento`
--
ALTER TABLE `docente_prospecto_evento`
  ADD PRIMARY KEY (`id_evento`),
  ADD KEY `idx_dpe_prospecto` (`id_prospecto`,`tipo`);

--
-- Indices de la tabla `docente_rubrica_area`
--
ALTER TABLE `docente_rubrica_area`
  ADD PRIMARY KEY (`id_rubrica`),
  ADD UNIQUE KEY `uq_dra_clave_tipo` (`clave`,`tipo`);

--
-- Indices de la tabla `docente_rubrica_criterio`
--
ALTER TABLE `docente_rubrica_criterio`
  ADD PRIMARY KEY (`id_criterio`),
  ADD UNIQUE KEY `uq_drc_rubrica_cod` (`id_rubrica`,`codigo`),
  ADD KEY `idx_drc_rubrica` (`id_rubrica`);

--
-- Indices de la tabla `docente_showclass_eval`
--
ALTER TABLE `docente_showclass_eval`
  ADD PRIMARY KEY (`id_eval`),
  ADD KEY `idx_dse_prospecto` (`id_prospecto`);

--
-- Indices de la tabla `documento_plantilla`
--
ALTER TABLE `documento_plantilla`
  ADD PRIMARY KEY (`id_plantilla`),
  ADD KEY `idx_dp_tipo` (`tipo`),
  ADD KEY `idx_dp_plantel` (`id_plantel`);

--
-- Indices de la tabla `en_audios`
--
ALTER TABLE `en_audios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audios_fase` (`fase`),
  ADD KEY `idx_audios_id_audio` (`id_audio`);

--
-- Indices de la tabla `en_gramatica`
--
ALTER TABLE `en_gramatica`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gram_fase` (`fase`),
  ADD KEY `idx_gram_fusion` (`id_fusion`);

--
-- Indices de la tabla `en_lecturas`
--
ALTER TABLE `en_lecturas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lect_fase` (`fase`),
  ADD KEY `idx_lect_id_lectura` (`id_lectura`);

--
-- Indices de la tabla `en_listening`
--
ALTER TABLE `en_listening`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_list_fase` (`fase`),
  ADD KEY `idx_list_audio` (`id_audio`);

--
-- Indices de la tabla `en_reading`
--
ALTER TABLE `en_reading`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_read_fase` (`fase`),
  ADD KEY `idx_read_lectura` (`id_lectura`);

--
-- Indices de la tabla `en_speaking`
--
ALTER TABLE `en_speaking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_speak_fase` (`fase`);

--
-- Indices de la tabla `en_vocabulario`
--
ALTER TABLE `en_vocabulario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vocab_fase` (`fase`),
  ADD KEY `idx_vocab_fusion` (`id_fusion`);

--
-- Indices de la tabla `en_writing`
--
ALTER TABLE `en_writing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_writ_fase` (`fase`);

--
-- Indices de la tabla `escuelas_externas`
--
ALTER TABLE `escuelas_externas`
  ADD PRIMARY KEY (`id_escuela`),
  ADD KEY `idx_ee_plantel` (`id_plantel`,`activo`),
  ADD KEY `idx_ee_nombre` (`nombre`);

--
-- Indices de la tabla `escuela_visita`
--
ALTER TABLE `escuela_visita`
  ADD PRIMARY KEY (`id_visita`),
  ADD KEY `idx_ev_plantel_fecha` (`id_plantel`,`fecha_visita`),
  ADD KEY `idx_ev_escuela` (`id_escuela`);

--
-- Indices de la tabla `especialidades`
--
ALTER TABLE `especialidades`
  ADD PRIMARY KEY (`id_especialidad`),
  ADD UNIQUE KEY `uq_especialidades_clave` (`clave`);

--
-- Indices de la tabla `especialidad_fases`
--
ALTER TABLE `especialidad_fases`
  ADD PRIMARY KEY (`id_fase`),
  ADD KEY `idx_fase_esp` (`id_especialidad`);

--
-- Indices de la tabla `especialidad_tarifa_cartas`
--
ALTER TABLE `especialidad_tarifa_cartas`
  ADD PRIMARY KEY (`id_especialidad`);

--
-- Indices de la tabla `especialidad_tarifa_historial`
--
ALTER TABLE `especialidad_tarifa_historial`
  ADD PRIMARY KEY (`id_hist`),
  ADD KEY `idx_eth_esp` (`id_especialidad`,`creado_en`);

--
-- Indices de la tabla `exam_calificaciones`
--
ALTER TABLE `exam_calificaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_exam_alumno` (`id_examen`,`id_alumno`),
  ADD KEY `idx_calif_fase` (`fase`),
  ADD KEY `idx_calif_control` (`numero_control`),
  ADD KEY `idx_calif_grupo` (`id_grupo`),
  ADD KEY `fk_calif_alumno` (`id_alumno`);

--
-- Indices de la tabla `exam_fusiones`
--
ALTER TABLE `exam_fusiones`
  ADD PRIMARY KEY (`id_fusion`),
  ADD KEY `idx_fusion_area` (`area`);

--
-- Indices de la tabla `exam_fusion_fases`
--
ALTER TABLE `exam_fusion_fases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ff_fusion` (`id_fusion`);

--
-- Indices de la tabla `exam_generados`
--
ALTER TABLE `exam_generados`
  ADD PRIMARY KEY (`id_examen`),
  ADD KEY `idx_exam_area` (`area`),
  ADD KEY `idx_exam_prof` (`id_profesor`);

--
-- Indices de la tabla `exam_generado_preguntas`
--
ALTER TABLE `exam_generado_preguntas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_egp_examen` (`id_examen`);

--
-- Indices de la tabla `exam_plantel_config`
--
ALTER TABLE `exam_plantel_config`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `expediente_entrega`
--
ALTER TABLE `expediente_entrega`
  ADD PRIMARY KEY (`id_entrega`),
  ADD UNIQUE KEY `uq_exp_entrega` (`id_requisito`,`tipo_entidad`,`id_entidad`,`id_hay_area`),
  ADD KEY `idx_exp_ent_plantel` (`id_plantel`,`estado`),
  ADD KEY `idx_exp_ent_entidad` (`tipo_entidad`,`id_entidad`);

--
-- Indices de la tabla `expediente_requisito`
--
ALTER TABLE `expediente_requisito`
  ADD PRIMARY KEY (`id_requisito`),
  ADD UNIQUE KEY `uq_exp_req_plantel_clave` (`id_plantel`,`clave`),
  ADD KEY `idx_exp_req_cat` (`categoria`,`activo`);

--
-- Indices de la tabla `fase_temario_semana`
--
ALTER TABLE `fase_temario_semana`
  ADD PRIMARY KEY (`id_semana`),
  ADD UNIQUE KEY `uq_fase_semana` (`id_fase`,`semana`),
  ADD KEY `idx_fts_fase` (`id_fase`);

--
-- Indices de la tabla `graduacion_alerta`
--
ALTER TABLE `graduacion_alerta`
  ADD PRIMARY KEY (`id_alerta`),
  ADD UNIQUE KEY `uq_grad_alumno_grupo` (`id_alumno`,`id_grupo`),
  ADD KEY `idx_grad_estado` (`id_plantel`,`estado`);

--
-- Indices de la tabla `grupos`
--
ALTER TABLE `grupos`
  ADD PRIMARY KEY (`id_grupo`),
  ADD UNIQUE KEY `uq_grupos_clave` (`clave`),
  ADD KEY `idx_grupos_tutor` (`id_tutor`);

--
-- Indices de la tabla `grupo_apertura_log`
--
ALTER TABLE `grupo_apertura_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gal_grupo` (`id_grupo`);

--
-- Indices de la tabla `grupo_avance_log`
--
ALTER TABLE `grupo_avance_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gal_grupo` (`id_grupo`);

--
-- Indices de la tabla `grupo_clave_historial`
--
ALTER TABLE `grupo_clave_historial`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `idx_gch_grupo` (`id_grupo`);

--
-- Indices de la tabla `grupo_clave_secuencia`
--
ALTER TABLE `grupo_clave_secuencia`
  ADD PRIMARY KEY (`id_plantel`,`prefijo`);

--
-- Indices de la tabla `grupo_docente`
--
ALTER TABLE `grupo_docente`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_grupo_docente_materia` (`id_grupo`,`materia_clave`),
  ADD KEY `idx_grupo_docente_prof` (`id_profesor`,`id_grupo`),
  ADD KEY `idx_grupo_docente_grupo` (`id_grupo`,`activo`);

--
-- Indices de la tabla `grupo_fusion_alumno`
--
ALTER TABLE `grupo_fusion_alumno`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_gfa_plan_alumno` (`id_fusion_plan`,`id_alumno`),
  ADD KEY `idx_gfa_plan` (`id_fusion_plan`);

--
-- Indices de la tabla `grupo_fusion_log`
--
ALTER TABLE `grupo_fusion_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gf_resultante` (`id_grupo_resultante`);

--
-- Indices de la tabla `grupo_fusion_pendiente_fase`
--
ALTER TABLE `grupo_fusion_pendiente_fase`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gfpf_plan` (`id_fusion_plan`),
  ADD KEY `idx_gfpf_grupo` (`id_grupo`,`estado`);

--
-- Indices de la tabla `grupo_fusion_plan`
--
ALTER TABLE `grupo_fusion_plan`
  ADD PRIMARY KEY (`id_fusion_plan`),
  ADD KEY `idx_gfp_plantel_estado` (`id_plantel`,`estado`),
  ADD KEY `idx_gfp_grupo_a` (`id_grupo_a`),
  ADD KEY `idx_gfp_grupo_b` (`id_grupo_b`),
  ADD KEY `idx_gfp_resultante` (`id_grupo_resultante`);

--
-- Indices de la tabla `grupo_horarios`
--
ALTER TABLE `grupo_horarios`
  ADD PRIMARY KEY (`id_horario`),
  ADD KEY `idx_gh_grupo` (`id_grupo`),
  ADD KEY `idx_gh_dia` (`dia_semana`);

--
-- Indices de la tabla `grupo_plan_periodo`
--
ALTER TABLE `grupo_plan_periodo`
  ADD PRIMARY KEY (`id_plan`),
  ADD UNIQUE KEY `uq_grupo_anio_mes` (`id_grupo`,`anio`,`mes`),
  ADD KEY `idx_gpp_grupo` (`id_grupo`),
  ADD KEY `idx_gpp_pendiente` (`id_grupo`,`pendiente_retomar`);

--
-- Indices de la tabla `grupo_preinicio_contacto`
--
ALTER TABLE `grupo_preinicio_contacto`
  ADD PRIMARY KEY (`id_contacto`),
  ADD UNIQUE KEY `uq_gpc_grupo_alumno` (`id_grupo`,`id_alumno`),
  ADD KEY `idx_gpc_plantel` (`id_plantel`),
  ADD KEY `idx_gpc_grupo` (`id_grupo`);

--
-- Indices de la tabla `grupo_rubrica_parcial`
--
ALTER TABLE `grupo_rubrica_parcial`
  ADD PRIMARY KEY (`id_rubrica`),
  ADD UNIQUE KEY `uq_grupo_fase_rubrica` (`id_grupo`,`id_fase`);

--
-- Indices de la tabla `grupo_suplencia`
--
ALTER TABLE `grupo_suplencia`
  ADD PRIMARY KEY (`id_suplencia`),
  ADD KEY `idx_gs_grupo` (`id_grupo`),
  ADD KEY `idx_gs_plantel` (`id_plantel`),
  ADD KEY `idx_gs_fechas` (`fecha_inicio`,`fecha_fin`);

--
-- Indices de la tabla `hay_app_meta`
--
ALTER TABLE `hay_app_meta`
  ADD PRIMARY KEY (`clave`);

--
-- Indices de la tabla `hay_area`
--
ALTER TABLE `hay_area`
  ADD PRIMARY KEY (`id_area`),
  ADD UNIQUE KEY `uq_hay_area_clave` (`clave`);

--
-- Indices de la tabla `hay_area_rol`
--
ALTER TABLE `hay_area_rol`
  ADD PRIMARY KEY (`id_area`,`rol_clave`);

--
-- Indices de la tabla `hay_area_usuario`
--
ALTER TABLE `hay_area_usuario`
  ADD PRIMARY KEY (`id_usuario`,`id_area`),
  ADD KEY `idx_hay_au_area` (`id_area`),
  ADD KEY `idx_hay_au_principal` (`id_usuario`,`es_principal`);

--
-- Indices de la tabla `hay_aspecto`
--
ALTER TABLE `hay_aspecto`
  ADD PRIMARY KEY (`id_aspecto`),
  ADD UNIQUE KEY `uq_hay_aspecto_rubro` (`id_rubro`,`codigo`),
  ADD KEY `idx_hay_aspecto_rubro` (`id_rubro`);

--
-- Indices de la tabla `hay_capacitacion`
--
ALTER TABLE `hay_capacitacion`
  ADD PRIMARY KEY (`id_capacitacion`),
  ADD KEY `idx_hay_cap_area` (`id_area`);

--
-- Indices de la tabla `hay_capacitacion_cumplimiento`
--
ALTER TABLE `hay_capacitacion_cumplimiento`
  ADD PRIMARY KEY (`id_cumplimiento`),
  ADD UNIQUE KEY `uq_hay_cap_cumpl` (`id_usuario`,`id_capacitacion`,`periodo`),
  ADD KEY `idx_hay_cc_usuario` (`id_usuario`,`periodo`);

--
-- Indices de la tabla `hay_config_version`
--
ALTER TABLE `hay_config_version`
  ADD PRIMARY KEY (`id_version`),
  ADD KEY `idx_hay_cv_area` (`id_area`,`publicada`);

--
-- Indices de la tabla `hay_eval_periodo`
--
ALTER TABLE `hay_eval_periodo`
  ADD PRIMARY KEY (`id_eval`),
  ADD UNIQUE KEY `uq_hay_eval_periodo` (`id_usuario`,`id_plantel`,`id_area`,`anio`,`mes`),
  ADD KEY `idx_hay_eval_plantel` (`id_plantel`,`anio`,`mes`);

--
-- Indices de la tabla `hay_eval_respuesta`
--
ALTER TABLE `hay_eval_respuesta`
  ADD PRIMARY KEY (`id_respuesta`),
  ADD UNIQUE KEY `uq_hay_eval_resp` (`id_eval`,`id_aspecto`),
  ADD KEY `idx_hay_er_eval` (`id_eval`);

--
-- Indices de la tabla `hay_legacy_equivalence`
--
ALTER TABLE `hay_legacy_equivalence`
  ADD PRIMARY KEY (`entidad`,`id_legacy`),
  ADD KEY `idx_equiv_hay` (`entidad`,`id_hay`);

--
-- Indices de la tabla `hay_legacy_import_log`
--
ALTER TABLE `hay_legacy_import_log`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_legacy_log_fase` (`fase`,`creado_en`);

--
-- Indices de la tabla `hay_legacy_map`
--
ALTER TABLE `hay_legacy_map`
  ADD PRIMARY KEY (`entidad`,`id_legacy`),
  ADD KEY `idx_legacy_hay` (`entidad`,`id_hay`);

--
-- Indices de la tabla `hay_nivel_cargo`
--
ALTER TABLE `hay_nivel_cargo`
  ADD PRIMARY KEY (`id_nivel`),
  ADD UNIQUE KEY `uq_hay_nivel_area` (`id_area`,`numero`),
  ADD KEY `idx_hay_nivel_area` (`id_area`);

--
-- Indices de la tabla `hay_opcion`
--
ALTER TABLE `hay_opcion`
  ADD PRIMARY KEY (`id_opcion`),
  ADD KEY `idx_hay_opcion_aspecto` (`id_aspecto`);

--
-- Indices de la tabla `hay_rubro`
--
ALTER TABLE `hay_rubro`
  ADD PRIMARY KEY (`id_rubro`),
  ADD UNIQUE KEY `uq_hay_rubro_area` (`id_area`,`clave`),
  ADD KEY `idx_hay_rubro_area` (`id_area`);

--
-- Indices de la tabla `huella_codigos`
--
ALTER TABLE `huella_codigos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_huella_codigo_plantel` (`codigo_huella`,`id_plantel`),
  ADD KEY `idx_huella_ref` (`tipo`,`id_referencia`);

--
-- Indices de la tabla `huella_eventos`
--
ALTER TABLE `huella_eventos`
  ADD PRIMARY KEY (`id_evento`),
  ADD KEY `idx_he_fecha` (`fecha_hora`);

--
-- Indices de la tabla `inscripcion_autorizacion`
--
ALTER TABLE `inscripcion_autorizacion`
  ADD PRIMARY KEY (`id_auth`),
  ADD KEY `idx_ia_estado` (`id_plantel`,`estado`),
  ADD KEY `idx_ia_alumno` (`id_alumno`);

--
-- Indices de la tabla `inscripcion_cartas_campana`
--
ALTER TABLE `inscripcion_cartas_campana`
  ADD PRIMARY KEY (`id_campana`),
  ADD KEY `idx_icc_plantel` (`id_plantel`,`vigente_desde`);

--
-- Indices de la tabla `inscripcion_cartas_reparto`
--
ALTER TABLE `inscripcion_cartas_reparto`
  ADD PRIMARY KEY (`id_reparto`),
  ADD KEY `idx_icr_pago` (`id_pago`);

--
-- Indices de la tabla `inscripcion_referidos`
--
ALTER TABLE `inscripcion_referidos`
  ADD PRIMARY KEY (`id_referido`),
  ADD KEY `idx_ref_inscrito` (`id_alumno_inscrito`),
  ADD KEY `idx_ref_referidor` (`id_alumno_referidor`),
  ADD KEY `idx_ref_plantel_fecha` (`id_plantel`,`creado_en`);

--
-- Indices de la tabla `marketing_banner`
--
ALTER TABLE `marketing_banner`
  ADD PRIMARY KEY (`id_banner`);

--
-- Indices de la tabla `nomina_ajuste_log`
--
ALTER TABLE `nomina_ajuste_log`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_nal_liq` (`id_liquidacion`);

--
-- Indices de la tabla `nomina_linea`
--
ALTER TABLE `nomina_linea`
  ADD PRIMARY KEY (`id_linea`),
  ADD KEY `idx_nl_liquidacion` (`id_liquidacion`),
  ADD KEY `idx_nl_usuario` (`id_usuario`);

--
-- Indices de la tabla `nomina_liquidacion`
--
ALTER TABLE `nomina_liquidacion`
  ADD PRIMARY KEY (`id_liquidacion`),
  ADD UNIQUE KEY `uq_nomina_periodo` (`id_plantel`,`tipo_periodo`,`fecha_inicio`,`fecha_fin`),
  ADD KEY `idx_nomina_plantel` (`id_plantel`);

--
-- Indices de la tabla `notificacion_panel_oculta`
--
ALTER TABLE `notificacion_panel_oculta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_notif_panel_user_clave` (`id_usuario`,`clave`),
  ADD KEY `idx_notif_panel_user` (`id_usuario`,`estado`);

--
-- Indices de la tabla `notificacion_usuario`
--
ALTER TABLE `notificacion_usuario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`id_usuario`,`leida`);

--
-- Indices de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token_hash`),
  ADD KEY `idx_user` (`id_usuario`);

--
-- Indices de la tabla `patrones`
--
ALTER TABLE `patrones`
  ADD PRIMARY KEY (`codigo`),
  ADD KEY `idx_patron_slug` (`patron_slug`);

--
-- Indices de la tabla `personal_pago_config`
--
ALTER TABLE `personal_pago_config`
  ADD PRIMARY KEY (`id_config`),
  ADD UNIQUE KEY `uq_ppc_usuario_plantel_alcance_area` (`id_usuario`,`id_plantel`,`alcance`,`id_hay_area`),
  ADD KEY `idx_ppc_plantel` (`id_plantel`);

--
-- Indices de la tabla `planeaciones`
--
ALTER TABLE `planeaciones`
  ADD PRIMARY KEY (`id_planeacion`),
  ADD KEY `idx_plan_grupo_fecha` (`id_grupo`,`fecha`),
  ADD KEY `idx_plan_grupo_anio_sem` (`id_grupo`,`anio`,`semana`);

--
-- Indices de la tabla `planeacion_observacion`
--
ALTER TABLE `planeacion_observacion`
  ADD PRIMARY KEY (`id_obs`),
  ADD KEY `idx_plan_obs_plan` (`id_planeacion`,`creado_en`);

--
-- Indices de la tabla `planteles`
--
ALTER TABLE `planteles`
  ADD PRIMARY KEY (`id_plantel`),
  ADD UNIQUE KEY `uq_planteles_slug` (`slug`);

--
-- Indices de la tabla `plantel_aulas`
--
ALTER TABLE `plantel_aulas`
  ADD PRIMARY KEY (`id_aula`),
  ADD UNIQUE KEY `uq_aula_plantel_codigo` (`id_plantel`,`codigo`),
  ADD KEY `idx_aula_plantel` (`id_plantel`);

--
-- Indices de la tabla `plan_estudio_version`
--
ALTER TABLE `plan_estudio_version`
  ADD PRIMARY KEY (`id_plan_version`),
  ADD KEY `idx_pev_esp` (`id_especialidad`,`activo_para_nuevos`);

--
-- Indices de la tabla `preregistros`
--
ALTER TABLE `preregistros`
  ADD PRIMARY KEY (`id_preregistro`);

--
-- Indices de la tabla `preregistro_alertas`
--
ALTER TABLE `preregistro_alertas`
  ADD PRIMARY KEY (`id_alerta`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id_producto`),
  ADD UNIQUE KEY `uq_productos_clave` (`clave`);

--
-- Indices de la tabla `producto_certificacion`
--
ALTER TABLE `producto_certificacion`
  ADD PRIMARY KEY (`id_producto`);

--
-- Indices de la tabla `producto_certificacion_campo`
--
ALTER TABLE `producto_certificacion_campo`
  ADD PRIMARY KEY (`id_producto`,`clave_campo`),
  ADD KEY `idx_pcc_orden` (`id_producto`,`orden`);

--
-- Indices de la tabla `producto_inventario`
--
ALTER TABLE `producto_inventario`
  ADD PRIMARY KEY (`id_inventario`),
  ADD UNIQUE KEY `uq_inv_producto_plantel` (`id_producto`,`id_plantel`),
  ADD KEY `idx_inv_plantel` (`id_plantel`);

--
-- Indices de la tabla `producto_movimientos`
--
ALTER TABLE `producto_movimientos`
  ADD PRIMARY KEY (`id_movimiento`),
  ADD KEY `idx_mov_plantel_estado` (`id_plantel`,`estado`),
  ADD KEY `idx_mov_producto` (`id_producto`);

--
-- Indices de la tabla `profesor_360_ciclo`
--
ALTER TABLE `profesor_360_ciclo`
  ADD PRIMARY KEY (`id_ciclo`),
  ADD UNIQUE KEY `uq_p360_ciclo` (`id_plantel`,`anio`,`mes`);

--
-- Indices de la tabla `profesor_360_eval`
--
ALTER TABLE `profesor_360_eval`
  ADD PRIMARY KEY (`id_eval`),
  ADD KEY `idx_p360_eval_ciclo_prof` (`id_ciclo`,`id_profesor`,`tipo`),
  ADD KEY `idx_p360_eval_evaluador` (`id_evaluador`,`tipo`);

--
-- Indices de la tabla `profesor_360_participante`
--
ALTER TABLE `profesor_360_participante`
  ADD PRIMARY KEY (`id_participante`),
  ADD UNIQUE KEY `uq_p360_part` (`id_ciclo`,`id_profesor`,`id_hay_area`),
  ADD KEY `idx_p360_adj` (`id_adjunto`);

--
-- Indices de la tabla `profesor_asesoria_materia`
--
ALTER TABLE `profesor_asesoria_materia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prof_ase_mat` (`id_usuario`,`id_plantel`,`materia_clave`,`nivel`),
  ADD KEY `idx_prof_ase_esp` (`id_especialidad`,`activo`);

--
-- Indices de la tabla `profesor_eval_periodo`
--
ALTER TABLE `profesor_eval_periodo`
  ADD PRIMARY KEY (`id_eval`),
  ADD UNIQUE KEY `uq_prof_eval_periodo` (`id_usuario`,`id_plantel`,`anio`,`mes`),
  ADD KEY `idx_prof_eval_plantel` (`id_plantel`,`anio`,`mes`);

--
-- Indices de la tabla `profesor_permiso_solicitud`
--
ALTER TABLE `profesor_permiso_solicitud`
  ADD PRIMARY KEY (`id_solicitud`),
  ADD KEY `idx_perm_prof` (`id_usuario`,`estado`);

--
-- Indices de la tabla `promociones_descuento`
--
ALTER TABLE `promociones_descuento`
  ADD PRIMARY KEY (`id_promocion`),
  ADD UNIQUE KEY `uq_promo_clave` (`clave`);

--
-- Indices de la tabla `prospectos_profesor`
--
ALTER TABLE `prospectos_profesor`
  ADD PRIMARY KEY (`id_prospecto`),
  ADD KEY `idx_pp_plantel` (`id_plantel`),
  ADD KEY `idx_pp_estado` (`estado`);

--
-- Indices de la tabla `reglas_colegiatura_combo`
--
ALTER TABLE `reglas_colegiatura_combo`
  ADD PRIMARY KEY (`id_regla`),
  ADD KEY `idx_regla_claves` (`claves_combo`),
  ADD KEY `idx_regla_activo` (`activo`);

--
-- Indices de la tabla `regla_combo_tarifa`
--
ALTER TABLE `regla_combo_tarifa`
  ADD PRIMARY KEY (`id_tarifa`),
  ADD UNIQUE KEY `uq_regla_esp` (`id_regla`,`id_especialidad`),
  ADD KEY `idx_tarifa_regla` (`id_regla`);

--
-- Indices de la tabla `reporte_semanal_movimiento`
--
ALTER TABLE `reporte_semanal_movimiento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rsm_mov` (`id_alumno`,`id_grupo`,`anio`,`semana`,`tipo`),
  ADD KEY `idx_rsm_plantel_sem` (`id_plantel`,`anio`,`semana`),
  ADD KEY `idx_rsm_grupo` (`id_grupo`,`anio`,`semana`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `uq_roles_clave` (`clave`);

--
-- Indices de la tabla `role_planteles`
--
ALTER TABLE `role_planteles`
  ADD PRIMARY KEY (`id_rol`,`id_plantel`),
  ADD KEY `idx_rpl_plantel` (`id_plantel`);

--
-- Indices de la tabla `role_privilegios`
--
ALTER TABLE `role_privilegios`
  ADD PRIMARY KEY (`id_rol`,`privilegio`),
  ADD KEY `idx_rp_priv` (`privilegio`);

--
-- Indices de la tabla `rol_aulas_asignacion`
--
ALTER TABLE `rol_aulas_asignacion`
  ADD PRIMARY KEY (`id_asignacion`),
  ADD UNIQUE KEY `uq_rol_asig_pub_grupo` (`id_publicacion`,`id_grupo`),
  ADD KEY `idx_rol_asig_aula` (`id_aula`),
  ADD KEY `idx_rol_asig_pub` (`id_publicacion`);

--
-- Indices de la tabla `rol_aulas_publicacion`
--
ALTER TABLE `rol_aulas_publicacion`
  ADD PRIMARY KEY (`id_publicacion`),
  ADD UNIQUE KEY `uq_rol_aulas_plantel_periodo` (`id_plantel`,`anio`,`mes`),
  ADD KEY `idx_rol_aulas_estado` (`estado`);

--
-- Indices de la tabla `soporte_reporte`
--
ALTER TABLE `soporte_reporte`
  ADD PRIMARY KEY (`id_reporte`),
  ADD KEY `idx_soporte_usuario` (`id_usuario`),
  ADD KEY `idx_soporte_plantel` (`id_plantel`),
  ADD KEY `idx_soporte_creado` (`creado_en`);

--
-- Indices de la tabla `tutor_conversaciones`
--
ALTER TABLE `tutor_conversaciones`
  ADD PRIMARY KEY (`id_conversacion`),
  ADD KEY `idx_tutor_conv_usuario` (`id_usuario`,`actualizado_en`),
  ADD KEY `idx_tutor_conv_tutor` (`id_tutor`);

--
-- Indices de la tabla `tutor_ia_logs`
--
ALTER TABLE `tutor_ia_logs`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_tutor_log_usuario` (`id_usuario`,`creado_en`),
  ADD KEY `idx_tutor_log_conv` (`id_conversacion`);

--
-- Indices de la tabla `tutor_mensajes`
--
ALTER TABLE `tutor_mensajes`
  ADD PRIMARY KEY (`id_mensaje`),
  ADD KEY `idx_tutor_msg_conv` (`id_conversacion`,`creado_en`);

--
-- Indices de la tabla `tutor_tutores`
--
ALTER TABLE `tutor_tutores`
  ADD PRIMARY KEY (`id_tutor`),
  ADD KEY `idx_tutor_esp` (`especialidad`,`activo`),
  ADD KEY `idx_tutor_activo` (`activo`,`orden`);

--
-- Indices de la tabla `ubicacion_examen`
--
ALTER TABLE `ubicacion_examen`
  ADD PRIMARY KEY (`id_examen`),
  ADD KEY `idx_ubex_esp` (`id_especialidad`),
  ADD KEY `idx_ubex_fase` (`id_fase`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `idx_usuarios_email` (`email`);

--
-- Indices de la tabla `usuario_huellas`
--
ALTER TABLE `usuario_huellas`
  ADD PRIMARY KEY (`id_huella`),
  ADD KEY `idx_uh_usuario` (`id_usuario`),
  ADD KEY `idx_uh_codigo` (`codigo_huella`,`id_plantel`);

--
-- Indices de la tabla `usuario_login_intento`
--
ALTER TABLE `usuario_login_intento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_uli_usuario` (`id_usuario`,`creado_en`),
  ADD KEY `idx_uli_ip` (`ip`,`creado_en`),
  ADD KEY `idx_uli_user_txt` (`username_intento`,`creado_en`);

--
-- Indices de la tabla `usuario_planteles`
--
ALTER TABLE `usuario_planteles`
  ADD PRIMARY KEY (`id_usuario`,`id_plantel`);

--
-- Indices de la tabla `usuario_privilegios`
--
ALTER TABLE `usuario_privilegios`
  ADD PRIMARY KEY (`id_usuario`,`privilegio`);

--
-- Indices de la tabla `usuario_tour`
--
ALTER TABLE `usuario_tour`
  ADD PRIMARY KEY (`id_usuario`,`tour_key`),
  ADD KEY `idx_tour_key` (`tour_key`);

--
-- Indices de la tabla `ventas_movimiento`
--
ALTER TABLE `ventas_movimiento`
  ADD PRIMARY KEY (`id_movimiento`),
  ADD KEY `idx_vm_asesor_fecha` (`id_usuario_asesor`,`creado_en`),
  ADD KEY `idx_vm_plantel_fecha` (`id_plantel`,`creado_en`),
  ADD KEY `idx_vm_tipo` (`tipo`);

--
-- Indices de la tabla `ventas_override`
--
ALTER TABLE `ventas_override`
  ADD PRIMARY KEY (`id_override`),
  ADD KEY `idx_vov_plantel` (`id_plantel`,`fecha_desde`,`fecha_hasta`);

--
-- Indices de la tabla `ventas_regla_especialidad_hist`
--
ALTER TABLE `ventas_regla_especialidad_hist`
  ADD PRIMARY KEY (`id_hist`),
  ADD KEY `idx_vreh_esp` (`id_especialidad`,`creado_en`);

--
-- Indices de la tabla `ventas_tabulador`
--
ALTER TABLE `ventas_tabulador`
  ADD PRIMARY KEY (`id_tabulador`),
  ADD KEY `idx_vtab_plantel` (`id_plantel`,`periodo`,`vigente_desde`);

--
-- Indices de la tabla `ventas_tabulador_tramo`
--
ALTER TABLE `ventas_tabulador_tramo`
  ADD PRIMARY KEY (`id_tramo`),
  ADD KEY `idx_vtt_tab` (`id_tabulador`,`orden`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `academico_libro`
--
ALTER TABLE `academico_libro`
  MODIFY `id_libro` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `academico_libro_version`
--
ALTER TABLE `academico_libro_version`
  MODIFY `id_version` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `academico_material`
--
ALTER TABLE `academico_material`
  MODIFY `id_material` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `academico_material_embedding`
--
ALTER TABLE `academico_material_embedding`
  MODIFY `id_embedding` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `acuerdo_escolar_version`
--
ALTER TABLE `acuerdo_escolar_version`
  MODIFY `id_acuerdo_version` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  MODIFY `id_alumno` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_acuerdo_aceptacion`
--
ALTER TABLE `alumno_acuerdo_aceptacion`
  MODIFY `id_aceptacion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_adeudo_condonacion`
--
ALTER TABLE `alumno_adeudo_condonacion`
  MODIFY `id_condonacion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_aviso`
--
ALTER TABLE `alumno_aviso`
  MODIFY `id_aviso` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_becas`
--
ALTER TABLE `alumno_becas`
  MODIFY `id_beca` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_calificaciones_fase`
--
ALTER TABLE `alumno_calificaciones_fase`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_calificacion_parcial`
--
ALTER TABLE `alumno_calificacion_parcial`
  MODIFY `id_calificacion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_chat_mensaje`
--
ALTER TABLE `alumno_chat_mensaje`
  MODIFY `id_mensaje` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_chat_sala`
--
ALTER TABLE `alumno_chat_sala`
  MODIFY `id_sala` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_documento`
--
ALTER TABLE `alumno_documento`
  MODIFY `id_documento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_documentos`
--
ALTER TABLE `alumno_documentos`
  MODIFY `id_documento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_especialidades`
--
ALTER TABLE `alumno_especialidades`
  MODIFY `id_alumno_especialidad` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_grupos`
--
ALTER TABLE `alumno_grupos`
  MODIFY `id_alumno_grupo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_huellas`
--
ALTER TABLE `alumno_huellas`
  MODIFY `id_huella` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_moodle_curso`
--
ALTER TABLE `alumno_moodle_curso`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_notas`
--
ALTER TABLE `alumno_notas`
  MODIFY `id_nota` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_nota_coordinacion`
--
ALTER TABLE `alumno_nota_coordinacion`
  MODIFY `id_nota` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_pagos`
--
ALTER TABLE `alumno_pagos`
  MODIFY `id_pago` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_pago_movimiento`
--
ALTER TABLE `alumno_pago_movimiento`
  MODIFY `id_mov` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_plan_asignado`
--
ALTER TABLE `alumno_plan_asignado`
  MODIFY `id_asignacion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_tarifa_override_hist`
--
ALTER TABLE `alumno_tarifa_override_hist`
  MODIFY `id_hist` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_ubicacion`
--
ALTER TABLE `alumno_ubicacion`
  MODIFY `id_ubicacion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumno_ubicacion_grupos`
--
ALTER TABLE `alumno_ubicacion_grupos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asesoria_cita`
--
ALTER TABLE `asesoria_cita`
  MODIFY `id_cita` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asesoria_cita_alumno`
--
ALTER TABLE `asesoria_cita_alumno`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asesoria_credito`
--
ALTER TABLE `asesoria_credito`
  MODIFY `id_credito` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asesoria_disp`
--
ALTER TABLE `asesoria_disp`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asesoria_pago_profesor`
--
ALTER TABLE `asesoria_pago_profesor`
  MODIFY `id_pago` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asesoria_tabulador`
--
ALTER TABLE `asesoria_tabulador`
  MODIFY `id_tabulador` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asesor_cartas_periodo`
--
ALTER TABLE `asesor_cartas_periodo`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asesor_entrevistas`
--
ALTER TABLE `asesor_entrevistas`
  MODIFY `id_entrevista` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  MODIFY `id_asistencia` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asistencia_falta_seguimiento`
--
ALTER TABLE `asistencia_falta_seguimiento`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asistencia_personal`
--
ALTER TABLE `asistencia_personal`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `aula_fotos`
--
ALTER TABLE `aula_fotos`
  MODIFY `id_foto` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calendario_dia_lectivo`
--
ALTER TABLE `calendario_dia_lectivo`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calendario_evento_admin`
--
ALTER TABLE `calendario_evento_admin`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calendario_evento_audiencia`
--
ALTER TABLE `calendario_evento_audiencia`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `certificacion_accesos`
--
ALTER TABLE `certificacion_accesos`
  MODIFY `id_acceso` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `certificacion_comision_historial`
--
ALTER TABLE `certificacion_comision_historial`
  MODIFY `id_historial` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `certificacion_documentos`
--
ALTER TABLE `certificacion_documentos`
  MODIFY `id_documento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `certificacion_reagendamientos`
--
ALTER TABLE `certificacion_reagendamientos`
  MODIFY `id_reagendamiento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `certificacion_solicitudes`
--
ALTER TABLE `certificacion_solicitudes`
  MODIFY `id_solicitud` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `corte_caja`
--
ALTER TABLE `corte_caja`
  MODIFY `id_corte` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `curso_personalizado`
--
ALTER TABLE `curso_personalizado`
  MODIFY `id_curso` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `curso_personalizado_pago`
--
ALTER TABLE `curso_personalizado_pago`
  MODIFY `id_pago_prog` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `disc_pat`
--
ALTER TABLE `disc_pat`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `disc_res`
--
ALTER TABLE `disc_res`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `disc_words`
--
ALTER TABLE `disc_words`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `docente_prospecto`
--
ALTER TABLE `docente_prospecto`
  MODIFY `id_prospecto` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `docente_prospecto_evento`
--
ALTER TABLE `docente_prospecto_evento`
  MODIFY `id_evento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `docente_rubrica_area`
--
ALTER TABLE `docente_rubrica_area`
  MODIFY `id_rubrica` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `docente_rubrica_criterio`
--
ALTER TABLE `docente_rubrica_criterio`
  MODIFY `id_criterio` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `docente_showclass_eval`
--
ALTER TABLE `docente_showclass_eval`
  MODIFY `id_eval` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `documento_plantilla`
--
ALTER TABLE `documento_plantilla`
  MODIFY `id_plantilla` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `en_audios`
--
ALTER TABLE `en_audios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `en_gramatica`
--
ALTER TABLE `en_gramatica`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `en_lecturas`
--
ALTER TABLE `en_lecturas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `en_listening`
--
ALTER TABLE `en_listening`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `en_reading`
--
ALTER TABLE `en_reading`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `en_speaking`
--
ALTER TABLE `en_speaking`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `en_vocabulario`
--
ALTER TABLE `en_vocabulario`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `en_writing`
--
ALTER TABLE `en_writing`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `escuelas_externas`
--
ALTER TABLE `escuelas_externas`
  MODIFY `id_escuela` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `escuela_visita`
--
ALTER TABLE `escuela_visita`
  MODIFY `id_visita` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `especialidades`
--
ALTER TABLE `especialidades`
  MODIFY `id_especialidad` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `especialidad_fases`
--
ALTER TABLE `especialidad_fases`
  MODIFY `id_fase` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `especialidad_tarifa_historial`
--
ALTER TABLE `especialidad_tarifa_historial`
  MODIFY `id_hist` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `exam_calificaciones`
--
ALTER TABLE `exam_calificaciones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `exam_fusiones`
--
ALTER TABLE `exam_fusiones`
  MODIFY `id_fusion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `exam_fusion_fases`
--
ALTER TABLE `exam_fusion_fases`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `exam_generado_preguntas`
--
ALTER TABLE `exam_generado_preguntas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `expediente_entrega`
--
ALTER TABLE `expediente_entrega`
  MODIFY `id_entrega` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `expediente_requisito`
--
ALTER TABLE `expediente_requisito`
  MODIFY `id_requisito` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fase_temario_semana`
--
ALTER TABLE `fase_temario_semana`
  MODIFY `id_semana` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `graduacion_alerta`
--
ALTER TABLE `graduacion_alerta`
  MODIFY `id_alerta` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupos`
--
ALTER TABLE `grupos`
  MODIFY `id_grupo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupo_apertura_log`
--
ALTER TABLE `grupo_apertura_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupo_avance_log`
--
ALTER TABLE `grupo_avance_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupo_clave_historial`
--
ALTER TABLE `grupo_clave_historial`
  MODIFY `id_historial` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupo_docente`
--
ALTER TABLE `grupo_docente`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupo_fusion_alumno`
--
ALTER TABLE `grupo_fusion_alumno`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupo_fusion_log`
--
ALTER TABLE `grupo_fusion_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupo_fusion_pendiente_fase`
--
ALTER TABLE `grupo_fusion_pendiente_fase`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupo_fusion_plan`
--
ALTER TABLE `grupo_fusion_plan`
  MODIFY `id_fusion_plan` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupo_horarios`
--
ALTER TABLE `grupo_horarios`
  MODIFY `id_horario` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupo_plan_periodo`
--
ALTER TABLE `grupo_plan_periodo`
  MODIFY `id_plan` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupo_preinicio_contacto`
--
ALTER TABLE `grupo_preinicio_contacto`
  MODIFY `id_contacto` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupo_rubrica_parcial`
--
ALTER TABLE `grupo_rubrica_parcial`
  MODIFY `id_rubrica` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupo_suplencia`
--
ALTER TABLE `grupo_suplencia`
  MODIFY `id_suplencia` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hay_area`
--
ALTER TABLE `hay_area`
  MODIFY `id_area` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hay_aspecto`
--
ALTER TABLE `hay_aspecto`
  MODIFY `id_aspecto` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hay_capacitacion`
--
ALTER TABLE `hay_capacitacion`
  MODIFY `id_capacitacion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hay_capacitacion_cumplimiento`
--
ALTER TABLE `hay_capacitacion_cumplimiento`
  MODIFY `id_cumplimiento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hay_config_version`
--
ALTER TABLE `hay_config_version`
  MODIFY `id_version` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hay_eval_periodo`
--
ALTER TABLE `hay_eval_periodo`
  MODIFY `id_eval` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hay_eval_respuesta`
--
ALTER TABLE `hay_eval_respuesta`
  MODIFY `id_respuesta` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hay_legacy_import_log`
--
ALTER TABLE `hay_legacy_import_log`
  MODIFY `id_log` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hay_nivel_cargo`
--
ALTER TABLE `hay_nivel_cargo`
  MODIFY `id_nivel` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hay_opcion`
--
ALTER TABLE `hay_opcion`
  MODIFY `id_opcion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hay_rubro`
--
ALTER TABLE `hay_rubro`
  MODIFY `id_rubro` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `huella_codigos`
--
ALTER TABLE `huella_codigos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `huella_eventos`
--
ALTER TABLE `huella_eventos`
  MODIFY `id_evento` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inscripcion_autorizacion`
--
ALTER TABLE `inscripcion_autorizacion`
  MODIFY `id_auth` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inscripcion_cartas_campana`
--
ALTER TABLE `inscripcion_cartas_campana`
  MODIFY `id_campana` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inscripcion_cartas_reparto`
--
ALTER TABLE `inscripcion_cartas_reparto`
  MODIFY `id_reparto` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inscripcion_referidos`
--
ALTER TABLE `inscripcion_referidos`
  MODIFY `id_referido` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `marketing_banner`
--
ALTER TABLE `marketing_banner`
  MODIFY `id_banner` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `nomina_ajuste_log`
--
ALTER TABLE `nomina_ajuste_log`
  MODIFY `id_log` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `nomina_linea`
--
ALTER TABLE `nomina_linea`
  MODIFY `id_linea` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `nomina_liquidacion`
--
ALTER TABLE `nomina_liquidacion`
  MODIFY `id_liquidacion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notificacion_panel_oculta`
--
ALTER TABLE `notificacion_panel_oculta`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notificacion_usuario`
--
ALTER TABLE `notificacion_usuario`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal_pago_config`
--
ALTER TABLE `personal_pago_config`
  MODIFY `id_config` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `planeaciones`
--
ALTER TABLE `planeaciones`
  MODIFY `id_planeacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `planeacion_observacion`
--
ALTER TABLE `planeacion_observacion`
  MODIFY `id_obs` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `planteles`
--
ALTER TABLE `planteles`
  MODIFY `id_plantel` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `plantel_aulas`
--
ALTER TABLE `plantel_aulas`
  MODIFY `id_aula` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `plan_estudio_version`
--
ALTER TABLE `plan_estudio_version`
  MODIFY `id_plan_version` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `preregistros`
--
ALTER TABLE `preregistros`
  MODIFY `id_preregistro` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `preregistro_alertas`
--
ALTER TABLE `preregistro_alertas`
  MODIFY `id_alerta` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id_producto` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `producto_inventario`
--
ALTER TABLE `producto_inventario`
  MODIFY `id_inventario` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `producto_movimientos`
--
ALTER TABLE `producto_movimientos`
  MODIFY `id_movimiento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `profesor_360_ciclo`
--
ALTER TABLE `profesor_360_ciclo`
  MODIFY `id_ciclo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `profesor_360_eval`
--
ALTER TABLE `profesor_360_eval`
  MODIFY `id_eval` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `profesor_360_participante`
--
ALTER TABLE `profesor_360_participante`
  MODIFY `id_participante` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `profesor_asesoria_materia`
--
ALTER TABLE `profesor_asesoria_materia`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `profesor_eval_periodo`
--
ALTER TABLE `profesor_eval_periodo`
  MODIFY `id_eval` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `profesor_permiso_solicitud`
--
ALTER TABLE `profesor_permiso_solicitud`
  MODIFY `id_solicitud` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `promociones_descuento`
--
ALTER TABLE `promociones_descuento`
  MODIFY `id_promocion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `prospectos_profesor`
--
ALTER TABLE `prospectos_profesor`
  MODIFY `id_prospecto` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reglas_colegiatura_combo`
--
ALTER TABLE `reglas_colegiatura_combo`
  MODIFY `id_regla` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `regla_combo_tarifa`
--
ALTER TABLE `regla_combo_tarifa`
  MODIFY `id_tarifa` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reporte_semanal_movimiento`
--
ALTER TABLE `reporte_semanal_movimiento`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rol_aulas_asignacion`
--
ALTER TABLE `rol_aulas_asignacion`
  MODIFY `id_asignacion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rol_aulas_publicacion`
--
ALTER TABLE `rol_aulas_publicacion`
  MODIFY `id_publicacion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `soporte_reporte`
--
ALTER TABLE `soporte_reporte`
  MODIFY `id_reporte` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tutor_conversaciones`
--
ALTER TABLE `tutor_conversaciones`
  MODIFY `id_conversacion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tutor_ia_logs`
--
ALTER TABLE `tutor_ia_logs`
  MODIFY `id_log` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tutor_mensajes`
--
ALTER TABLE `tutor_mensajes`
  MODIFY `id_mensaje` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tutor_tutores`
--
ALTER TABLE `tutor_tutores`
  MODIFY `id_tutor` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ubicacion_examen`
--
ALTER TABLE `ubicacion_examen`
  MODIFY `id_examen` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario_huellas`
--
ALTER TABLE `usuario_huellas`
  MODIFY `id_huella` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario_login_intento`
--
ALTER TABLE `usuario_login_intento`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ventas_movimiento`
--
ALTER TABLE `ventas_movimiento`
  MODIFY `id_movimiento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ventas_override`
--
ALTER TABLE `ventas_override`
  MODIFY `id_override` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ventas_regla_especialidad_hist`
--
ALTER TABLE `ventas_regla_especialidad_hist`
  MODIFY `id_hist` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ventas_tabulador`
--
ALTER TABLE `ventas_tabulador`
  MODIFY `id_tabulador` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ventas_tabulador_tramo`
--
ALTER TABLE `ventas_tabulador_tramo`
  MODIFY `id_tramo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD CONSTRAINT `fk_alumnos_grupo` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id_grupo`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `alumno_grupos`
--
ALTER TABLE `alumno_grupos`
  ADD CONSTRAINT `fk_ag_grupo` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id_grupo`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD CONSTRAINT `fk_asist_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_asist_grupo` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id_grupo`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `calendario_evento_audiencia`
--
ALTER TABLE `calendario_evento_audiencia`
  ADD CONSTRAINT `fk_cea_evento` FOREIGN KEY (`id_evento`) REFERENCES `calendario_evento_admin` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `disc_cod`
--
ALTER TABLE `disc_cod`
  ADD CONSTRAINT `fk_disc_cod_pat` FOREIGN KEY (`pat_id`) REFERENCES `disc_pat` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `disc_res`
--
ALTER TABLE `disc_res`
  ADD CONSTRAINT `fk_disc_res_pat` FOREIGN KEY (`pat_id`) REFERENCES `disc_pat` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_10m` FOREIGN KEY (`10-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_10p` FOREIGN KEY (`10+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_11m` FOREIGN KEY (`11-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_11p` FOREIGN KEY (`11+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_12m` FOREIGN KEY (`12-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_12p` FOREIGN KEY (`12+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_13m` FOREIGN KEY (`13-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_13p` FOREIGN KEY (`13+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_14m` FOREIGN KEY (`14-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_14p` FOREIGN KEY (`14+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_15m` FOREIGN KEY (`15-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_15p` FOREIGN KEY (`15+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_16m` FOREIGN KEY (`16-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_16p` FOREIGN KEY (`16+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_17m` FOREIGN KEY (`17-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_17p` FOREIGN KEY (`17+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_18m` FOREIGN KEY (`18-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_18p` FOREIGN KEY (`18+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_19m` FOREIGN KEY (`19-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_19p` FOREIGN KEY (`19+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_1m` FOREIGN KEY (`1-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_1p` FOREIGN KEY (`1+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_20m` FOREIGN KEY (`20-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_20p` FOREIGN KEY (`20+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_21m` FOREIGN KEY (`21-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_21p` FOREIGN KEY (`21+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_22m` FOREIGN KEY (`22-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_22p` FOREIGN KEY (`22+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_23m` FOREIGN KEY (`23-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_23p` FOREIGN KEY (`23+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_24m` FOREIGN KEY (`24-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_24p` FOREIGN KEY (`24+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_25m` FOREIGN KEY (`25-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_25p` FOREIGN KEY (`25+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_26m` FOREIGN KEY (`26-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_26p` FOREIGN KEY (`26+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_27m` FOREIGN KEY (`27-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_27p` FOREIGN KEY (`27+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_28m` FOREIGN KEY (`28-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_28p` FOREIGN KEY (`28+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_2m` FOREIGN KEY (`2-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_2p` FOREIGN KEY (`2+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_3m` FOREIGN KEY (`3-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_3p` FOREIGN KEY (`3+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_4m` FOREIGN KEY (`4-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_4p` FOREIGN KEY (`4+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_5m` FOREIGN KEY (`5-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_5p` FOREIGN KEY (`5+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_6m` FOREIGN KEY (`6-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_6p` FOREIGN KEY (`6+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_7m` FOREIGN KEY (`7-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_7p` FOREIGN KEY (`7+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_8m` FOREIGN KEY (`8-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_8p` FOREIGN KEY (`8+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_9m` FOREIGN KEY (`9-`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_res_9p` FOREIGN KEY (`9+`) REFERENCES `disc_words` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `exam_calificaciones`
--
ALTER TABLE `exam_calificaciones`
  ADD CONSTRAINT `fk_calif_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_calif_examen` FOREIGN KEY (`id_examen`) REFERENCES `exam_generados` (`id_examen`) ON DELETE CASCADE;

--
-- Filtros para la tabla `exam_fusion_fases`
--
ALTER TABLE `exam_fusion_fases`
  ADD CONSTRAINT `fk_ff_fusion` FOREIGN KEY (`id_fusion`) REFERENCES `exam_fusiones` (`id_fusion`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `exam_generado_preguntas`
--
ALTER TABLE `exam_generado_preguntas`
  ADD CONSTRAINT `fk_egp_examen` FOREIGN KEY (`id_examen`) REFERENCES `exam_generados` (`id_examen`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `planeaciones`
--
ALTER TABLE `planeaciones`
  ADD CONSTRAINT `fk_plan_grupo` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id_grupo`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
