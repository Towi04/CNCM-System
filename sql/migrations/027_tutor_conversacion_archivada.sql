-- Archivar conversaciones del tutor IA (ocultar del historial activo)

ALTER TABLE tutor_conversaciones
    ADD COLUMN archivada TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = archivada, oculta del listado activo'
    AFTER origen;

SELECT 1;
