-- Planteles CNCM: tabla maestra y separación por sede
-- Ejecutar en cncmedum_hay_system (o dejar que config.php aplique plantel_ensure_schema al entrar)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS planteles (
  id_plantel INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(40) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_plantel),
  UNIQUE KEY uq_planteles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO planteles (slug, nombre, orden) VALUES
  ('salamanca', 'Plantel Salamanca', 1),
  ('celaya',    'Plantel Celaya',    2),
  ('guerrero',  'Plantel Guerrero',  3),
  ('fuentes',   'Plantel Fuentes',   4)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), orden = VALUES(orden);

-- Eliminar sedes incorrectas si se habían insertado antes (León, Irapuato)
DELETE FROM planteles WHERE slug IN ('leon', 'irapuato');

-- Columnas id_plantel (ejecutar solo si no existen; config.php también las crea)
-- ALTER TABLE grupos ADD COLUMN id_plantel INT UNSIGNED NULL AFTER id_grupo;
-- UPDATE grupos SET id_plantel = (SELECT id_plantel FROM planteles WHERE slug = 'salamanca' LIMIT 1) WHERE id_plantel IS NULL;
-- ALTER TABLE alumnos ADD COLUMN id_plantel INT UNSIGNED NULL AFTER id_alumno;
-- UPDATE alumnos a INNER JOIN grupos g ON g.id_grupo = a.id_grupo SET a.id_plantel = g.id_plantel WHERE a.id_plantel IS NULL;
-- ALTER TABLE usuarios ADD COLUMN id_plantel INT UNSIGNED NULL;
-- ALTER TABLE asesoria_disp ADD COLUMN id_plantel INT UNSIGNED NULL;
-- ALTER TABLE exam_generados ADD COLUMN id_plantel INT UNSIGNED NULL;
