-- Tablas de soporte para importar datos del sistema Laravel legado (cncm/)
-- Ejecutar en la BD de HAY antes del importador (o dejar que legacy_import_ensure_schema lo cree).

CREATE TABLE IF NOT EXISTS hay_legacy_equivalence (
    entidad VARCHAR(32) NOT NULL,
    id_legacy BIGINT UNSIGNED NOT NULL,
    id_hay INT UNSIGNED NULL,
    modo ENUM('usar','omitir','crear') NOT NULL DEFAULT 'usar',
    notas VARCHAR(255) NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (entidad, id_legacy)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hay_legacy_map (
    entidad VARCHAR(32) NOT NULL COMMENT 'plantel, usuario, especialidad, producto, grupo, preregistro, alumno, pago, ...',
    id_legacy BIGINT UNSIGNED NOT NULL,
    id_hay INT UNSIGNED NOT NULL,
    notas VARCHAR(255) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (entidad, id_legacy),
    KEY idx_legacy_hay (entidad, id_hay)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hay_legacy_import_log (
    id_log INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fase VARCHAR(40) NOT NULL,
    nivel ENUM('info','warn','error') NOT NULL DEFAULT 'info',
    mensaje TEXT NOT NULL,
    id_legacy BIGINT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_log),
    KEY idx_legacy_log_fase (fase, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
