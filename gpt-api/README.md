# GPT Actions — Elite Padel League

API REST con OAuth para usar EPL desde un GPT personalizado y posteriormente desde ChatGPT Android.

## URLs de producción

- OpenAPI: `https://epleague.cl/gpt-api/openapi.php`
- Authorization URL: `https://epleague.cl/gpt-api/oauth/authorize.php`
- Token URL: `https://epleague.cl/gpt-api/oauth/token.php`
- Scope: `epl.read epl.write`
- Token exchange method: `POST`
- Política de privacidad: `https://epleague.cl/privacidad_ia.php`

El Client ID, Client Secret, callback y enlace compartido se administran en `Admin → Acceso IA`.

## Seguridad

- OAuth Authorization Code con secreto de cliente y tokens rotativos.
- Callback restringido a dominios oficiales de ChatGPT y registrado exactamente.
- Cada solicitud valida nuevamente que el usuario siga activo y habilitado.
- Permisos derivados del rol vigente: jugador, club o administrador.
- Las escrituras requieren `confirmar=true` y se marcan como consecuenciales en OpenAPI.
- Todas las acciones quedan en `mcp_audit_log`.
- No hay acceso a SQL, archivos, SSH, despliegue ni eliminación.

## Actions

- `obtenerMiPerfilEPL`
- `listarMisLigas`
- `buscarPartidosEPL`
- `verDetallePartidoEPL`
- `verReprogramacionesEPL`
- `listarRecintosEPL` (administrador)
- `solicitarReprogramacionEPL`
- `administrarPartidoEPL` (administrador)

## Configuración del GPT

1. Generar credenciales desde el panel EPL.
2. Crear un GPT desde ChatGPT web.
3. Importar el esquema OpenAPI.
4. Configurar OAuth con los valores mostrados por EPL.
5. Copiar desde ChatGPT el callback exacto y guardarlo en EPL.
6. Probar la Action `obtenerMiPerfilEPL`.
7. Compartir como “Cualquiera con el enlace” y guardar ese enlace en EPL.
8. Los jugadores abren el enlace desde ChatGPT Android y autorizan su cuenta EPL.

