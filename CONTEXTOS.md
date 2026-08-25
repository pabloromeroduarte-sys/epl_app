# Elite Padel League — contexto integral del proyecto

> Actualizado: **25 de agosto de 2026**  
> Última edición: integración de la Tabla de Posiciones y Fixture directamente en la vista de Mis Torneos del jugador (`mis_torneos.php`).  
> Documento compartido para Claude, Gemini, Antigravity, Codex y futuros agentes.  
> Fuente de verdad operativa complementaria a Git: verificar siempre con `git status`, `git diff` y `git log`.

## 1. Propósito de este documento

Este archivo resume:

- Qué es el sistema y cómo está construido.
- Dónde se desarrolla y dónde está producción.
- Qué funciones ya existen.
- Qué cambios fueron incorporados a Git.
- Qué modificaciones siguen únicamente en local.
- Reglas críticas de seguridad y despliegue.
- Problemas conocidos y el punto exacto desde donde continuar.

No guardar aquí contraseñas, tokens, claves API, respaldos de base de datos ni contenido de `.env`.

## 2. Resumen ejecutivo

Elite Padel League (EPL) es una aplicación web propia para administrar ligas y torneos de pádel. Actualmente es un sistema PHP tradicional, sin Laravel ni React como framework principal. Incluye:

- Portal público.
- Portal de jugadores.
- Portal limitado para clubes.
- Panel administrativo completo.
- Gestión de ligas, equipos, jugadores, partidos y resultados.
- Reprogramaciones, recintos, notificaciones, correos y suplentes.
- BI, finanzas, presupuestos, automatizaciones y contenido.
- Calendarios y aplicación PWA para celular.
- Integraciones de IA mediante MCP y GPT Actions con OAuth.
- Simulador administrativo de Torneo Copa.

La dirección comercial pública vigente es potenciar un **circuito anual de torneos por categoría**, con cada fecha resuelta en un día. Cada posición entrega puntos a la pareja y alimenta su ranking durante la temporada; al cerrar el calendario, las mejores parejas de cada categoría clasifican al **Máster Final EPL**. La estructura histórica de ligas/equipos sigue disponible para conservar datos y compatibilidad.

La idea de reconstruir el proyecto en una carpeta paralela usando un framework moderno quedó propuesta para el futuro, pero **no se ha iniciado una migración oficial**. El sistema PHP actual sigue siendo producción.

## 3. Entornos y ubicaciones

### Desarrollo local

- Carpeta: `C:\xampp\htdocs\elitepadelleague`.
- URL: `http://localhost/elitepadelleague/`.
- Entorno habitual: XAMPP, PHP y MariaDB local.
- Base de pruebas: `epldb`.
- La información local puede utilizarse para pruebas; nunca asumir que coincide exactamente con producción.

### Repositorio

- GitHub: `https://github.com/pabloromeroduarte-sys/epl_app.git`.
- Rama principal: `main`.
- Al momento de este documento, `HEAD`, `origin/main` y producción están sincronizados en `4d5ec9bf`.

### Producción

- Dominio: `https://epleague.cl`.
- Proveedor: DigitalOcean.
- IP pública: `165.227.109.215`.
- Ruta del proyecto: `/var/www/elitepadelleague`.
- Stack: Nginx, PHP-FPM y MariaDB/MySQL compatible.
- Base productiva: `epldb`.
- Las credenciales viven únicamente en `.env` del entorno correspondiente.

### Servidor antiguo

- Vultr: `207.246.68.77`.
- Se conserva como respaldo histórico.
- **No modificar, borrar, desplegar ni sincronizar sobre este servidor**.

### Operación de producción (migración y crons)

Notas agregadas tras completar la migración a DigitalOcean:

- El DNS de `epleague.cl` en Cloudflare apunta a la IP de DigitalOcean (proxied). El vhost de producción incluye un header de verificación `X-Origen: do-nuevo` para confirmar desde qué servidor responde el dominio.
- HTTPS: certificado propio en el origen bajo `/etc/nginx/ssl/`; Cloudflare adelante.
- Los **crons corren en DigitalOcean** como usuario `www-data`: envío de correos (cada minuto), recordatorios de partido (cada hora) y cumpleaños (una vez al día). El servidor viejo de Vultr no debe seguir ejecutando estos crons para evitar "alertas duplicadas/zombi" con fechas antiguas.
- Git en el servidor: el repositorio es propiedad de `www-data` y hay `safe.directory` a nivel sistema, para que el webhook (que corre como `www-data`) pueda hacer `git pull` sin conflictos de permisos en `.git/objects`.
- **Bug conocido no crítico (no resuelto):** el envío de Web Push a administradores falla al firmar con la clave VAPID (`openssl_sign` en `includes/WebPush.php`); ese push nunca operó. Los correos y las notificaciones internas sí funcionan. Revisar/regenerar VAPID si se quiere activar el push.
- Recordatorio de seguridad: rotar credenciales que hayan viajado por chat y no dejar públicos scripts de diagnóstico/respaldo que expongan datos o `.env`.

## 4. Reglas de oro

1. No tocar directamente la base de datos productiva durante cambios normales de sistema.
2. Cualquier operación excepcional sobre datos productivos necesita autorización explícita, respaldo y validación previa.
3. Nunca tocar el antiguo Vultr.
4. Probar en local antes de publicar.
5. No publicar hasta que el usuario diga expresamente “envíalo”, “publica”, “queda arriba” o equivalente.
6. No commitear `.env`, SQL, ZIP de respaldo, claves, tokens ni contraseñas.
7. Preservar cambios locales de otros agentes o del usuario.
8. No usar `git reset --hard` ni restauraciones destructivas.
9. Separar cambios por función y usar mensajes de commit en español.
10. Si el árbol está sucio, no ejecutar a ciegas un script que haga `git add -A`.

