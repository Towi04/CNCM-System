-- Acuerdo escolar versionado y aceptación por alumno.



CREATE TABLE IF NOT EXISTS acuerdo_escolar_version (

    id_acuerdo_version INT UNSIGNED NOT NULL AUTO_INCREMENT,

    version_label VARCHAR(40) NOT NULL,

    contenido MEDIUMTEXT NOT NULL,

    vigente_desde DATE NULL,

    activo_para_nuevos TINYINT(1) NOT NULL DEFAULT 0,

    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    id_usuario INT UNSIGNED NULL,

    PRIMARY KEY (id_acuerdo_version),

    KEY idx_aev_activo (activo_para_nuevos, id_acuerdo_version)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS alumno_acuerdo_aceptacion (

    id_aceptacion INT UNSIGNED NOT NULL AUTO_INCREMENT,

    id_alumno INT UNSIGNED NOT NULL,

    id_acuerdo_version INT UNSIGNED NOT NULL,

    fecha_aceptacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    ip VARCHAR(45) NULL,

    id_usuario INT UNSIGNED NULL,

    PRIMARY KEY (id_aceptacion),

    UNIQUE KEY uq_aaa_alumno_version (id_alumno, id_acuerdo_version),

    KEY idx_aaa_version (id_acuerdo_version)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



ALTER TABLE alumnos ADD COLUMN acuerdo_pendiente_version INT UNSIGNED NULL AFTER perfil_completado_en;



INSERT IGNORE INTO role_privilegios (id_rol, privilegio)

SELECT r.id_rol, 'menu_supervisor_acuerdo'

FROM roles r

WHERE r.clave = 'supervisor' AND r.activo = 1;



SELECT 1;


