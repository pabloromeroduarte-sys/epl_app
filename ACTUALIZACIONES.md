# Elite Padel League — bitácora compartida entre agentes

> Última actualización: **25 de agosto de 2026**  
> Objetivo: que Claude, Gemini, Antigravity, Codex u otro agente puedan continuar el trabajo sin perder el contexto.  
> Este archivo **no debe contener contraseñas, tokens, claves API ni contenido de `.env`**.

## Actualización 25 de agosto de 2026 — simplificación de pestañas en reprogramaciones del jugador

- Se simplificó la vista de reprogramaciones del jugador en `reprogramar.php` ocultando las pestañas secundarias **Todos los Reprogramados** y **Adelantar Fecha**.
- La interfaz del jugador quedó enfocada exclusivamente en dos opciones claras:
  1. **🔄 Solicitar**: para crear una solicitud de reprogramación con buscador de rivales.
  2. **📅 Mis Reprogramaciones**: vista unificada para el seguimiento de solicitudes activas de la pareja y partidos jugados.
- Se eliminaron las consultas SQL pesadas y la lógica JS/Flatpickr asociada a esas pestañas secundarias para hacer la carga mucho más rápida y ligera.
- En `dashboard.php`, se simplificó el enlace de reprogramaciones para dirigir de forma consistente a `#mis-reprogramaciones`.
- Validación: sintaxis PHP sin errores en `reprogramar.php` y `dashboard.php`. No se modificaron datos de la base de datos ni producción.
- Archivos: `reprogramar.php`, `dashboard.php`, `CONTEXTOS.md` y `ACTUALIZACIONES.md`.
- Estado: cambio **desplegado a producción (DigitalOcean) y subido a GitHub (commit `4d5ec9bf`)**.

## Actualización 24 de agosto de 2026 — flujo definitivo de reprogramación y cancha

- Se definió la separación de responsabilidades: EPL o el mutuo acuerdo aprueban la reprogramación y su fecha; el club **solo** libera una reserva original todavía vigente y asigna/confirma la cancha de la nueva fecha.
- Un partido sin fecha queda en **Sin fecha** para coordinación deportiva. El club no recibe una solicitud de cancha nueva hasta que exista una fecha definida.
- Un partido con fecha aprobada queda en **Con fecha propuesta / Cancha por confirmar** hasta que `cancha_confirmada_at` y `recinto_id` acrediten una confirmación explícita del club o del administrador.
- Se detectó que `recinto_id` podía conservar la cancha original después de pedir el cambio. Desde ahora, al crear o aprobar una reprogramación la reserva anterior se conserva en `recinto_original_id` y la cancha actual se limpia; no se confunde una referencia antigua con una cancha nueva confirmada.
- El enlace público de gestión dejó de aprobar fechas o solicitudes. Solo procesa la liberación de la reserva anterior cuando sigue vigente y/o la selección de la cancha nueva.
- El formulario administrativo de aprobación ya no permite escoger una cancha: aprueba únicamente la fecha y avisa que la cancha queda por confirmar con el club.
- El panel decide primero **Solicitudes por aprobar** y luego presenta **Partidos por completar**, separados en **Sin fecha** y **Con fecha propuesta**. Los casos con cancha confirmada pasan a **Gestionados**.
- Abrir el panel ya no borra confirmaciones de cancha ni crea el estado falso de “mensaje enviado”. Las reservas originales vencidas dejan de solicitar liberación, pero una fecha nueva continúa pendiente hasta confirmar su cancha.
- Validación local completa con un partido temporal: apareció en **Club: confirmar cancha nueva**, el enlace del club mostró únicamente la selección de cancha, no permitió aprobar la fecha y, tras confirmar una cancha, desapareció de Pendientes y apareció en Gestionados. El partido y la solicitud temporales fueron eliminados.
- PHP sin errores y sin archivos temporales. No se modificaron datos productivos.
- Archivos: `reprogramar.php`, `admin/api_reprogramacion.php`, `admin/dashboard_repro.php`, `admin/partido_detalle.php`, `admin/partidos.php`, `gestion_reserva.php`, `includes/functions.php` y `assets/css/repro.css`.
- Estado: cambio **solo en local, no commiteado y no publicado**.
- Próximo paso: publicación aislada cuando la autorice el usuario y comprobación productiva sin ejecutar acciones sobre partidos reales.

## Actualización 24 de agosto de 2026 — corrección de Aprobar (Lib.)

