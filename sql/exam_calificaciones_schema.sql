-- Calificaciones por escaneo de hoja de respuestas (inglés)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS exam_plantel_config (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  digitos_control TINYINT UNSIGNED NOT NULL DEFAULT 5,
  peso_mc DECIMAL(5,2) NOT NULL DEFAULT 70.00,
  peso_writing DECIMAL(5,2) NOT NULL DEFAULT 15.00,
  peso_speaking DECIMAL(5,2) NOT NULL DEFAULT 15.00,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO exam_plantel_config (id, digitos_control, peso_mc, peso_writing, peso_speaking)
VALUES (1, 5, 70, 15, 15)
ON DUPLICATE KEY UPDATE id = id;

CREATE TABLE IF NOT EXISTS exam_calificaciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_examen VARCHAR(20) NOT NULL,
  fase VARCHAR(80) NOT NULL,
  id_alumno INT UNSIGNED NOT NULL,
  id_grupo INT UNSIGNED NULL,
  numero_control VARCHAR(10) NOT NULL,
  correctas_mc SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_mc SMALLINT UNSIGNED NOT NULL DEFAULT 41,
  calificacion_mc DECIMAL(5,2) NOT NULL DEFAULT 0,
  calificacion_writing DECIMAL(5,2) NOT NULL DEFAULT 0,
  calificacion_speaking DECIMAL(5,2) NOT NULL DEFAULT 0,
  calificacion_final DECIMAL(5,2) NOT NULL DEFAULT 0,
  respuestas_mc JSON NULL,
  rubrica_writing JSON NULL,
  rubrica_speaking JSON NULL,
  escaneado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_profesor INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_exam_alumno (id_examen, id_alumno),
  KEY idx_calif_fase (fase),
  KEY idx_calif_control (numero_control),
  KEY idx_calif_grupo (id_grupo),
  CONSTRAINT fk_calif_examen
    FOREIGN KEY (id_examen) REFERENCES exam_generados(id_examen)
    ON DELETE CASCADE,
  CONSTRAINT fk_calif_alumno
    FOREIGN KEY (id_alumno) REFERENCES alumnos(id_alumno)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
