# LuisInstaTelegram 1.0.0

Bot de Telegram escrito en PHP para recibir enlaces públicos de Instagram, detectar el shortcode del Reel/Post/TV y responder por Telegram. Está pensado para correr como webhook en un servidor Linux propio con Apache o Nginx y HTTPS.

> Esta versión inicial no incluye tokens, cookies, claves de API ni datos personales. Toda la configuración sensible vive fuera de Git, en `config.txt`.

---

## Qué hace

- Recibe mensajes de Telegram mediante webhook.
- Acepta enlaces de Instagram de tipo:
  - `https://www.instagram.com/reel/...`
  - `https://www.instagram.com/p/...`
  - `https://www.instagram.com/tv/...`
- Extrae el shortcode del contenido.
- Responde al chat de Telegram con el shortcode detectado.
- Tiene comandos básicos:
  - `/start`
  - `/help`
  - `/id`
  - `/version`
- Permite restringir qué chats pueden usarlo.
- Permite validar el header secreto de webhook de Telegram.
- Deja logs locales en `storage/bot.log`.
- Queda preparado para conectar una API externa de descarga/procesamiento de Instagram.

---

## Qué NO hace todavía

- No descarga videos/fotos de Instagram por sí solo.
- No hace scraping directo de Instagram.
- No usa cookies de Instagram.
- No incluye claves de servicios externos.

La idea es tener una base limpia, segura y versionada para luego conectar el proveedor/API que quieras usar.

---

## Estructura del proyecto

```text
LuisInstaTelegram/
├── public/
│   └── index.php             # Webhook principal
├── storage/
│   └── .gitkeep              # Carpeta de logs/runtime
├── config.example.txt        # Configuración de ejemplo, sin secretos
├── config.txt                # Configuración real local, ignorada por Git
├── composer.json             # Metadata PHP / script de lint
├── VERSION                   # Versión actual
├── CHANGELOG.md              # Historial de cambios
└── README.md
```

---

## Requisitos

Servidor:

- Ubuntu Server 22.04, 24.04 o similar.
- Apache 2.4 o Nginx.
- PHP 8.0 o superior.
- Extensión PHP cURL.
- HTTPS válido si vas a usar webhook público de Telegram.
- Un puerto expuesto soportado por Telegram: `443`, `80`, `88` o `8443`.

Paquetes mínimos en Ubuntu con Apache:

```bash
sudo apt update
sudo apt install -y apache2 php php-cli php-curl libapache2-mod-php curl git
```

Verificar PHP:

```bash
php -v
php -m | grep curl
```

---

## 1. Crear el bot en Telegram

1. Abrí Telegram.
2. Buscá `@BotFather`.
3. Enviá:

```text
/newbot
```

4. Elegí un nombre visible, por ejemplo:

```text
LuisInstaTelegram
```

5. Elegí un username terminado en `bot`, por ejemplo:

```text
LuisInstaTelegram_bot
```

6. BotFather te va a entregar un token con este formato aproximado:

```text
123456789:ABCDEF_REEMPLAZAR_POR_TOKEN_REAL
```

Guardalo para el archivo `config.txt`.

Importante: ese token es una contraseña. No lo subas a GitHub, no lo pegues en issues, no lo compartas en capturas.

---

## 2. Obtener el chat ID

### Opción A: usando el comando `/id` del propio bot

Cuando el webhook ya esté configurado:

1. Abrí un chat privado con tu bot.
2. Enviá:

```text
/id
```

El bot responderá algo como:

```text
Chat ID: 123456789
```

Ese número puede ir en `TELEGRAM_ALLOWED_CHAT_IDS`.

### Opción B: usando `getUpdates`

Esta opción sirve antes de configurar webhook.

1. Hablale al bot desde Telegram y mandale `/start`.
2. Ejecutá:

```bash
curl "https://api.telegram.org/botTU_TOKEN/getUpdates"
```

3. Buscá este bloque:

```json
"chat": {
  "id": 123456789,
  "type": "private"
}
```

El valor de `id` es el chat ID.

### Chat ID de grupos

Para grupos, agregá el bot al grupo y mandá un mensaje. Luego usá `getUpdates`. Los grupos suelen tener IDs negativos, por ejemplo:

```text
-1001234567890
```

Si querés que el bot lea mensajes normales en grupos, revisá la privacidad del bot en BotFather:

```text
/setprivacy
```

Para comandos como `/id`, normalmente no hace falta desactivar la privacidad.

---

## 3. Instalar el proyecto en el servidor

Ejemplo usando `/opt/LuisInstaTelegram`:

```bash
cd /opt
sudo git clone https://github.com/luistombo/LuisInstaTelegram.git
sudo chown -R $USER:www-data /opt/LuisInstaTelegram
cd /opt/LuisInstaTelegram
```

Crear configuración real:

```bash
cp config.example.txt config.txt
nano config.txt
```

Ejemplo de `config.txt`:

```ini
TELEGRAM_BOT_TOKEN=TU_TOKEN_REAL_DE_BOTFATHER
TELEGRAM_ALLOWED_CHAT_IDS=TU_CHAT_ID
TELEGRAM_WEBHOOK_SECRET=UN_SECRETO_LARGO_RANDOM
THIRD_PARTY_API_URL=
THIRD_PARTY_API_KEY=
APP_DEBUG=false
APP_LOG_FILE=../storage/bot.log
```

Permisos recomendados:

```bash
sudo chown -R www-data:www-data /opt/LuisInstaTelegram/storage
sudo chmod 750 /opt/LuisInstaTelegram/storage
sudo chown root:www-data /opt/LuisInstaTelegram/config.txt
sudo chmod 640 /opt/LuisInstaTelegram/config.txt
```