## 5. Flujo de despliegue

Cuando todos los cambios locales corresponden al trabajo aprobado:

```powershell
cd C:\xampp\htdocs\elitepadelleague
.\scripts\publicar.ps1 -Aprobado -Mensaje "descripción breve del cambio"
```

El flujo esperado es:

1. Commit en `main`.
2. Push a GitHub.
3. Webhook en DigitalOcean.
4. `git pull` en producción.
5. Reinicio/limpieza de OPcache.

Si existen modificaciones locales ajenas al cambio autorizado:

- Agregar solamente los archivos correspondientes.
- Revisar `git diff --cached --name-only`.
- Crear el commit aislado.
- Hacer push.
- Activar el webhook sin mostrar su token.

El webhook puede mostrar una advertencia de permisos sobre `/var/www/.gitconfig`; si el `git pull`, OPcache y código de salida terminan correctamente, verificar el resultado real antes de concluir que falló.

## 6. Arquitectura técnica

### Backend

- PHP con `PDO`.
- Configuración de base de datos en `includes/db.php` y variables de `.env`.
- Funciones principales en `includes/functions.php`.
- Autenticación y control de sesión en `includes/auth.php`.
- Correos en `includes/mail.php` y automatizaciones relacionadas.
- OneSignal/Web Push y notificaciones internas.
- Migraciones defensivas mediante funciones `epl_ensure_*()` en varios módulos.

### Frontend

- HTML generado por PHP.
- CSS propio en `assets/css/`.
- JavaScript nativo.
- Tailwind compilado/local en partes del proyecto.
- Flatpickr para calendarios y selección de fechas.
- Diseño responsive con navegación específica para móvil.
- Redacción pública para Chile: usar tuteo neutral (`compite`, `crea`, `elige`, `puedes`) y evitar voseo argentino (`competí`, `creá`, `elegí`, `podés`).

### PWA

- `manifest.json`.
- `sw.js`.
- Instalación en pantalla de inicio.
- Push notifications.
- Navegación móvil inferior.

### Regla visual importante de producción

En el VPS se detectó que estilos colocados en bloques `<style>` dentro del `<body>` podían no aplicarse como en local. Para CSS específico de página:

1. Crear `assets/css/<nombre>.css`.
2. Definir `$page_css = '<nombre>';` antes de incluir `includes/header.php`.
3. El header lo carga desde `<head>` con versión por `filemtime`.

No volver a depender de grandes bloques `<style>` dentro del body para páginas administrativas importantes.

## 7. Roles y permisos

### Administrador

- Acceso total al panel administrativo autorizado.
- Gestión de ligas, equipos, jugadores, partidos, resultados y reprogramaciones.
- Calendario global con filtro por liga.
- Edición de partidos desde calendario/modal.
- BI, finanzas, recintos, automatizaciones, notificaciones y módulos especiales.

### Club

- Rol limitado.
- Administración asigna las ligas permitidas.
- Puede ver resultados y calendario de esas ligas.
- Si tiene varias ligas, puede filtrarlas.
- Los permisos se validan también en backend.

### Jugador

- Ve sus torneos, partidos, resultados, clasificación y notificaciones.
- Puede ingresar resultados bajo las reglas vigentes.
- Puede solicitar reprogramaciones de partidos propios.
- Puede administrar perfil, suplentes e inscripciones según las reglas del sistema.

## 8. Módulos principales existentes

### Portal público y jugadores

- `index.php`: inicio público centrado en calendario anual, puntos de pareja y camino al Máster Final.
- `torneos.php`: directorio público de fechas, categorías, cupos y resultados históricos.
- `ranking.php`: ranking anual de parejas, con filtros, podio, tabla, modelo de puntos y clasificación al Máster.
- `registro.php`, `login.php`, `recuperar.php`, `cambiar_password.php`.
- `dashboard.php`: inicio del jugador.
- `mis_torneos.php`, `clasificacion.php`, `resultados.php`.
- `ingresar_resultado.php`.
- `reprogramar.php`.
- `mis_suplentes.php`.
- `inscribirse.php`, `inscribirse_partner.php`.
- `mi_perfil.php`.
- `notificaciones.php`.
- `tutoriales.php`.
- `conectar_ia.php`.

### Portal Club

- `club/resultados.php`.
- `club/calendario.php`.
- `includes/club_sidebar.php`.

### Administración deportiva

- `admin/ligas.php` y `admin/liga_detalle.php`.
- `admin/equipos.php` y `admin/equipo_detalle.php`.
- `admin/jugadores.php`.
- `admin/partidos.php`, `admin/partido_detalle.php`, `admin/proximos_partidos.php`.
- `admin/dashboard_resultados.php`, `admin/cargar_resultados.php`.
- `admin/dashboard_repro.php`, `admin/api_reprogramacion.php`.
- `admin/inscripciones.php`, `admin/suplentes.php`, `admin/disputas.php`.
- `admin/recintos.php`, `admin/calendario.php`.

### Administración comercial y operación

- `admin/bi.php`.
- `admin/erp_financiero.php`.
- `admin/presupuestos.php` y páginas de detalle/PDF.
- `admin/automatizaciones.php`.
- `admin/alertas.php`.
- `admin/notificaciones.php`.
- `admin/content_studio.php`.
- `admin/cumpleanos.php`.
- `admin/configuracion.php`.

### Procesos automáticos

- `cron/cron_recordatorio_partidos.php`.
- `cron/cron_mail_sender.php`.
- `cron/cron_cumpleanos.php`.

## 9. Funciones deportivas terminadas

### Resultados

- Bloqueo de resultados para fechas demasiado futuras.
- Se permite registrar desde el mismo día, un día hacia adelante y fechas pasadas.
- Un partido reprogramado sin fecha válida no permite resultado.
- Registro set a set y recalculo de clasificación.
- Flujo de reclamo/disputa.
- Exportación administrativa de partidos a Excel/CSV.

