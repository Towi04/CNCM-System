-- Consultas de extracción desde cncmedum_legado (estructura: sql/cncmedum_legado.sql)
-- Destino HAY: preregistros / alumnos / alumno_pagos / grupos / alumno_grupos
-- Usar con la BD legado restaurada (p. ej. LEGACY_DB_NAME = cncmedum_legado).

-- =============================================================================
-- 1) ALUMNOS → preregistros (status vacío o 'Pre-Registro')
-- =============================================================================
SELECT
  a.id AS id_legacy,
  a.id_sucursal,
  a.id_especialidad,
  a.id_asesor_educativo,
  a.nombres,
  a.apellido_paterno,
  a.apellido_materno,
  a.fecha_nacimiento,
  a.edad,
  a.como_supiste_nosotros,
  a.domicilio,
  a.colonia,
  a.municipio,
  COALESCE(a.telefono, a.celular) AS telefono,
  a.celular AS telefono2,
  a.email,
  a.codigo_postal,
  a.ocupacion,
  a.grado_estudios,
  a.tutor AS padre_tutor,
  a.objetivo_inscripcion,
  a.enfermedad_cronica,
  a.observaciones,
  a.solicitud_factura,
  a.rfc,
  a.curp,
  a.telefono_general,
  a.razon_social,
  a.correo_general,
  a.domicilio_fiscal,
  a.status,
  a.created_at
FROM alumnos a
WHERE a.deleted_at IS NULL
  AND (a.status IS NULL OR TRIM(a.status) = '' OR a.status = 'Pre-Registro');

-- Destino HAY: preregistros
--   id_plantel        ← equivalencia sucursales.id → planteles
--   id_especialidad   ← equivalencia especialidades.id → especialidades
--   id_usuario_registro ← equivalencia users.id (id_asesor_educativo) → usuarios
--   estado            = 'pendiente'
--   medio_entero      ← map de como_supiste_nosotros
--   padre_tutor       ← tutor
--   requiere_factura  ← solicitud_factura
--   factura_*         ← rfc, curp, telefono_general, razon_social, correo_general, domicilio_fiscal

-- =============================================================================
-- 2) ALUMNOS → alumnos (status = 'Alumno')
-- =============================================================================
SELECT
  a.id AS id_legacy,
  a.id_sucursal,
  a.id_especialidad,
  COALESCE(NULLIF(a.nuevo_numero_control, 0), a.numero_control) AS numero_control,
  a.nombres,
  a.apellido_paterno,
  a.apellido_materno,
  a.foto,
  a.email,
  COALESCE(a.telefono, a.celular) AS telefono,
  a.forma_pago,
  a.created_at AS fecha_alta,
  a.status
FROM alumnos a
WHERE a.deleted_at IS NULL
  AND a.status = 'Alumno';

-- Destino HAY: alumnos
--   numero_control  ← nuevo_numero_control (si > 0) o numero_control
--   estado          = 'activo'
--   forma_pago      ← 'semanal' | 'mensual' (default mensual)
--   id_plantel / id_especialidad ← equivalencias

-- =============================================================================
-- 3) GRUPOS (clave + especialidad)
-- =============================================================================
-- Columnas reales en cncmedum_legado.grupos:
--   id, id_sucursal, id_especialidad, horario, dias, infantil, fecha_inicio,
--   precio_*, status, clave, max_alumnos
-- NO existen: especialidad (texto), horario_texto, id_profesor, aula

SELECT
  g.id AS id_legacy,
  g.id_sucursal,
  g.id_especialidad,
  e.nombre AS especialidad_nombre,
  g.clave,
  g.fecha_inicio,
  g.horario,
  g.dias,
  TRIM(CONCAT(COALESCE(g.horario, ''), ' ', COALESCE(g.dias, ''))) AS horario_texto,
  g.status,
  g.infantil,
  g.max_alumnos
FROM grupos g
LEFT JOIN especialidades e ON e.id = g.id_especialidad
ORDER BY g.id;

-- Destino HAY: grupos
--   clave           ← g.clave (si vacía: LEG-G{id}); al importar se puede generar clave CNCM nueva
--   clave_anterior  ← clave legado (asistente de migración)
--   id_especialidad ← equivalencia especialidades.id
--   id_plantel      ← equivalencia sucursales.id
--   horario_texto   ← horario + ' ' + dias
--   fecha_inicio    ← DATE(fecha_inicio)

-- =============================================================================
-- 4) ALUMNOS ↔ GRUPOS
-- =============================================================================
SELECT
  ag.id,
  ag.id_alumno,
  ag.id_grupo,
  ag.fecha_inicio,
  ag.fecha_final,
  ag.status
FROM alumnos_grupos ag
WHERE ag.status IS NULL
   OR ag.status = ''
   OR ag.status = 'Inscrito';

