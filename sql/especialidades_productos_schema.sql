-- Catálogo: especialidades (cursos) y productos con inventario por plantel
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS especialidades (
    id_especialidad INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clave VARCHAR(30) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion TEXT NULL,
    costo_inscripcion DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    costo_mensualidad DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    costo_pronto_pago DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    costo_semanal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    duracion_meses SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    duracion_semanas SMALLINT UNSIGNED NULL,
    es_fija TINYINT(1) NOT NULL DEFAULT 0,
    visible TINYINT(1) NOT NULL DEFAULT 1,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_especialidad),
    UNIQUE KEY uq_especialidades_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS productos (
    id_producto INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clave VARCHAR(40) NOT NULL,
    nombre VARCHAR(160) NOT NULL,
    descripcion TEXT NULL,
    precio DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    clave_sat VARCHAR(20) NOT NULL DEFAULT '01010101',
    unidad_sat VARCHAR(10) NOT NULL DEFAULT 'H87',
    gratis_profesor TINYINT(1) NOT NULL DEFAULT 0,
    visible TINYINT(1) NOT NULL DEFAULT 1,
    descontinuado TINYINT(1) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    stock_minimo INT UNSIGNED NOT NULL DEFAULT 5,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_producto),
    UNIQUE KEY uq_productos_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS producto_inventario (
    id_inventario INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_producto INT UNSIGNED NOT NULL,
    id_plantel INT UNSIGNED NOT NULL,
    existencia INT NOT NULL DEFAULT 0,
    stock_minimo INT UNSIGNED NULL,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_inventario),
    UNIQUE KEY uq_inv_producto_plantel (id_producto, id_plantel),
    KEY idx_inv_plantel (id_plantel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS producto_movimientos (
    id_movimiento INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_producto INT UNSIGNED NOT NULL,
    id_plantel INT UNSIGNED NOT NULL,
    tipo ENUM('entrada','merma','ajuste','salida') NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    notas TEXT NULL,
    estado ENUM('pendiente','aplicado','cancelado') NOT NULL DEFAULT 'pendiente',
    id_usuario_registro INT UNSIGNED NULL,
    id_usuario_confirma INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmado_en DATETIME NULL,
    PRIMARY KEY (id_movimiento),
    KEY idx_mov_plantel_estado (id_plantel, estado),
    KEY idx_mov_producto (id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
