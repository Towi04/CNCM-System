-- Especialidades infantiles y tarifas de combo infantil.
UPDATE especialidades SET nombre = 'Inglés Infantil' WHERE clave = 'ING-K';
UPDATE especialidades SET nombre = 'Computación Infantil' WHERE clave = 'COMP-K';

INSERT IGNORE INTO regla_combo_tarifa (id_regla, id_especialidad, costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal)
SELECT r.id_regla, e.id_especialidad, e.costo_inscripcion, e.costo_mensualidad, e.costo_pronto_pago, e.costo_semanal
FROM reglas_colegiatura_combo r
CROSS JOIN especialidades e
WHERE r.activo = 1
  AND (r.claves_combo LIKE '%ING-K%' OR r.claves_combo LIKE '%COMP-K%')
  AND e.clave IN ('ING-K', 'COMP-K');

SELECT 1;
