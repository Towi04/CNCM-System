# 419 Página caducada (móvil) — ADN / cncm_legado

## Causa típica
Laravel no confía en el proxy TLS → cree que la petición es HTTP → cookie de sesión / CSRF inconsistente. En celulares falla más que en PC.

## En el servidor (`/var/www/adn` o donde viva ADN)

1. Aplique el código de esta carpeta (o al menos `TrustProxies` + `config/session.php`).
2. En `.env`:

```env
APP_URL=https://adn.cncm.edu.mx
SESSION_DRIVER=file
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
# SESSION_COOKIE=adn_session
```

3. Limpie caché y permisos:

```bash
cd /var/www/adn
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan view:clear
```

4. Pruebe en el celular: ventana privada → `https://adn.cncm.edu.mx` → login.
