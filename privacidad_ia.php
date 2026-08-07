<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$appUrl = rtrim(epl_env('APP_URL', 'https://epleague.cl'), '/');
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Privacidad — Integraciones de IA | Elite Padel League</title>
  <meta name="robots" content="index,follow">
  <style>
    *{box-sizing:border-box}body{margin:0;background:#f4f6f8;color:#25364a;font-family:Arial,sans-serif;line-height:1.6}.top{height:8px;background:#c9a762}.wrap{max-width:820px;margin:36px auto;padding:0 18px}.card{background:#fff;border:1px solid #e0e6ec;border-radius:18px;padding:32px;box-shadow:0 12px 35px #0f27420d}.brand{color:#b28b36;font-size:.72rem;font-weight:900;letter-spacing:.14em}.card h1{margin:.4rem 0 1rem;color:#102b49;font-size:2rem}.card h2{margin:1.8rem 0 .4rem;color:#173653;font-size:1.05rem}.card p,.card li{font-size:.88rem}.card a{color:#1d5f9c}.updated{color:#7b8795;font-size:.72rem}.back{display:inline-block;margin-top:1.4rem;padding:.65rem .9rem;border-radius:9px;background:#102b49;color:#fff!important;text-decoration:none;font-weight:800;font-size:.75rem}@media(max-width:600px){.wrap{margin:18px auto}.card{padding:22px}.card h1{font-size:1.55rem}}
  </style>
</head>
<body><div class="top"></div><main class="wrap"><article class="card">
  <div class="brand">ELITE PADEL LEAGUE</div>
  <h1>Política de privacidad para integraciones de IA</h1>
  <p class="updated">Última actualización: 7 de agosto de 2026.</p>
  <p>Esta política explica cómo Elite Padel League (“EPL”) trata la información cuando una persona conecta su cuenta con ChatGPT, Claude, Gemini u otro asistente autorizado.</p>
  <h2>Información utilizada</h2>
  <p>Después de iniciar sesión y autorizar la conexión, EPL utiliza la identidad de la cuenta, su rol y los datos deportivos necesarios para responder la solicitud: ligas autorizadas, equipos, partidos, resultados, recintos y reprogramaciones.</p>
  <h2>Permisos y finalidad</h2>
  <p>La integración solo permite operaciones disponibles para el rol vigente en EPL. Los jugadores acceden a sus datos; los clubes a las ligas asignadas; los administradores a las funciones administrativas habilitadas. Los datos se usan exclusivamente para responder consultas o ejecutar acciones solicitadas.</p>
  <h2>Modificaciones y confirmación</h2>
  <p>Las acciones que cambian información requieren confirmación del usuario. EPL registra las operaciones en una auditoría de seguridad con usuario, acción, fecha, resultado y dirección IP.</p>
  <h2>Credenciales y seguridad</h2>
  <p>EPL no entrega la contraseña del usuario al asistente. La conexión utiliza OAuth y tokens revocables. Las integraciones no ofrecen acceso directo a la base de datos, archivos del servidor, SSH ni comandos del sistema.</p>
  <h2>Servicios externos</h2>
  <p>Al utilizar un asistente, parte de la solicitud necesaria puede ser procesada por su proveedor. También se aplican las condiciones y políticas de privacidad del proveedor elegido.</p>
  <h2>Revocación</h2>
  <p>El usuario puede desconectar EPL desde el asistente. Un administrador también puede deshabilitar el acceso y revocar todas las sesiones desde el panel de EPL.</p>
  <h2>Contacto</h2>
  <p>Para consultas, revocaciones o solicitudes relacionadas con estos datos, utiliza los canales oficiales disponibles en <a href="<?= htmlspecialchars($appUrl) ?>"><?= htmlspecialchars(parse_url($appUrl, PHP_URL_HOST) ?: 'epleague.cl') ?></a>.</p>
  <a class="back" href="<?= htmlspecialchars($appUrl) ?>">Volver a Elite Padel League</a>
</article></main></body></html>