- Se identificó que la confirmación de **Aprobar (Lib.)** estaba asociada al formulario: al marcar **Sí, liberar cancha**, el código hacía clic sobre el formulario y no lo enviaba.
- La confirmación quedó asociada también al botón de envío, por lo que ahora el segundo clic ejecuta el `POST` administrativo real.
- Validación local con una página temporal sin datos deportivos: apareció la confirmación y **Sí, liberar cancha** envió correctamente el formulario. El archivo temporal fue eliminado.
- PHP sin errores de sintaxis; no se aprobó, rechazó ni modificó ningún partido durante la prueba.
- Archivo: `admin/dashboard_repro.php`.
- Commit: `02e389f8` — **Corrige aprobación de reprogramaciones sin fecha**.
- Publicado en GitHub y DigitalOcean; el webhook aplicó avance rápido, reinició OPcache y terminó con código 0.
- Verificación productiva con sesión administrativa: el botón del caso conserva la confirmación y **Sí, liberar cancha** queda conectado al submit real.
- No se ejecutó la aprobación ni se modificaron partidos durante la publicación o verificación.
- Próximo paso: el usuario puede aprobar nuevamente el caso desde producción.

## Actualización 24 de agosto de 2026 — enlace seguro a la gestión administrativa

- El WhatsApp de **Contactar EPL** ahora incluye un enlace a la solicitud exacta dentro del panel de Reprogramaciones.
- Sin sesión, el enlace pasa por el login y conserva el destino; una cuenta de jugador queda bloqueada y solo una cuenta administradora puede ver el caso.
- El panel abre la pestaña y filtro correctos, resalta la tarjeta y deja disponibles Gestionar, Aprobar, Rechazar y Eliminar gestión.
- Para solicitudes con fecha propuesta se incorporó un modal de aprobación con fecha precargada y cancha opcional; las solicitudes sin fecha mantienen su aprobación de liberación.
- Validación local: flujo sin sesión, bloqueo de jugador, acceso administrador, enlace y solicitud correctos, modal en escritorio y 390×844 sin desborde. No se confirmó ninguna aprobación.
- La solicitud, sesión y acceso auxiliar temporales fueron eliminados; no quedaron datos deportivos de prueba.
- Archivos: `reprogramar.php`, `admin/dashboard_repro.php`, `admin/api_reprogramacion.php` y `assets/css/repro.css`.
- Commit: `9fa13e0f` — **Agrega enlace directo a gestión administrativa**.
- Publicado en GitHub y DigitalOcean; el webhook confirmó que el servidor estaba actualizado, reinició OPcache y terminó con código 0.
- Verificación productiva: CSS HTTP 200 con el enfoque y el modal; el acceso sin sesión redirige al login conservando el identificador de la solicitud y respondió desde DigitalOcean.
- No se modificaron partidos ni datos deportivos durante la publicación.
- Próximo paso: revisión del usuario en producción.

## Actualización 24 de agosto de 2026 — contacto con EPL para reprogramaciones pendientes

- Se agregó **Contactar EPL** únicamente a las tarjetas activas cuyo estado es **Pendiente**.
- El enlace abre WhatsApp al número de organización con un mensaje preparado: partido, liga, jornada, fecha original, fecha propuesta o **Sin fecha** y quién originó la solicitud.
- El botón no ejecuta cambios sobre el partido o la solicitud; solo abre el canal de ayuda.
- Validación local con una solicitud temporal: destino y texto correctos en variantes con y sin fecha propuesta; ningún botón en tarjetas aprobadas; escritorio y 390×844 sin desborde.
- La solicitud y la sesión temporales fueron eliminadas; el partido quedó nuevamente con su solicitud anterior rechazada. No quedaron datos deportivos de prueba.
- Archivos: `reprogramar.php` y `assets/css/reprogramar.css`.
- Commit: `e79219f4` — **Agrega contacto EPL en solicitudes pendientes**.
- Publicado en GitHub y DigitalOcean; el webhook aplicó avance rápido, reinició OPcache y terminó con código 0.
- Verificación productiva: CSS HTTP 200 con el estilo del botón; la página protegida mantuvo la redirección esperada al login y confirmó el origen DigitalOcean.
- No se modificaron partidos ni datos deportivos durante la publicación.
- Próximo paso: revisión del usuario en producción.

## Actualización 24 de agosto de 2026 — vista unificada de reprogramaciones del jugador