### Equipos

- Búsqueda por nombre de pareja/jugadores.
- Ficha detallada con estadísticas, historial y pendientes.
- Historial de “galletas” (sets 6-0 a favor/en contra).
- Reemplazo de uno o ambos jugadores conservando el mismo `equipo_id` y su historial.
- Cambio de nombre del equipo.

### Calendarios

- Calendario para Club y Administrador.
- Filtro por liga.
- Vista diaria de partidos, jugadores, fecha y recinto.
- En Admin, click para abrir información completa y editar el partido.

### Reprogramaciones
 
- Registro de fecha y recinto originales.
- Estados pendiente, reprogramado y solicitudes asociadas.
- Gestión administrativa con tarjetas claras y diseño móvil.
- Filtros Todos, Pendientes y Gestionados.
- Buscador por pareja, partido o liga.
- Indicadores de urgencia, vencimiento, acuerdo y estado.
- Tratamiento especial de partidos sin fecha.
- Flujos de baja/confirmación de cancha y notificaciones.
- Portal del jugador simplificado a dos pestañas esenciales: **Solicitar** (con buscador de rivales) y **Mis Reprogramaciones** (vista unificada de cambios de la pareja y contacto directo por WhatsApp). Se ocultaron las pestañas secundarias (*Todos los Reprogramados* y *Adelantar Fecha*) para agilizar el proceso y evitar consultas pesadas innecesarias.
- Protección contra recordatorios enviados usando una fecha original que ya fue suspendida.

### Correos y notificaciones

- Recinto jerárquico correcto en recordatorios.
- Etiquetas `<strong>` renderizadas correctamente.
- Botones de correo mejorados.
- Notificaciones internas, email y push.
- Avisos por resultado, disputa, cambio de fecha/recinto, inscripción y reprogramación.

## 10. MCP y asistentes externos

### MCP remoto publicado

- Endpoint: `https://epleague.cl/mcp/`.
- OAuth con descubrimiento, registro dinámico, autorización y tokens.
- Streamable HTTP y compatibilidad HTTP+SSE.
- Commit base: `a854f0f8`.
- Corrección SSE: `7be888f2`.

### Herramientas MCP

- `quien_soy`.
- `listar_ligas`.
- `buscar_partidos`.
- `ver_partido`.
- `ver_reprogramaciones`.
- `listar_recintos` para administradores.
- `solicitar_reprogramacion` con permisos y confirmación.
- `administrar_partido` para administradores y con confirmación.

### Seguridad MCP

- Los permisos dependen siempre del rol actual en EPL.
- Club solo accede a ligas asignadas.
- Jugador solo accede a información propia autorizada.
- Las escrituras exigen confirmación explícita.
- Las llamadas relevantes quedan en auditoría.
- Deshabilitar acceso MCP revoca el uso de esa cuenta.

### Diagnóstico de Claude

- OAuth funcionaba correctamente.
- Claude obtenía el token y después consultaba `GET /mcp/`.
- Antes recibía `405`; se agregó SSE y ahora un GET sin token recibe `401`, comportamiento correcto.
- El usuario informó que el conector todavía no aparecía en la interfaz de Claude.
- No asumir automáticamente que esto es una falla del servidor: puede ser disponibilidad del producto, plan o limitación de la aplicación móvil.

## 11. GPT Actions / ChatGPT móvil

La alternativa GPT Actions ya fue incorporada a `main` mediante los commits:

- `d0508cc1`: API GPT Actions, OAuth, tutoriales y pantallas.
- `35e19080`: tutorial visual para crear el GPT.
- `549d1544`: corrección del botón para regenerar credenciales.
- `8a94dd3a`: corrección del esquema OpenAPI.

Componentes principales:

- `gpt-api/openapi.php`.
- `gpt-api/oauth/authorize.php` y `gpt-api/oauth/token.php`.
- Endpoints para cuenta, ligas, partidos, reprogramaciones, recintos y acciones autorizadas.
- `includes/gpt_actions.php`.
- `admin/mcp.php` para administración.
- `conectar_ia.php` para usuarios.
- `privacidad_ia.php`.
- CSS visual de tutoriales y administración.

`includes/ai_assistant.php` está rastreado por Git, pero actualmente no aparece referenciado por otras páginas PHP. Tratarlo como infraestructura/experimento hasta revisar su uso real; no asumir que es el chat productivo.

## 12. Decisión sobre consumo de IA

El usuario aclaró que desea **externalizar el consumo del modelo**:

- Preferencia principal: Claude, Gemini, Antigravity, ChatGPT u otro cliente consumen sus propios recursos y usan EPL como fuente/herramienta.
- MCP es la vía correcta para clientes compatibles.
- GPT Actions es una vía específica para un GPT personalizado.
- No crear un chat central que consuma claves pagadas por EPL sin definir presupuesto, cuotas y responsable del consumo.
- Alternativa futura posible: BYOK, donde cada usuario aporta su propia clave API y asume su costo.
- Las suscripciones de aplicaciones de consumo no equivalen necesariamente a créditos de API.

## 13. Torneo Copa

Módulo agregado en agosto de 2026:

- Archivo principal: `admin/torneos.php`.
- Estilos: `assets/css/torneos.css`.
- Acceso desde sidebar administrativo.
- Simula formatos de Copa con fase de grupos y eliminación.
- Partidos de grupos estimados en 20 minutos.
- Partidos eliminatorios estimados en 30 minutos.
- Calcula parejas, grupos, clasificados, partidos y horas-cancha.
- Permite definir estructura mínima/máxima de canchas:
  - Máximo/rápido: aproximadamente dos canchas por grupo.
  - Mínimo/lento: aproximadamente una cancha por grupo.
