-- Normaliza roles legacy en usuarios (por si 006 no aplicó el UPDATE).
UPDATE usuarios SET rol = 'coordinador' WHERE rol = 'coordinacion';
UPDATE usuarios SET rol = 'admin' WHERE rol IN ('recepcion', 'imagen');

UPDATE usuarios u
INNER JOIN roles r ON r.clave = u.rol AND r.activo = 1
SET u.id_rol = r.id_rol
WHERE u.rol IS NOT NULL AND u.rol <> '';

DELETE FROM hay_app_meta WHERE clave = 'rbac_jerarquia_v3_done';

SELECT 1;
