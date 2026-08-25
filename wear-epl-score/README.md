# EPL Score para Galaxy Watch

Prototipo nativo Wear OS, pensado para funcionar junto con Samsung Health.

## Funciones incluidas

- Vinculación segura mediante código, sin escribir la contraseña en el reloj.
- Lista de partidos pendientes pertenecientes al jugador.
- Marcador al mejor de tres sets.
- Punto de oro o ventaja tradicional.
- Tie-break automático en 6-6.
- Deshacer.
- Guardado local después de cada punto.
- Recuperación del marcador después de apagar la pantalla o cambiar de aplicación.
- Confirmación y envío único del resultado a EPL.
- Modo de prueba sin vinculación.

La aplicación no solicita permisos de sensores, ubicación, frecuencia cardiaca ni actividad física. Samsung Health puede registrar el entrenamiento por separado.

## APK

El APK de prueba está en apk/EPL-Score-Watch-FE.apk. Es una compilación de desarrollo firmada con la clave de depuración de Android. Sirve para probarla en el reloj; no es el paquete definitivo para Google Play.

## Instalación por Wi-Fi

1. Conecta el PC y el reloj a la misma red Wi-Fi.
2. En el reloj entra a Ajustes > Acerca del reloj > Información de software.
3. Toca cinco veces Versión del software.
4. En Ajustes > Opciones de desarrollador, activa Depuración ADB y Depuración inalámbrica.
5. En Depuración inalámbrica, abre Vincular dispositivo nuevo.
6. Anota la IP, el puerto de vinculación y el código de seis dígitos.
7. Vuelve una pantalla atrás y anota el puerto de conexión, que normalmente es diferente.
8. Ejecuta:

    .\instalar-en-reloj.ps1 -Ip "192.168.1.50" -PuertoVinculacion 37123 -Codigo "123456" -PuertoConexion 38888

## Estado del backend

Los endpoints y la página reloj.php están preparados únicamente en local. Hasta que se publiquen en EPL, el APK permite probar el marcador en modo demo, pero no puede vincularse con producción.

