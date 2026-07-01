-- Plantilla de prompt IA para planeación de clase por especialidad.

ALTER TABLE especialidades
    ADD COLUMN prompt_planeacion MEDIUMTEXT NULL
    COMMENT 'Plantilla IA planeación; placeholders <<Tema>>, <<Nivel>>, etc.'
    AFTER descripcion;
