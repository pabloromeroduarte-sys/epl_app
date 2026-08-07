# MCP remoto — Elite Padel League

Servidor MCP multiusuario para conectar EPL con Claude y ChatGPT.

## URL

- Producción: `https://epleague.cl/mcp/`
- Local: `http://localhost/elitepadelleague/mcp/`

## Seguridad

- OAuth 2.1 Authorization Code + PKCE (S256) y registro dinámico de clientes.
- El usuario inicia sesión con su cuenta EPL; la contraseña no se entrega al cliente MCP.
- Se exige `jugadores.mcp_habilitado = 1`.
- Los permisos se verifican nuevamente en cada llamada según el rol vigente.
- Los tokens pueden revocarse desde `admin/mcp.php`.
- Consultas y modificaciones quedan registradas en `mcp_audit_log`.
- No existen herramientas de SQL, SSH, archivos, despliegue ni eliminación.

## Herramientas iniciales

- `quien_soy`
- `listar_ligas`
- `buscar_partidos`
- `ver_partido`
- `ver_reprogramaciones`
- `listar_recintos` (admin)
- `solicitar_reprogramacion` (jugador/admin)
- `administrar_partido` (admin)

Las escrituras requieren confirmación explícita. Los jugadores solo pueden consultar partidos propios y solicitar cambios en ellos; los clubes solo acceden a ligas asignadas; los administradores operan sobre todo el sistema.

## Conexión

1. Un administrador habilita al usuario en **Admin → Acceso MCP**.
2. En Claude web: **Configuración → Conectores → Agregar conector personalizado**.
3. Ingresar `https://epleague.cl/mcp/`.
4. Iniciar sesión en EPL y seleccionar **Conectar EPL**.
5. El conector ya configurado queda disponible también en Claude móvil.

En ChatGPT se agrega como aplicación MCP personalizada desde el modo desarrollador, cuando el plan de la cuenta lo permite.
