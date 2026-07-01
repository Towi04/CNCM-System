-- Migración 006: roles CNCM, tablas RBAC/planteles, normalización usuarios.rol
-- Ejecutar una vez en producción (phpMyAdmin o hay_schema_migrate).

-- Meta
CREATE TABLE IF NOT EXISTS hay_app_meta (
    clave VARCHAR(64) NOT NULL,
    valor VARCHAR(255) NOT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles y privilegios (si faltan)
CREATE TABLE IF NOT EXISTS roles (
    id_rol INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clave VARCHAR(40) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion TEXT NULL,
    acceso_total TINYINT(1) NOT NULL DEFAULT 0,
    alcance_planteles VARCHAR(20) NOT NULL DEFAULT 'solo_usuario',
    departamento_default VARCHAR(40) NULL,
    es_sistema TINYINT(1) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_rol),
    UNIQUE KEY uq_roles_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_privilegios (
    id_rol INT UNSIGNED NOT NULL,
    privilegio VARCHAR(64) NOT NULL,
    PRIMARY KEY (id_rol, privilegio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_planteles (
    id_rol INT UNSIGNED NOT NULL,
    id_plantel INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_rol, id_plantel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_privilegios (
    id_usuario INT UNSIGNED NOT NULL,
    privilegio VARCHAR(64) NOT NULL,
    tipo ENUM('otorgar','denegar') NOT NULL DEFAULT 'otorgar',
    vigente_hasta DATE NULL,
    motivo VARCHAR(255) NULL,
    id_usuario_otorga INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario, privilegio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_planteles (
    id_usuario INT UNSIGNED NOT NULL,
    id_plantel INT UNSIGNED NOT NULL,
    vigente_hasta DATE NULL,
    motivo VARCHAR(255) NULL,
    id_usuario_otorga INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario, id_plantel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Normalizar valores legacy en usuarios.rol antes de ampliar ENUM
UPDATE usuarios SET rol = 'admin' WHERE rol IN ('recepcion', 'imagen');
UPDATE usuarios SET rol = 'coordinador' WHERE rol = 'coordinacion';

-- Ampliar ENUM (MySQL: MODIFY con lista completa)
ALTER TABLE usuarios
    MODIFY COLUMN rol ENUM(
        'supervisor','director','gerente','coordinador','admin',
        'asesor','profesor','alumno'
    ) NOT NULL DEFAULT 'profesor';

-- Columna id_rol (ignorar error si ya existe — lo maneja PHP)
ALTER TABLE usuarios ADD COLUMN id_rol INT UNSIGNED NULL AFTER rol;

-- Roles del sistema (insertar faltantes)
INSERT IGNORE INTO roles (clave, nombre, descripcion, acceso_total, alcance_planteles, departamento_default, es_sistema, activo, orden) VALUES
('supervisor', 'Supervisora / Dirección general', 'Rol del sistema', 1, 'todos', 'administrativo', 1, 1, 0),
('director', 'Director de plantel', 'Rol del sistema', 0, 'solo_usuario', 'administrativo', 1, 1, 1),
('gerente', 'Gerente de ventas', 'Rol del sistema', 0, 'todos', 'ventas', 1, 1, 2),
('coordinador', 'Coordinador académico', 'Rol del sistema', 0, 'solo_usuario', 'ingles', 1, 1, 3),
('admin', 'Recepción / Caja', 'Rol del sistema', 0, 'solo_usuario', 'administrativo', 1, 1, 4),
('profesor', 'Profesor', 'Rol del sistema', 0, 'solo_usuario', 'ingles', 1, 1, 5),
('asesor', 'Asesor de ventas', 'Rol del sistema', 0, 'solo_usuario', 'ventas', 1, 1, 6),
('alumno', 'Alumno', 'Rol del sistema', 0, 'solo_usuario', 'administrativo', 1, 1, 7);

-- Supervisor siempre acceso total
UPDATE roles SET acceso_total = 1, alcance_planteles = 'todos' WHERE clave = 'supervisor';
UPDATE roles SET alcance_planteles = 'solo_usuario' WHERE clave IN ('director','coordinador','admin','asesor','profesor','alumno');
UPDATE roles SET alcance_planteles = 'todos' WHERE clave = 'gerente';

-- Vincular usuarios.id_rol
UPDATE usuarios u
INNER JOIN roles r ON r.clave = u.rol AND r.activo = 1
SET u.id_rol = r.id_rol
WHERE u.rol IS NOT NULL AND u.rol <> '';

-- Forzar resincronización de privilegios vía PHP (meta flag)
DELETE FROM hay_app_meta WHERE clave = 'rbac_jerarquia_v3_done';

INSERT INTO hay_app_meta (clave, valor) VALUES
('schema_sql_006_production_roles_schema', '1'),
('schema_bootstrap_version', '6'),
('schema_ddl_runtime', '0')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);
