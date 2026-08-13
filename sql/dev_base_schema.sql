-- Base schema for local/dev bootstrap.
--
-- The production database predates this repo and already contained a `usuarios`
-- table, so there is no CREATE TABLE for it anywhere in the codebase (helpers only
-- ALTER it via ensure_schema / plantel_ensure_column). This file recreates the
-- minimal base `usuarios` table so a fresh local database can be bootstrapped.
-- All other columns (id_alumno, debe_cambiar_password, suspendido, ultimo_acceso,
-- id_hay_area, login_fallidos, avatar, moodle_*, etc.) are added idempotently by
-- the app's schema bootstrap (hay_bootstrap_schema) and SQL migrations.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id_usuario INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  apellido VARCHAR(120) NULL,
  username VARCHAR(120) NOT NULL,
  email VARCHAR(120) NULL DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  rol VARCHAR(40) NOT NULL DEFAULT 'usuario',
  departamento VARCHAR(80) NULL,
  id_plantel INT UNSIGNED NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuarios_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- `alumno_grupos` must exist before the schema bootstrap runs: plantel_ensure_schema
-- performs a backfill UPDATE that JOINs alumno_grupos, and on a fresh DB that query
-- throws and aborts every ensure_schema step (a chicken-and-egg deadlock). The app
-- (alumno_ensure_schema) creates this same table once the deadlock is broken.
CREATE TABLE IF NOT EXISTS alumno_grupos (
  id_alumno_grupo INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_alumno INT UNSIGNED NOT NULL,
  id_grupo INT UNSIGNED NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_inicio DATE NOT NULL DEFAULT (CURRENT_DATE),
  fecha_baja DATE NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_alumno_grupo),
  UNIQUE KEY uq_alumno_grupo (id_alumno, id_grupo),
  KEY idx_ag_alumno (id_alumno),
  KEY idx_ag_grupo (id_grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
