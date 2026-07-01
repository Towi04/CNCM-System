-- Phase 3: escuelas externas, visitas y origen cartas
CREATE TABLE IF NOT EXISTS escuelas_externas (
    id_escuela INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_plantel INT UNSIGNED NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    direccion VARCHAR(255) NULL,
    colonia VARCHAR(120) NULL,
    municipio VARCHAR(120) NULL,
    contacto_nombre VARCHAR(160) NULL,
    contacto_telefono VARCHAR(30) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_escuela),
    KEY idx_ee_plantel (id_plantel, activo),
    KEY idx_ee_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS escuela_visita (
    id_visita INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_plantel INT UNSIGNED NOT NULL,
    id_escuela INT UNSIGNED NOT NULL,
    id_usuario_asesor INT UNSIGNED NOT NULL,
    fecha_visita DATE NOT NULL,
    cartas_entregadas INT UNSIGNED NOT NULL DEFAULT 0,
    notas VARCHAR(500) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_visita),
    KEY idx_ev_plantel_fecha (id_plantel, fecha_visita),
    KEY idx_ev_escuela (id_escuela)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE preregistros ADD COLUMN id_escuela_origen INT UNSIGNED NULL AFTER medio_entero_otro;
ALTER TABLE alumnos ADD COLUMN id_escuela_origen INT UNSIGNED NULL AFTER id_especialidad;

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'menu_gerente_escuelas' FROM roles r WHERE r.clave IN ('gerente', 'supervisor', 'admin');

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'menu_reporte_escuelas' FROM roles r WHERE r.clave IN ('gerente', 'supervisor', 'admin', 'director');

SELECT 1;