- Se reemplazó la separación entre activas propias e historial propio por una sola vista de **Mis reprogramaciones**.
- La consulta toma el movimiento más reciente de cada partido del equipo e incluye solicitudes iniciadas por el jugador, su pareja o el rival.
- Las tarjetas muestran origen, solicitante, jornada, fecha original, nueva fecha o **Sin fecha definida**, motivo y estado pendiente, aprobado o rechazado.
- Los partidos jugados desaparecen de la vista normal y se recuperan con **Ver historial**, donde también aparece el resultado.
- El orden se mantiene desde la solicitud más reciente a la más antigua.
- La lógica de cupo, solicitudes y asignación de fecha no fue modificada; **Asignar fecha** conserva sus condiciones anteriores.
- Validación local con Romero–Merino: 3 reprogramaciones activas, 1 jugada en historial, solicitudes propias y del rival, cambio rechazado con fecha original recuperada para la vista, escritorio y 390×844 sin desborde horizontal ni errores de consola.
- La sesión temporal se cerró y el acceso auxiliar fue eliminado. No se modificaron partidos ni datos deportivos.
- Archivos: `reprogramar.php` y `assets/css/reprogramar.css`.
- Commit: `2d28f15d` — **Unifica las reprogramaciones del jugador**.
- Publicado en GitHub y DigitalOcean; el webhook aplicó avance rápido, terminó con código 0 y reinició OPcache.
- La comprobación del CSS versionado devolvió HTTP 200 con los estilos nuevos; la página protegida mantuvo la redirección esperada al login para visitantes.
- No se modificaron partidos ni datos deportivos durante la publicación.
- Próximo paso: revisión del usuario en producción.

## Actualización 24 de agosto de 2026 — búsqueda de pareja rival al solicitar reprogramación

- Se agregó en la pestaña **Solicitar** de `reprogramar.php` una búsqueda en tiempo real por nombre de la pareja o de sus jugadores rivales.
- La búsqueda ignora mayúsculas y tildes, muestra coincidencias y estado vacío, permite limpiar con un botón y conserva el orden original de los partidos.
- Se corrigieron expresiones residuales de voseo en esta página para mantener español chileno neutral.
- Los estilos nuevos viven en `assets/css/reprogramar.css`, cargados desde el `<head>` mediante `$page_css`.
- Validaciones locales: PHP sin errores; 11 partidos visibles al inicio y después de limpiar; coincidencias por `Martiz`, `Vargas` y `Verastegui`; estado sin resultados correcto; vista de escritorio y 390×844 sin desborde horizontal; cero errores de consola.
- La sesión local temporal usada para la prueba fue cerrada y el acceso auxiliar eliminado. No se modificaron partidos ni otros datos deportivos.
- Commit: `3f5e7433` — **Agrega buscador de rivales en reprogramaciones**.
- Publicado en GitHub y DigitalOcean; el webhook aplicó avance rápido, terminó con código 0 y reinició OPcache.
- Verificación productiva: el CSS nuevo respondió HTTP 200 y la página mantuvo la redirección esperada al login para visitantes sin sesión.
- Próximo paso: revisión del usuario en producción y continuar con la siguiente mejora de reprogramaciones.

## Actualización 20 de agosto de 2026 — prototipo EPL Score para Galaxy Watch

- Se creó en `wear-epl-score/` una aplicación nativa Wear OS para Galaxy Watch FE, compatible con el uso simultáneo de Samsung Health.
- Incluye vinculación por código sin contraseña, listado de partidos del jugador, punto de oro o ventaja, tie-break, deshacer, guardado después de cada punto, recuperación tras apagar la pantalla y envío idempotente del resultado.
- Se agregó en local `reloj.php`, `watch/api.php`, `includes/watch.php` y `assets/css/reloj.css` para autorizar/revocar relojes, consultar partidos propios y registrar resultados con las mismas restricciones temporales vigentes.
- La aplicación no pide permisos de sensores, actividad física, frecuencia cardiaca ni ubicación; no mantiene pantalla, red o servicios activos durante el partido.
- Validaciones: PHP sin errores; vinculación completa contra la base local con registros de prueba limpiados; API HTTP local operativa; 11 partidos recuperados para el jugador de prueba; envío a partido inexistente rechazado sin modificar datos; pruebas unitarias del marcador, compilación y Android Lint correctos.
- APK instalable local: `wear-epl-score/apk/EPL-Score-Watch-FE.apk`. Se agregó `instalar-en-reloj.ps1` para vincular e instalar por ADB inalámbrico.
- Estado: **solo local, no commiteado y no publicado**. El APK funciona en modo demo; la vinculación con EPL productivo requiere publicar primero los endpoints, únicamente cuando el usuario lo autorice.
- Próximo paso: conectar el Watch FE por depuración inalámbrica, instalar el APK y revisar la experiencia real en cancha antes de publicar el backend.

## Actualización 20 de agosto de 2026 — pestañas de partidos publicadas en el dashboard

