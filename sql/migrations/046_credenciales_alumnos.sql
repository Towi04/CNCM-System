-- Datos oficiales por plantel y credenciales de alumnos.

ALTER TABLE planteles ADD COLUMN cct VARCHAR(40) NULL AFTER logo_url;
ALTER TABLE planteles ADD COLUMN rvoe VARCHAR(80) NULL AFTER cct;
ALTER TABLE planteles ADD COLUMN prepa_nombre_sep VARCHAR(160) NULL AFTER rvoe;
ALTER TABLE planteles ADD COLUMN prepa_cct VARCHAR(40) NULL AFTER prepa_nombre_sep;
ALTER TABLE planteles ADD COLUMN prepa_rvoe VARCHAR(80) NULL AFTER prepa_cct;
ALTER TABLE planteles ADD COLUMN prepa_logo_url VARCHAR(255) NULL AFTER prepa_rvoe;
ALTER TABLE planteles ADD COLUMN prepa_direccion VARCHAR(255) NULL AFTER prepa_logo_url;

CREATE TABLE IF NOT EXISTS credencial_plantilla (
  id_plantilla INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_plantel INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  ancho_mm DECIMAL(6,2) NOT NULL DEFAULT 85.60,
  alto_mm DECIMAL(6,2) NOT NULL DEFAULT 54.00,
  fondo_frente_path VARCHAR(255) NULL,
  fondo_reverso_path VARCHAR(255) NULL,
  campos_frente_json JSON NULL,
  campos_reverso_json JSON NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  actualizado_por INT UNSIGNED NULL,
  PRIMARY KEY (id_plantilla),
  KEY idx_credencial_plantel (id_plantel, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS alumno_credencial (
  id_credencial INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_alumno INT UNSIGNED NOT NULL,
  id_plantel INT UNSIGNED NOT NULL,
  id_plantilla INT UNSIGNED NOT NULL,
  numero_control VARCHAR(50) NOT NULL,
  token_verificacion CHAR(32) NOT NULL,
  vigencia_inicio DATE NOT NULL,
  vigencia_fin DATE NOT NULL,
  especialidad_nombre VARCHAR(180) NULL,
  nombre_completo VARCHAR(200) NOT NULL,
  foto_path VARCHAR(255) NULL,
  pdf_path VARCHAR(255) NULL,
  generado_por INT UNSIGNED NULL,
  generado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_credencial),
  UNIQUE KEY uq_credencial_token (token_verificacion),
  KEY idx_credencial_alumno (id_alumno, generado_en),
  KEY idx_credencial_control (numero_control, activo),
  KEY idx_credencial_plantel (id_plantel, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT id_rol, 'menu_credenciales' FROM roles WHERE clave IN ('admin', 'director', 'supervisor');

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT id_rol, 'menu_credenciales_diseno' FROM roles WHERE clave = 'supervisor';
