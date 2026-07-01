-- Corregir avatares legacy que apuntan a default_avatar.png (no existe en el proyecto nuevo)
UPDATE usuarios
SET avatar = 'src/icono.png'
WHERE avatar IS NULL
   OR avatar = ''
   OR avatar = 'default_avatar.png'
   OR avatar LIKE '%default_avatar%';
