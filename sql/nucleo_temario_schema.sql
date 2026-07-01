SET NAMES utf8mb4;

-- Ejecutar ANTES de ingles_temario_seed.sql (phpMyAdmin o consola MySQL)

CREATE TABLE IF NOT EXISTS fase_temario_semana (
    id_semana INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_fase INT UNSIGNED NOT NULL,
    semana TINYINT UNSIGNED NOT NULL COMMENT '1-4 dentro del parcial',
    titulo_leccion VARCHAR(160) NULL,
    objetivo TEXT NULL,
    vocabulario TEXT NULL,
    gramatica TEXT NULL,
    listening TEXT NULL,
    reading TEXT NULL,
    writing TEXT NULL,
    speaking TEXT NULL,
    notas TEXT NULL,
    es_examen TINYINT(1) NOT NULL DEFAULT 0,
    proyecto_tipo VARCHAR(80) NULL COMMENT 'Project A, investigacion, etc.',
    PRIMARY KEY (id_semana),
    UNIQUE KEY uq_fase_semana (id_fase, semana),
    KEY idx_fts_fase (id_fase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Columnas en especialidad_fases (MySQL 8+: ejecutar una vez; si falla "duplicate column", omitir la línea)
ALTER TABLE especialidad_fases
    ADD COLUMN objetivo_parcial TEXT NULL AFTER asesoria,
    ADD COLUMN tipo_contenido ENUM('regular','proyecto_nivel','proyecto_final') NOT NULL DEFAULT 'regular' AFTER objetivo_parcial,
    ADD COLUMN eval_listening TEXT NULL AFTER tipo_contenido,
    ADD COLUMN eval_reading TEXT NULL AFTER eval_listening,
    ADD COLUMN eval_writing TEXT NULL AFTER eval_reading,
    ADD COLUMN eval_speaking TEXT NULL AFTER eval_writing,
    ADD COLUMN eval_grammar TEXT NULL AFTER eval_speaking,
    ADD COLUMN eval_vocabulary TEXT NULL AFTER eval_grammar,
    ADD COLUMN vocabulario_resumen TEXT NULL AFTER eval_vocabulary,
    ADD COLUMN gramatica_resumen TEXT NULL AFTER vocabulario_resumen;
