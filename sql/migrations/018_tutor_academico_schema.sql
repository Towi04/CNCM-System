-- Tutor Académico Institucional — tablas base
-- Migración 018

CREATE TABLE IF NOT EXISTS tutor_tutores (
    id_tutor INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(120) NOT NULL,
    descripcion TEXT NULL,
    especialidad VARCHAR(40) NOT NULL DEFAULT 'general',
    instrucciones TEXT NOT NULL,
    avatar_url VARCHAR(255) NULL,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_tutor),
    KEY idx_tutor_esp (especialidad, activo),
    KEY idx_tutor_activo (activo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutor_conversaciones (
    id_conversacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_usuario INT UNSIGNED NOT NULL,
    id_tutor INT UNSIGNED NOT NULL,
    titulo VARCHAR(200) NULL,
    id_especialidad INT UNSIGNED NULL,
    id_fase INT UNSIGNED NULL,
    origen VARCHAR(32) NOT NULL DEFAULT 'hay',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_conversacion),
    KEY idx_tutor_conv_usuario (id_usuario, actualizado_en),
    KEY idx_tutor_conv_tutor (id_tutor),
    CONSTRAINT fk_tutor_conv_tutor FOREIGN KEY (id_tutor) REFERENCES tutor_tutores (id_tutor) ON DELETE RESTRICT,
    CONSTRAINT fk_tutor_conv_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutor_mensajes (
    id_mensaje INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_conversacion INT UNSIGNED NOT NULL,
    role ENUM('system','user','assistant') NOT NULL,
    mensaje MEDIUMTEXT NOT NULL,
    tokens INT UNSIGNED NOT NULL DEFAULT 0,
    metadata_json JSON NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_mensaje),
    KEY idx_tutor_msg_conv (id_conversacion, creado_en),
    CONSTRAINT fk_tutor_msg_conv FOREIGN KEY (id_conversacion) REFERENCES tutor_conversaciones (id_conversacion) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutor_ia_logs (
    id_log INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_usuario INT UNSIGNED NOT NULL,
    id_conversacion INT UNSIGNED NULL,
    id_tutor INT UNSIGNED NULL,
    prompt_enviado MEDIUMTEXT NOT NULL,
    respuesta_recibida MEDIUMTEXT NULL,
    modelo VARCHAR(80) NOT NULL,
    tokens_prompt INT UNSIGNED NOT NULL DEFAULT 0,
    tokens_respuesta INT UNSIGNED NOT NULL DEFAULT 0,
    tokens_total INT UNSIGNED NOT NULL DEFAULT 0,
    costo_estimado DECIMAL(10,6) NOT NULL DEFAULT 0,
    http_code SMALLINT UNSIGNED NULL,
    provider VARCHAR(32) NOT NULL DEFAULT 'openrouter',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_log),
    KEY idx_tutor_log_usuario (id_usuario, creado_en),
    KEY idx_tutor_log_conv (id_conversacion),
    CONSTRAINT fk_tutor_log_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 1;
