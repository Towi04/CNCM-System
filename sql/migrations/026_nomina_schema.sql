-- Nómina institucional: configuración de pago por persona y liquidaciones
CREATE TABLE IF NOT EXISTS personal_pago_config (
    id_config INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_usuario INT UNSIGNED NOT NULL,
    id_plantel INT UNSIGNED NOT NULL,
    tipo_pago ENUM('fijo_quincena','fijo_mes','por_hora','tabulador_asesor','nivel_hay') NOT NULL DEFAULT 'fijo_quincena',
    monto_fijo DECIMAL(12,2) NULL,
    tarifa_hora DECIMAL(12,2) NULL,
    id_hay_nivel INT UNSIGNED NULL,
    id_hay_area INT UNSIGNED NULL,
    notas VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_config),
    UNIQUE KEY uq_ppc_usuario_plantel (id_usuario, id_plantel),
    KEY idx_ppc_plantel (id_plantel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nomina_liquidacion (
    id_liquidacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_plantel INT UNSIGNED NOT NULL,
    tipo_periodo ENUM('semana','quincena','mes') NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    etiqueta VARCHAR(120) NULL,
    estado ENUM('borrador','cerrada') NOT NULL DEFAULT 'borrador',
    total DECIMAL(14,2) NOT NULL DEFAULT 0,
    creado_por INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_liquidacion),
    UNIQUE KEY uq_nomina_periodo (id_plantel, tipo_periodo, fecha_inicio, fecha_fin),
    KEY idx_nomina_plantel (id_plantel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nomina_linea (
    id_linea INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_liquidacion INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    rol VARCHAR(40) NULL,
    area_nombre VARCHAR(80) NULL,
    nivel_nombre VARCHAR(80) NULL,
    tipo_pago VARCHAR(40) NULL,
    concepto VARCHAR(255) NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL DEFAULT 1,
    tarifa DECIMAL(12,2) NOT NULL DEFAULT 0,
    importe DECIMAL(12,2) NOT NULL DEFAULT 0,
    detalle_json JSON NULL,
    PRIMARY KEY (id_linea),
    KEY idx_nl_liquidacion (id_liquidacion),
    KEY idx_nl_usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
