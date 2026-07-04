-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 03-07-2026 a las 18:02:21
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
-- Base de datos: `cncmedum_legado`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `abonos`
--

CREATE TABLE `abonos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_sucursal` bigint(20) UNSIGNED DEFAULT NULL,
  `id_pago` bigint(20) UNSIGNED DEFAULT NULL,
  `id_alumno_pago` bigint(20) UNSIGNED DEFAULT NULL,
  `id_documento` bigint(20) UNSIGNED DEFAULT NULL,
  `monto` decimal(16,2) NOT NULL DEFAULT 0.00,
  `venta_fiscal` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `abonos_documentos`
--

CREATE TABLE `abonos_documentos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_sucursal` bigint(20) UNSIGNED DEFAULT NULL,
  `id_pago` bigint(20) UNSIGNED DEFAULT NULL,
  `id_documento` bigint(20) UNSIGNED DEFAULT NULL,
  `monto` decimal(15,2) DEFAULT NULL,
  `venta_fiscal` tinyint(1) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_especialidad` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertas`
--

CREATE TABLE `alertas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `fecha` date NOT NULL,
  `id_usuario_asignado` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_sucursal` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos`
--

CREATE TABLE `alumnos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `numero_control` bigint(20) UNSIGNED DEFAULT NULL,
  `nuevo_numero_control` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `id_sucursal` int(10) UNSIGNED DEFAULT NULL,
  `como_supiste_nosotros` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `nombres` varchar(255) NOT NULL,
  `apellido_paterno` varchar(255) DEFAULT NULL,
  `apellido_materno` varchar(255) DEFAULT NULL,
  `edad` int(11) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `domicilio` text DEFAULT NULL,
  `colonia` varchar(255) DEFAULT NULL,
  `municipio` varchar(255) DEFAULT NULL,
  `telefono` varchar(255) DEFAULT NULL,
  `celular` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `codigo_postal` varchar(255) DEFAULT NULL,
  `ocupacion` varchar(255) DEFAULT NULL,
  `grado_estudios` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`grado_estudios`)),
  `otro_grado_estudios` varchar(255) DEFAULT NULL,
  `tutor` varchar(255) DEFAULT NULL,
  `id_especialidad` bigint(20) UNSIGNED DEFAULT NULL,
  `especialidad` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`especialidad`)),
  `otra_especialidad` varchar(255) DEFAULT NULL,
  `escuela_procedencia` varchar(255) DEFAULT NULL,
  `objetivo_inscripcion` text DEFAULT NULL,
  `enfermedad_cronica` text DEFAULT NULL,
  `solicitud_factura` tinyint(1) DEFAULT NULL,
  `id_asesor_educativo` bigint(20) UNSIGNED DEFAULT NULL,
  `rfc` varchar(255) DEFAULT NULL,
  `cfdi` varchar(255) DEFAULT NULL,
  `curp` varchar(255) DEFAULT NULL,
  `razon_social` varchar(255) DEFAULT NULL,
  `telefono_general` varchar(255) DEFAULT NULL,
  `correo_general` varchar(255) DEFAULT NULL,
  `domicilio_fiscal` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `forma_pago` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `saldo` decimal(12,2) DEFAULT 0.00,
  `huella` blob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos_especialidades`
--

CREATE TABLE `alumnos_especialidades` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_alumno` bigint(20) UNSIGNED NOT NULL,
  `id_especialidad` bigint(20) UNSIGNED NOT NULL,
  `fecha_inicio` date NOT NULL,
  `forma_pago` varchar(255) NOT NULL,
  `monto` decimal(12,2) DEFAULT NULL,
  `monto_pronto_pago` decimal(15,2) DEFAULT NULL,
  `semanas_cursar` int(11) NOT NULL,
  `semanas_cursadas` int(11) DEFAULT NULL,
  `semanas_pagadas` int(11) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Activo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos_grupos`
--

CREATE TABLE `alumnos_grupos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_alumno` bigint(20) UNSIGNED NOT NULL,
  `id_grupo` bigint(20) UNSIGNED NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_final` date DEFAULT NULL,
  `status` varchar(255) DEFAULT 'Inscrito'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos_pagos`
--

CREATE TABLE `alumnos_pagos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_alumno` bigint(20) UNSIGNED DEFAULT NULL,
  `id_grupo` bigint(20) UNSIGNED DEFAULT NULL,
  `concepto` varchar(255) DEFAULT NULL,
  `monto` decimal(12,2) DEFAULT NULL,
  `monto_apoyo_inscripcion` decimal(12,2) DEFAULT NULL,
  `saldo` decimal(16,2) NOT NULL DEFAULT 0.00,
  `fecha_limite` date DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `tipo` varchar(255) DEFAULT NULL,
  `mes` smallint(6) DEFAULT NULL,
  `semana` smallint(6) DEFAULT NULL,
  `anio` smallint(6) DEFAULT NULL,
  `modalidad` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `apoyos_especiales`
--

CREATE TABLE `apoyos_especiales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_alumno` bigint(20) UNSIGNED DEFAULT NULL,
  `id_especialidad` bigint(20) UNSIGNED DEFAULT NULL,
  `id_grupo` bigint(20) UNSIGNED DEFAULT NULL,
  `id_sucursal` bigint(20) UNSIGNED DEFAULT NULL,
  `precio` decimal(16,2) NOT NULL DEFAULT 0.00,
  `tipo` varchar(255) DEFAULT 'Manual',
  `fecha_inicio` date DEFAULT NULL,
  `fecha_final` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_descuento` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `apoyos_inscripciones`
