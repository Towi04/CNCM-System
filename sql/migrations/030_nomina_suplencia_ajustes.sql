-- Suplencias de grupo, ajustes manuales de nómina y bitácora para supervisores

CREATE TABLE IF NOT EXISTS grupo_suplencia (

    id_suplencia INT UNSIGNED NOT NULL AUTO_INCREMENT,

    id_grupo INT UNSIGNED NOT NULL,

    id_plantel INT UNSIGNED NOT NULL,

    id_profesor_titular INT UNSIGNED NOT NULL,

    id_profesor_suplente INT UNSIGNED NULL,

    fecha_inicio DATE NOT NULL,

    fecha_fin DATE NOT NULL,

    motivo ENUM('enfermedad','evento_institucional','apoyo_evento','otro') NOT NULL DEFAULT 'enfermedad',

    regla_pago ENUM('solo_suplente','ambos','solo_titular_apoyo') NOT NULL DEFAULT 'solo_suplente',

    pago_titular_concepto VARCHAR(160) NULL,

    pago_titular_monto DECIMAL(12,2) NULL,

    pago_titular_horas DECIMAL(8,2) NULL,

    notas TEXT NULL,

    activo TINYINT(1) NOT NULL DEFAULT 1,

    creado_por INT UNSIGNED NULL,

    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_suplencia),

    KEY idx_gs_grupo (id_grupo),

    KEY idx_gs_plantel (id_plantel),

    KEY idx_gs_fechas (fecha_inicio, fecha_fin)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE IF NOT EXISTS nomina_ajuste_log (

    id_log INT UNSIGNED NOT NULL AUTO_INCREMENT,

    id_liquidacion INT UNSIGNED NOT NULL,

    id_linea INT UNSIGNED NULL,

    id_usuario_afectado INT UNSIGNED NOT NULL,

    accion ENUM('agregar','editar','eliminar') NOT NULL,

    concepto_antes VARCHAR(255) NULL,

    importe_antes DECIMAL(12,2) NULL,

    concepto_despues VARCHAR(255) NULL,

    importe_despues DECIMAL(12,2) NULL,

    observacion TEXT NOT NULL,

    id_usuario_editor INT UNSIGNED NOT NULL,

    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_log),

    KEY idx_nal_liq (id_liquidacion)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

