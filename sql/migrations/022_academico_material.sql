-- Catálogo de materiales institucionales para RAG del tutor (libros, Moodle, PDF)
CREATE TABLE IF NOT EXISTS academico_material (
    id_material INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('libro_alumno','libro_profesor','workbook','studentbook','guia_profesor','moodle_actividad','pdf_fragmento','otro') NOT NULL DEFAULT 'otro',
    id_especialidad INT UNSIGNED NULL,
    id_fase INT UNSIGNED NULL,
    semana TINYINT UNSIGNED NULL,
    pagina_inicio SMALLINT UNSIGNED NULL,
    pagina_fin SMALLINT UNSIGNED NULL,
    titulo VARCHAR(220) NOT NULL,
    descripcion TEXT NULL,
    contenido_texto MEDIUMTEXT NULL,
    ruta_archivo VARCHAR(500) NULL,
    moodle_course_id INT UNSIGNED NULL,
    moodle_cm_id INT UNSIGNED NULL,
    moodle_url VARCHAR(500) NULL,
    etiquetas VARCHAR(500) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_material),
    KEY idx_mat_esp (id_especialidad, activo),
    KEY idx_mat_fase_sem (id_fase, semana),
    KEY idx_mat_tipo (tipo, activo),
    KEY idx_mat_pagina (pagina_inicio, pagina_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 1;