--

CREATE TABLE `apoyos_inscripciones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_alumno` bigint(20) UNSIGNED NOT NULL,
  `id_especialidad` bigint(20) UNSIGNED DEFAULT NULL,
  `id_grupo` bigint(20) UNSIGNED DEFAULT NULL,
  `apoyo` decimal(8,2) NOT NULL,
  `id_usuario` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_usuario_autoriza` bigint(20) UNSIGNED DEFAULT NULL,
  `motivo` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asesorias`
--

CREATE TABLE `asesorias` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_sucursal` bigint(20) UNSIGNED DEFAULT NULL,
  `id_profesor` bigint(20) UNSIGNED DEFAULT NULL,
  `id_alumno` bigint(20) UNSIGNED DEFAULT NULL,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_final` datetime DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `status` enum('Espera de confirmacion','Agendados','Rechazados','Cancelados') NOT NULL DEFAULT 'Espera de confirmacion',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencias`
--

CREATE TABLE `asistencias` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_alumno` bigint(20) UNSIGNED DEFAULT NULL,
  `fecha` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_usuario` bigint(20) UNSIGNED DEFAULT NULL,
  `id_grupo` bigint(20) UNSIGNED DEFAULT NULL,
  `id_especialidad` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuraciones`
--

CREATE TABLE `configuraciones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuentas_bancarias`
--

CREATE TABLE `cuentas_bancarias` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `banco` varchar(255) DEFAULT NULL,
  `no_cuenta` varchar(255) DEFAULT NULL,
  `id_sucursal` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `descuentos`
--

CREATE TABLE `descuentos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_especialidad_1` bigint(20) UNSIGNED NOT NULL,
  `monto_1` decimal(14,2) DEFAULT NULL,
  `forma_pago_1` varchar(255) DEFAULT NULL,
  `id_especialidad_2` bigint(20) UNSIGNED NOT NULL,
  `monto_2` decimal(14,2) DEFAULT NULL,
  `forma_pago_2` varchar(255) DEFAULT NULL,
  `id_usuario` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos`
--

CREATE TABLE `documentos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_alumno` bigint(20) UNSIGNED DEFAULT NULL,
  `id_especialidad` bigint(20) UNSIGNED DEFAULT NULL,
  `id_grupo` bigint(20) UNSIGNED DEFAULT NULL,
  `concepto` varchar(255) DEFAULT NULL,
  `monto` decimal(15,2) DEFAULT NULL,
  `pronto_pago` decimal(12,2) DEFAULT NULL,
  `normal_pago` decimal(12,2) DEFAULT NULL,
  `fecha_limite_pronto_pago` date DEFAULT NULL,
  `monto_apoyo_inscripcion` decimal(15,2) DEFAULT NULL,
  `saldo` decimal(15,2) DEFAULT NULL,
  `fecha_limite` date DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `tipo` varchar(255) DEFAULT NULL,
  `mes` int(11) DEFAULT NULL,
  `semana` int(11) DEFAULT NULL,
  `anio` int(11) DEFAULT NULL,
  `modalidad` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `especial` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especialidades`