- Calcula costos de cancha, trofeos/medallas, precio, cobranza y utilidad.
- Genera dos salidas PDF mediante impresión nativa:
  - Presentación comercial.
  - Documento interno con gastos neto/IVA.

Commits asociados:

- `74b1b45d`: módulo inicial.
- `2bef2e15`: esquema visual, cobranza, ganancia y canchas.
- `fd8d7a55`, `9bd8f31e`: evolución de generación PDF.
- `4d6eae88`: separación presentación/interno.
- `f4918417`: estructura min/max, tiempos y horas-cancha.

### Modelo de costos y estructura de canchas (Torneo Copa)

Valores de referencia (todo "en negro"; el IVA de trofeos/medallas no se recupera y se suma completo). No son secretos, pero se pueden editar en la propia pantalla:

- Cancha: `$26.000` por bloque de 1h30. El costo de canchas se calcula por **horas-cancha de juego × valor/hora** (valor por 1h30 ÷ 1,5), por eso escala con la cantidad de partidos.
- Galvanos: proveedor **Trofeos Premium**, modelo **CR 005**. `$13.900` neto c/u + IVA. **2 unidades** (ganadores).
- Medallas: `$1.500` neto c/u + IVA. **6 unidades** (1º a 3º puesto).
- Tiempos: partido de grupo **20 min**; partido de eliminación (cuartos/semis/final/3º-4º) **30 min**. Todo en **1 día**.

Estructura de canchas por tamaño (2 canchas por grupo = rápido; 1 por grupo = largo):

| Parejas | Grupos | Máximo (rápido) | Mínimo (largo) | Horas-cancha de juego |
|---|---|---|---|---|
| 8 | 2 | 4 canchas · 2 h | 2 canchas · 3 h | 6 |
| 12 | 3 | 6 canchas · 2 h | 3 canchas · 3 h | 8 |
| 16 | 4 | 8 canchas · 2,5 h | 4 canchas · 3,5 h | 12 |

Reglas de la llave: 2 grupos → semis + final + 3º/4º (pasan los 2 mejores de cada grupo). 3 grupos → 3 ganadores de grupo + mejor 2º → misma definición. 4 grupos → pasan los 2 mejores → aparece **cuartos** antes de semis.

PDF: dos botones con impresión nativa del navegador (sin librerías, con marca EPL) — **"Descargar PDF"** (presentación para clubes/jugadores) y **"Descarga interna"** (el mismo + tabla de gastos con neto/IVA, margen por pareja e IVA no recuperable).

### Plan de calendario semanal (propuesta Oct–Dic 2026, todavía sin construir)

Objetivo: al menos un torneo semanal por categoría, cada uno en 1 día. **Martes = 4ta, Jueves = 5ta**.

- Rango: semana del **6 de octubre** a la semana del **15–17 de diciembre de 2026**.
- **11 semanas × 2 torneos = 22 torneos** (11 de 4ta + 11 de 5ta).
- Por mes: octubre 8, noviembre 8, diciembre 6.
- Feriado a considerar: **martes 8 de diciembre** (Inmaculada Concepción) → mover o saltar ese 4ta.
- Uso de canchas si todos son de 8 parejas: entre **132 (mínimo) y 176 (máximo) canchas-hora** en el período; 2 a 4 canchas por día.

Pendiente: armar la **presentación de calendario en PDF** para ofrecer a los clubes (fechas por mes + bloque de canchas a reservar). Aún no construida; definir si se muestra el mínimo, el máximo o el rango de canchas.

## 14. Estado Git actual

### Sincronización

- Rama: `main`.
- `HEAD`, `origin/main` y producción: sincronizados en `4d5ec9bf`.
- Último commit: `4d5ec9bf`.

### Cambios locales todavía no commiteados

No publicar con `git add -A` sin revisar. Actualmente existen:

- `CLAUDE.md`: referencia al contexto compartido.
- `admin/dashboard_repro.php`: recupera partidos sin resultado/sin fecha, trata el marcador 31/12 como sin fecha, permite eliminar una gestión sin modificar el partido y excluye de la bandeja activa las reservas originales ya vencidas.
- `assets/css/repro.css`: menú visual de gestión rápida para separar casos sin fecha y con fecha propuesta, priorizar solicitudes por aprobar y mostrar estados vacíos claros.
- `admin/api_reprogramacion.php`: al rechazar una solicitud restaura estado, fecha y recinto originales.
- `admin/partido_detalle.php`: “revertir a pendiente” restaura fecha/recinto originales y la ficha deja de pedir la baja de reservas vencidas.
- `admin/partidos.php`: una cancha escogida explícitamente para un reprogramado queda confirmada por el administrador; volver a dejarlo sin fecha/cancha reabre la tarea.
- `reprogramar.php`: separa la reserva original de la cancha nueva, simplifica las pestañas a Solicitar y Mis Reprogramaciones (ocultando Todos los Reprogramados y Adelantar Fecha), y aplica de inmediato la fecha cuando existe mutuo acuerdo.
- `dashboard.php`: actualiza el enlace de reprogramaciones hacia la vista unificada del jugador (`#mis-reprogramaciones`).
- `gestion_reserva.php`: el enlace público del club solo libera reservas vigentes y confirma canchas; no aprueba solicitudes ni fechas.
- `includes/functions.php`: generar un enlace de confirmación ya no marca falsamente el aviso como enviado; incorpora la regla común de vigencia de reservas.
- `assets/css/epl.css`: estado activo consistente mediante `aria-current`.
- `includes/player_sidebar.php`: acceso a Conectar IA en sidebar y navegación móvil.
- `login.php`: conserva correctamente el retorno OAuth para MCP y GPT Actions, incluido rol Club.
- `tutoriales.php`: tarjeta para conectar ChatGPT, Claude o Gemini.
- `index.php`: portada pública modernizada y orientada al circuito anual, puntos de pareja y Máster Final.
- `assets/css/home.css`: estilos específicos de la portada y sus módulos de formato/ranking.
- `ranking.php` y `assets/css/ranking.css`: clasificación pública anual de parejas rumbo al Máster Final.
- `torneos.php` y `assets/css/torneos-public.css`: calendario público modernizado, puntos por posición y ruta de cuatro etapas al Máster.
- `includes/header.php` y `includes/footer.php`: navegación y contenido público coherentes con el nuevo formato; rutas PWA aptas para subcarpeta local.
- `ACTUALIZACIONES.md`: bitácora todavía no rastreada.
- `AGENTS.md`: instrucciones para agentes todavía no rastreadas.
- `.claude/worktrees/confident-chaplygin-e5fd5c`: aparece modificado; no agregar automáticamente.
- `CONTEXTOS.md`: este archivo nuevo.