- El dashboard del jugador incorpora el bloque **Mis partidos** con dos pestañas: **Pendientes** y **Reprogramados**.
- **Pendientes** muestra de forma destacada día, fecha completa, hora, rival, jornada, recinto/cancha y si falta ingresar el resultado. Las reprogramaciones activas se excluyen de esta pestaña para evitar duplicados.
- **Reprogramados** incluye tanto los cambios solicitados por el jugador o su partner como los solicitados por el rival. Cada tarjeta indica el origen, solicitante, estado, fecha propuesta/reprogramada, fecha original y cuándo aún no existe una fecha acordada.
- El diseño anterior que creaba una página y un acceso separado fue descartado; no existe `mis_partidos.php` ni se cambió la navegación principal.
- Validación local con un jugador que tiene ambos sentidos de reprogramación: PHP sin errores; HTTP 200; 8 pendientes y 3 reprogramados; una solicitud propia y una del rival identificadas correctamente; revisión visual en escritorio y 390×844 sin desborde horizontal; cero errores de consola.
- Las sesiones temporales de prueba fueron eliminadas. No se modificaron partidos ni otros datos deportivos.
- Archivo: `dashboard.php`.
- Commit: `caa0413a` — **Agrega pestañas de partidos al dashboard**.
- Publicado en GitHub y DigitalOcean. El webhook aplicó el avance rápido, finalizó con código 0 y reinició OPcache.
- La comprobación HTTPS devolvió la redirección 302 esperada al login para visitantes sin sesión. La verificación SSH directa no estuvo disponible por autenticación de clave, pero el propio webhook confirmó el commit y la actualización del archivo en el VPS.
- Producción no recibió modificaciones de datos deportivos; solo código del dashboard.

## Actualización 19 de agosto de 2026 — menú simple de Reprogramaciones pendiente de publicación

- El flujo principal quedó reducido a dos accesos grandes: **Sin fecha** y **Con fecha propuesta**, cada uno con su contador e indicación de la tarea siguiente.
- La clasificación depende únicamente de si existe `fecha_propuesta`; **Rival no responde** se mantiene como alerta, pero no mueve una propuesta válida al grupo equivocado.
- **Gestionados** pasó a una acción secundaria y **Informe** se renombró **Resumen general**.
- La búsqueda respeta el grupo seleccionado y oculta las secciones que no tienen coincidencias.
- Validación local: HTTP 200, PHP y JavaScript válidos; 12 casos sin fecha clasificados correctamente. Una solicitud temporal con fecha propuesta produjo 11 sin fecha y 1 con fecha, mostró su información en el grupo correcto y fue eliminada al terminar.
- Archivos: `admin/dashboard_repro.php` y `assets/css/repro.css`.
- Estado: cambio **solo en local, no commiteado y no publicado**. Producción no fue modificada.

## Actualización 18 de agosto de 2026 — recuperación de partidos sin fecha pendiente de publicación

- El panel incorpora a **Partidos en gestión** cualquier partido sin resultado y sin fecha, aunque su solicitud administrativa haya sido eliminada.
- El marcador histórico del 31 de diciembre del año actual se normaliza a fecha nula; antes se guarda la fecha anterior en `reprogramaciones_fecha_normalizada` para auditoría y recuperación.
- Se agregó **Eliminar gestión** a las tarjetas de Partidos en gestión. La acción solo oculta la tarjeta mediante `reprogramaciones_ocultas`; no cambia estado, fecha, cancha, resultado ni solicitudes del partido.
- Validación local: 12 partidos sin resultado quedaron visibles; 9 fechas `31/12/2026` fueron respaldadas y normalizadas; la prueba de eliminación ocultó una tarjeta y confirmó que el partido completo permaneció idéntico. El registro de prueba fue limpiado.
- PHP sin errores, HTTP local 200 y los 12 botones de eliminación renderizados.
- Estado: cambio **solo en local, no commiteado y no publicado**. Producción no fue modificada.

## Actualización 18 de agosto de 2026 — ficha emergente desde Reprogramaciones

- Los botones **Gestionar** del panel ya no abandonan la página: abren la misma ficha editable usada por la gestión administrativa de partidos.
- La ficha carga al momento del clic los datos actuales de fecha, recinto, estado, resultado, reserva original y contactos.
- Guardar sigue siendo una acción explícita y vuelve a la pestaña de Reprogramaciones desde la cual se abrió el popup.
- Se agregó un endpoint de solo lectura y acceso exclusivo para administradores: `admin/api_partido.php`.
- Validación local: PHP sin errores, panel HTTP 200, datos del partido correctos, un solo modal renderizado, recintos disponibles y retorno HTTP 302 al panel.
- La sesión administrativa temporal usada en la prueba fue eliminada y no se modificaron partidos.
- Commit: `3e989e5a` — **Abre la ficha del partido desde reprogramaciones**.
- Publicado en GitHub y DigitalOcean; webhook finalizó con código 0 y reseteó OPcache.
- Durante la publicación no se abrió la ficha ni se ejecutaron Guardar, Eliminar gestión u otras acciones sobre partidos en producción.

