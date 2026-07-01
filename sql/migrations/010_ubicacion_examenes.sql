-- Catálogo de exámenes de ubicación en Moodle por especialidad/fase.

CREATE TABLE IF NOT EXISTS ubicacion_examen (
  id_examen INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_especialidad INT UNSIGNED NOT NULL,
  id_fase INT UNSIGNED NULL COMMENT 'Opcional: examen según fase destino (ej. Excel avanzado)',
  nombre VARCHAR(160) NOT NULL,
  descripcion TEXT NULL,
  moodle_course_id INT UNSIGNED NOT NULL,
  moodle_shortname VARCHAR(80) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  orden INT NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_examen),
  KEY idx_ubex_esp (id_especialidad),
  KEY idx_ubex_fase (id_fase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE alumno_ubicacion
  ADD COLUMN IF NOT EXISTS id_examen_ubicacion INT UNSIGNED NULL AFTER id_especialidad,
  ADD COLUMN IF NOT EXISTS moodle_inscrito TINYINT(1) NOT NULL DEFAULT 0 AFTER observaciones;