Estos cambios deben probarse y separarse en uno o más commits antes de publicar.

### Menú simple de Reprogramaciones pendiente de publicación

- **Gestionar** presenta dos accesos principales: **Sin fecha** y **Con fecha propuesta**.
- La vista seleccionada filtra tanto solicitudes nuevas como partidos que ya están en gestión, conservando un único registro por partido.
- **Gestionados** queda como acción secundaria y la antigua pestaña Informe se presenta como **Resumen general**.
- Las solicitudes por aprobar aparecen antes que los partidos ya aprobados por completar.
- Una reserva original solo permanece como tarea si su fecha es de hoy o futura; los casos vencidos conservan su historial sin ensuciar la gestión diaria.
- Abrir el panel o la ficha ya no crea el estado falso de “mensaje enviado / esperando confirmación”.
- Prueba local con una solicitud temporal: 11 casos sin fecha y 1 con fecha propuesta; clasificación, fecha y motivo correctos. El registro temporal fue eliminado.
- Archivos: `admin/dashboard_repro.php` y `assets/css/repro.css`.
- Estado: no commiteado y no publicado; producción no fue modificada.

### Flujo definitivo de reprogramación y cancha pendiente de publicación

- Revisión productiva realizada el 24 de agosto de 2026 únicamente en modo lectura: el panel mostraba **8 partidos en gestión** y **2 solicitudes por aprobar**.
- Regla de negocio acordada:
  1. El jugador o la pareja solicita la reprogramación.
  2. EPL aprueba la solicitud cuando corresponde; una propuesta con mutuo acuerdo mantiene la aprobación automática vigente.
  3. El club no aprueba fechas ni solicitudes. Solo libera una reserva original todavía vigente y asigna/confirma la cancha de una fecha nueva ya aprobada.
  4. Sin fecha aprobada no se solicita una cancha nueva al club.
  5. Con fecha aprobada, el partido sigue operativo hasta tener `recinto_id` y `cancha_confirmada_at`.
  6. Con fecha y cancha confirmadas pasa a **Gestionados**; al registrar resultado sale del flujo activo y queda en historial.
- Se detectó que `recinto_id` podía conservar la cancha original después de solicitar el cambio. Por eso no se puede interpretar un `recinto_id` aislado como confirmación de cancha nueva.
- En el flujo local nuevo, `fecha_original` y `recinto_original_id` conservan la reserva anterior; `fecha_programada` contiene únicamente la fecha aprobada y `recinto_id` parte vacío hasta la confirmación explícita del club o del administrador.
- `epl_partido_baja_token()` ya no llena `baja_solicitada_at` al renderizar una pantalla. Abrir el panel no significa que el WhatsApp haya sido enviado.
- El dashboard dejó de borrar confirmaciones de cancha al abrirse. Una reserva original vencida deja de pedir liberación, pero el partido con fecha nueva continúa en **Cancha por confirmar** hasta recibir la respuesta del club.
- El modal de aprobación administrativa define solo la fecha. La cancha no se escribe ni se selecciona en esa aprobación; se confirma después mediante la gestión del club o una asignación manual explícita del administrador.
- Distribución esperada de los 8 casos productivos revisados: **4 Sin fecha** y **4 Con fecha / Cancha por confirmar**. Las **2 solicitudes por aprobar** se conservan arriba y por separado.
- Casos **Sin fecha**:
  - Eyzaguirre - Casasempere vs Romero - Merino.
  - Vidal - Benítez vs Montaner Reyes - Montaner Covarrubias.
  - Eyzaguirre - Casasempere vs Baeza - Salinas.
  - Martiz - Kattan vs Vargas - Diaz; su marcador 31/12 se trata como “sin fecha”.
- Casos **Con fecha / Cancha por confirmar**:
  - Hurtado Sotomayor - Martínez Cuadro vs Baeza - Salinas.
  - Sfeir - Amigo vs Eyzaguirre - Casasempere.
  - Martiz - Kattan vs Montaner Reyes - Montaner Covarrubias.
  - Freyre - Brosky vs Vogel - Silva.
- Las solicitudes sin fecha solo piden liberar cancha si la reserva anterior continúa vigente. El botón administrativo se llama **Aprobar sin fecha** y explica qué ocurrirá.
- Validación local integral: un partido temporal con fecha aprobada y cancha sin confirmar apareció como **Club: confirmar cancha nueva**; la vista del club permitió únicamente seleccionar cancha, no aprobar fecha; al confirmar pasó de Pendientes a Gestionados. Los datos y accesos temporales fueron eliminados.
- Archivos del cambio: `reprogramar.php`, `admin/api_reprogramacion.php`, `admin/dashboard_repro.php`, `admin/partido_detalle.php`, `admin/partidos.php`, `gestion_reserva.php`, `includes/functions.php` y `assets/css/repro.css`.
- Estado: **solo local, no commiteado y no publicado**. Próximo paso: publicar este conjunto de forma aislada cuando el usuario lo autorice y verificar los grupos en DigitalOcean sin modificar partidos reales.