Validar sintaxis PHP:

```bash
php -l /opt/LuisInstaTelegram/public/index.php
```

---

## 4. Publicar con Apache en puerto 8443

Telegram acepta webhooks en puertos concretos. Si no querés usar el 443 normal, una opción cómoda es exponer `8443`.

### Habilitar módulos Apache

```bash
sudo a2enmod ssl rewrite headers
sudo systemctl restart apache2
```

### Hacer que Apache escuche en 8443

Editá:

```bash
sudo nano /etc/apache2/ports.conf
```

Agregá:

```apache
Listen 8443
```

### Crear VirtualHost

Crear archivo:

```bash
sudo nano /etc/apache2/sites-available/luisinstatelegram.conf
```

Contenido ejemplo:

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

Activar sitio:

```bash
sudo a2ensite luisinstatelegram.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Abrir firewall:

```bash
sudo ufw allow 8443/tcp
sudo ufw status
```

---

## 5. Certificado HTTPS

Telegram necesita una URL pública HTTPS válida para el webhook, salvo que uses un Bot API Server local propio.

Con Certbot y Apache:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d bot.tudominio.com
```

Si Certbot te configura el puerto 443 pero vos querés usar 8443, podés usar el certificado emitido en el VirtualHost de 8443 como se mostró arriba.

Probar desde afuera:

```bash
curl -i https://bot.tudominio.com:8443/index.php
```

Debe devolver algo como:

```json
{"ok":true,"version":"1.0.0"}
```

---

## 6. Configurar webhook de Telegram

Usá el mismo secreto que pusiste en `TELEGRAM_WEBHOOK_SECRET`.

```bash
BOT_TOKEN="TU_TOKEN_REAL"
WEBHOOK_SECRET="UN_SECRETO_LARGO_RANDOM"
WEBHOOK_URL="https://bot.tudominio.com:8443/index.php"

curl -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
  -d "url=${WEBHOOK_URL}" \
  -d "secret_token=${WEBHOOK_SECRET}" \
  -d "drop_pending_updates=true"
```

Verificar estado:

```bash
curl "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo"
```

Campos importantes:

- `url`: debe mostrar tu URL.
- `pending_update_count`: si sube mucho, Telegram no está pudiendo entregar updates.
- `last_error_message`: muestra el último error de entrega.

---

## 7. Probar el bot

En Telegram:

```text
/start
```

Luego:

```text
/id
```

Luego mandá un link público de Instagram:

```text
https://www.instagram.com/reel/SHORTCODE_DE_EJEMPLO/
```

El bot debería responder con el shortcode detectado.

---

## 8. Logs y diagnóstico

Logs propios del bot:

```bash
sudo tail -f /opt/LuisInstaTelegram/storage/bot.log
```

Logs Apache:

```bash
sudo tail -f /var/log/apache2/luisinstatelegram_error.log
sudo tail -f /var/log/apache2/error.log
```

Ver estado Apache:

```bash
sudo systemctl status apache2
```

Validar configuración Apache:

```bash
sudo apache2ctl configtest
```

Validar si Apache escucha en 8443:

```bash
sudo ss -tulpen | grep 8443
```

---

## 9. Errores comunes

### `500 Internal Server Error`

Revisar:

```bash
sudo tail -n 100 /var/log/apache2/error.log
php -l /opt/LuisInstaTelegram/public/index.php
```

Causas típicas:

- Falta `php-curl`.
- Permisos incorrectos sobre `storage/`.
- Error de sintaxis PHP.
- `config.txt` inaccesible para Apache.

### El bot no responde

Revisar:

```bash
curl "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo"
```

Si `last_error_message` dice timeout o connection refused:

- El puerto no está abierto.
- Apache no está escuchando en ese puerto.
- DNS apunta a otra IP.
- El certificado HTTPS no es válido.

### `401 unauthorized`

El `secret_token` configurado en Telegram no coincide con `TELEGRAM_WEBHOOK_SECRET`.

Solución:

```bash
curl -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
  -d "url=${WEBHOOK_URL}" \
  -d "secret_token=${WEBHOOK_SECRET}"
```

### `Chat ID no autorizado`

Si configuraste `TELEGRAM_ALLOWED_CHAT_IDS`, agregá el chat ID correcto al archivo `config.txt`:

```ini
TELEGRAM_ALLOWED_CHAT_IDS=123456789,-1001234567890
```

---

## 10. Seguridad operacional

No subir nunca a GitHub:

- `config.txt`
- Tokens de BotFather
- Cookies de Instagram
- API keys
- Logs reales
- Capturas con tokens o chat IDs privados

Si un token se filtró:

1. Ir a `@BotFather`.
2. Usar `/revoke`.
3. Generar token nuevo.
4. Actualizar `config.txt`.
5. Reconfigurar webhook.

Recomendaciones:

- Usar `TELEGRAM_WEBHOOK_SECRET` siempre.
- Restringir `TELEGRAM_ALLOWED_CHAT_IDS` cuando termines las pruebas.
- Mantener `config.txt` con permisos `640` y grupo `www-data`.
- No dejar `APP_DEBUG=true` en producción.

---

## 11. Actualizar desde GitHub

```bash
cd /opt/LuisInstaTelegram
sudo git pull
php -l public/index.php
sudo systemctl reload apache2
```

---

## 12. Roadmap posible

- Integrar una API externa concreta para resolver medios de Instagram.
- Enviar video/foto directo a Telegram.
- Cola de procesamiento para enlaces pesados.
- Soporte para grupos y canales con reglas específicas.
- Instalador automático para Apache/Nginx.
