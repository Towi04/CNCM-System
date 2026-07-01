-- Promover usuarios a rol supervisor (acceso multi-plantel y menú completo).
-- Ejecutar una vez en producción tras desplegar rbac_helper.php.

UPDATE usuarios
SET rol = 'supervisor'
WHERE username IN ('englishcoordinator')
  AND rol <> 'supervisor';

-- Diana (supervisora sin usuario aún): crear cuenta cuando tengan correo/username.
