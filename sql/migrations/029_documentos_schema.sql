-- Constancias y diplomas con plantillas personalizables

CREATE TABLE IF NOT EXISTS documento_plantilla (

    id_plantilla INT UNSIGNED NOT NULL AUTO_INCREMENT,

    tipo ENUM('constancia','diploma') NOT NULL,

    nombre VARCHAR(120) NOT NULL,

    id_plantel INT UNSIGNED NULL,

    fondo_path VARCHAR(255) NULL,

    ancho_mm DECIMAL(6,2) NOT NULL DEFAULT 215.9,

    alto_mm DECIMAL(6,2) NOT NULL DEFAULT 279.4,

    campos_json JSON NULL,

    firma_path VARCHAR(255) NULL,

    vigencia_dias SMALLINT UNSIGNED NOT NULL DEFAULT 90,

    activo TINYINT(1) NOT NULL DEFAULT 1,

    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id_plantilla),

    KEY idx_dp_tipo (tipo),

    KEY idx_dp_plantel (id_plantel)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE IF NOT EXISTS alumno_documento (

    id_documento INT UNSIGNED NOT NULL AUTO_INCREMENT,

    tipo ENUM('constancia','diploma') NOT NULL,

    id_alumno INT UNSIGNED NOT NULL,

    id_plantel INT UNSIGNED NOT NULL,

    id_grupo INT UNSIGNED NULL,

    id_plantilla INT UNSIGNED NOT NULL,

    id_producto INT UNSIGNED NULL,

    id_pago INT UNSIGNED NULL,

    folio VARCHAR(32) NOT NULL,

    token_verificacion CHAR(32) NOT NULL,

    campos_opciones JSON NULL,

    campos_extra JSON NULL,

    estado ENUM('pendiente_pago','pagada','expirada','cancelada') NOT NULL DEFAULT 'pendiente_pago',

    vigente_hasta DATE NULL,

    pdf_path VARCHAR(255) NULL,

    solicitado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    pagado_en DATETIME NULL,

    pagado_por INT UNSIGNED NULL,

    generado_en DATETIME NULL,

    PRIMARY KEY (id_documento),

    UNIQUE KEY uq_ad_folio (folio),

    UNIQUE KEY uq_ad_token (token_verificacion),

    KEY idx_ad_alumno (id_alumno),

    KEY idx_ad_estado (estado),

    KEY idx_ad_grupo (id_grupo)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



INSERT IGNORE INTO productos (clave, nombre, descripcion, precio, clave_sat, unidad_sat, activo, visible, orden)

VALUES ('CONST-EST', 'Constancia de estudios', 'Constancia oficial generada por el sistema HAY', 150.00, '01010101', 'E48', 1, 0, 900);

