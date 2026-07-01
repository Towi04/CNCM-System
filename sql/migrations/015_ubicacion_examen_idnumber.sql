-- Referencia visible en Moodle (Número ID del curso / idnumber).

ALTER TABLE ubicacion_examen
  ADD COLUMN IF NOT EXISTS moodle_idnumber VARCHAR(80) NULL AFTER moodle_shortname;
