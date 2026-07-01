-- Grupos: tutor asignado según especialidad (idempotente vía hay_schema_ejecutar_sql)
ALTER TABLE grupos ADD COLUMN id_tutor INT UNSIGNED NULL AFTER id_especialidad;
ALTER TABLE grupos ADD KEY idx_grupos_tutor (id_tutor);

SELECT 1;
