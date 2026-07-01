-- Privilegios de menú para supervisor (incluye pre-registro).
UPDATE roles SET acceso_total = 1, alcance_planteles = 'todos' WHERE clave = 'supervisor';

INSERT IGNORE INTO role_privilegios (id_rol, privilegio)
SELECT r.id_rol, p.privilegio
FROM roles r
CROSS JOIN (
    SELECT 'menu_preregistro' AS privilegio UNION ALL
    SELECT 'menu_ventas' UNION ALL
    SELECT 'menu_entrevistas' UNION ALL
    SELECT 'menu_grupos_fases' UNION ALL
    SELECT 'menu_cert_preregistro' UNION ALL
    SELECT 'menu_reporte_inscritos' UNION ALL
    SELECT 'menu_comisiones_consulta' UNION ALL
    SELECT 'menu_comisiones_admin' UNION ALL
    SELECT 'menu_caja' UNION ALL
    SELECT 'menu_consulta_adeudo' UNION ALL
    SELECT 'menu_punto_venta' UNION ALL
    SELECT 'menu_venta_productos' UNION ALL
    SELECT 'menu_certificaciones' UNION ALL
    SELECT 'menu_reportes' UNION ALL
    SELECT 'menu_alumnos' UNION ALL
    SELECT 'menu_academico' UNION ALL
    SELECT 'menu_grupos' UNION ALL
    SELECT 'menu_especialidades' UNION ALL
    SELECT 'menu_asistencia' UNION ALL
    SELECT 'menu_admin' UNION ALL
    SELECT 'menu_mi_evaluacion' UNION ALL
    SELECT 'menu_matriz_entrenamiento' UNION ALL
    SELECT 'menu_soporte'
) p
WHERE r.clave = 'supervisor';

SELECT 1;