-- Destino HAY: alumno_grupos (activo=1)

-- =============================================================================
-- 5) PAGOS: colegiatura / inscripción (fecha + quien recibió)
-- =============================================================================
-- Modelo legado:
--   alumnos_pagos = cargo/concepto (inscripción, colegiatura, etc.)
--   pagos         = recibo (folio, fecha, id_recibio, monto, forma_pago)
--   abonos        = desglose del recibo ligado a un cargo (id_alumno_pago)

SELECT
  a.id AS id_abono,
  p.id AS id_pago,
  p.folio,
  p.fecha,
  p.forma_pago,
  p.id_alumno,
  p.id_especialidad,
  p.id_recibio,
  CONCAT(u.nombres, ' ', u.apellido_paterno, ' ', COALESCE(u.apellido_materno, '')) AS recibio_nombre,
  a.monto AS monto_abono,
  ap.concepto,
  ap.tipo AS tipo_cargo,
  ap.mes,
  ap.semana,
  ap.anio,
  ap.modalidad,
  CASE
    WHEN LOWER(COALESCE(ap.concepto, '')) LIKE '%inscrip%' THEN 'inscripcion'
    WHEN LOWER(COALESCE(ap.concepto, '')) LIKE '%colegiat%'
      OR LOWER(COALESCE(ap.concepto, '')) LIKE '%mensual%' THEN 'mensualidad'
    WHEN LOWER(COALESCE(ap.concepto, '')) LIKE '%semanal%' THEN 'semanal'
    ELSE 'abono'
  END AS tipo_hay
FROM abonos a
INNER JOIN pagos p ON p.id = a.id_pago
LEFT JOIN alumnos_pagos ap ON ap.id = a.id_alumno_pago
LEFT JOIN users u ON u.id = p.id_recibio
WHERE a.deleted_at IS NULL
  AND p.deleted_at IS NULL
ORDER BY a.id;

-- Destino HAY: alumno_pagos
--   monto       ← abonos.monto
--   fecha_pago / creado_en ← pagos.fecha (o abonos.created_at)
--   id_usuario  ← equivalencia users.id (pagos.id_recibio)
--   folio       ← 'LEG-' + pagos.folio
--   concepto    ← alumnos_pagos.concepto
--   tipo        ← inscripcion | mensualidad | semanal | abono
--   forma_pago  ← pagos.forma_pago (o 'Efectivo' si se normaliza)

-- Pagos sin abonos desglosados:
SELECT
  p.id AS id_pago,
  p.folio,
  p.fecha,
  p.monto,
  p.forma_pago,
  p.id_alumno,
  p.id_especialidad,
  p.id_recibio,
  CONCAT(u.nombres, ' ', u.apellido_paterno, ' ', COALESCE(u.apellido_materno, '')) AS recibio_nombre
FROM pagos p
LEFT JOIN users u ON u.id = p.id_recibio
WHERE p.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM abonos a2 WHERE a2.id_pago = p.id AND a2.deleted_at IS NULL)
ORDER BY p.id;

-- =============================================================================
-- 6) PRODUCTOS (ventas de mostrador)
-- =============================================================================
SELECT
  v.id AS id_venta,
  v.folio,
  v.fecha,
  v.total,
  v.forma_pago,
  v.id_alumno,
  v.id_sucursal,
  v.id_recibio,
  CONCAT(u.nombres, ' ', u.apellido_paterno, ' ', COALESCE(u.apellido_materno, '')) AS recibio_nombre,
  v.status,
  v.nombre AS cliente_nombre,
  pv.id_producto,
  pr.nombre AS producto_nombre,
  pv.cantidad,
  pv.precio,
  pv.total AS partida_total
FROM ventas v
INNER JOIN partidas_ventas pv ON pv.id_venta = v.id
LEFT JOIN productos pr ON pr.id = pv.id_producto
LEFT JOIN users u ON u.id = v.id_recibio
WHERE (pr.deleted_at IS NULL OR pr.id IS NULL)
ORDER BY v.id, pv.id;

-- Destino HAY (opcional): alumno_pagos con tipo='producto'
--   (el importador PHP actual no migra ventas; usar esta consulta si se cargan a mano)

-- =============================================================================
-- 7) ESPECIALIDAD por alumno (tarifas)
-- =============================================================================
SELECT
  ae.id,
  ae.id_alumno,
  ae.id_especialidad,
  ae.fecha_inicio,
  ae.forma_pago,
  ae.monto,
  ae.monto_pronto_pago,
  ae.semanas_cursar,
  ae.semanas_cursadas,
  ae.semanas_pagadas,
  ae.status
FROM alumnos_especialidades ae
ORDER BY ae.id;

-- Destino HAY: alumno_especialidades