## Actualización 18 de agosto de 2026 — gestión rápida de reprogramaciones publicada

- Cada reprogramación muestra un acceso directo **Ver partido**.
- Se agregó **Eliminar gestión**, que borra únicamente la solicitud administrativa y no modifica estado, fecha, recinto ni otros datos del partido.
- Se retiró de la interfaz y del backend del panel la acción antigua que restauraba el partido al borrar una reprogramación.
- La confirmación explica explícitamente que el partido conservará sus datos actuales.
- Validación local: PHP sin errores; tarjeta sin fecha renderizada con ambos botones y prueba transaccional con solicitud temporal eliminada, partido completo sin cambios y datos de prueba limpiados.
- Se corrigieron expresiones residuales de voseo en este panel para mantener español chileno neutral.
- Commit: `70fb9ca6` — **Agrega gestión segura y filtro de partidos sin fecha**.
- Publicado en GitHub y DigitalOcean; webhook finalizó con código 0 y reseteó OPcache.
- Durante la publicación no se ejecutó ninguna acción del panel ni se modificaron partidos en producción.

## Actualización 18 de agosto de 2026 — filtro de partidos sin fecha publicado

- Se agregó **📅 Sin fecha** al filtro Estado de Gestión de Partidos.
- La consulta incluye tanto fechas nulas como el marcador histórico `2026-12-31` y el filtro se conserva al exportar a Excel/CSV.
- Validación local: PHP sin errores; los 12 partidos esperados coinciden con la consulta, las filas renderizadas y la opción seleccionada.
- Commit y publicación: `70fb9ca6`, GitHub y DigitalOcean verificados.
- Próximo paso: revisión visual del usuario en producción.

## Actualización 18 de agosto de 2026 — panel de reprogramaciones

- Se corrigió la separación entre **Pendientes** y **Gestionados**: las solicitudes aprobadas o rechazadas ya no aparecen dentro del grupo pendiente.
- Cada partido se representa una sola vez; las solicitudes pendientes ya no se duplican además como “partido en gestión”.
- Los contadores ahora usan partidos únicos y el historial reciente queda en **Gestionados**.
- Los rechazos residuales dejan de contaminar la lista activa y el rechazo desde el panel restaura el partido a su fecha/recinto original.
- Validación local: PHP sin errores, render completo con la base local, 2 pendientes reales, 15 gestionados y cero aprobadas/rechazadas visibles en Pendientes.
- Commit: `ecc8eb20` — **Ordena las reprogramaciones pendientes y gestionadas**.
- Publicado en GitHub y DigitalOcean; webhook finalizó con código 0 y reseteó OPcache.
- Próximo paso: revisión visual del usuario en producción.

## Cómo usar este archivo

- Leerlo completo antes de modificar el proyecto.
- Actualizar la fecha y las secciones **Publicado**, **Pendiente local** y **Próximo paso** después de cada trabajo importante.
- No marcar algo como publicado hasta comprobar GitHub y producción.
- Los cambios reales se verifican con `git status`, `git diff` y `git log`; esta bitácora es un resumen de coordinación.
- Para el contexto integral actualizado del sistema, leer también `CONTEXTOS.md`.

## Ajuste de enfoque 16 de agosto de 2026 — Road to Máster

- Se realizó una auditoría del texto realmente renderizado en portada, FAQ, ranking, calendario, navegación y footer. Se reemplazó el voseo argentino por tuteo chileno consistente y se localizaron expresiones visibles como “Road to Máster”, “Partners”, “win-rate”, “pack” y “email”.
- El usuario definió el foco definitivo: **un calendario de torneos durante el año, puntos acumulados por pareja y clasificación de las mejores parejas al Máster Final**.
- La portada ahora comunica “Todo el año. Una gran final”, con calendario anual, ranking de parejas y ruta al Máster.
- `ranking.php` dejó de presentar una clasificación individual móvil: ahora agrupa los puntos de ambos integrantes por `equipo_id` dentro de la temporada anual y muestra la carrera de parejas al Máster.
- `torneos.php` presenta el calendario anual y una ruta de cuatro etapas: elegir fechas, competir, acumular puntos y clasificar al Máster Final.
- Se ajustaron SEO, footer, FAQ y el resumen de puntos del área del jugador para mantener el mismo concepto.
- La asignación administrativa sigue guardando el mismo puntaje para ambos jugadores; el ranking público lo consolida como resultado de la pareja sin modificar producción.
- Validado en local: PHP correcto, HTTP 200, escritorio y 390×844, sin desbordes ni imágenes rotas.
- Estado: cambios **solo en local, no commiteados y no publicados**.

