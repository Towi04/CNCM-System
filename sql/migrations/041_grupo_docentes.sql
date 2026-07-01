-- Docentes por grupo y materia (preparatoria, verano, etc.)
CREATE TABLE IF NOT EXISTS grupo_docente (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_grupo INT UNSIGNED NOT NULL,
    id_profesor INT UNSIGNED NOT NULL,
    materia_clave VARCHAR(80) NOT NULL DEFAULT '',
    materia_nombre VARCHAR(160) NULL,
    es_titular TINYINT(1) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_grupo_docente_materia (id_grupo, materia_clave),
    KEY idx_grupo_docente_prof (id_profesor, id_grupo),
    KEY idx_grupo_docente_grupo (id_grupo, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
