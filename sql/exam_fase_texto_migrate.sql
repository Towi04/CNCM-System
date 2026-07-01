-- Migración: fase numérica → texto (ej. "A1 1-4", "Windows")
-- Ejecutar en phpMyAdmin si ya creó las tablas con fase TINYINT.

SET NAMES utf8mb4;

ALTER TABLE en_vocabulario MODIFY fase VARCHAR(80) NOT NULL;
ALTER TABLE en_gramatica MODIFY fase VARCHAR(80) NOT NULL;
ALTER TABLE en_audios MODIFY fase VARCHAR(80) NOT NULL;
ALTER TABLE en_lecturas MODIFY fase VARCHAR(80) NOT NULL;
ALTER TABLE en_listening MODIFY fase VARCHAR(80) NOT NULL;
ALTER TABLE en_reading MODIFY fase VARCHAR(80) NOT NULL;
ALTER TABLE en_writing MODIFY fase VARCHAR(80) NOT NULL;
ALTER TABLE en_speaking MODIFY fase VARCHAR(80) NOT NULL;
ALTER TABLE exam_fusion_fases MODIFY fase VARCHAR(80) NOT NULL;
ALTER TABLE exam_generados MODIFY fases_usadas VARCHAR(500) NOT NULL;
