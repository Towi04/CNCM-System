SET NAMES utf8mb4;

-- Grupos
CREATE TABLE IF NOT EXISTS grupos (
  id_grupo INT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave VARCHAR(50) NOT NULL,
  fecha_inicio DATE NOT NULL,
  PRIMARY KEY (id_grupo),
  UNIQUE KEY uq_grupos_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alumnos
CREATE TABLE IF NOT EXISTS alumnos (
  id_alumno INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_grupo INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  apellido VARCHAR(120) NOT NULL,
  matricula VARCHAR(60) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_alta DATE NOT NULL DEFAULT (CURRENT_DATE),
  PRIMARY KEY (id_alumno),
  KEY idx_alumnos_grupo (id_grupo),
  KEY idx_alumnos_activo (activo),
  UNIQUE KEY uq_alumnos_matricula (matricula),
  CONSTRAINT fk_alumnos_grupo
    FOREIGN KEY (id_grupo) REFERENCES grupos(id_grupo)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Asistencias (1 registro por alumno por fecha)
CREATE TABLE IF NOT EXISTS asistencias (
  id_asistencia BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_grupo INT UNSIGNED NOT NULL,
  id_alumno INT UNSIGNED NOT NULL,
  fecha DATE NOT NULL,
  anio SMALLINT UNSIGNED NOT NULL,
  semana TINYINT UNSIGNED NOT NULL, -- WEEK(fecha, 0) semana inicia domingo
  presente TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_asistencia),
  UNIQUE KEY uq_asistencia (id_alumno, fecha),
  KEY idx_asist_grupo_fecha (id_grupo, fecha),
  KEY idx_asist_grupo_anio_sem (id_grupo, anio, semana),
  CONSTRAINT fk_asist_grupo
    FOREIGN KEY (id_grupo) REFERENCES grupos(id_grupo)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_asist_alumno
    FOREIGN KEY (id_alumno) REFERENCES alumnos(id_alumno)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Planeaciones
CREATE TABLE IF NOT EXISTS planeaciones (
  id_planeacion BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_grupo INT UNSIGNED NOT NULL,
  id_profesor INT NULL, -- opcional: usuarios.user_id
  fecha DATE NOT NULL,
  anio SMALLINT UNSIGNED NOT NULL,
  semana TINYINT UNSIGNED NOT NULL, -- WEEK(fecha, 0) semana inicia domingo
  titulo VARCHAR(160) NOT NULL,
  contenido MEDIUMTEXT NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_planeacion),
  KEY idx_plan_grupo_fecha (id_grupo, fecha),
  KEY idx_plan_grupo_anio_sem (id_grupo, anio, semana),
  CONSTRAINT fk_plan_grupo
    FOREIGN KEY (id_grupo) REFERENCES grupos(id_grupo)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Disponibilidad de asesorías por profesor / semana (semana inicia domingo)
CREATE TABLE IF NOT EXISTS asesoria_disp (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_profesor INT NOT NULL,                -- usuarios.id (session user_id)
  anio SMALLINT UNSIGNED NOT NULL,
  semana TINYINT UNSIGNED NOT NULL,        -- WEEK(date, 0) (domingo = inicio)
  dow TINYINT UNSIGNED NOT NULL,           -- 0=Dom,1=Lun,...6=Sab
  hora TINYINT UNSIGNED NOT NULL,          -- hora inicio (24h). ej 8..19
  disponible TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_disp (id_profesor, anio, semana, dow, hora),
  KEY idx_disp_prof_sem (id_profesor, anio, semana)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

