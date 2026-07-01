-- Núcleo operativo piloto Inglés (ING)
-- Ejecutar en phpMyAdmin o vía bootstrap (academico_ensure_schema)
SET NAMES utf8mb4;

-- Grupo: especialidad, fase actual del grupo, nivel Moodle
ALTER TABLE grupos
  ADD COLUMN IF NOT EXISTS id_fase_actual INT UNSIGNED NULL COMMENT 'Parcial que cursa el grupo' AFTER id_especialidad,
  ADD COLUMN IF NOT EXISTS moodle_nivel VARCHAR(20) NULL COMMENT 'A1, A1+, B1, etc.' AFTER id_fase_actual,
  ADD COLUMN IF NOT EXISTS horario_texto VARCHAR(120) NULL AFTER moodle_nivel;

-- Vínculo alumno–grupo: fase de entrada y riesgo académico
ALTER TABLE alumno_grupos
  ADD COLUMN IF NOT EXISTS id_fase_entrada INT UNSIGNED NULL AFTER id_grupo,
  ADD COLUMN IF NOT EXISTS ubicacion_examen TINYINT(1) NOT NULL DEFAULT 0 AFTER id_fase_entrada,
  ADD COLUMN IF NOT EXISTS en_riesgo_academico TINYINT(1) NOT NULL DEFAULT 0 AFTER ubicacion_examen,
  ADD COLUMN IF NOT EXISTS omitir_alerta_riesgo TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Coordinador documentó decisión' AFTER en_riesgo_academico;

-- Examen de ubicación y grupos autorizados
CREATE TABLE IF NOT EXISTS alumno_ubicacion (
  id_ubicacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_alumno INT UNSIGNED NOT NULL,
  id_plantel INT UNSIGNED NOT NULL,
  id_especialidad INT UNSIGNED NOT NULL,
  evaluado_por INT UNSIGNED NULL,
  fecha_evaluacion DATE NOT NULL,
  nivel_detectado VARCHAR(20) NULL COMMENT 'A1, A1+, etc.',
  observaciones TEXT NULL,
  estado ENUM('pendiente','autorizado','rechazado','usado') NOT NULL DEFAULT 'pendiente',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_ubicacion),
  KEY idx_ub_alumno (id_alumno),
  KEY idx_ub_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alumno_ubicacion_grupos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_ubicacion INT UNSIGNED NOT NULL,
  id_grupo INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ub_grupo (id_ubicacion, id_grupo),
  CONSTRAINT fk_ubg_ubicacion FOREIGN KEY (id_ubicacion) REFERENCES alumno_ubicacion(id_ubicacion) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rúbrica / ponderaciones por grupo y parcial
CREATE TABLE IF NOT EXISTS grupo_rubrica_parcial (
  id_rubrica INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_grupo INT UNSIGNED NOT NULL,
  id_fase INT UNSIGNED NOT NULL,
  criterios_json JSON NOT NULL COMMENT '[{codigo,peso_pct,obligatorio}]',
  actualizado_por INT UNSIGNED NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_rubrica),
  UNIQUE KEY uq_grupo_fase_rubrica (id_grupo, id_fase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Calificación por parcial (notas + promedio)
CREATE TABLE IF NOT EXISTS alumno_calificacion_parcial (
  id_calificacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_alumno INT UNSIGNED NOT NULL,
  id_fase INT UNSIGNED NOT NULL,
  id_grupo INT UNSIGNED NULL COMMENT 'Grupo donde se capturó',
  notas_json JSON NOT NULL COMMENT 'listening,reading,...,extras',
  promedio DECIMAL(4,2) NULL,
  aprobado TINYINT(1) NULL,
  capturado_por INT UNSIGNED NULL,
  editado_por INT UNSIGNED NULL,
  observaciones VARCHAR(500) NULL,
  capturado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_calificacion),
  UNIQUE KEY uq_alumno_fase_cal (id_alumno, id_fase),
  KEY idx_cal_fase (id_fase),
  KEY idx_cal_grupo (id_grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notas de coordinación (orientación, cambio de grupo)
CREATE TABLE IF NOT EXISTS alumno_nota_coordinacion (
  id_nota INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_alumno INT UNSIGNED NOT NULL,
  id_usuario INT UNSIGNED NULL,
  tipo ENUM('orientacion_grupo','ubicacion','riesgo_academico','general') NOT NULL DEFAULT 'general',
  nota TEXT NOT NULL,
  alumno_acepto_cambio TINYINT(1) NULL COMMENT 'NULL=no aplica, 0=rechazó, 1=aceptó',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_nota),
  KEY idx_anc_alumno (id_alumno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Avance automático del grupo (auditoría)
CREATE TABLE IF NOT EXISTS grupo_avance_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_grupo INT UNSIGNED NOT NULL,
  id_fase_anterior INT UNSIGNED NULL,
  id_fase_nueva INT UNSIGNED NOT NULL,
  semanas_lectivas INT UNSIGNED NOT NULL,
  avanzado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  automatico TINYINT(1) NOT NULL DEFAULT 1,
  id_usuario INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_gal_grupo (id_grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vacaciones configurables por año (opcional; el helper también calcula Semana Santa)
CREATE TABLE IF NOT EXISTS calendario_vacaciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  anio SMALLINT UNSIGNED NOT NULL,
  clave VARCHAR(40) NOT NULL COMMENT 'enero, semana_santa, diciembre',
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vac_anio_clave (anio, clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notificaciones a profesor (cola simple)
CREATE TABLE IF NOT EXISTS notificacion_usuario (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario INT UNSIGNED NOT NULL,
  tipo VARCHAR(60) NOT NULL,
  titulo VARCHAR(160) NOT NULL,
  mensaje TEXT NOT NULL,
  enlace_seccion VARCHAR(80) NULL,
  enlace_params VARCHAR(255) NULL,
  leida TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (id_usuario, leida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
