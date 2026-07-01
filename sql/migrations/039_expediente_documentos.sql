-- Expediente documental: requisitos configurables y entregas (SEP, candidatos, personal).
-- Las tablas se crean también vía expediente_documental_ensure_schema() en bootstrap.

CREATE TABLE IF NOT EXISTS expediente_requisito (
    id_requisito INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_plantel INT UNSIGNED NULL,
    clave VARCHAR(40) NOT NULL,
    nombre VARCHAR(160) NOT NULL,
    descripcion TEXT NULL,
    categoria ENUM('general','candidato_profesor','profesor','alumno_sep','personal') NOT NULL DEFAULT 'general',
    roles_json JSON NULL,
    obligatorio TINYINT(1) NOT NULL DEFAULT 1,
    tipo_verificacion ENUM('documento','certificacion','examen_moodle') NOT NULL DEFAULT 'documento',
    moodle_course_id INT UNSIGNED NULL,
    umbral_aprobacion DECIMAL(5,2) NULL DEFAULT 70.00,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_requisito),
    UNIQUE KEY uq_exp_req_plantel_clave (id_plantel, clave),
    KEY idx_exp_req_cat (categoria, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expediente_entrega (
    id_entrega INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_requisito INT UNSIGNED NOT NULL,
    id_plantel INT UNSIGNED NOT NULL,
    tipo_entidad ENUM('usuario','alumno','prospecto') NOT NULL,
    id_entidad INT UNSIGNED NOT NULL,
    ruta VARCHAR(255) NULL,
    nombre_original VARCHAR(200) NULL,
    estado ENUM('pendiente','aprobado','rechazado','exento') NOT NULL DEFAULT 'pendiente',
    puntaje DECIMAL(6,2) NULL,
    origen_puntaje ENUM('documento','moodle','manual') NULL,
    comentario_rechazo TEXT NULL,
    moodle_inscrito TINYINT(1) NOT NULL DEFAULT 0,
    id_usuario_subio INT UNSIGNED NULL,
    id_usuario_evaluo INT UNSIGNED NULL,
    evaluado_en DATETIME NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_entrega),
    UNIQUE KEY uq_exp_entrega (id_requisito, tipo_entidad, id_entidad),
    KEY idx_exp_ent_plantel (id_plantel, estado),
    KEY idx_exp_ent_entidad (tipo_entidad, id_entidad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE hay_meta SET meta_value = '0' WHERE meta_key = 'rbac_jerarquia_v3_done';

SELECT 1;