## Actualización 16 de agosto de 2026

- Se redefinió localmente el portal público alrededor del nuevo foco comercial EPL: **torneos por categoría resueltos en un día, puntos por posición y ranking 100% individual de las últimas 52 semanas**.
- La portada ahora presenta el formato, la escala de puntos, el recorrido grupos → finales → ranking y un adelanto de la clasificación personal.
- Se creó `ranking.php` con filtros por categoría/sexo, podio, tabla completa, estado inicial sin resultados y reglas de puntuación; sus estilos viven en `assets/css/ranking.css`.
- Se modernizó `torneos.php` para priorizar próximas fechas, cupos y puntos personales; sus estilos se movieron a `assets/css/torneos-public.css`.
- Se añadió **Ranking** a la navegación pública de escritorio, móvil y footer, junto con mejoras de accesibilidad del menú móvil.
- Se corrigieron las rutas PWA del header para que el Service Worker, manifest, icono y suscripción push funcionen también bajo la subcarpeta local.
- Se mantiene la estructura histórica de ligas y parejas para no perder datos ni compatibilidad; el nuevo ranking se calcula por jugador.
- Pruebas locales: sintaxis PHP correcta, HTTP 200 en portada/ranking/torneos, revisión visual de escritorio y 390×844, menú móvil operativo, sin desborde horizontal ni imágenes rotas.
- Estado: cambios **solo en local, no commiteados y no publicados**.

## Actualización 15 de agosto de 2026

- Se modernizó localmente la portada pública conservando la identidad EPL azul noche, dorada y deportiva.
- El nuevo hero muestra llamados a acción visibles, temporada activa, próximo partido y líder de la clasificación.
- Los estilos específicos de la portada se movieron desde un bloque `<style>` dentro del body a `assets/css/home.css`, cargado desde el `<head>` mediante `$page_css`.
- Se restauró en XAMPP la copia local `migration/dump_vps_maria.sql` en la base `epldb` para recuperar el entorno de pruebas; producción no fue modificada.
- Pruebas locales: PHP sin errores de sintaxis, HTTP 200, CSS específico cargado, revisión visual en escritorio y 390×844, sin desborde horizontal ni imágenes rotas.
- Cambio pendiente local y **no publicado**: `index.php` y `assets/css/home.css`.

## Actualización 14 de agosto de 2026

- Se creó `CONTEXTOS.md` como fotografía integral para trabajo entre agentes.
- GPT Actions y sus tutoriales ya están incorporados en `main` mediante los commits `d0508cc1`, `35e19080`, `549d1544` y `8a94dd3a`.
- Se agregó el módulo administrativo **Torneo Copa**, cuyo último commit es `f4918417`.
- `HEAD` y `origin/main` están sincronizados en `f4918417`.
- Siguen pendientes en local las correcciones para restaurar fecha/recinto originales al rechazar o revertir reprogramaciones, además de accesos visuales a Conectar IA y los documentos de contexto.

## Estado general

- Aplicación: sistema PHP propio de **Elite Padel League**; no es WordPress.
- Carpeta local: `C:\xampp\htdocs\elitepadelleague`.
- URL local: `http://localhost/elitepadelleague/`.
- Repositorio: `https://github.com/pabloromeroduarte-sys/epl_app.git`.
- Rama de producción: `main`.
- Dominio productivo: `https://epleague.cl`.
- Servidor productivo: DigitalOcean, IP pública `165.227.109.215`.
- Ruta de producción: `/var/www/elitepadelleague`.
- Servidor antiguo Vultr: `207.246.68.77`; se conserva únicamente como respaldo y **no debe tocarse**.
- Base de datos: `epldb`. Las credenciales están solo en `.env` local/servidor y nunca deben escribirse aquí.

## Reglas de oro

1. **Nunca modificar directamente la base de datos de producción** salvo autorización explícita y un procedimiento previamente revisado. Los cambios normales son de código/sistema.
2. **Nunca tocar ni borrar el servidor antiguo de Vultr**.
3. Probar primero en local.
4. No publicar hasta que el usuario diga expresamente “envíalo”, “publica” o equivalente.
5. No commitear `.env`, respaldos SQL, claves, tokens ni contraseñas.
6. Preservar los cambios locales ajenos; no usar `git reset --hard` ni restaurar archivos modificados por otro agente.
7. Desplegar preferentemente con PowerShell/GitHub/webhook, no desde la consola web de Vultr.

