-- Seguridad de login: auditoría de intentos (columnas en usuarios vía login_security_ensure_schema)

CREATE TABLE IF NOT EXISTS usuario_login_intento (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_usuario INT UNSIGNED NULL,
    username_intento VARCHAR(120) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    exito TINYINT(1) NOT NULL DEFAULT 0,
    motivo VARCHAR(80) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_uli_usuario (id_usuario, creado_en),
    KEY idx_uli_ip (ip, creado_en),
    KEY idx_uli_user_txt (username_intento, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
