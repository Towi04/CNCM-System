-- Nómina dual: salario principal + docencia por separado

ALTER TABLE personal_pago_config

    ADD COLUMN IF NOT EXISTS alcance ENUM('principal','docencia') NOT NULL DEFAULT 'principal' AFTER id_plantel;



-- MySQL 8.0.12+ no tiene IF NOT EXISTS en ADD COLUMN; la migración PHP también lo aplica.

-- Índice único por usuario/plantel/alcance (ejecutar en PHP si falla aquí):

-- ALTER TABLE personal_pago_config DROP INDEX uq_ppc_usuario_plantel;

-- ALTER TABLE personal_pago_config ADD UNIQUE KEY uq_ppc_usuario_plantel_alcance (id_usuario, id_plantel, alcance);

