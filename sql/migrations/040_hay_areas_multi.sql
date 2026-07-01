-- Multi-área HAY: un usuario, varias áreas (evaluación, nómina, Moodle por área).
-- La migración de datos la ejecuta hay_eval_migrate_multi_area() en bootstrap.

UPDATE hay_meta SET meta_value = '0' WHERE meta_key = 'rbac_jerarquia_v3_done';

SELECT 1;
