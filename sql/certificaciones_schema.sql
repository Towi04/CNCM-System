-- Certificaciones v2: familias de flujo, confirmación supervisor, credenciales y reagendamiento
-- Se aplica automáticamente vía certificacion_ensure_schema() en PHP.

-- productos.es_certificacion = 1 para aparecer en el módulo
-- producto_certificacion.familia: cambridge_online | cambridge_presencial | uks | toefl | oxford | itep | certiport
-- certificacion_solicitudes: fecha/hora solicitada, confirmada, sede, reagendamientos
-- certificacion_accesos: credenciales únicas por alumno (usuario, voucher, zoom, etc.)
-- certificacion_reagendamientos: historial; invalida accesos previos (~90% requieren nuevos datos)
-- certificacion_documentos: INE, reglamento firmado, etc.

-- Especialidad CERT: sin fases ni parciales; certificacion_seed_especialidad()
