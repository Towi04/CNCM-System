-- Módulo asesorías académicas (citas, créditos, tabulador, pago profesor)

CREATE TABLE IF NOT EXISTS asesoria_tabulador (
    id_tabulador INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_plantel INT UNSIGNED NULL,
    clave VARCHAR(48) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    monto_alumno DECIMAL(12,2) NOT NULL DEFAULT 0,
    monto_profesor DECIMAL(12,2) NOT NULL DEFAULT 0,
    vigente_desde DATE NULL,
    vigente_hasta DATE NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_tabulador),
    KEY idx_ase_tab_clave (clave, id_plantel, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profesor_asesoria_materia (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_usuario INT UNSIGNED NOT NULL,
    id_plantel INT UNSIGNED NOT NULL,
    id_especialidad INT UNSIGNED NULL,
    materia_clave VARCHAR(48) NOT NULL DEFAULT '',
    materia_nombre VARCHAR(120) NULL,
    nivel VARCHAR(24) NOT NULL DEFAULT 'general',
    puede_kids_dual TINYINT(1) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prof_ase_mat (id_usuario, id_plantel, materia_clave, nivel),
    KEY idx_prof_ase_esp (id_especialidad, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asesoria_credito (
    id_credito BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_alumno INT UNSIGNED NOT NULL,
    id_plantel INT UNSIGNED NOT NULL,
    origen VARCHAR(32) NOT NULL,
    horas_otorgadas DECIMAL(4,2) NOT NULL DEFAULT 1,
    horas_usadas DECIMAL(4,2) NOT NULL DEFAULT 0,
    solo_individual TINYINT(1) NOT NULL DEFAULT 0,
    vence_en DATE NULL,
    id_grupo INT UNSIGNED NULL,
    semana_falta DATE NULL,
    notas VARCHAR(500) NULL,
    id_usuario_otorga INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_credito),
    KEY idx_ase_cred_alum (id_alumno, id_plantel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asesoria_cita (
    id_cita BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_plantel INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    hora_inicio TINYINT UNSIGNED NOT NULL,
    id_profesor INT UNSIGNED NOT NULL,
    materia_clave VARCHAR(48) NOT NULL DEFAULT '',
    id_especialidad INT UNSIGNED NULL,
    tema VARCHAR(200) NOT NULL DEFAULT '',
    tipo ENUM(
        'falta_gratis','pagada_materia','pagada_cross','regularizacion','kids','kids_dual'
    ) NOT NULL DEFAULT 'pagada_materia',
    estado ENUM(
        'agendada','confirmada','impartida','np','cancelada_a_tiempo','reagendada','cancelada'
    ) NOT NULL DEFAULT 'agendada',
    max_alumnos TINYINT UNSIGNED NOT NULL DEFAULT 1,
    mismo_tema TINYINT(1) NOT NULL DEFAULT 1,
    moodle_verificado TINYINT(1) NOT NULL DEFAULT 0,
    costo_total_alumnos DECIMAL(12,2) NOT NULL DEFAULT 0,
    cancelada_en DATETIME NULL,
    motivo_cancelacion VARCHAR(500) NULL,
    confirmada_recepcion_en DATETIME NULL,
    id_usuario_agenda INT UNSIGNED NULL,
    id_autorizacion_mismo_dia INT UNSIGNED NULL,
    notas_internas VARCHAR(500) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_cita),
    KEY idx_ase_cita_fecha (id_plantel, fecha, estado),
    KEY idx_ase_cita_prof (id_profesor, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asesoria_cita_alumno (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_cita BIGINT UNSIGNED NOT NULL,
    id_alumno INT UNSIGNED NOT NULL,
    id_grupo INT UNSIGNED NULL,
    costo DECIMAL(12,2) NOT NULL DEFAULT 0,
    id_credito BIGINT UNSIGNED NULL,
    id_pago INT UNSIGNED NULL,
    asistio TINYINT(1) NULL,
    estado_cobro VARCHAR(24) NOT NULL DEFAULT 'pendiente',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cita_alumno (id_cita, id_alumno),
    KEY idx_ase_ca_alum (id_alumno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asesoria_pago_profesor (
    id_pago BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_cita BIGINT UNSIGNED NOT NULL,
    id_profesor INT UNSIGNED NOT NULL,
    concepto VARCHAR(200) NOT NULL,
    importe DECIMAL(12,2) NOT NULL,
    id_nomina_linea INT UNSIGNED NULL,
    liquidado TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pago),
    KEY idx_ase_pago_prof (id_profesor, liquidado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
