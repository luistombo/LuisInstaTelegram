# Release Notes

## 1.0.0 - 2026-07-06

- Versión basada en el archivo productivo `peuigbot.php`.
- Mantiene descarga/envío de fotos y videos vía RapidAPI `instagram-looter2`.
- Soporta posts individuales y carruseles.
- Envía imágenes con `sendPhoto` y videos con `sendVideo`.
- Agrega comandos `/id` y `/version`.
- Mueve logs/cookies a `storage/`.
- Evita escribir tokens/API keys en logs.
- Deja `DEBUG_MODE` en `false` por defecto.
