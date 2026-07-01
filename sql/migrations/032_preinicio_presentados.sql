-- Phase 2: contacto pre-inicio + reporte presentados RBAC
CREATE TABLE IF NOT EXISTS grupo_preinicio_contacto (
    id_contacto INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_plantel INT UNSIGNED NOT NULL,
    id_grupo INT UNSIGNED NOT NULL,
    id_alumno INT UNSIGNED NOT NULL,
    contactado TINYINT(1) NOT NULL DEFAULT 0,
    fecha_contacto DATETIME NULL,
    medio ENUM('telefono','whatsapp','presencial','correo','otro') NULL,
    notas VARCHAR(500) NULL,
    id_usuario_registro INT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_contacto),
    UNIQUE KEY uq_gpc_grupo_alumno (id_grupo, id_alumno),
    KEY idx_gpc_plantel (id_plantel),
    KEY idx_gpc_grupo (id_grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'menu_reporte_presentados' FROM roles r WHERE r.clave IN ('gerente', 'supervisor', 'admin', 'director');

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, 'menu_asesor_preinicio' FROM roles r WHERE r.clave IN ('asesor', 'gerente', 'supervisor', 'admin', 'director');

SELECT 1;
