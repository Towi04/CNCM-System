-- Descuentos extraordinarios supervisor: resto de curso y condonación de adeudo.



ALTER TABLE alumno_especialidades

    ADD COLUMN override_resto_curso TINYINT(1) NOT NULL DEFAULT 0 AFTER override_actualizado;



CREATE TABLE IF NOT EXISTS alumno_adeudo_condonacion (

    id_condonacion INT UNSIGNED NOT NULL AUTO_INCREMENT,

    id_alumno INT UNSIGNED NOT NULL,

    id_alumno_especialidad INT UNSIGNED NULL,

    id_especialidad INT UNSIGNED NULL,

    monto_condonado DECIMAL(12,2) NOT NULL DEFAULT 0,

    adeudo_antes DECIMAL(12,2) NOT NULL DEFAULT 0,

    detalle_json JSON NULL,

    motivo VARCHAR(255) NOT NULL,

    id_usuario INT UNSIGNED NULL,

    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_condonacion),

    KEY idx_cond_alumno (id_alumno),

    KEY idx_cond_ae (id_alumno_especialidad)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



ALTER TABLE alumno_tarifa_override_hist

    MODIFY accion ENUM('aplicar','restaurar','vencer','condonar') NOT NULL;



SELECT 1;


