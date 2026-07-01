-- Portal alumno: avisos académicos y chat
CREATE TABLE IF NOT EXISTS alumno_aviso (
    id_aviso INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_plantel INT UNSIGNED NOT NULL,
    id_grupo INT UNSIGNED NULL COMMENT 'NULL = todo el plantel',
    titulo VARCHAR(160) NOT NULL,
    mensaje TEXT NOT NULL,
    id_usuario_autor INT UNSIGNED NULL,
    autor_nombre VARCHAR(120) NULL,
    vigente_hasta DATE NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_aviso),
    KEY idx_aviso_plantel (id_plantel, activo, creado_en),
    KEY idx_aviso_grupo (id_grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alumno_chat_sala (
    id_sala INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_plantel INT UNSIGNED NOT NULL,
    tipo ENUM('grupo','recepcion','coordinacion') NOT NULL,
    id_grupo INT UNSIGNED NULL,
    nombre VARCHAR(120) NOT NULL,
    PRIMARY KEY (id_sala),
    UNIQUE KEY uq_chat_sala (id_plantel, tipo, id_grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alumno_chat_mensaje (
    id_mensaje INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_sala INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NULL,
    id_alumno INT UNSIGNED NULL,
    autor_nombre VARCHAR(120) NOT NULL,
    mensaje TEXT NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_mensaje),
    KEY idx_chat_sala_fecha (id_sala, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
