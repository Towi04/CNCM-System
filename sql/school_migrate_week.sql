SET NAMES utf8mb4;

-- Agregar columnas si aún no existen (MySQL no soporta IF NOT EXISTS para columnas en todas versiones)
-- Ejecuta manualmente las líneas que aplique según tu versión:

ALTER TABLE asistencias
  ADD COLUMN anio SMALLINT UNSIGNED NULL,
  ADD COLUMN semana TINYINT UNSIGNED NULL;

UPDATE asistencias
SET anio = YEAR(fecha),
    semana = WEEK(fecha, 0)
WHERE anio IS NULL OR semana IS NULL;

ALTER TABLE asistencias
  MODIFY anio SMALLINT UNSIGNED NOT NULL,
  MODIFY semana TINYINT UNSIGNED NOT NULL;

ALTER TABLE asistencias
  ADD KEY idx_asist_grupo_anio_sem (id_grupo, anio, semana);

ALTER TABLE planeaciones
  ADD COLUMN anio SMALLINT UNSIGNED NULL,
  ADD COLUMN semana TINYINT UNSIGNED NULL;

UPDATE planeaciones
SET anio = YEAR(fecha),
    semana = WEEK(fecha, 0)
WHERE anio IS NULL OR semana IS NULL;

ALTER TABLE planeaciones
  MODIFY anio SMALLINT UNSIGNED NOT NULL,
  MODIFY semana TINYINT UNSIGNED NOT NULL;

ALTER TABLE planeaciones
  ADD KEY idx_plan_grupo_anio_sem (id_grupo, anio, semana);