### Recuperación de partidos sin fecha pendiente de publicación

- Todo partido sin resultado y con fecha nula aparece como pendiente de gestión aunque su solicitud haya sido borrada.
- Las fechas del 31 de diciembre del año actual se respaldan en `reprogramaciones_fecha_normalizada` y luego se convierten a fecha nula.
- **Eliminar gestión** en Partidos en gestión crea una exclusión reversible en `reprogramaciones_ocultas`; no altera estado, fecha, recinto, resultado ni solicitudes.
- Prueba local: 12 partidos visibles, 9 fechas `31/12/2026` normalizadas con respaldo, 12 botones renderizados y partido sin cambios después de probar la eliminación.
- Estado: no commiteado y no publicado; la base productiva no fue modificada.

### Ficha emergente de partido publicada el 18 de agosto de 2026

- **Gestionar** abre un popup con fecha, cancha, estado, resultado, reserva original y contactos, usando la misma ficha administrativa de partidos.
- Los datos se consultan al abrir mediante `admin/api_partido.php`, protegido para rol administrador.
- Guardar se procesa por `admin/partidos.php` y vuelve a la pestaña correspondiente de Reprogramaciones.
- Las pruebas locales confirmaron HTTP 200, carga correcta, modal único y retorno HTTP 302; no se modificaron partidos durante la validación.
- Commit y producción: `3e989e5a`; webhook completado con código 0 y OPcache reiniciado.
- La publicación no abrió la ficha ni ejecutó Guardar, Eliminar gestión u otra acción sobre partidos en producción.

### Acciones rápidas de reprogramación publicadas el 18 de agosto de 2026

- Cada tarjeta permite abrir directamente **Ver partido**.
- **Eliminar gestión** borra solo la fila de `solicitudes_reprogramacion`; no actualiza ningún campo de `partidos`.
- La antigua acción del panel que devolvía el partido a pendiente y restauraba fecha/recinto dejó de estar disponible.
- La prueba local confirmó que el partido completo queda idéntico después de eliminar la solicitud y que no quedaron registros temporales de prueba.
- Archivo: `admin/dashboard_repro.php`.
- Commit y producción: `70fb9ca6`; webhook completado con código 0 y OPcache reiniciado.
- La publicación no ejecutó la acción ni modificó partidos; el usuario administrará cada caso desde el panel.

### Filtro Sin fecha publicado el 18 de agosto de 2026

- Gestión de Partidos permite filtrar los encuentros cuya `fecha_programada` es nula o corresponde al marcador histórico `2026-12-31`.
- La exportación Excel/CSV conserva el mismo filtro.
- La validación local devolvió 12 registros tanto en la consulta como en las filas renderizadas, sin mezclar fechas válidas.
- Archivo: `admin/partidos.php`.
- Commit y producción: `70fb9ca6`.

### Panel de reprogramaciones publicado el 18 de agosto de 2026

- `Pendientes` muestra únicamente casos que todavía requieren una acción.
- Las solicitudes aprobadas o rechazadas recientes están en `Gestionados`.
- Una solicitud pendiente no se duplica como partido en gestión y los badges cuentan partidos únicos.
- El panel ignora rechazos residuales que hayan quedado con `partidos.estado='reprogramado'`.
- El rechazo desde el propio dashboard restaura el partido a pendiente con su fecha y recinto originales.
- Commit y producción: `ecc8eb20`.
- Próximo paso: revisión visual del usuario en producción.

### Pestañas de partidos publicadas en dashboard el 20 de agosto de 2026

- `dashboard.php` incorpora un único bloque **Mis partidos** con dos pestañas internas.
- **Pendientes** contiene solo los encuentros sin una reprogramación activa y presenta de forma destacada día, fecha, hora, rival, jornada y cancha.
- **Reprogramados** reúne solicitudes propias, de la pareja y del rival; muestra quién inició el cambio, estado, fecha propuesta o vigente, fecha original y casos todavía sin fecha.
- Los partidos con reprogramación activa se excluyen de Pendientes para no duplicarlos.
- Se descartó la página independiente y el cambio de navegación propuestos inicialmente; `mis_partidos.php` no existe.
- Validación local: PHP correcto; HTTP 200; caso de prueba con 8 pendientes y 3 reprogramados, incluyendo una solicitud propia y una rival; vista de escritorio y 390×844 sin desborde; cero errores de consola.
- Commit y producción: `caa0413a`; webhook completado con código 0 y OPcache reiniciado.
- La publicación modificó únicamente `dashboard.php`; no cambió partidos ni otros datos deportivos.

### Buscador de rival al solicitar reprogramación publicado el 24 de agosto de 2026

- La pestaña **Solicitar** de `reprogramar.php` incorpora una barra de búsqueda antes de la lista de partidos.
- Filtra en tiempo real por nombre de la pareja o de sus jugadores rivales, sin distinguir mayúsculas ni tildes.
- El filtrado solo oculta tarjetas: conserva el orden original de los partidos y lo restaura al limpiar la búsqueda.
- Incluye contador de coincidencias, acción **Limpiar** y un estado claro cuando no hay resultados.
- Se corrigieron expresiones residuales de voseo en esta página para mantener español chileno neutral.
- Los estilos específicos se cargan desde `assets/css/reprogramar.css` mediante `$page_css`.
- Validación local: PHP correcto; 11 partidos restaurados en el mismo orden; coincidencias con y sin tilde; estado vacío correcto; escritorio y 390×844 sin desborde horizontal ni errores de consola.
- Commit y producción: `3f5e7433` — **Agrega buscador de rivales en reprogramaciones**.
- GitHub y DigitalOcean verificados; el webhook completó con código 0 y reinició OPcache.
- El CSS productivo respondió HTTP 200 y la página mantuvo la redirección protegida al login para visitantes sin sesión.
- No se modificaron partidos ni datos deportivos.

