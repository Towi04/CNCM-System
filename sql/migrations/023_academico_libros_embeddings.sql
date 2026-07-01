-- Libros institucionales versionados + embeddings para RAG semántico

CREATE TABLE IF NOT EXISTS academico_libro (
    id_libro INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_especialidad INT UNSIGNED NOT NULL,
    tipo ENUM('studentbook','workbook','libro_profesor','guia_profesor') NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_libro),
    UNIQUE KEY uq_libro_esp_tipo (id_especialidad, tipo),
    KEY idx_libro_esp (id_especialidad, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academico_libro_version (
    id_version INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_libro INT UNSIGNED NOT NULL,
    etiqueta VARCHAR(40) NOT NULL,
    ruta_pdf VARCHAR(500) NOT NULL,
    num_paginas SMALLINT UNSIGNED NULL,
    hash_sha256 CHAR(64) NULL,
    activo_alumno TINYINT(1) NOT NULL DEFAULT 0,
    activo_rag TINYINT(1) NOT NULL DEFAULT 0,
    estado_indexacion ENUM('pendiente','procesando','listo','error') NOT NULL DEFAULT 'pendiente',
    error_indexacion TEXT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_version),
    KEY idx_ver_libro (id_libro),
    KEY idx_ver_rag (activo_rag, estado_indexacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academico_material_embedding (
    id_embedding BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_material INT UNSIGNED NOT NULL,
    id_version INT UNSIGNED NOT NULL,
    modelo VARCHAR(80) NOT NULL,
    embedding_json JSON NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_embedding),
    UNIQUE KEY uq_mat_model (id_material, modelo),
    KEY idx_emb_version (id_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 1;
