-- Privilegios rol de aulas para coordinador y recepción
INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'menu_gestion_aulas' FROM roles r WHERE r.clave IN ('coordinador', 'director', 'supervisor');

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'menu_rol_aulas_gestionar' FROM roles r WHERE r.clave IN ('coordinador', 'director', 'supervisor');

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'menu_rol_aulas_consulta' FROM roles r WHERE r.clave IN ('coordinador', 'director', 'supervisor', 'admin', 'gerente');
