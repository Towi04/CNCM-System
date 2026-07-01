-- Tour guiado, marketing alumno, designación cartas (gerente)
CREATE TABLE IF NOT EXISTS usuario_tour (
    id_usuario INT UNSIGNED NOT NULL,
    tour_key VARCHAR(80) NOT NULL,
    completado TINYINT(1) NOT NULL DEFAULT 0,
    completado_en DATETIME NULL,
    PRIMARY KEY (id_usuario, tour_key),
    KEY idx_tour_key (tour_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_banner (
    id_banner INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(160) NOT NULL,
    imagen_url VARCHAR(500) NULL,
    enlace_url VARCHAR(500) NULL,
    texto_alt VARCHAR(200) NULL,
    audiencia ENUM('alumno','todos','staff') NOT NULL DEFAULT 'alumno',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    orden INT NOT NULL DEFAULT 0,
    vigente_desde DATE NULL,
    vigente_hasta DATE NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_banner)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asesor_cartas_periodo (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_plantel INT UNSIGNED NOT NULL,
    id_usuario_asesor INT UNSIGNED NOT NULL,
    periodo_mes CHAR(7) NOT NULL COMMENT 'YYYY-MM',
    notas VARCHAR(255) NULL,
    registrado_por INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cartas_asesor_mes (id_plantel, id_usuario_asesor, periodo_mes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