### Vista unificada de reprogramaciones del jugador publicada el 24 de agosto de 2026

- **Mis reprogramaciones** muestra una tarjeta por partido y toma la solicitud más reciente, tanto si la inició el jugador, su pareja o el rival.
- Cada tarjeta identifica el origen del cambio, fecha original, nueva fecha o **Sin fecha definida**, motivo y estado **Pendiente**, **Cambio aprobado** o **Cambio rechazado**.
- La vista normal excluye automáticamente los partidos con resultado o estado final; **Ver historial** incluye también esos partidos y muestra su resultado.
- El orden es cronológico descendente según la creación de la solicitud más reciente.
- Se conservó sin cambios la lógica de cupo y el botón **Asignar fecha** solo aparece bajo las mismas condiciones anteriores.
- La implementación modifica únicamente consultas de lectura y presentación; no escribe ni cambia partidos, solicitudes o resultados.
- Validación local con la pareja Romero–Merino: 3 tarjetas activas y 1 partido jugado en historial; casos propios y del rival; aprobados, rechazado y sin fecha; escritorio y 390×844 sin desborde ni errores de consola.
- Archivos: `reprogramar.php` y `assets/css/reprogramar.css`.
- Commit: `2d28f15d` — **Unifica las reprogramaciones del jugador**.
- Publicado en GitHub y DigitalOcean; el webhook completó con código 0 y reinició OPcache.
- El CSS versionado de producción respondió HTTP 200 y contenía la vista unificada; el acceso sin sesión mantuvo la redirección esperada al login.
- No se modificaron partidos ni datos deportivos durante la publicación.

### Contacto con EPL para solicitudes pendientes publicado el 24 de agosto de 2026

- Las tarjetas activas con estado **Pendiente** incorporan **Contactar EPL**; aprobadas, rechazadas y partidos jugados no muestran esta acción.
- El enlace abre WhatsApp al número de organización con un mensaje preparado que incluye parejas, liga, jornada, fecha original, fecha propuesta o **Sin fecha** y origen de la solicitud.
- Es una acción exclusivamente de contacto: no aprueba, rechaza ni modifica partidos o solicitudes.
- Validación local con solicitud temporal: mensaje completo en casos con y sin fecha propuesta, destino `56988182431`, escritorio y 390×844 sin desborde. La solicitud y sesión temporales fueron eliminadas y el partido conservó su solicitud anterior.
- Archivos: `reprogramar.php` y `assets/css/reprogramar.css`.
- Commit: `e79219f4` — **Agrega contacto EPL en solicitudes pendientes**.
- Publicado en GitHub y DigitalOcean; el webhook avanzó correctamente, reinició OPcache y terminó con código 0.
- Verificación productiva: CSS HTTP 200 con `.rp-repro-contact-btn`; la página protegida mantuvo su redirección al login y respondió desde DigitalOcean.
- No se modificaron partidos ni datos deportivos durante la publicación.

### Enlace administrativo dentro del WhatsApp publicado el 24 de agosto de 2026

- El mensaje de **Contactar EPL** incorpora la URL de la solicitud exacta en `admin/dashboard_repro.php`.
- Sin sesión, el enlace redirige al login conservando el destino; una cuenta de jugador común no puede abrirlo y solo el rol administrador accede al caso.
- El panel activa la pestaña y filtro correctos, desplaza la vista a la tarjeta y la resalta como **Abierta desde WhatsApp**.
- Para propuestas con fecha se agregó **Aprobar cambio**, con fecha precargada y cancha opcional; siguen disponibles Gestionar, Rechazar y Eliminar gestión. Los casos sin fecha conservan **Aprobar (Lib.)**.
- Validación local: retorno seguro al login, bloqueo de jugador, acceso administrador, tarjeta exacta visible, modal completo en escritorio y 390×844 sin desborde. No se envió el formulario de aprobación.
- La solicitud y sesiones temporales fueron eliminadas; el partido mantuvo su solicitud anterior y no quedaron datos de prueba.
- Archivos: `reprogramar.php`, `admin/dashboard_repro.php`, `admin/api_reprogramacion.php` y `assets/css/repro.css`.
- Commit: `9fa13e0f` — **Agrega enlace directo a gestión administrativa**.
- Publicado en GitHub y DigitalOcean; el webhook confirmó el repositorio actualizado, reinició OPcache y terminó con código 0.
- Verificación productiva: CSS HTTP 200 con el enfoque y el modal; el enlace administrativo sin sesión redirige al login conservando la solicitud y respondió desde DigitalOcean.
- No se modificaron partidos ni datos deportivos durante la publicación.

### Confirmación de Aprobar (Lib.) publicada el 24 de agosto de 2026

- Causa: el atributo de confirmación estaba solo en el formulario; después de aceptar, el código común ejecutaba `form.click()`, que no envía formularios.
- Corrección: el botón submit también contiene la confirmación, de modo que el segundo clic confirmado ejecuta el `POST` esperado.
- Validación local con formulario temporal: diálogo correcto y navegación posterior con el campo enviado; el archivo de prueba fue eliminado.
- PHP correcto y ningún partido fue aprobado o modificado durante la prueba.
- Archivo: `admin/dashboard_repro.php`.
- Commit: `02e389f8` — **Corrige aprobación de reprogramaciones sin fecha**.
- Publicado en GitHub y DigitalOcean; el webhook avanzó correctamente, reinició OPcache y terminó con código 0.
- Verificación productiva con sesión administrativa: el botón del caso contiene la confirmación y queda conectado al submit real.
- No se ejecutó la aprobación ni se modificaron partidos durante la publicación o verificación.

### EPL Score para Galaxy Watch pendiente de prueba y publicación

