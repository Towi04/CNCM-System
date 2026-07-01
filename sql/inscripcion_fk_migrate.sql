-- Migración: id_grupo nullable en alumnos + FK alumno_grupos → grupos
-- Ejecutar una sola vez en producción si hay errores fk_alumnos_grupo.

SET NAMES utf8mb4;

-- Permitir alumnos sin grupo principal (inscripción vía alumno_grupos)
ALTER TABLE alumnos MODIFY COLUMN id_grupo INT UNSIGNED NULL;

-- Corregir valores huérfanos antes de agregar FK
UPDATE alumnos a
LEFT JOIN grupos g ON g.id_grupo = a.id_grupo
SET a.id_grupo = NULL
WHERE a.id_grupo IS NOT NULL AND g.id_grupo IS NULL;

DELETE ag FROM alumno_grupos ag
LEFT JOIN grupos g ON g.id_grupo = ag.id_grupo
WHERE g.id_grupo IS NULL;

-- FK en alumno_grupos (evita relaciones huérfanas)
-- Si ya existe, ignorar error Duplicate foreign key
ALTER TABLE alumno_grupos
  ADD CONSTRAINT fk_ag_grupo
  FOREIGN KEY (id_grupo) REFERENCES grupos(id_grupo)
  ON DELETE RESTRICT ON UPDATE CASCADE;