## Flujo de publicación

Cuando todo el árbol local corresponde a un único cambio aprobado:

```powershell
cd C:\xampp\htdocs\elitepadelleague
.\scripts\publicar.ps1 -Aprobado -Mensaje "descripción breve del cambio"
```

Si existen cambios locales de otros trabajos, **no usar el script con `git add -A`**. Agregar y commitear únicamente los archivos autorizados; después activar el webhook de DigitalOcean sin exponer el token.

El servidor viejo no forma parte del despliegue.

## Trabajo funcional ya incorporado al sistema

### Ambiente e infraestructura

- Se restauró el ambiente local después del formateo del PC usando el sistema PHP y una copia de datos de producción para pruebas.
- Se descartó un respaldo antiguo que correspondía a WordPress.
- Se corrigió la incompatibilidad de collation MySQL 8 → MariaDB durante la restauración local.
- Producción fue migrada desde Vultr a DigitalOcean.
- `epleague.cl` apunta al servidor nuevo.
- El despliegue usa GitHub + webhook y resetea OPcache para evitar que producción muestre código antiguo.

### Resultados y partidos

- Registro de resultados bloqueado para partidos demasiado futuros.
- Se permite registrar desde el mismo día, un día hacia adelante y cualquier fecha anterior.
- Los partidos reprogramados sin fecha no permiten registrar resultados.
- Exportación de partidos a Excel/CSV desde administración.
- Calendario para administradores y clubes con filtro por liga.
- Los administradores pueden abrir un partido desde el calendario y modificarlo en un modal.

### Equipos y jugadores

- Detalle de equipos con estadísticas, historial, “galletas”, partidos jugados y pendientes.
- Edición de equipo conservando el mismo `equipo_id`: permite cambiar nombre y reemplazar uno o ambos jugadores sin perder el historial deportivo.
- Buscador de equipos/parejas por nombre.

### Rol Club

- Rol `club` con acceso limitado a ligas asignadas por administración.
- Pantalla de resultados y calendario filtrados por sus ligas.
- Los permisos se validan en servidor, no solo en la interfaz.

### Reprogramaciones

- Panel administrativo rediseñado y optimizado para móvil.
- Filtros por pendientes, gestionados y todos.
- Buscador por pareja, partido o liga.
- Mejor manejo de fecha/cancha original, partidos sin fecha, solicitudes y notificaciones.
- Pestaña **Adelantar Fecha**: identifica equipos afectados por una reprogramación, muestra enfrentamientos previos y permite filtrar disponibilidad por fecha.
- Corrección publicada para borrar `fecha_programada` al reprogramar y evitar recordatorios con la fecha original cuando el partido sigue sin aprobar.

### Correos y notificaciones

- Corrección de recinto jerárquico en recordatorios.
- Corrección de etiquetas `<strong>` que aparecían como texto.
- Mejora visual del botón de correo.
- Notificaciones de cambios de fecha/recinto y flujos de reprogramación.

## MCP / acceso desde asistentes externos

### Publicado

- Commit `a854f0f8`: **MCP remoto con OAuth y permisos por rol**.
- Commit `7be888f2`: **compatibilidad SSE para la conexión de Claude**.
- Endpoint productivo: `https://epleague.cl/mcp/`.
- OAuth incluye descubrimiento, registro dinámico de clientes, autorización y token.
- Herramientas disponibles:
  - `quien_soy`
  - `listar_ligas`
  - `buscar_partidos`
  - `ver_partido`
  - `ver_reprogramaciones`
  - `listar_recintos` (admin)
  - `solicitar_reprogramacion` (jugador autorizado y con confirmación)
  - `administrar_partido` (admin y con confirmación)
- Permisos efectivos:
  - Admin: consulta general y administración autorizada.
  - Club: solo ligas asignadas.
  - Jugador: solo sus equipos/partidos y solicitudes propias.
- Las escrituras requieren confirmación explícita y quedan en auditoría.

### Diagnóstico más reciente

- Claude completaba OAuth correctamente, pero luego hacía `GET /mcp/` y EPL respondía `405`.
- Se agregó transporte HTTP+SSE compatible, conservando el transporte Streamable HTTP existente.
- Validaciones realizadas antes de publicar:
  - SSE autenticado entrega el evento `endpoint`.
  - El POST del transporte SSE devuelve `202` y encola la respuesta JSON-RPC.
  - El POST Streamable HTTP continúa devolviendo `200` con `initialize` correcto.
  - Producción dejó de responder `405`; un GET sin token devuelve `401`, que es lo correcto.
