-- Avisos del panel de inicio: leídos / archivados por usuario

ALTER TABLE notificacion_usuario
  ADD COLUMN archivada TINYINT(1) NOT NULL DEFAULT 0 AFTER leida;

CREATE TABLE IF NOT EXISTS notificacion_panel_oculta (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario INT UNSIGNED NOT NULL,
  clave VARCHAR(80) NOT NULL,
  estado ENUM('leida','archivada') NOT NULL DEFAULT 'leida',
  id_plantel INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notif_panel_user_clave (id_usuario, clave),
  KEY idx_notif_panel_user (id_usuario, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
