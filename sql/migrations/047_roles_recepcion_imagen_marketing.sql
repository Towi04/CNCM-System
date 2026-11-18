-- Migración 047: roles operativos recepción / imagen / marketing
-- Amplía ENUM usuarios.rol (producción) y crea filas en `roles`.
-- Los privilegios los sincroniza PHP (rbac_db_ensure_roles_recepcion_imagen_marketing).

ALTER TABLE usuarios
    MODIFY COLUMN rol ENUM(
        'supervisor','director','gerente','coordinador','admin',
        'recepcion','imagen','marketing','manuales',
        'asesor','profesor','alumno'
    ) NOT NULL DEFAULT 'profesor';

INSERT IGNORE INTO roles (clave, nombre, descripcion, acceso_total, alcance_planteles, departamento_default, es_sistema, activo, orden) VALUES
('recepcion', 'Recepción / Caja', 'Alumnos, cobranza y mostrador', 0, 'solo_usuario', 'administrativo', 1, 1, 5),
('imagen', 'Imagen / Diseño', 'Personal de imagen sin alumnos ni caja', 0, 'solo_usuario', 'administrativo', 1, 1, 6),
('marketing', 'Marketing', 'Estadísticas de captación y alumnos; sin caja', 0, 'todos', 'ventas', 1, 1, 7),
('manuales', 'Inventario / Manuales', 'Gestión de stock y envíos de manuales', 0, 'todos', 'administrativo', 1, 1, 8);

UPDATE roles SET
    nombre = 'Recepción / Caja',
    descripcion = 'Alumnos, cobranza y mostrador',
    alcance_planteles = COALESCE(NULLIF(alcance_planteles, ''), 'solo_usuario'),
    departamento_default = COALESCE(NULLIF(departamento_default, ''), 'administrativo'),
    es_sistema = 1,
    activo = 1
WHERE clave = 'recepcion';

UPDATE roles SET
    nombre = 'Imagen / Diseño',
    descripcion = 'Personal de imagen sin alumnos ni caja',
    alcance_planteles = COALESCE(NULLIF(alcance_planteles, ''), 'solo_usuario'),
    departamento_default = COALESCE(NULLIF(departamento_default, ''), 'administrativo'),
    es_sistema = 1,
    activo = 1
WHERE clave = 'imagen';

UPDATE roles SET
    nombre = 'Marketing',
    descripcion = 'Estadísticas de captación y alumnos; sin caja',
    alcance_planteles = COALESCE(NULLIF(alcance_planteles, ''), 'todos'),
    departamento_default = COALESCE(NULLIF(departamento_default, ''), 'ventas'),
    es_sistema = 1,
    activo = 1
WHERE clave = 'marketing';

UPDATE roles SET nombre = 'Recepción / Caja (legacy)' WHERE clave = 'admin';

-- Forzar re-sync de privilegios en el siguiente bootstrap PHP
DELETE FROM hay_app_meta WHERE clave = 'rbac_jerarquia_v3_done';