- A pesar de esto, el usuario informó que el conector aún **no aparece dentro de Claude**. Puede tratarse de una limitación/interfaz del cliente Claude, no del OAuth ni del endpoint EPL.

## Decisión actual sobre IA y consumo

El usuario quiere que el procesamiento del modelo se **externalice** para no asumir centralmente el consumo de Claude/Gemini:

- Preferencia: que Claude, Gemini o Antigravity usen el MCP con la cuenta/cuota del usuario o del cliente externo.
- No se debe implementar todavía un chat central que consuma claves API pagadas por EPL sin definir quién paga y cómo se limita el consumo.
- Antigravity en PC admite MCP remoto.
- Claude puede usar MCP remoto donde su producto/plan lo permita.
- La aplicación móvil de cada proveedor puede tener limitaciones para conectores personalizados; no confundir una limitación de la app móvil con una falla del MCP.
- Alternativas a evaluar:
  1. Mantener MCP como integración principal para clientes compatibles.
  2. BYOK: cada usuario aporta su propia clave API y asume su consumo.
  3. Un cliente/PWA EPL que ejecute consultas, pero solo después de definir claramente proveedor, claves, cuotas y almacenamiento seguro.

## Trabajo local pendiente y NO publicado

El árbol local contiene cambios que no deben mezclarse ni publicarse automáticamente:

- Modificados:
  - `admin/dashboard_repro.php`
  - `admin/api_reprogramacion.php`
  - `admin/partido_detalle.php`
  - `assets/css/repro.css`
  - `assets/css/epl.css`
  - `includes/player_sidebar.php`
  - `login.php`
  - `tutoriales.php`
- Nuevos/no rastreados:
  - `ACTUALIZACIONES.md`
  - `AGENTS.md`
  - `CONTEXTOS.md`
- `CLAUDE.md` está modificado para referenciar ambos documentos compartidos.
- `.claude/worktrees/confident-chaplygin-e5fd5c` aparece modificado; es infraestructura de trabajo y no debe incluirse a ciegas en un commit.

### Qué contienen estos cambios pendientes

- Menú simple para trabajar por separado reprogramaciones sin fecha y con fecha propuesta, dejando historial y resumen como opciones secundarias.
- Recuperación automática de partidos sin resultado/sin fecha en Reprogramaciones, normalización respaldada del 31/12 y eliminación reversible de tarjetas del panel.
- Restauración de fecha y recinto originales al rechazar una solicitud o revertir un partido reprogramado a pendiente.
- Ajustes visuales para marcar correctamente el acceso activo en sidebar y navegación móvil.
- Acceso a `conectar_ia.php` desde navegación del jugador y tutoriales.
- Corrección del retorno OAuth de MCP y GPT Actions después del login, incluido el rol Club.
- Documentación compartida para continuidad entre agentes.

### IA ya incorporada a main

- GPT Actions, OAuth, OpenAPI y tutoriales fueron incorporados en los commits `d0508cc1`, `35e19080`, `549d1544` y `8a94dd3a`.
- `includes/ai_assistant.php` está rastreado por Git, pero actualmente no aparece incluido por otras páginas; revisar su utilidad antes de ampliarlo.
- El usuario mantiene la decisión de externalizar el consumo del modelo.

## Próximo paso recomendado

1. Revisar con el usuario la nueva portada local y aplicar los ajustes visuales solicitados.
2. Probar la restauración de fecha/recinto al rechazar o revertir reprogramaciones.
3. Probar visualmente los accesos a Conectar IA y el retorno OAuth.
4. Separar los cambios funcionales de los documentos de contexto en commits revisables.
5. No modificar más el MCP hasta comprobar desde qué producto/plan de Claude se intenta usar y dónde debería aparecer el conector.
6. Mantener MCP/GPT Actions como consumo externo; evaluar BYOK solo con una decisión explícita.
7. Nunca publicar todos los cambios locales con `git add -A` sin auditoría.

## Archivos de contexto para agentes

- `CLAUDE.md`: instrucciones operativas para Claude.
- `AGENTS.md`: instrucciones operativas para Codex y otros agentes compatibles.
- `CONTEXTOS.md`: fotografía integral y actual del proyecto.
- `ACTUALIZACIONES.md`: esta bitácora de estado compartida.
- `.env`: configuración secreta local; **no leerla en voz alta, no copiarla a conversaciones y no commitearla**.

## Plantilla para futuras actualizaciones

Al terminar una tarea, agregar una entrada breve:

```markdown
### AAAA-MM-DD — Título

- Solicitud:
- Archivos modificados:
- Pruebas realizadas:
- Commit:
- Publicado en producción: sí/no
- Pendiente:
```
