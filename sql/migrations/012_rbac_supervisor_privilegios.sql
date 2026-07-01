-- Supervisor: acceso total + privilegios completos (incluye menu_preregistro).
UPDATE roles
SET acceso_total = 1, alcance_planteles = 'todos'
WHERE clave = 'supervisor';

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'menu_preregistro'
FROM roles r
WHERE r.clave = 'supervisor' AND r.activo = 1;

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'menu_ventas'
FROM roles r
WHERE r.clave = 'supervisor' AND r.activo = 1;

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'menu_caja'
FROM roles r
WHERE r.clave = 'supervisor' AND r.activo = 1;

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'menu_academico'
FROM roles r
WHERE r.clave = 'supervisor' AND r.activo = 1;

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'menu_alumnos'
FROM roles r
WHERE r.clave = 'supervisor' AND r.activo = 1;

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'menu_admin'
FROM roles r
WHERE r.clave = 'supervisor' AND r.activo = 1;

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'admin_roles'
FROM roles r
WHERE r.clave = 'supervisor' AND r.activo = 1;

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'admin_planteles'
FROM roles r
WHERE r.clave = 'supervisor' AND r.activo = 1;

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'catalogo_editar_costos'
FROM roles r
WHERE r.clave = 'supervisor' AND r.activo = 1;

SELECT 1;
