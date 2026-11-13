# BRAGI Site Connector

Plugin piloto que enlaza un WordPress con un único proyecto de BRAGI.

## Alcance v0.1

- Alta única mediante URL BRAGI, ID de proyecto y token de vinculación.
- Sincronización saliente firmada (HMAC-SHA256) del estado técnico del sitio.
- Descubrimiento de sitemap y robots, HTTPS, visibilidad de buscadores, versiones y plugins activos.
- Consulta de órdenes pendientes desde BRAGI y ejecución limitada de publicación/retirada de etiquetas META de verificación.
- No guarda credenciales de Google Analytics, Search Console ni DataForSEO.

## Requisitos de BRAGI

Configurar en el entorno seguro:

- `BRAGI_CONNECTOR_ENROLLMENT_TOKEN`: secreto para una instalación inicial.
- `BRAGI_CONNECTOR_ENCRYPTION_KEY`: clave aleatoria de 32 bytes, codificada en base64, para cifrar por sitio las credenciales de conector.

El token de vinculación se elimina del WordPress tras un alta correcta. La credencial propia del sitio se conserva en las opciones de WordPress y BRAGI la cifra en su base de datos.

## Instalación piloto

1. Empaquetar esta carpeta como `bragi-site-connector.zip`.
2. Instalar y activar el plugin en el WordPress piloto.
3. En Ajustes → BRAGI Connector, introducir URL de BRAGI, ID del proyecto y token de vinculación.
4. Ejecutar la vinculación y comprobar en BRAGI que el estado del sitio quedó actualizado.

No instalar en producción hasta comprobar el flujo en un entorno autorizado.