--

CREATE TABLE `especialidades` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_sucursal` bigint(20) UNSIGNED DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `precio_inscripcion` decimal(16,2) DEFAULT NULL,
  `precio_mensualidad` decimal(16,2) DEFAULT NULL,
  `precio_mensualidad_pronto_pago` decimal(16,2) DEFAULT NULL,
  `precio_semanal` decimal(16,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `formas_pago` varchar(255) DEFAULT '["mensual","semanal"]'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especialidades_users`
--

CREATE TABLE `especialidades_users` (
  `id_especialidad` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupos`
--

CREATE TABLE `grupos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_sucursal` int(10) UNSIGNED DEFAULT NULL,
  `id_especialidad` bigint(20) UNSIGNED DEFAULT NULL,
  `horario` varchar(255) NOT NULL,
  `dias` varchar(255) DEFAULT NULL,
  `infantil` tinyint(1) NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `precio_inscripcion` decimal(16,2) DEFAULT NULL,
  `precio_mensualidad` decimal(16,2) DEFAULT NULL,
  `precio_mensualidad_pronto_pago` decimal(16,2) DEFAULT NULL,
  `precio_semanal` decimal(16,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Activo',
  `clave` varchar(255) DEFAULT NULL,
  `max_alumnos` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupos_deserciones`
--

CREATE TABLE `grupos_deserciones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `semana` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `id_grupo` bigint(20) UNSIGNED NOT NULL,
  `anterior` int(11) DEFAULT NULL,
  `inicios` int(11) DEFAULT NULL,
  `reingresos` int(11) DEFAULT NULL,
  `cambios_horarios_altas` int(11) DEFAULT NULL,
  `bajas` int(11) DEFAULT NULL,
  `cambios_horarios_bajas` int(11) DEFAULT NULL,
  `fin_curso` int(11) DEFAULT NULL,
  `total_final` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupos_dias`
--

CREATE TABLE `grupos_dias` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_grupo` bigint(20) UNSIGNED NOT NULL,
  `dayofweek` int(11) DEFAULT NULL,
  `dia` varchar(255) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_final` time NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupos_materias`
--

CREATE TABLE `grupos_materias` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_materia` bigint(20) UNSIGNED DEFAULT NULL,
  `id_grupo` bigint(20) UNSIGNED DEFAULT NULL,
  `id_profesor` bigint(20) UNSIGNED DEFAULT NULL,
  `horas_semana` decimal(8,2) DEFAULT NULL,
  `orden` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios_profesores`
--

CREATE TABLE `horarios_profesores` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_sucursal` bigint(20) UNSIGNED DEFAULT NULL,
  `id_profesor` bigint(20) UNSIGNED DEFAULT NULL,
  `dia` varchar(255) DEFAULT NULL,
  `hora_inicio` smallint(6) NOT NULL DEFAULT 0,
  `hora_final` smallint(6) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripciones`
--

CREATE TABLE `inscripciones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_alumno` bigint(20) UNSIGNED NOT NULL,
  `id_grupo` bigint(20) UNSIGNED NOT NULL,
  `id_especialidad` bigint(20) UNSIGNED DEFAULT NULL,
  `id_sucursal` bigint(20) UNSIGNED NOT NULL,
  `id_asesor` bigint(20) UNSIGNED DEFAULT NULL,
  `fecha` datetime NOT NULL,
  `fecha_inicio_grupo` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripciones_recomendaciones`
--

CREATE TABLE `inscripciones_recomendaciones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `id_alumno_recomendado` bigint(20) UNSIGNED NOT NULL,
  `id_alumno_recomendo` bigint(20) UNSIGNED NOT NULL,
  `id_especialidad` bigint(20) UNSIGNED NOT NULL,
  `id_documento_aplicado` bigint(20) UNSIGNED DEFAULT NULL,
  `id_autorizo` bigint(20) UNSIGNED NOT NULL,
  `id_sucursal` bigint(20) UNSIGNED NOT NULL,
  `monto` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias`
--

CREATE TABLE `materias` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_sucursal` int(10) UNSIGNED DEFAULT NULL,
  `id_especialidad` bigint(20) UNSIGNED DEFAULT NULL,
  `especialidad` varchar(255) DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `fase` varchar(255) NOT NULL,
  `orden` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `semanas` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notas`
--

CREATE TABLE `notas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `fecha` datetime NOT NULL,
  `nota` text NOT NULL,
  `id_alumno` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `folio` bigint(20) UNSIGNED DEFAULT NULL,
  `folio_fiscal` int(11) DEFAULT NULL,
  `id_sucursal` bigint(20) UNSIGNED DEFAULT NULL,
  `id_alumno` bigint(20) UNSIGNED DEFAULT NULL,
  `id_especialidad` bigint(20) UNSIGNED DEFAULT NULL,
  `monto` decimal(16,2) NOT NULL DEFAULT 0.00,
  `forma_pago` varchar(255) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `id_recibio` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `id_usuario_elimino` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `partidas_compras`
--

CREATE TABLE `partidas_compras` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_producto` bigint(20) UNSIGNED DEFAULT NULL,
  `cantidad` decimal(8,2) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Emitida',
  `fecha` date DEFAULT NULL,
  `id_usuario` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `partidas_ventas`
--

CREATE TABLE `partidas_ventas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_venta` bigint(20) UNSIGNED NOT NULL,
  `id_producto` bigint(20) UNSIGNED NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `precio` decimal(12,2) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_resets`
--

CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `precios`
--

CREATE TABLE `precios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tipo` varchar(255) NOT NULL,
  `id_grupo` bigint(20) UNSIGNED DEFAULT NULL,
  `id_especialidad` bigint(20) UNSIGNED DEFAULT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_final` datetime DEFAULT NULL,
  `precio_pronto_pago` decimal(15,2) DEFAULT NULL,
  `precio_normal` decimal(15,2) NOT NULL,
  `id_usuario` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_sucursal` bigint(20) UNSIGNED DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `clave_sat` varchar(255) DEFAULT NULL,
  `clave_unidad_sat` varchar(255) NOT NULL,
  `precio` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `guard_name` varchar(255) NOT NULL,
  `id_sucursal` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sucursales`
--

CREATE TABLE `sucursales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `direccion` text NOT NULL,
  `telefono` varchar(255) DEFAULT NULL,
  `rfc` varchar(255) DEFAULT NULL,
  `municipio` varchar(255) NOT NULL,
  `estado` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sucursales_usuarios`
--

CREATE TABLE `sucursales_usuarios` (
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_sucursal` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nombres` varchar(255) NOT NULL,
  `apellido_paterno` varchar(255) NOT NULL,
  `apellido_materno` varchar(255) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `celular` varchar(255) DEFAULT NULL,
  `telefono` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `huella` blob DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `id_ultima_sucursal` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_dias`
--

CREATE TABLE `usuarios_dias` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` bigint(20) UNSIGNED NOT NULL,
  `dayofweek` int(11) NOT NULL,
  `dia` varchar(255) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_final` time NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `folio` int(11) NOT NULL,
  `id_alumno` bigint(20) UNSIGNED DEFAULT NULL,
  `id_sucursal` bigint(20) UNSIGNED NOT NULL,
  `fecha` date DEFAULT NULL,
  `total` decimal(12,2) DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `forma_pago` varchar(255) DEFAULT NULL,
  `id_recibio` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `nombre` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `abonos`
--
ALTER TABLE `abonos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `abonos_id_pago_foreign` (`id_pago`),
  ADD KEY `abonos_id_sucursal_foreign` (`id_sucursal`),
  ADD KEY `abonos_id_alumno_pago_foreign` (`id_alumno_pago`),
  ADD KEY `abonos_id_documento_foreign` (`id_documento`);

--
-- Indices de la tabla `abonos_documentos`
--
ALTER TABLE `abonos_documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `abonos_documentos_id_sucursal_foreign` (`id_sucursal`),
  ADD KEY `abonos_documentos_id_pago_foreign` (`id_pago`),
  ADD KEY `abonos_documentos_id_documento_foreign` (`id_documento`),
  ADD KEY `abonos_documentos_id_especialidad_foreign` (`id_especialidad`);

--
-- Indices de la tabla `alertas`
--
ALTER TABLE `alertas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alertas_id_usuario_asignado_foreign` (`id_usuario_asignado`),
  ADD KEY `alertas_id_sucursal_foreign` (`id_sucursal`);

--
-- Indices de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `alumnos_especialidades`
--
ALTER TABLE `alumnos_especialidades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alumnos_especialidades_id_alumno_foreign` (`id_alumno`),
  ADD KEY `alumnos_especialidades_id_especialidad_foreign` (`id_especialidad`);

--
-- Indices de la tabla `alumnos_grupos`
--
ALTER TABLE `alumnos_grupos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alumnos_grupos_id_alumno_foreign` (`id_alumno`),
  ADD KEY `alumnos_grupos_id_grupo_foreign` (`id_grupo`);

--
-- Indices de la tabla `alumnos_pagos`
--
ALTER TABLE `alumnos_pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alumnos_pagos_id_alumno_foreign` (`id_alumno`),
  ADD KEY `alumnos_pagos_id_grupo_foreign` (`id_grupo`);

--
-- Indices de la tabla `apoyos_especiales`
--
ALTER TABLE `apoyos_especiales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `apoyos_especiales_id_alumno_foreign` (`id_alumno`),
  ADD KEY `apoyos_especiales_id_grupo_foreign` (`id_grupo`),
  ADD KEY `apoyos_especiales_id_sucursal_foreign` (`id_sucursal`),
  ADD KEY `apoyos_especiales_id_especialidad_foreign` (`id_especialidad`),
  ADD KEY `apoyos_especiales_id_descuento_foreign` (`id_descuento`);

--
-- Indices de la tabla `apoyos_inscripciones`
--
ALTER TABLE `apoyos_inscripciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `apoyos_inscripciones_id_alumno_foreign` (`id_alumno`),
  ADD KEY `apoyos_inscripciones_id_grupo_foreign` (`id_grupo`),
  ADD KEY `apoyos_inscripciones_id_usuario_foreign` (`id_usuario`),
  ADD KEY `apoyos_inscripciones_id_usuario_autoriza_foreign` (`id_usuario_autoriza`),
  ADD KEY `apoyos_inscripciones_id_especialidad_foreign` (`id_especialidad`);

--
-- Indices de la tabla `asesorias`
--
ALTER TABLE `asesorias`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asistencias_id_alumno_foreign` (`id_alumno`),
  ADD KEY `asistencias_id_usuario_foreign` (`id_usuario`),
  ADD KEY `asistencias_id_grupo_foreign` (`id_grupo`),
  ADD KEY `asistencias_id_especialidad_foreign` (`id_especialidad`);

--
-- Indices de la tabla `configuraciones`
--
ALTER TABLE `configuraciones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `cuentas_bancarias`
--
ALTER TABLE `cuentas_bancarias`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `descuentos`
--
ALTER TABLE `descuentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `descuentos_id_especialidad_1_foreign` (`id_especialidad_1`),
  ADD KEY `descuentos_id_especialidad_2_foreign` (`id_especialidad_2`),
  ADD KEY `descuentos_id_usuario_foreign` (`id_usuario`);

--
-- Indices de la tabla `documentos`
--
ALTER TABLE `documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `documentos_id_alumno_foreign` (`id_alumno`),
  ADD KEY `documentos_id_grupo_foreign` (`id_grupo`),
  ADD KEY `documentos_id_especialidad_foreign` (`id_especialidad`);

--
-- Indices de la tabla `especialidades`
--
ALTER TABLE `especialidades`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `especialidades_users`
--
ALTER TABLE `especialidades_users`
  ADD PRIMARY KEY (`id_especialidad`,`id_usuario`),
  ADD KEY `especialidades_users_id_usuario_foreign` (`id_usuario`);

--
-- Indices de la tabla `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indices de la tabla `grupos`
--
ALTER TABLE `grupos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `grupos_deserciones`
--
ALTER TABLE `grupos_deserciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grupos_deserciones_id_grupo_foreign` (`id_grupo`);

--
-- Indices de la tabla `grupos_dias`
--
ALTER TABLE `grupos_dias`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `grupos_materias`
--
ALTER TABLE `grupos_materias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grupos_materias_id_profesor_foreign` (`id_profesor`),
  ADD KEY `grupos_materias_id_grupo_foreign` (`id_grupo`),
  ADD KEY `grupos_materias_id_materia_foreign` (`id_materia`),
  ADD KEY `grupos_materias_orden_index` (`orden`);

--
-- Indices de la tabla `horarios_profesores`
--
ALTER TABLE `horarios_profesores`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inscripciones_id_alumno_foreign` (`id_alumno`),
  ADD KEY `inscripciones_id_grupo_foreign` (`id_grupo`),
  ADD KEY `inscripciones_id_sucursal_foreign` (`id_sucursal`),
  ADD KEY `inscripciones_id_asesor_foreign` (`id_asesor`),
  ADD KEY `inscripciones_id_especialidad_foreign` (`id_especialidad`);

--
-- Indices de la tabla `inscripciones_recomendaciones`
--
ALTER TABLE `inscripciones_recomendaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inscripciones_recomendaciones_id_alumno_recomendado_foreign` (`id_alumno_recomendado`),
  ADD KEY `inscripciones_recomendaciones_id_alumno_recomendo_foreign` (`id_alumno_recomendo`),
  ADD KEY `inscripciones_recomendaciones_id_especialidad_foreign` (`id_especialidad`),
  ADD KEY `inscripciones_recomendaciones_id_documento_aplicado_foreign` (`id_documento_aplicado`),
  ADD KEY `inscripciones_recomendaciones_id_autorizo_foreign` (`id_autorizo`),
  ADD KEY `inscripciones_recomendaciones_id_sucursal_foreign` (`id_sucursal`);

--
-- Indices de la tabla `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indices de la tabla `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indices de la tabla `notas`
--
ALTER TABLE `notas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notas_id_alumno_foreign` (`id_alumno`),
  ADD KEY `notas_id_usuario_foreign` (`id_usuario`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pagos_id_alumno_foreign` (`id_alumno`),
  ADD KEY `pagos_id_sucursal_foreign` (`id_sucursal`),
  ADD KEY `pagos_id_recibio_foreign` (`id_recibio`),
  ADD KEY `pagos_id_usuario_elimino_foreign` (`id_usuario_elimino`),
  ADD KEY `pagos_id_especialidad_foreign` (`id_especialidad`);

--
-- Indices de la tabla `partidas_compras`
--
ALTER TABLE `partidas_compras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `partidas_compras_id_producto_foreign` (`id_producto`),
  ADD KEY `partidas_compras_id_usuario_foreign` (`id_usuario`);

--
-- Indices de la tabla `partidas_ventas`
--
ALTER TABLE `partidas_ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `partidas_ventas_id_venta_foreign` (`id_venta`),
  ADD KEY `partidas_ventas_id_producto_foreign` (`id_producto`);

--
-- Indices de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD KEY `password_resets_email_index` (`email`);

--
-- Indices de la tabla `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indices de la tabla `precios`
--
ALTER TABLE `precios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `precios_id_usuario_foreign` (`id_usuario`),
  ADD KEY `precios_id_grupo_foreign` (`id_grupo`),
  ADD KEY `precios_id_especialidad_foreign` (`id_especialidad`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `productos_id_sucursal_foreign` (`id_sucursal`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indices de la tabla `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indices de la tabla `sucursales`
--
ALTER TABLE `sucursales`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `sucursales_usuarios`
--
ALTER TABLE `sucursales_usuarios`
  ADD PRIMARY KEY (`id_usuario`,`id_sucursal`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Indices de la tabla `usuarios_dias`
--
ALTER TABLE `usuarios_dias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuarios_dias_id_usuario_foreign` (`id_usuario`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ventas_id_alumno_foreign` (`id_alumno`),
  ADD KEY `ventas_id_sucursal_foreign` (`id_sucursal`),
  ADD KEY `ventas_id_recibio_foreign` (`id_recibio`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `abonos`
--
ALTER TABLE `abonos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `abonos_documentos`
--
ALTER TABLE `abonos_documentos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alertas`
--
ALTER TABLE `alertas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumnos_especialidades`
--
ALTER TABLE `alumnos_especialidades`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumnos_grupos`
--
ALTER TABLE `alumnos_grupos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alumnos_pagos`
--
ALTER TABLE `alumnos_pagos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `apoyos_especiales`
--
ALTER TABLE `apoyos_especiales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `apoyos_inscripciones`
--
ALTER TABLE `apoyos_inscripciones`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asesorias`
--
ALTER TABLE `asesorias`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `configuraciones`
--
ALTER TABLE `configuraciones`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cuentas_bancarias`
--
ALTER TABLE `cuentas_bancarias`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `descuentos`
--
ALTER TABLE `descuentos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `documentos`
--
ALTER TABLE `documentos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `especialidades`
--
ALTER TABLE `especialidades`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupos`
--
ALTER TABLE `grupos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupos_deserciones`
--
ALTER TABLE `grupos_deserciones`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupos_dias`
--
ALTER TABLE `grupos_dias`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupos_materias`
--
ALTER TABLE `grupos_materias`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `horarios_profesores`
--
ALTER TABLE `horarios_profesores`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inscripciones_recomendaciones`
--
ALTER TABLE `inscripciones_recomendaciones`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notas`
--
ALTER TABLE `notas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `partidas_compras`
--
ALTER TABLE `partidas_compras`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `partidas_ventas`
--
ALTER TABLE `partidas_ventas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `precios`
--
ALTER TABLE `precios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sucursales`
--
ALTER TABLE `sucursales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios_dias`
--
ALTER TABLE `usuarios_dias`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `abonos`
--
ALTER TABLE `abonos`
  ADD CONSTRAINT `abonos_id_alumno_pago_foreign` FOREIGN KEY (`id_alumno_pago`) REFERENCES `alumnos_pagos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `abonos_id_documento_foreign` FOREIGN KEY (`id_documento`) REFERENCES `documentos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `abonos_id_pago_foreign` FOREIGN KEY (`id_pago`) REFERENCES `pagos` (`id`),
  ADD CONSTRAINT `abonos_id_sucursal_foreign` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`);

--
-- Filtros para la tabla `abonos_documentos`
--
ALTER TABLE `abonos_documentos`
  ADD CONSTRAINT `abonos_documentos_id_documento_foreign` FOREIGN KEY (`id_documento`) REFERENCES `documentos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `abonos_documentos_id_especialidad_foreign` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `abonos_documentos_id_pago_foreign` FOREIGN KEY (`id_pago`) REFERENCES `pagos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `abonos_documentos_id_sucursal_foreign` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `alertas`
--
ALTER TABLE `alertas`
  ADD CONSTRAINT `alertas_id_sucursal_foreign` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `alertas_id_usuario_asignado_foreign` FOREIGN KEY (`id_usuario_asignado`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `alumnos_especialidades`
--
ALTER TABLE `alumnos_especialidades`
  ADD CONSTRAINT `alumnos_especialidades_id_alumno_foreign` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `alumnos_especialidades_id_especialidad_foreign` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `alumnos_grupos`
--
ALTER TABLE `alumnos_grupos`
  ADD CONSTRAINT `alumnos_grupos_id_alumno_foreign` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id`),
  ADD CONSTRAINT `alumnos_grupos_id_grupo_foreign` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `alumnos_pagos`
--
ALTER TABLE `alumnos_pagos`
  ADD CONSTRAINT `alumnos_pagos_id_alumno_foreign` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id`),
  ADD CONSTRAINT `alumnos_pagos_id_grupo_foreign` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `apoyos_especiales`
--
ALTER TABLE `apoyos_especiales`
  ADD CONSTRAINT `apoyos_especiales_id_alumno_foreign` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `apoyos_especiales_id_descuento_foreign` FOREIGN KEY (`id_descuento`) REFERENCES `descuentos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `apoyos_especiales_id_especialidad_foreign` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `apoyos_especiales_id_grupo_foreign` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `apoyos_especiales_id_sucursal_foreign` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `apoyos_inscripciones`
--
ALTER TABLE `apoyos_inscripciones`
  ADD CONSTRAINT `apoyos_inscripciones_id_alumno_foreign` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `apoyos_inscripciones_id_especialidad_foreign` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `apoyos_inscripciones_id_grupo_foreign` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `apoyos_inscripciones_id_usuario_autoriza_foreign` FOREIGN KEY (`id_usuario_autoriza`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `apoyos_inscripciones_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD CONSTRAINT `asistencias_id_alumno_foreign` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `asistencias_id_especialidad_foreign` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `asistencias_id_grupo_foreign` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `asistencias_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `descuentos`
--
ALTER TABLE `descuentos`
  ADD CONSTRAINT `descuentos_id_especialidad_1_foreign` FOREIGN KEY (`id_especialidad_1`) REFERENCES `especialidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `descuentos_id_especialidad_2_foreign` FOREIGN KEY (`id_especialidad_2`) REFERENCES `especialidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `descuentos_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `documentos`
--
ALTER TABLE `documentos`
  ADD CONSTRAINT `documentos_id_alumno_foreign` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `documentos_id_especialidad_foreign` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documentos_id_grupo_foreign` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `especialidades_users`
--
ALTER TABLE `especialidades_users`
  ADD CONSTRAINT `especialidades_users_id_especialidad_foreign` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `especialidades_users_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `grupos_deserciones`
--
ALTER TABLE `grupos_deserciones`
  ADD CONSTRAINT `grupos_deserciones_id_grupo_foreign` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `grupos_materias`
--
ALTER TABLE `grupos_materias`
  ADD CONSTRAINT `grupos_materias_id_grupo_foreign` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `grupos_materias_id_materia_foreign` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `grupos_materias_id_profesor_foreign` FOREIGN KEY (`id_profesor`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD CONSTRAINT `inscripciones_id_alumno_foreign` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inscripciones_id_asesor_foreign` FOREIGN KEY (`id_asesor`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inscripciones_id_especialidad_foreign` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inscripciones_id_grupo_foreign` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inscripciones_id_sucursal_foreign` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `inscripciones_recomendaciones`
--
ALTER TABLE `inscripciones_recomendaciones`
  ADD CONSTRAINT `inscripciones_recomendaciones_id_alumno_recomendado_foreign` FOREIGN KEY (`id_alumno_recomendado`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inscripciones_recomendaciones_id_alumno_recomendo_foreign` FOREIGN KEY (`id_alumno_recomendo`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inscripciones_recomendaciones_id_autorizo_foreign` FOREIGN KEY (`id_autorizo`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inscripciones_recomendaciones_id_documento_aplicado_foreign` FOREIGN KEY (`id_documento_aplicado`) REFERENCES `documentos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inscripciones_recomendaciones_id_especialidad_foreign` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inscripciones_recomendaciones_id_sucursal_foreign` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notas`
--
ALTER TABLE `notas`
  ADD CONSTRAINT `notas_id_alumno_foreign` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `notas_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `pagos_id_alumno_foreign` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id`),
  ADD CONSTRAINT `pagos_id_especialidad_foreign` FOREIGN KEY (`id_especialidad`) REFERENCES `pagos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `pagos_id_recibio_foreign` FOREIGN KEY (`id_recibio`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `pagos_id_sucursal_foreign` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`),
  ADD CONSTRAINT `pagos_id_usuario_elimino_foreign` FOREIGN KEY (`id_usuario_elimino`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `partidas_compras`
--
ALTER TABLE `partidas_compras`
  ADD CONSTRAINT `partidas_compras_id_producto_foreign` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `partidas_compras_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `partidas_ventas`
--
ALTER TABLE `partidas_ventas`
  ADD CONSTRAINT `partidas_ventas_id_producto_foreign` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `partidas_ventas_id_venta_foreign` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `precios`
--
ALTER TABLE `precios`
  ADD CONSTRAINT `precios_id_especialidad_foreign` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `precios_id_grupo_foreign` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `precios_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_id_sucursal_foreign` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`);

--
-- Filtros para la tabla `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios_dias`
--
ALTER TABLE `usuarios_dias`
  ADD CONSTRAINT `usuarios_dias_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `ventas_id_alumno_foreign` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ventas_id_recibio_foreign` FOREIGN KEY (`id_recibio`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ventas_id_sucursal_foreign` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
