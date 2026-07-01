-- Alinea id_rol de cuentas supervisoras y fuerza re-sync de privilegios si hace falta.
UPDATE usuarios u
INNER JOIN roles r ON r.clave = 'supervisor' AND r.activo = 1
SET u.rol = 'supervisor', u.id_rol = r.id_rol
WHERE LOWER(TRIM(u.rol)) IN ('supervisor', 'supervisora', 'supervisora general', 'direccion general')
   OR (LOWER(TRIM(u.rol)) = 'supervisor' AND (u.id_rol IS NULL OR u.id_rol <> r.id_rol));

DELETE FROM hay_app_meta WHERE clave = 'rbac_jerarquia_v3_done';

SELECT 1;
