SET NAMES utf8mb4;

-- ============================================================
-- Banco de preguntas - Inglés
-- ============================================================

CREATE TABLE IF NOT EXISTS en_vocabulario (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fase VARCHAR(80) NOT NULL,
  pregunta TEXT NOT NULL,
  opcion_a VARCHAR(500) NOT NULL,
  opcion_b VARCHAR(500) NOT NULL,
  opcion_c VARCHAR(500) NOT NULL,
  opcion_d VARCHAR(500) NOT NULL,
  respuesta CHAR(1) NOT NULL,
  id_fusion INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_vocab_fase (fase),
  KEY idx_vocab_fusion (id_fusion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS en_gramatica (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fase VARCHAR(80) NOT NULL,
  pregunta TEXT NOT NULL,
  opcion_a VARCHAR(500) NOT NULL,
  opcion_b VARCHAR(500) NOT NULL,
  opcion_c VARCHAR(500) NOT NULL,
  opcion_d VARCHAR(500) NOT NULL,
  respuesta CHAR(1) NOT NULL,
  id_fusion INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_gram_fase (fase),
  KEY idx_gram_fusion (id_fusion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS en_audios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fase VARCHAR(80) NOT NULL,
  id_audio VARCHAR(40) NOT NULL,
  nombre_audio VARCHAR(200) NULL,
  link_audio VARCHAR(500) NOT NULL,
  script_audio MEDIUMTEXT NULL,
  id_fusion INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_audios_fase (fase),
  KEY idx_audios_id_audio (id_audio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS en_lecturas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fase VARCHAR(80) NOT NULL,
  id_lectura VARCHAR(40) NOT NULL,
  nombre_lectura VARCHAR(200) NULL,
  lectura MEDIUMTEXT NOT NULL,
  id_fusion INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_lect_fase (fase),
  KEY idx_lect_id_lectura (id_lectura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS en_listening (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fase VARCHAR(80) NOT NULL,
  id_audio VARCHAR(40) NOT NULL,
  pregunta TEXT NOT NULL,
  opcion_a VARCHAR(500) NOT NULL,
  opcion_b VARCHAR(500) NOT NULL,
  opcion_c VARCHAR(500) NOT NULL,
  opcion_d VARCHAR(500) NOT NULL,
  respuesta CHAR(1) NOT NULL,
  id_fusion INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_list_fase (fase),
  KEY idx_list_audio (id_audio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS en_reading (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fase VARCHAR(80) NOT NULL,
  id_lectura VARCHAR(40) NOT NULL,
  pregunta TEXT NOT NULL,
  opcion_a VARCHAR(500) NOT NULL,
  opcion_b VARCHAR(500) NOT NULL,
  opcion_c VARCHAR(500) NOT NULL,
  opcion_d VARCHAR(500) NOT NULL,
  respuesta CHAR(1) NOT NULL,
  id_fusion INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_read_fase (fase),
  KEY idx_read_lectura (id_lectura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS en_writing (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fase VARCHAR(80) NOT NULL,
  pregunta TEXT NOT NULL,
  id_fusion INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_writ_fase (fase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS en_speaking (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fase VARCHAR(80) NOT NULL,
  pregunta TEXT NOT NULL,
  id_fusion INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_speak_fase (fase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Fusiones guardadas (reutilizables como "fases" nombradas)
-- ============================================================

CREATE TABLE IF NOT EXISTS exam_fusiones (
  id_fusion INT UNSIGNED NOT NULL AUTO_INCREMENT,
  area VARCHAR(40) NOT NULL DEFAULT 'ingles',
  nombre VARCHAR(160) NOT NULL,
  id_profesor INT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_fusion),
  KEY idx_fusion_area (area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_fusion_fases (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_fusion INT UNSIGNED NOT NULL,
  fase VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ff_fusion (id_fusion),
  CONSTRAINT fk_ff_fusion
    FOREIGN KEY (id_fusion) REFERENCES exam_fusiones(id_fusion)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Exámenes generados
-- ============================================================

CREATE TABLE IF NOT EXISTS exam_generados (
  id_examen VARCHAR(20) NOT NULL,
  area VARCHAR(40) NOT NULL DEFAULT 'ingles',
  tipo ENUM('fase','nivel','fusion') NOT NULL,
  nombre_examen VARCHAR(200) NOT NULL,
  fases_usadas VARCHAR(500) NOT NULL,
  id_fusion INT UNSIGNED NULL,
  id_profesor INT NULL,
  audio_link VARCHAR(500) NULL,
  pdf_path VARCHAR(300) NOT NULL,
  csv_path VARCHAR(300) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_examen),
  KEY idx_exam_area (area),
  KEY idx_exam_prof (id_profesor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_generado_preguntas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_examen VARCHAR(20) NOT NULL,
  numero TINYINT UNSIGNED NOT NULL,
  seccion VARCHAR(30) NOT NULL,
  tipo VARCHAR(20) NOT NULL DEFAULT 'opcion_multiple',
  pregunta TEXT NOT NULL,
  opcion_a VARCHAR(500) NULL,
  opcion_b VARCHAR(500) NULL,
  opcion_c VARCHAR(500) NULL,
  opcion_d VARCHAR(500) NULL,
  respuesta VARCHAR(10) NULL,
  contexto MEDIUMTEXT NULL,
  id_audio VARCHAR(40) NULL,
  id_lectura VARCHAR(40) NULL,
  PRIMARY KEY (id),
  KEY idx_egp_examen (id_examen),
  CONSTRAINT fk_egp_examen
    FOREIGN KEY (id_examen) REFERENCES exam_generados(id_examen)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
