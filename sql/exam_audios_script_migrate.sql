-- Agrega script del audio a en_audios (ejecutar en phpMyAdmin si la tabla ya existe)
ALTER TABLE en_audios
  ADD COLUMN script_audio MEDIUMTEXT NULL AFTER link_audio;
