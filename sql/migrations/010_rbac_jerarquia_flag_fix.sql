-- Repara flag de sincronización RBAC (evita re-sync pesado en cada petición).
-- Ejecutar si tras la 009 manual el panel quedó lento o sin vistas.

INSERT INTO hay_app_meta (clave, valor) VALUES
('rbac_jerarquia_v3_done', '1')
ON DUPLICATE KEY UPDATE valor = '1';

SELECT 1;
