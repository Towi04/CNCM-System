-- Auditoría de pagos: anulación/edición por supervisor
ALTER TABLE alumno_pagos
  ADD COLUMN IF NOT EXISTS estado ENUM('activo','anulado') NOT NULL DEFAULT 'activo',
  ADD COLUMN IF NOT EXISTS anulado_en DATETIME NULL,
  ADD COLUMN IF NOT EXISTS anulado_por INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS anulado_motivo VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS id_pago_reemplazo INT UNSIGNED NULL;

CREATE TABLE IF NOT EXISTS alumno_pago_movimiento (
  id_mov INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pago INT UNSIGNED NOT NULL,
  id_pago_nuevo INT UNSIGNED NULL,
  id_alumno INT UNSIGNED NOT NULL,
  tipo ENUM('anular','editar_monto','editar_concepto') NOT NULL,
  snapshot_json JSON NULL,
  motivo VARCHAR(500) NOT NULL,
  id_usuario INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_mov),
  KEY idx_apm_pago (id_pago),
  KEY idx_apm_alumno (id_alumno),
  KEY idx_apm_fecha (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 1;
