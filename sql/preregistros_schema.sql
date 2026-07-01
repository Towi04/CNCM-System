SET NAMES utf8mb4;

-- Columna en especialidades: inscripción abierta / fecha prevista
ALTER TABLE especialidades
  ADD COLUMN IF NOT EXISTS inscripcion_abierta TINYINT(1) NOT NULL DEFAULT 1 AFTER visible,
  ADD COLUMN IF NOT EXISTS fecha_apertura_prevista DATE NULL AFTER inscripcion_abierta;

-- (En hosting sin IF NOT EXISTS, usar plantel_ensure_column desde PHP o ejecutar manualmente una vez)

CREATE TABLE IF NOT EXISTS preregistros (
    id_preregistro INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_plantel INT UNSIGNED NOT NULL,
    id_usuario_registro INT UNSIGNED NOT NULL,
    id_especialidad INT UNSIGNED NULL,
    estado ENUM('activo','pendiente','perdido','inscrito') NOT NULL DEFAULT 'activo',
    categoria_perdido VARCHAR(40) NULL,
    motivo_perdido TEXT NULL,
    foto VARCHAR(255) NULL,
    nombres VARCHAR(120) NOT NULL,
    apellido_paterno VARCHAR(80) NOT NULL,
    apellido_materno VARCHAR(80) NULL,
    fecha_nacimiento DATE NULL,
    edad TINYINT UNSIGNED NULL,
    medio_entero ENUM('redes_sociales','publicidad','cartas','pasando','recomendado','otro') NOT NULL DEFAULT 'otro',
    medio_entero_otro VARCHAR(120) NULL,
    domicilio VARCHAR(200) NULL,
    colonia VARCHAR(120) NULL,
    municipio VARCHAR(120) NULL,
    telefono VARCHAR(30) NULL,
    telefono2 VARCHAR(30) NULL,
    email VARCHAR(160) NULL,
    codigo_postal VARCHAR(10) NULL,
    ocupacion VARCHAR(120) NULL,
    grado_estudios ENUM('primaria','secundaria','preparatoria','universidad','otros') NULL,
    padre_tutor VARCHAR(160) NULL,
    objetivo_inscripcion TEXT NULL,
    enfermedad_cronica TINYINT(1) NOT NULL DEFAULT 0,
    enfermedad_detalle VARCHAR(200) NULL,
    observaciones TEXT NULL,
    tiene_apartado TINYINT(1) NOT NULL DEFAULT 0,
    monto_apartado DECIMAL(12,2) NULL,
    requiere_factura TINYINT(1) NOT NULL DEFAULT 0,
    factura_rfc VARCHAR(20) NULL,
    factura_curp VARCHAR(22) NULL,
    factura_telefono VARCHAR(30) NULL,
    factura_razon_social VARCHAR(200) NULL,
    factura_correo VARCHAR(160) NULL,
    factura_domicilio_fiscal VARCHAR(255) NULL,
    factura_constancia_path VARCHAR(255) NULL,
    factura_datos_pendientes TINYINT(1) NOT NULL DEFAULT 0,
    espera_apertura_curso TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    fecha_estado DATETIME NULL,
    PRIMARY KEY (id_preregistro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preregistro_alertas (
    id_alerta INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_preregistro INT UNSIGNED NOT NULL,
    id_plantel INT UNSIGNED NOT NULL,
    tipo ENUM('curso_no_abierto','curso_abierto_seguimiento','factura_incompleta','general') NOT NULL,
    mensaje VARCHAR(500) NOT NULL,
    leida TINYINT(1) NOT NULL DEFAULT 0,
    resuelta TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_alerta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
