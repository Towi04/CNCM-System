-- Correo institucional y recuperación de contraseña
-- Ejecutar una vez en la base cncmedum_hay_system

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS email VARCHAR(120) NULL DEFAULT NULL AFTER username;

-- MySQL < 8.0: omitir IF NOT EXISTS y ejecutar solo si la columna no existe
-- ALTER TABLE usuarios ADD COLUMN email VARCHAR(120) NULL DEFAULT NULL AFTER username;

CREATE UNIQUE INDEX IF NOT EXISTS idx_usuarios_email ON usuarios (email);

CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token_hash),
  INDEX idx_user (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Opcional: rellenar correo a partir del username para usuarios existentes
-- UPDATE usuarios SET email = CONCAT(LOWER(username), '@cncm.edu.mx') WHERE email IS NULL OR email = '';
