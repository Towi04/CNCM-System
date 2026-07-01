-- Privilegio nómina para directores y supervisores

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)

SELECT r.id_rol, 'menu_nomina_gestionar' FROM roles r WHERE r.clave IN ('director', 'supervisor');

