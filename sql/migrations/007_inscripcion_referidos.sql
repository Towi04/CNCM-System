-- Tabla de referidos (inscripción con beneficio al referidor).
CREATE TABLE IF NOT EXISTS inscripcion_referidos (
    id_referido INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_plantel INT UNSIGNED NOT NULL,
    id_alumno_inscrito INT UNSIGNED NOT NULL,
    id_alumno_referidor INT UNSIGNED NOT NULL,
    id_especialidad INT UNSIGNED NOT NULL,
    id_grupo INT UNSIGNED NULL,
    id_pago_inscripcion INT UNSIGNED NULL,
    id_pago_beneficio INT UNSIGNED NULL,
    monto_beneficio DECIMAL(12,2) NOT NULL DEFAULT 0,
    tipo_beneficio VARCHAR(40) NOT NULL DEFAULT 'semana_colegiatura',
    id_usuario_registro INT UNSIGNED NULL,
    firma_referidor_at DATETIME NULL,
    ticket_copia_impresa TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_referido),
    KEY idx_ref_inscrito (id_alumno_inscrito),
    KEY idx_ref_referidor (id_alumno_referidor),
    KEY idx_ref_plantel_fecha (id_plantel, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 1;
