-- Curso Moodle por bloque de fases en temario (definir en 1ª fase de cada bloque).

ALTER TABLE especialidad_fases
  ADD COLUMN IF NOT EXISTS moodle_course_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS moodle_shortname VARCHAR(80) NULL;

CREATE TABLE IF NOT EXISTS alumno_moodle_curso (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_alumno INT UNSIGNED NOT NULL,
  id_especialidad INT UNSIGNED NOT NULL,
  id_fase INT UNSIGNED NULL,
  moodle_course_id INT UNSIGNED NOT NULL,
  moodle_user_id INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_amc_alumno_esp_curso (id_alumno, id_especialidad, moodle_course_id),
  KEY idx_amc_alumno (id_alumno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
