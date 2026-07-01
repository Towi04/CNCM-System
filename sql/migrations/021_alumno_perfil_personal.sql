-- Perfil personal del alumno (gustos / afinidades para tutor y planeación)
ALTER TABLE alumnos ADD COLUMN perfil_gustos TEXT NULL AFTER moodle_user_id;
ALTER TABLE alumnos ADD COLUMN perfil_intereses_json JSON NULL AFTER perfil_gustos;
ALTER TABLE alumnos ADD COLUMN perfil_completado TINYINT(1) NOT NULL DEFAULT 0 AFTER perfil_intereses_json;
ALTER TABLE alumnos ADD COLUMN perfil_completado_en DATETIME NULL AFTER perfil_completado;

SELECT 1;
