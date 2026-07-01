-- Normaliza usuarios asesor (la resincronización de privilegios corre en bootstrap del panel).
UPDATE usuarios u
INNER JOIN roles r ON r.clave = 'asesor' AND r.activo = 1
SET u.rol = 'asesor', u.id_rol = r.id_rol
WHERE LOWER(TRIM(u.rol)) IN ('asesor', 'ventas', 'asesor ventas', 'asesor de ventas')
   OR (u.id_rol = r.id_rol AND (u.rol IS NULL OR u.rol = ''));

SELECT 1;
