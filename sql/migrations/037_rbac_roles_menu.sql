-- Re-sincronizar privilegios por rol según organigrama CNCM (menú lateral).
-- Ejecutar reparación vía ensure_schema o rbac_db_reparar_roles_sistema.

UPDATE hay_meta SET meta_value = '0' WHERE meta_key = 'rbac_jerarquia_v3_done';

SELECT 1;
