-- Rol de aulas: catálogo ampliado y publicaciones mensuales
-- Idempotente: columnas vía plantel_ensure_column en helpers si esta migración no corre.

CREATE TABLE IF NOT EXISTS aula_fotos (
    id_foto INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_aula INT UNSIGNED NOT NULL,
    orden TINYINT UNSIGNED NOT NULL DEFAULT 1,
    ruta VARCHAR(255) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_foto),
    KEY idx_af_aula (id_aula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rol_aulas_publicacion (
    id_publicacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_plantel INT UNSIGNED NOT NULL,
    anio SMALLINT UNSIGNED NOT NULL,
    mes TINYINT UNSIGNED NOT NULL,
    estado ENUM('borrador','publicado') NOT NULL DEFAULT 'borrador',
    notas TEXT NULL,
    creado_por INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    publicado_por INT UNSIGNED NULL,
    publicado_en DATETIME NULL,
    PRIMARY KEY (id_publicacion),
    UNIQUE KEY uq_rol_aulas_plantel_periodo (id_plantel, anio, mes),
    KEY idx_rol_aulas_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rol_aulas_asignacion (
    id_asignacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_publicacion INT UNSIGNED NOT NULL,
    id_grupo INT UNSIGNED NOT NULL,
    id_aula INT UNSIGNED NULL,
    cupo_grupo INT UNSIGNED NOT NULL DEFAULT 0,
    cupo_aula INT UNSIGNED NULL,
    es_manual TINYINT(1) NOT NULL DEFAULT 0,
    notas VARCHAR(255) NULL,
    PRIMARY KEY (id_asignacion),
    UNIQUE KEY uq_rol_asig_pub_grupo (id_publicacion, id_grupo),
    KEY idx_rol_asig_aula (id_aula),
    KEY idx_rol_asig_pub (id_publicacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
