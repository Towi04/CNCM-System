-- RBAC caps para auditoría de pagos (supervisor)
INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'pago_supervisor_editar'
FROM roles r WHERE r.clave IN ('supervisor', 'admin');

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'menu_reporte_pagos_anulados'
FROM roles r WHERE r.clave IN ('supervisor', 'admin');

SELECT 1;
