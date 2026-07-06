# LuisInstaTelegram 1.0.0

Bot de Telegram en PHP para recibir enlaces públicos de Instagram, resolver los medios mediante RapidAPI `instagram-looter2` y devolver por Telegram las fotos y/o videos del post.

Esta versión está basada en el archivo productivo `peuigbot.php` que ya estaba funcionando en el servidor. Se saneó para publicar en GitHub sin tokens, API keys, cookies ni logs reales.

---

## Qué hace

- Recibe mensajes de Telegram por webhook.
- Detecta enlaces de Instagram:
  - `https://www.instagram.com/p/...`
  - `https://www.instagram.com/reel/...`
  - `https://www.instagram.com/reels/...`
  - `https://www.instagram.com/tv/...`
  - `https://instagr.am/p/...`
- Extrae el shortcode del post.
- Consulta RapidAPI usando `instagram-looter2.p.rapidapi.com`.
- Interpreta respuestas tipo `GraphImage`, `GraphVideo` y `GraphSidecar`.
- Envía fotos con `sendPhoto`.
- Envía videos con `sendVideo`.
- Soporta carruseles de imágenes/videos.
- Responde comandos:
  - `/start`
  - `/id`
  - `/version`
- Registra logs locales en `storage/bot_log.txt`.
- Evita registrar tokens y API keys en logs.

---

## Qué necesitás

- Servidor Linux propio, por ejemplo Ubuntu Server 22.04/24.04.
- Apache 2.4 o Nginx.
- PHP 8.0+.
- Extensiones PHP:
  - `curl`
  - `json`
- Dominio o subdominio público apuntando al servidor.
- HTTPS válido.
- Bot creado con BotFather.
- Cuenta/API key de RapidAPI con acceso a `instagram-looter2`.

En Ubuntu con Apache:

```bash
sudo apt update
sudo apt install -y apache2 php php-cli php-curl php-json libapache2-mod-php curl git
```

Validar:

```bash
php -v
php -m | grep -E 'curl|json'
```

---

## Crear el bot en Telegram

1. Abrí Telegram.
2. Buscá `@BotFather`.
3. Enviá:

```text
/newbot
```

4. Elegí nombre visible, por ejemplo:

```text
LuisInstaTelegram
```

5. Elegí username terminado en `bot`, por ejemplo:

```text
LuisInstaTelegram_bot
```

6. BotFather devuelve un token similar a:

```text
123456789:ABCDEF_REEMPLAZAR
```

Ese token va en `config.txt` como `token:`.

---

## Obtener el chat ID

### Método recomendado con el propio bot

Una vez configurado el webhook, mandale al bot:

```text
/id
```

El bot responde:

```text
Chat ID: 123456789
```

### Método alternativo con getUpdates

Antes de configurar webhook:

```bash
BOT_TOKEN="TU_TOKEN"
curl "https://api.telegram.org/bot${BOT_TOKEN}/getUpdates"
```

Buscá:

```json
"chat": {
  "id": 123456789
}
```

En grupos el ID suele ser negativo, por ejemplo `-1001234567890`.

---

## Instalar en el servidor

Ejemplo usando `/opt/LuisInstaTelegram`:

```bash
cd /opt
sudo git clone https://github.com/luistombo/LuisInstaTelegram.git
sudo chown -R $USER:www-data /opt/LuisInstaTelegram
cd /opt/LuisInstaTelegram
```

Crear configuración:

```bash
cp config.example.txt config.txt
nano config.txt
```

Formato esperado:

```ini
token: TU_TOKEN_REAL_DE_BOTFATHER
chat_id: TU_CHAT_ID
third_party_api_key: TU_RAPIDAPI_KEY
webhook_secret: UN_SECRETO_LARGO_RANDOM
```

Permisos:

```bash
sudo chown root:www-data /opt/LuisInstaTelegram/config.txt
sudo chmod 640 /opt/LuisInstaTelegram/config.txt

sudo chown -R www-data:www-data /opt/LuisInstaTelegram/storage
sudo chmod 750 /opt/LuisInstaTelegram/storage
```

Validar sintaxis:

```bash
php -l /opt/LuisInstaTelegram/public/peuigbot.php
```

---

## Publicar con Apache en puerto 8443

Telegram soporta webhooks en puertos `443`, `80`, `88` y `8443`.

Habilitar módulos:

```bash
sudo a2enmod ssl rewrite headers
sudo systemctl restart apache2
```

Editar puertos:

```bash
sudo nano /etc/apache2/ports.conf
```

Agregar:

```apache
Listen 8443
```

Crear sitio:

```bash
sudo nano /etc/apache2/sites-available/luisinstatelegram.conf
```

Ejemplo:

```apache
<VirtualHost *:8443>
    ServerName bot.tudominio.com

    DocumentRoot /opt/LuisInstaTelegram/public

    <Directory /opt/LuisInstaTelegram/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/luisinstatelegram_error.log
    CustomLog ${APACHE_LOG_DIR}/luisinstatelegram_access.log combined

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/bot.tudominio.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/bot.tudominio.com/privkey.pem
</VirtualHost>
```

Activar:

```bash
sudo a2ensite luisinstatelegram.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Firewall:

```bash
sudo ufw allow 8443/tcp
```

---

## HTTPS con Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d bot.tudominio.com
```

Probar:

```bash
curl -i https://bot.tudominio.com:8443/peuigbot.php
```

Sin update de Telegram debería responder `OK` o mensaje simple, o al menos no dar error 500.

---

## Configurar webhook de Telegram

```bash
BOT_TOKEN="TU_TOKEN_REAL"
WEBHOOK_SECRET="EL_MISMO_webhook_secret_DE_config_txt"
WEBHOOK_URL="https://bot.tudominio.com:8443/peuigbot.php"

curl -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
  -d "url=${WEBHOOK_URL}" \
  -d "secret_token=${WEBHOOK_SECRET}" \
  -d "drop_pending_updates=true"
```

Verificar:

```bash
curl "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo"
```

Revisar:

- `url`
- `pending_update_count`
- `last_error_message`

---

## Probar

En Telegram:

```text
/start
/id
/version
```

Luego enviar:

```text
https://www.instagram.com/reel/SHORTCODE/
```

El bot debería:

1. responder que está procesando;
2. consultar RapidAPI;
3. enviar cada foto/video;
4. finalizar con mensaje de éxito.

---

## Logs

```bash
sudo tail -f /opt/LuisInstaTelegram/storage/bot_log.txt
sudo tail -f /var/log/apache2/luisinstatelegram_error.log
```

---

## Diagnóstico rápido

### Error 500

```bash
php -l /opt/LuisInstaTelegram/public/peuigbot.php
sudo tail -n 100 /var/log/apache2/error.log
```

### No responde Telegram

```bash
curl "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo"
```

### Permisos

```bash
ls -l /opt/LuisInstaTelegram/config.txt
ls -ld /opt/LuisInstaTelegram/storage
```

### Apache escuchando

```bash
sudo ss -tulpen | grep 8443
```

---

## Seguridad

No commitear nunca:

- `config.txt`
- tokens de Telegram
- API keys de RapidAPI
- cookies
- logs reales
- capturas con tokens/chat IDs

Si un token se filtró, regenerarlo con BotFather usando `/revoke`.

---

## Actualizar desde GitHub

```bash
cd /opt/LuisInstaTelegram
git pull
php -l public/peuigbot.php
sudo systemctl reload apache2
```