- Proyecto Android nativo: `wear-epl-score/`, paquete de prueba `cl.epleague.score.debug`, mínimo API 30.
- APK local: `wear-epl-score/apk/EPL-Score-Watch-FE.apk`.
- Backend local: `includes/watch.php`, `watch/api.php`, `reloj.php` y `assets/css/reloj.css`.
- El reloj se vincula mediante un código corto autorizado desde la sesión web del jugador; los secretos y tokens se guardan con hash y pueden revocarse desde `reloj.php`.
- El marcador funciona sin red durante el partido, persiste cada punto, soporta punto de oro/ventaja, tie-break y deshacer. Solo usa la red para vincular, listar partidos y enviar el resultado final.
- El envío usa clave de idempotencia para impedir duplicados y valida pertenencia del jugador, estado, fecha y prioridad de partidos atrasados antes de escribir.
- No solicita sensores ni permisos de actividad; Samsung Health sigue siendo responsable del entrenamiento físico.
- Pruebas locales completas: PHP, flujo de vinculación y limpieza, API HTTP, consulta de 11 partidos, rechazo seguro de resultado inválido, pruebas del motor, compilación APK y Android Lint.
- Estado: todo **solo en local**. El APK permite modo demo, pero no podrá vincularse a producción hasta que el usuario autorice publicar el backend.
- Instalación: depuración inalámbrica del Watch FE y script `wear-epl-score/instalar-en-reloj.ps1`.

## 15. Pendiente funcional inmediato

### Reprogramaciones

Antes de continuar con otras mejoras:

- Publicar de forma aislada el flujo definitivo de fecha aprobada y confirmación de cancha cuando el usuario lo autorice.
- Verificar en producción que la bandeja quede ordenada en 4 partidos **Sin fecha**, 4 partidos **Con fecha / Cancha por confirmar** y 2 **Solicitudes por aprobar**.
- No ejecutar Aprobar, Rechazar, Eliminar gestión ni Guardar durante la comprobación productiva.

Después, revisar localmente el cambio que restaura fecha y recinto originales al:

- Rechazar una solicitud desde API administrativa.
- Rechazar desde dashboard.
- Revertir manualmente un partido a pendiente.

Validar especialmente:

- Partido con fecha y recinto originales.
- Partido sin recinto original.
- Solicitud rechazada varias veces.
- Notificaciones posteriores y cron de recordatorios.
- Que no reaparezca una alerta usando una fecha suspendida.

### Acceso IA

- Probar el retorno OAuth después del login.
- Verificar visualmente `conectar_ia.php` en PC y móvil.
- Confirmar cuál integración realmente puede usar cada aplicación móvil.
- Mantener externalizado el consumo salvo nueva decisión explícita.

### Contexto compartido

- Commitear `AGENTS.md`, `ACTUALIZACIONES.md`, `CONTEXTOS.md` y el cambio de `CLAUDE.md` solo cuando el usuario apruebe publicar.
- Mantener `CONTEXTOS.md` como fotografía integral.
- Mantener `ACTUALIZACIONES.md` como bitácora cronológica.

### Portal público

- Revisar con el usuario la nueva dirección visual disponible en local antes de publicar.
- Portada, ranking de parejas y calendario de torneos validados en escritorio y viewport móvil 390×844, sin desborde horizontal ni imágenes rotas.
- El ranking muestra un estado inicial cuando `ranking_puntos` no tiene movimientos; al asignar posiciones desde los torneos, consolida como pareja los puntos idénticos de sus dos integrantes mediante `equipo_id`.
- La estructura histórica de ligas y parejas se conserva para no romper la operación existente.
- La base local `epldb` fue restaurada desde `migration/dump_vps_maria.sql` para pruebas; no corresponde a una sincronización actual de producción.

## 16. Datos y convenciones importantes

- `equipo_id` representa la identidad histórica de una pareja/equipo; reemplazar jugadores no debe crear un historial nuevo cuando la intención es continuidad deportiva.
- La fecha especial `2026-12-31` se ha usado históricamente como marcador de “sin fecha/TBD” en distintas consultas. Revisar siempre este caso.
- Estados comunes de partido: `pendiente`, `reprogramado`, `jugado`, `walkover`, `no_presentado`.
- Una reprogramación puede tener `fecha_original`, `recinto_original_id`, solicitud, propuesta, aprobación y confirmaciones independientes.
- No confiar solo en controles visuales: permisos y reglas deben validarse en PHP.
- Cambiar fecha/recinto puede requerir limpiar o regenerar tokens/confirmaciones y actualizar notificaciones.

## 17. Archivos sensibles o heredados

Existen en la carpeta local archivos de diagnóstico, CSV, ZIP y scripts históricos. No abrirlos ni publicarlos indiscriminadamente:

- `.env`.
- `vultr_backup.zip`.
- Exportaciones CSV.
- Archivos `scratch_*`, `check_*`, `test_*`, `debug_*`.
- Carpetas `scratch/`, `temp_archive/`, `.claude/worktrees/`.

Antes de limpiar o borrar cualquiera, confirmar si está rastreado por Git y obtener autorización. No copiar su contenido a una conversación.

## 18. Cómo continuar como agente

1. Leer `AGENTS.md`, `CLAUDE.md`, `CONTEXTOS.md` y `ACTUALIZACIONES.md`.
2. Ejecutar `git status --short`.
3. Identificar qué cambios pertenecen al usuario y cuáles a la tarea actual.
4. Probar solo en local.
5. Actualizar documentación al terminar.
6. Esperar aprobación antes de publicar.
7. Después de desplegar, verificar GitHub, producción y OPcache.

## 19. Plantilla para actualizar este contexto

```markdown
### Cambio: título breve

- Fecha:
- Solicitud:
- Estado anterior:
- Archivos modificados:
- Pruebas realizadas:
- Commit:
- GitHub: sí/no
- Producción: sí/no/no verificado
- Base de datos productiva tocada: no/sí con autorización
- Pendiente:
```
