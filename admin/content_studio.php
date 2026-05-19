<?php
$page_title = 'Admin — Content Studio';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();
require_once '../includes/header.php';
?>

<div class="dash-layout">
<?php include __DIR__ . '/../admin/partials/sidebar.php'; ?>
<main class="dash-main" style="padding-bottom:3rem">

  <div class="dash-header">
    <h1 class="dash-title">🎬 Content Studio</h1>
    <p style="color:var(--gray-400);font-size:.88rem">Genera contenido para Instagram con las fotos de la fecha.</p>
  </div>

  <div class="cs-layout">

    <!-- ── Panel izquierdo: controles ── -->
    <div class="cs-controls">

      <!-- Drop Zone -->
      <div class="cs-card">
        <div class="cs-card-title">📸 Fotos de la fecha</div>
        <div id="drop-zone" class="cs-dropzone">
          <div class="cs-drop-icon">📁</div>
          <p class="cs-drop-text">Arrastra fotos aquí<br><span>o toca para seleccionar</span></p>
          <input type="file" id="file-input" accept="image/*" multiple style="display:none">
        </div>
        <div id="photo-grid" class="cs-photo-grid">
          <p class="cs-empty-photos">Sin fotos aún</p>
        </div>
        <div id="photo-count" class="cs-photo-count" style="display:none"></div>
        <p id="sort-hint" class="cs-sort-hint" style="display:none">↕ Arrastrá las fotos para reordenarlas</p>
      </div>

      <!-- Configuración -->
      <div class="cs-card">
        <div class="cs-card-title">⚙️ Configuración</div>

        <label class="cs-label">Nombre de la fecha</label>
        <input type="text" id="inp-event" class="cs-input" placeholder="Ej: Fecha 3 · Mayo 2026" value="Fecha 3 · Mayo 2026">

        <label class="cs-label" style="margin-top:.9rem">Subtítulo</label>
        <input type="text" id="inp-subtitle" class="cs-input" placeholder="Ej: Temporada 2026" value="Temporada 2026">

        <label class="cs-label" style="margin-top:.9rem">Formato</label>
        <div class="cs-format-group">
          <label class="cs-format-btn active" id="fmt-916">
            <input type="radio" name="fmt" value="916" checked> 9:16 Reels
          </label>
          <label class="cs-format-btn" id="fmt-11">
            <input type="radio" name="fmt" value="11"> 1:1 Feed
          </label>
          <label class="cs-format-btn" id="fmt-45">
            <input type="radio" name="fmt" value="45"> 4:5 Feed
          </label>
        </div>

        <label class="cs-label" style="margin-top:.9rem">Duración por foto</label>
        <div style="display:flex;align-items:center;gap:.75rem">
          <input type="range" id="inp-duration" min="2" max="6" step="0.5" value="3" style="flex:1">
          <span id="duration-label" style="font-weight:700;color:var(--navy);min-width:32px">3s</span>
        </div>

        <label class="cs-label" style="margin-top:.9rem">Transición</label>
        <select id="inp-transition" class="cs-input">
          <option value="fade">Fade suave</option>
          <option value="slide">Slide lateral</option>
          <option value="zoom">Zoom in/out</option>
        </select>
      </div>

    </div><!-- /cs-controls -->

    <!-- ── Panel derecho: preview ── -->
    <div class="cs-preview-panel">
      <div class="cs-card cs-preview-card">
        <div class="cs-card-title">👁 Preview</div>

        <div class="cs-canvas-wrap" id="canvas-wrap">
          <canvas id="epl-canvas"></canvas>
          <div class="cs-canvas-overlay" id="canvas-overlay">
            <div class="cs-no-photos">
              <span>📸</span>
              <p>Agrega fotos para ver el preview</p>
            </div>
          </div>
        </div>

        <!-- Controles de playback -->
        <div class="cs-playback">
          <button id="btn-play" class="cs-play-btn" disabled>▶ Play</button>
          <div class="cs-progress-wrap">
            <div class="cs-progress-bar" id="progress-bar"></div>
          </div>
          <span id="slide-counter" class="cs-slide-counter">0 / 0</span>
        </div>

        <!-- Botones de exportación -->
        <div class="cs-export-btns">
          <button id="btn-png" class="cs-btn cs-btn-outline" disabled>
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Descargar PNG
          </button>
          <button id="btn-record" class="cs-btn cs-btn-gold" disabled>
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3" fill="currentColor"/></svg>
            Grabar Video
          </button>
        </div>

        <!-- Estado del audio -->
        <div id="audio-status" class="cs-audio-status loading">
          <span class="cs-audio-dot"></span>
          <span id="audio-status-txt">⏳ Cargando música...</span>
        </div>

        <p class="cs-export-note">El video incluye la música de fondo. Se guarda como <strong>.mp4</strong>.</p>

        <!-- Barra de progreso de grabación -->
        <div id="record-progress" style="display:none">
          <div class="cs-record-bar-wrap">
            <div class="cs-record-bar" id="record-bar"></div>
          </div>
          <p id="record-status" style="text-align:center;font-size:.78rem;color:#dc2626;font-weight:700;margin:.5rem 0 0">⏺ Grabando...</p>
        </div>

        <!-- Panel de descarga (aparece al terminar) -->
        <div id="download-panel" style="display:none;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #86efac;border-radius:14px;padding:1.1rem 1.25rem;margin-top:.5rem">
          <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.85rem">
            <span style="font-size:1.4rem">✅</span>
            <div>
              <div style="font-weight:800;font-size:.88rem;color:#166534">¡Video listo!</div>
              <div id="download-info" style="font-size:.72rem;color:#15803d;margin-top:.1rem"></div>
            </div>
          </div>
          <video id="download-preview" controls style="width:100%;border-radius:10px;margin-bottom:.85rem;max-height:200px;background:#000;display:none"></video>
          <a id="download-link" href="#" download
             style="display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;box-sizing:border-box;padding:.8rem;background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;border-radius:10px;font-weight:800;font-size:.82rem;text-transform:uppercase;letter-spacing:.06em;text-decoration:none;transition:filter .15s">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            <span id="download-link-txt">Descargar Video (.mp4)</span>
          </a>
          <!-- Convirtiendo a MP4 (se muestra si el navegador grabó en WebM) -->
          <div id="converting-panel" style="display:none;margin-top:.75rem;text-align:center">
            <div style="font-size:.82rem;font-weight:700;color:#1d4ed8;margin-bottom:.4rem">⏳ <span id="converting-txt">Convirtiendo a MP4...</span></div>
            <div style="height:6px;background:#dbeafe;border-radius:3px;overflow:hidden">
              <div id="converting-bar" style="height:100%;background:#2563eb;width:0%;transition:width .3s;border-radius:3px"></div>
            </div>
          </div>
          <button onclick="document.getElementById('download-panel').style.display='none'"
                  style="width:100%;margin-top:.5rem;padding:.45rem;background:none;border:none;font-size:.72rem;color:#15803d;cursor:pointer;font-weight:600">
            Cerrar
          </button>
        </div>

      </div>
    </div><!-- /cs-preview-panel -->

  </div><!-- /cs-layout -->

</main>
</div>

<!-- ══════════════════════════════════════════════════════ ESTILOS -->
<style>
.cs-layout {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 1.25rem;
  align-items: start;
}
@media(max-width:900px) {
  .cs-layout { grid-template-columns: 1fr; }
}

.cs-card {
  background: var(--white);
  border-radius: 18px;
  border: 1px solid var(--gray-100);
  padding: 1.25rem 1.25rem 1.5rem;
  box-shadow: 0 4px 20px rgba(0,0,0,.06);
  margin-bottom: 1.25rem;
}
.cs-card-title {
  font-size: .62rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: .12em; color: var(--gold); margin-bottom: 1rem;
}

/* Drop zone */
.cs-dropzone {
  border: 2px dashed var(--gray-200); border-radius: 14px;
  padding: 1.5rem 1rem; text-align: center; cursor: pointer;
  transition: all .2s; background: var(--gray-100);
}
.cs-dropzone:hover, .cs-dropzone.drag-over {
  border-color: var(--gold); background: rgba(201,167,98,.06);
}
.cs-drop-icon { font-size: 2rem; margin-bottom: .4rem; }
.cs-drop-text { margin: 0; font-size: .82rem; color: var(--gray-400); line-height: 1.5; }
.cs-drop-text span { font-size: .72rem; color: var(--gold); font-weight: 700; }

/* Photo grid */
.cs-photo-grid {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: .4rem; margin-top: .75rem; min-height: 48px;
}
.cs-empty-photos { color: var(--gray-400); font-size: .75rem; text-align: center; grid-column: 1/-1; margin: .5rem 0; }
.cs-photo-thumb {
  position: relative; aspect-ratio: 1; border-radius: 8px; overflow: hidden;
  border: 2px solid transparent; cursor: grab; transition: border-color .15s, opacity .15s, transform .15s;
  user-select: none;
}
.cs-photo-thumb:hover { border-color: var(--gold); }
.cs-photo-thumb.active { border-color: var(--navy); }
.cs-photo-thumb img { width: 100%; height: 100%; object-fit: cover; pointer-events: none; }
.cs-photo-thumb-del {
  position: absolute; top: 2px; right: 2px;
  width: 18px; height: 18px; border-radius: 50%;
  background: rgba(220,38,38,.85); color: #fff;
  font-size: .6rem; font-weight: 900;
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity .15s; border: none; cursor: pointer;
  line-height: 1; z-index: 2;
}
.cs-photo-thumb:hover .cs-photo-thumb-del { opacity: 1; }

/* Número de orden */
.cs-photo-thumb-num {
  position: absolute; bottom: 3px; left: 3px;
  background: rgba(0,0,0,.65); color: #fff;
  font-size: .55rem; font-weight: 800; border-radius: 4px;
  padding: 1px 5px; line-height: 1.5; pointer-events: none;
  font-family: 'Montserrat', sans-serif;
}

/* Estados drag-and-drop */
.cs-photo-thumb.dragging {
  opacity: .35; cursor: grabbing; transform: scale(.95);
}
.cs-photo-thumb.drag-over-left {
  border-left: 3px solid var(--gold);
  border-radius: 8px;
}
.cs-photo-thumb.drag-over-right {
  border-right: 3px solid var(--gold);
  border-radius: 8px;
}

.cs-photo-count { font-size: .72rem; color: var(--gray-400); text-align: right; margin-top: .35rem; }
.cs-sort-hint {
  font-size: .68rem; color: var(--gold); text-align: center;
  margin: .3rem 0 0; font-weight: 700; letter-spacing: .02em;
  opacity: .8;
}

/* Form */
.cs-label { display: block; font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: var(--gray-400); margin-bottom: .35rem; }
.cs-input {
  width: 100%; padding: .6rem .85rem; border: 1.5px solid var(--gray-200);
  border-radius: 10px; font-size: .85rem; color: var(--navy);
  font-family: var(--font-body, 'Montserrat', sans-serif);
  background: var(--white); box-sizing: border-box;
  transition: border-color .2s, box-shadow .2s;
}
.cs-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,167,98,.15); }

.cs-format-group { display: flex; gap: .4rem; }
.cs-format-btn {
  flex: 1; text-align: center; padding: .5rem .3rem;
  border: 1.5px solid var(--gray-200); border-radius: 9px;
  font-size: .72rem; font-weight: 700; color: var(--gray-400);
  cursor: pointer; transition: all .15s; user-select: none;
}
.cs-format-btn input { display: none; }
.cs-format-btn.active { background: var(--navy); color: var(--gold); border-color: var(--navy); }

/* Canvas preview */
.cs-preview-card { position: sticky; top: 1rem; }
.cs-canvas-wrap {
  position: relative; width: 100%;
  display: flex; justify-content: center; margin-bottom: 1rem;
}
#epl-canvas {
  border-radius: 14px;
  box-shadow: 0 16px 48px rgba(0,0,0,.22);
  display: block; max-width: 100%; max-height: 480px;
  width: auto; height: auto;
}
.cs-canvas-overlay {
  position: absolute; inset: 0; display: flex;
  align-items: center; justify-content: center;
  background: rgba(28,47,72,.88); border-radius: 14px;
  transition: opacity .3s;
}
.cs-canvas-overlay.hidden { opacity: 0; pointer-events: none; }
.cs-no-photos { text-align: center; color: #fff; }
.cs-no-photos span { font-size: 2.5rem; display: block; margin-bottom: .5rem; }
.cs-no-photos p { font-size: .82rem; opacity: .7; margin: 0; }

.cs-playback { display: flex; align-items: center; gap: .75rem; margin-bottom: 1rem; }
.cs-play-btn {
  background: var(--navy); color: var(--white); border: none;
  border-radius: 8px; padding: .4rem .9rem; font-size: .8rem;
  font-weight: 700; cursor: pointer; white-space: nowrap;
  transition: background .15s; flex-shrink: 0;
}
.cs-play-btn:hover { background: var(--gold); color: var(--navy); }
.cs-play-btn:disabled { opacity: .4; cursor: not-allowed; }
.cs-progress-wrap {
  flex: 1; height: 6px; background: var(--gray-100);
  border-radius: 3px; overflow: hidden;
}
.cs-progress-bar { height: 100%; background: var(--gold); width: 0%; transition: width .1s linear; border-radius: 3px; }
.cs-slide-counter { font-size: .7rem; font-weight: 700; color: var(--gray-400); white-space: nowrap; flex-shrink: 0; }

.cs-export-btns { display: flex; gap: .6rem; margin-bottom: .5rem; }
.cs-btn {
  flex: 1; display: flex; align-items: center; justify-content: center;
  gap: .4rem; padding: .75rem; border-radius: 10px; font-size: .78rem;
  font-weight: 800; text-transform: uppercase; letter-spacing: .05em;
  cursor: pointer; border: none; transition: all .2s;
}
.cs-btn:disabled { opacity: .35; cursor: not-allowed; pointer-events: none; }
.cs-btn-outline { border: 2px solid var(--gray-200); color: var(--navy); background: #fff; }
.cs-btn-outline:hover { border-color: var(--navy); }
.cs-btn-gold { background: linear-gradient(135deg, var(--gold), #b8975a); color: var(--navy); }
.cs-btn-gold:hover { filter: brightness(1.07); transform: translateY(-1px); }
.cs-export-note { font-size: .7rem; color: var(--gray-400); text-align: center; margin: 0 0 .75rem; }

.cs-record-bar-wrap { height: 8px; background: var(--gray-100); border-radius: 4px; overflow: hidden; }
.cs-record-bar { height: 100%; background: #dc2626; width: 0%; transition: width .3s linear; border-radius: 4px; }

/* Audio status */
.cs-audio-status {
  display: flex; align-items: center; gap: .5rem;
  padding: .5rem .85rem; border-radius: 8px; margin-bottom: .5rem;
  font-size: .75rem; font-weight: 700;
}
.cs-audio-status.loading { background: #f8fafc; color: var(--gray-400); }
.cs-audio-status.ready   { background: #f0fdf4; color: #166534; }
.cs-audio-status.error   { background: #fef2f2; color: #991b1b; }
.cs-audio-dot {
  width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
  background: currentColor; animation: cs-pulse 1.5s ease infinite;
}
.cs-audio-status.ready .cs-audio-dot { animation: none; }
.cs-audio-status.error .cs-audio-dot { animation: none; }
@keyframes cs-pulse { 0%,100%{opacity:.3} 50%{opacity:1} }
</style>

<!-- ══════════════════════════════════════════════════════ JAVASCRIPT -->
<script>
(function() {
'use strict';

// ── Elementos DOM ──────────────────────────────────────────
const dropZone    = document.getElementById('drop-zone');
const fileInput   = document.getElementById('file-input');
const photoGrid   = document.getElementById('photo-grid');
const photoCount  = document.getElementById('photo-count');
const inpEvent    = document.getElementById('inp-event');
const inpSubtitle = document.getElementById('inp-subtitle');
const inpDuration = document.getElementById('inp-duration');
const durLabel    = document.getElementById('duration-label');
const inpTransit  = document.getElementById('inp-transition');
const canvas      = document.getElementById('epl-canvas');
const ctx         = canvas.getContext('2d');
const overlay     = document.getElementById('canvas-overlay');
const btnPlay     = document.getElementById('btn-play');
const btnPng      = document.getElementById('btn-png');
const btnRecord   = document.getElementById('btn-record');
const progressBar = document.getElementById('progress-bar');
const slideCounter= document.getElementById('slide-counter');
const recordProg  = document.getElementById('record-progress');
const recordBar   = document.getElementById('record-bar');
const audioStatus = document.getElementById('audio-status');
const audioStatusTxt = document.getElementById('audio-status-txt');

// ── Audio ─────────────────────────────────────────────────
let audioCtx    = null;
let audioBuffer = null;
let previewSrc  = null;   // fuente de audio activa en preview
let previewGain = null;
let audioReady  = false;
const AUDIO_URL = '../assets/audio/Match_Point_Morning.mp3';

async function initAudio() {
  try {
    const resp = await fetch(AUDIO_URL);
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    const ab = await resp.arrayBuffer();
    // Crear contexto DESPUÉS de interacción del usuario (política autoplay)
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    audioBuffer = await audioCtx.decodeAudioData(ab);
    audioReady = true;
    audioStatus.className = 'cs-audio-status ready';
    audioStatusTxt.textContent = '🎵 Match Point Morning — lista';
  } catch(e) {
    audioStatus.className = 'cs-audio-status error';
    audioStatusTxt.textContent = '⚠️ No se pudo cargar la música';
    console.warn('Audio error:', e);
  }
}

function startPreviewAudio(offsetSec) {
  if (!audioReady || !audioBuffer) return;
  stopPreviewAudio();
  if (audioCtx.state === 'suspended') audioCtx.resume();
  const src  = audioCtx.createBufferSource();
  src.buffer = audioBuffer;
  src.loop   = true;
  const gain = audioCtx.createGain();
  gain.gain.value = 0.55;
  src.connect(gain);
  gain.connect(audioCtx.destination);
  src.start(0, offsetSec % audioBuffer.duration);
  previewSrc  = src;
  previewGain = gain;
}

function stopPreviewAudio() {
  if (previewSrc) {
    try { previewSrc.stop(); } catch(e){}
    previewSrc = null;
  }
}

// ── Estado ────────────────────────────────────────────────
let photos        = [];
let isPlaying     = false;
let animFrame     = null;
let animStartTime = null;
let animOffset    = 0;  // para pause/resume
let isRecording   = false;
let recorder      = null;
let recordChunks  = [];

// ── Configuración ─────────────────────────────────────────
const INTRO_DUR   = 2000;
const OUTRO_DUR   = 2000;
const TRANS_DUR   = 700;
const FORMATS     = { '916': [1080,1920], '11': [1080,1080], '45': [1080,1350] };
let   curFormat   = '916';

// Ken Burns per-photo movements (fracciones del canvas)
const KB = [
  { s0:1.00, s1:1.08, dx:0.00, dy:-0.02 },
  { s0:1.08, s1:1.00, dx:0.02, dy: 0.00 },
  { s0:1.00, s1:1.08, dx:-0.02,dy: 0.02 },
  { s0:1.05, s1:1.00, dx:0.01, dy:-0.01 },
  { s0:1.00, s1:1.06, dx:0.00, dy: 0.02 },
  { s0:1.07, s1:1.00, dx:-0.01,dy: 0.00 },
];

// ── Logo EPL ──────────────────────────────────────────────
const logoImg = new Image();
logoImg.crossOrigin = 'anonymous';
logoImg.src = '../assets/img/logo-epl-lateral.png';

// ── Helpers ───────────────────────────────────────────────
function getWH() {
  const [W,H] = FORMATS[curFormat];
  return {W,H};
}
function getPhotoDuration() {
  return parseFloat(inpDuration.value) * 1000;
}
function getTotalDur() {
  const pd = getPhotoDuration();
  return INTRO_DUR + photos.length * pd + OUTRO_DUR;
}
function easeInOut(t) {
  return t < 0.5 ? 2*t*t : -1+(4-2*t)*t;
}

// ── Canvas: dibujar foto con Ken Burns ────────────────────
function drawPhoto(img, progress, kbIdx, alpha) {
  const {W,H} = getWH();
  const m     = KB[kbIdx % KB.length];
  const scale = m.s0 + (m.s1 - m.s0) * easeInOut(progress);
  const dx    = m.dx * W * easeInOut(progress);
  const dy    = m.dy * H * easeInOut(progress);
  const base  = Math.max(W / img.width, H / img.height);
  const fs    = base * scale;
  const sx    = (W - img.width  * fs) / 2 + dx;
  const sy    = (H - img.height * fs) / 2 + dy;
  ctx.globalAlpha = Math.max(0, Math.min(1, alpha));
  ctx.drawImage(img, sx, sy, img.width * fs, img.height * fs);
  ctx.globalAlpha = 1;
}

// ── Canvas: gradiente overlay + branding ─────────────────
function drawBranding(W, H) {
  // Gradiente superior
  const gTop = ctx.createLinearGradient(0, 0, 0, H * 0.32);
  gTop.addColorStop(0, 'rgba(10,20,33,.75)');
  gTop.addColorStop(1, 'rgba(0,0,0,0)');
  ctx.fillStyle = gTop;
  ctx.fillRect(0, 0, W, H * 0.32);

  // Gradiente inferior
  const gBot = ctx.createLinearGradient(0, H * 0.55, 0, H);
  gBot.addColorStop(0, 'rgba(0,0,0,0)');
  gBot.addColorStop(1, 'rgba(10,20,33,.9)');
  ctx.fillStyle = gBot;
  ctx.fillRect(0, H * 0.55, W, H * 0.45);

  // Logo (filtro blanco)
  if (logoImg.complete && logoImg.naturalWidth) {
    const lw = W * 0.30;
    const lh = lw * (logoImg.naturalHeight / logoImg.naturalWidth);
    ctx.filter = 'brightness(0) invert(1)';
    ctx.globalAlpha = 0.88;
    ctx.drawImage(logoImg, 65, 58, lw, lh);
    ctx.filter = 'none';
    ctx.globalAlpha = 1;
  }

  // Línea dorada
  const bottomY = curFormat === '916' ? H - 240 : H - 200;
  ctx.fillStyle = '#C9A762';
  ctx.fillRect(65, bottomY, 90, 3);

  // Nombre del evento
  const evtName = inpEvent.value.trim() || 'Fecha EPL';
  const fs1 = W * 0.061;
  ctx.save();
  ctx.font = `900 ${fs1}px "Anton", "Arial Black", sans-serif`;
  ctx.fillStyle = '#C9A762';
  ctx.fillText(evtName.toUpperCase(), 65, bottomY + fs1 + 12);
  ctx.restore();

  // Subtítulo
  const sub = inpSubtitle.value.trim() || 'Elite Padel League';
  ctx.save();
  ctx.font = `700 ${W * 0.034}px "Montserrat", Arial, sans-serif`;
  ctx.fillStyle = 'rgba(255,255,255,.68)';
  ctx.fillText(sub, 65, bottomY + fs1 + 12 + W * 0.052);
  ctx.restore();
}

// ── Canvas: slide intro ───────────────────────────────────
function drawIntro(W, H, fadeAlpha) {
  // Fondo
  const bg = ctx.createLinearGradient(0, 0, W, H);
  bg.addColorStop(0, '#0a1421');
  bg.addColorStop(1, '#1C2F48');
  ctx.fillStyle = bg;
  ctx.fillRect(0, 0, W, H);

  // Líneas doradas decorativas
  ctx.fillStyle = `rgba(201,167,98,${0.35 * fadeAlpha})`;
  ctx.fillRect(W * 0.08, H * 0.365, W * 0.84, 1.5);
  ctx.fillRect(W * 0.08, H * 0.635, W * 0.84, 1.5);

  // Logo centrado
  if (logoImg.complete && logoImg.naturalWidth) {
    const lw = W * 0.58;
    const lh = lw * (logoImg.naturalHeight / logoImg.naturalWidth);
    ctx.globalAlpha = fadeAlpha;
    ctx.filter = 'brightness(0) invert(1)';
    ctx.drawImage(logoImg, (W - lw)/2, H * 0.39 - lh/2, lw, lh);
    ctx.filter = 'none';
    ctx.globalAlpha = 1;
  }

  // Nombre del evento
  const name = inpEvent.value.trim() || 'Fecha EPL';
  const sub  = inpSubtitle.value.trim() || 'Elite Padel League';
  ctx.save();
  ctx.textAlign = 'center';
  ctx.globalAlpha = fadeAlpha;
  ctx.font = `900 ${W * 0.068}px "Anton","Arial Black",sans-serif`;
  ctx.fillStyle = '#C9A762';
  ctx.fillText(name.toUpperCase(), W/2, H * 0.73);
  ctx.font = `700 ${W * 0.036}px "Montserrat",Arial,sans-serif`;
  ctx.fillStyle = 'rgba(255,255,255,.6)';
  ctx.fillText(sub, W/2, H * 0.79);
  ctx.globalAlpha = 1;
  ctx.restore();
}

// ── Canvas: slide outro ───────────────────────────────────
function drawOutro(W, H, fadeAlpha) {
  const bg = ctx.createLinearGradient(0, 0, W, H);
  bg.addColorStop(0, '#0a1421');
  bg.addColorStop(1, '#1C2F48');
  ctx.fillStyle = bg;
  ctx.fillRect(0, 0, W, H);

  ctx.fillStyle = `rgba(201,167,98,${0.35 * fadeAlpha})`;
  ctx.fillRect(W * 0.08, H * 0.40, W * 0.84, 1.5);
  ctx.fillRect(W * 0.08, H * 0.60, W * 0.84, 1.5);

  if (logoImg.complete && logoImg.naturalWidth) {
    const lw = W * 0.45;
    const lh = lw * (logoImg.naturalHeight / logoImg.naturalWidth);
    ctx.globalAlpha = fadeAlpha;
    ctx.filter = 'brightness(0) invert(1)';
    ctx.drawImage(logoImg, (W - lw)/2, H * 0.415 - lh/2, lw, lh);
    ctx.filter = 'none';
    ctx.globalAlpha = 1;
  }

  ctx.save();
  ctx.textAlign = 'center';
  ctx.globalAlpha = fadeAlpha;
  ctx.font = `900 ${W * 0.062}px "Anton","Arial Black",sans-serif`;
  ctx.fillStyle = '#C9A762';
  ctx.fillText('@EPLEAGUECL', W/2, H * 0.67);
  ctx.font = `600 ${W * 0.033}px "Montserrat",Arial,sans-serif`;
  ctx.fillStyle = 'rgba(255,255,255,.55)';
  ctx.fillText('epleague.cl', W/2, H * 0.725);
  ctx.globalAlpha = 1;
  ctx.restore();
}

// ── Motor de render principal ─────────────────────────────
function render(elapsed) {
  const {W,H} = getWH();
  if (canvas.width !== W || canvas.height !== H) {
    canvas.width  = W;
    canvas.height = H;
  }
  ctx.clearRect(0, 0, W, H);

  const pd    = getPhotoDuration();
  const total = INTRO_DUR + photos.length * pd + OUTRO_DUR;

  // ── Intro
  if (elapsed <= INTRO_DUR) {
    const p = Math.min(1, elapsed / INTRO_DUR);
    const alpha = p < 0.2 ? p/0.2 : 1; // fade in rápido al inicio
    drawIntro(W, H, alpha);
    return;
  }

  // ── Outro
  const photoEnd = INTRO_DUR + photos.length * pd;
  if (elapsed >= photoEnd) {
    const p = Math.min(1, (elapsed - photoEnd) / OUTRO_DUR);
    const alpha = p < 0.2 ? p/0.2 : 1;
    drawOutro(W, H, alpha);
    return;
  }

  if (photos.length === 0) return;

  // ── Fotos
  const photoElapsed = elapsed - INTRO_DUR;
  const curIdx  = Math.min(Math.floor(photoElapsed / pd), photos.length - 1);
  const inPhoto = photoElapsed - curIdx * pd;
  const progress= inPhoto / pd; // 0→1 dentro de esta foto
  const nextIdx = curIdx + 1;

  // Fondo negro
  ctx.fillStyle = '#0a1421';
  ctx.fillRect(0, 0, W, H);

  // Transición crossfade al final de la foto
  const transStart = 1 - TRANS_DUR / pd;
  const transProgress = progress > transStart
    ? easeInOut((progress - transStart) / (TRANS_DUR / pd))
    : 0;

  // Foto siguiente (debajo)
  if (transProgress > 0 && nextIdx < photos.length) {
    drawPhoto(photos[nextIdx].img, 0, nextIdx, transProgress);
  }
  // Foto actual (encima)
  drawPhoto(photos[curIdx].img, progress, curIdx, 1 - transProgress);

  // Overlay de branding
  drawBranding(W, H);

  // Actualizar UI
  updatePlaybackUI(elapsed, total, curIdx);
}

function updatePlaybackUI(elapsed, total, curIdx) {
  const pct = Math.min(100, (elapsed / total) * 100);
  progressBar.style.width = pct + '%';
  if (!isRecording) {
    slideCounter.textContent = `${curIdx + 1} / ${photos.length}`;
  }
}

// ── Animación loop ────────────────────────────────────────
function tick(ts) {
  if (!animStartTime) animStartTime = ts;
  const elapsed = ts - animStartTime + animOffset;
  const total   = getTotalDur();

  render(elapsed % total);

  // Actualizar barra
  progressBar.style.width = ((elapsed % total) / total * 100) + '%';
  const pd = getPhotoDuration();
  const curIdx = Math.max(0, Math.min(photos.length-1,
    Math.floor((Math.max(0, (elapsed % total) - INTRO_DUR)) / pd)));
  slideCounter.textContent = `${Math.min(curIdx+1, photos.length)} / ${photos.length}`;

  if (isPlaying) animFrame = requestAnimationFrame(tick);
}

function startAnimation() {
  if (!photos.length) return;
  isPlaying     = true;
  animStartTime = null;
  btnPlay.textContent = '⏸ Pausa';
  animFrame = requestAnimationFrame(tick);
  // Audio preview
  const offsetSec = animOffset / 1000;
  startPreviewAudio(offsetSec);
}

function pauseAnimation() {
  if (isPlaying) animOffset += performance.now() - (animStartTime || performance.now());
  isPlaying = false;
  cancelAnimationFrame(animFrame);
  btnPlay.textContent = '▶ Play';
  stopPreviewAudio();
}

// ── Render estático inicial (frame intro) ─────────────────
function renderStatic(elapsed) {
  render(elapsed || 0);
}

// ── Upload de fotos ───────────────────────────────────────
function handleFiles(files) {
  const arr = Array.from(files).filter(f => f.type.startsWith('image/'));
  if (!arr.length) return;

  arr.forEach(file => {
    const reader = new FileReader();
    reader.onload = e => {
      const img = new Image();
      img.onload = () => {
        photos.push({ img, name: file.name });
        refreshPhotoGrid();
        enableButtons();
        overlay.classList.add('hidden');
        if (!isPlaying) renderStatic(0);
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });
}

function refreshPhotoGrid() {
  const sortHint = document.getElementById('sort-hint');
  if (!photos.length) {
    photoGrid.innerHTML = '<p class="cs-empty-photos">Sin fotos aún</p>';
    photoCount.style.display = 'none';
    if (sortHint) sortHint.style.display = 'none';
    return;
  }
  photoGrid.innerHTML = photos.map((p, i) => `
    <div class="cs-photo-thumb" data-i="${i}" title="${p.name}" draggable="true">
      <img src="${p.img.src}" alt="" draggable="false">
      <div class="cs-photo-thumb-num">${i + 1}</div>
      <button class="cs-photo-thumb-del" onclick="removePhoto(${i})" type="button">✕</button>
    </div>
  `).join('');
  photoCount.style.display = 'block';
  photoCount.textContent = `${photos.length} foto${photos.length!==1?'s':''}`;
  if (sortHint) sortHint.style.display = photos.length > 1 ? 'block' : 'none';
}

window.removePhoto = function(i) {
  photos.splice(i, 1);
  refreshPhotoGrid();
  if (!photos.length) {
    overlay.classList.remove('hidden');
    disableButtons();
    pauseAnimation();
    progressBar.style.width = '0%';
    slideCounter.textContent = '0 / 0';
  } else {
    renderStatic(0);
  }
};

function enableButtons() {
  btnPlay.disabled   = false;
  btnPng.disabled    = false;
  btnRecord.disabled = false;
}
function disableButtons() {
  btnPlay.disabled   = true;
  btnPng.disabled    = true;
  btnRecord.disabled = true;
}

// ── Drag & drop para subir fotos ─────────────────────────
dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
  e.preventDefault(); dropZone.classList.remove('drag-over');
  handleFiles(e.dataTransfer.files);
});
fileInput.addEventListener('change', () => { handleFiles(fileInput.files); fileInput.value=''; });

// ── Reordenar fotos con drag-and-drop ────────────────────
let dragSrcIdx = null;

function clearDragStyles() {
  document.querySelectorAll('.cs-photo-thumb').forEach(el => {
    el.classList.remove('dragging', 'drag-over-left', 'drag-over-right');
  });
}

photoGrid.addEventListener('dragstart', e => {
  const thumb = e.target.closest('.cs-photo-thumb[draggable]');
  if (!thumb) return;
  dragSrcIdx = parseInt(thumb.dataset.i);
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', String(dragSrcIdx));
  // Pequeño delay para que el ghost se vea bien
  requestAnimationFrame(() => thumb.classList.add('dragging'));
});

photoGrid.addEventListener('dragend', () => {
  clearDragStyles();
  dragSrcIdx = null;
});

photoGrid.addEventListener('dragover', e => {
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
  const thumb = e.target.closest('.cs-photo-thumb[draggable]');
  // Limpiar estilos previos
  document.querySelectorAll('.cs-photo-thumb').forEach(el => {
    el.classList.remove('drag-over-left', 'drag-over-right');
  });
  if (!thumb || parseInt(thumb.dataset.i) === dragSrcIdx) return;
  // Detectar si el cursor está en la mitad izquierda o derecha
  const rect = thumb.getBoundingClientRect();
  const side = e.clientX < rect.left + rect.width / 2 ? 'left' : 'right';
  thumb.classList.add(side === 'left' ? 'drag-over-left' : 'drag-over-right');
});

photoGrid.addEventListener('dragleave', e => {
  const thumb = e.target.closest('.cs-photo-thumb');
  if (thumb) thumb.classList.remove('drag-over-left', 'drag-over-right');
});

photoGrid.addEventListener('drop', e => {
  e.preventDefault();
  const thumb = e.target.closest('.cs-photo-thumb[draggable]');
  clearDragStyles();
  if (!thumb || dragSrcIdx === null) return;
  const dropIdx = parseInt(thumb.dataset.i);
  if (dropIdx === dragSrcIdx) return;

  // Determinar si insertar antes o después según dónde cayó
  const rect = thumb.getBoundingClientRect();
  const insertAfter = e.clientX >= rect.left + rect.width / 2;
  let targetIdx = insertAfter ? dropIdx + 1 : dropIdx;

  // Reordenar el array
  const [moved] = photos.splice(dragSrcIdx, 1);
  if (targetIdx > dragSrcIdx) targetIdx--;  // ajustar por el splice
  photos.splice(targetIdx, 0, moved);

  refreshPhotoGrid();
  if (!isPlaying) renderStatic(0);
});

// ── Play / Pause ──────────────────────────────────────────
btnPlay.addEventListener('click', () => {
  // Inicializar audio en primer clic (requiere gesto del usuario)
  if (!audioCtx) initAudio();
  if (isPlaying) {
    pauseAnimation();
  } else {
    startAnimation();
  }
});

// ── Controles de configuración ────────────────────────────
inpDuration.addEventListener('input', () => {
  durLabel.textContent = inpDuration.value + 's';
  if (!isPlaying && photos.length) renderStatic(0);
});

[inpEvent, inpSubtitle].forEach(el => {
  el.addEventListener('input', () => {
    if (!isPlaying && photos.length) renderStatic(0);
    else if (photos.length) { /* se actualiza en el loop */ }
  });
});

inpTransit.addEventListener('change', () => {
  if (!isPlaying && photos.length) renderStatic(0);
});

// Formato
document.querySelectorAll('input[name="fmt"]').forEach(radio => {
  radio.addEventListener('change', () => {
    curFormat = radio.value;
    document.querySelectorAll('.cs-format-btn').forEach(b => b.classList.remove('active'));
    radio.closest('.cs-format-btn').classList.add('active');
    // Resetear canvas
    const {W,H} = getWH();
    canvas.width = W; canvas.height = H;
    if (photos.length) renderStatic(0);
  });
});

// ── Descargar PNG ─────────────────────────────────────────
btnPng.addEventListener('click', () => {
  if (!photos.length) return;
  // Renderizar el primer frame de fotos (después del intro)
  render(INTRO_DUR + 100);
  const name = (inpEvent.value.trim() || 'EPL').replace(/\s+/g,'_');
  const a = document.createElement('a');
  a.download = `EPL_${name}.png`;
  a.href = canvas.toDataURL('image/png', 1.0);
  a.click();
  // Volver al estado actual
  if (!isPlaying) renderStatic(animOffset);
});

// ── Grabar video (con audio mezclado) ─────────────────────
btnRecord.addEventListener('click', async () => {
  if (!photos.length || isRecording) return;

  // Asegurar audio inicializado
  if (!audioCtx) await initAudio();
  else if (audioCtx.state === 'suspended') await audioCtx.resume();

  // Parar preview
  pauseAnimation();
  stopPreviewAudio();
  animOffset = 0; animStartTime = null;

  const totalMs  = getTotalDur();
  const totalSec = totalMs / 1000;
  isRecording    = true;
  btnRecord.disabled = true;
  btnRecord.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="#dc2626"><circle cx="12" cy="12" r="8"/></svg> Grabando...';
  btnPlay.disabled = true;
  recordProg.style.display = 'block';
  recordChunks = [];

  // ── Configurar audio para grabación ───────────────────
  let recAudioSrc = null;
  let combinedStream;

  if (audioReady && audioBuffer) {
    recAudioSrc = audioCtx.createBufferSource();
    recAudioSrc.buffer = audioBuffer;
    recAudioSrc.loop   = true;

    const recGain = audioCtx.createGain();
    recGain.gain.setValueAtTime(0.75, audioCtx.currentTime);
    // Fade out en los últimos 2.5 segundos
    if (totalSec > 3) {
      recGain.gain.setValueAtTime(0.75, audioCtx.currentTime + totalSec - 2.5);
      recGain.gain.linearRampToValueAtTime(0, audioCtx.currentTime + totalSec);
    }
    recAudioSrc.connect(recGain);

    // MediaStreamDestination para captura + speakers para monitoreo
    const recDest = audioCtx.createMediaStreamDestination();
    recGain.connect(recDest);
    recGain.connect(audioCtx.destination); // también suena mientras graba

    recAudioSrc.start(0);
    recAudioSrc.stop(audioCtx.currentTime + totalSec);

    // Combinar video canvas + audio
    const canvasStream = canvas.captureStream(30);
    combinedStream = new MediaStream([
      ...canvasStream.getVideoTracks(),
      ...recDest.stream.getAudioTracks()
    ]);
  } else {
    // Sin audio: solo video
    combinedStream = canvas.captureStream(30);
  }

  // ── MediaRecorder — intentar MP4 nativo primero (Chrome 130+ Windows) ──
  const preferredTypes = [
    'video/mp4;codecs="avc1,mp4a.40.2"',
    'video/mp4;codecs="avc1"',
    'video/mp4',
    'video/webm;codecs=vp9,opus',
    'video/webm;codecs=vp8,opus',
    'video/webm',
  ];
  const mimeType  = preferredTypes.find(t => MediaRecorder.isTypeSupported(t)) || 'video/webm';
  const nativeMP4 = mimeType.startsWith('video/mp4');

  recorder = new MediaRecorder(combinedStream, { mimeType, videoBitsPerSecond: 8_000_000 });
  recorder.ondataavailable = e => { if (e.data.size > 0) recordChunks.push(e.data); };

  recorder.onstop = async () => {
    if (recAudioSrc) { try { recAudioSrc.stop(); } catch(e){} }

    const rawBlob = new Blob(recordChunks, { type: mimeType });
    const nm      = (inpEvent.value.trim() || 'EPL').replace(/[\s\/\\:*?"<>|]+/g,'_');

    // Reset UI de grabación
    isRecording = false;
    btnRecord.disabled = false;
    btnRecord.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3" fill="currentColor"/></svg> Grabar Video';
    btnPlay.disabled = false;
    recordProg.style.display = 'none';
    recordBar.style.width = '0%';

    const panel         = document.getElementById('download-panel');
    const dlLink        = document.getElementById('download-link');
    const dlLinkTxt     = document.getElementById('download-link-txt');
    const dlPreview     = document.getElementById('download-preview');
    const dlInfo        = document.getElementById('download-info');
    const convPanel     = document.getElementById('converting-panel');
    const convTxt       = document.getElementById('converting-txt');
    const convBar       = document.getElementById('converting-bar');

    function showDownload(blob, ext) {
      const url   = URL.createObjectURL(blob);
      const fname = `EPL_${nm}.${ext}`;
      dlLink.href      = url;
      dlLink.download  = fname;
      dlLinkTxt.textContent = `Descargar Video (.${ext})`;
      dlPreview.src    = url;
      dlPreview.style.display = 'block';
      dlInfo.textContent = `${fname} · ${(blob.size/1024/1024).toFixed(1)} MB · ${Math.round(totalSec)}s`;
      convPanel.style.display = 'none';
      panel.style.display = 'block';
      panel.scrollIntoView({ behavior:'smooth', block:'nearest' });
      dlLink.onclick = () => setTimeout(() => URL.revokeObjectURL(url), 15000);
    }

    if (nativeMP4) {
      // ✅ MP4 nativo — directo
      showDownload(rawBlob, 'mp4');
      startAnimation();
      return;
    }

    // WebM grabado → convertir a MP4 con FFmpeg.wasm
    panel.style.display   = 'block';
    convPanel.style.display = 'block';
    dlLink.style.display  = 'none';
    dlPreview.style.display = 'none';
    dlInfo.textContent    = `Convirtiendo ${Math.round(totalSec)}s de video...`;
    panel.scrollIntoView({ behavior:'smooth', block:'nearest' });

    try {
      convTxt.textContent = 'Cargando conversor MP4...';
      convBar.style.width = '10%';

      // Cargar FFmpeg.wasm (single-thread, no necesita COOP/COEP)
      const { FFmpeg } = await import('https://unpkg.com/@ffmpeg/ffmpeg@0.12.10/dist/esm/index.js');
      const { fetchFile, toBlobURL } = await import('https://unpkg.com/@ffmpeg/util@0.12.1/dist/esm/index.js');

      const ffmpeg = new FFmpeg();
      ffmpeg.on('progress', ({ progress }) => {
        convBar.style.width = (10 + progress * 85) + '%';
        convTxt.textContent = `Convirtiendo... ${Math.round(progress * 100)}%`;
      });

      convBar.style.width = '20%';
      convTxt.textContent = 'Cargando motor de conversión...';

      const baseURL = 'https://unpkg.com/@ffmpeg/core@0.12.6/dist/esm';
      await ffmpeg.load({
        coreURL: await toBlobURL(`${baseURL}/ffmpeg-core.js`,   'text/javascript'),
        wasmURL: await toBlobURL(`${baseURL}/ffmpeg-core.wasm`, 'application/wasm'),
      });

      convBar.style.width = '30%';
      convTxt.textContent = 'Procesando video...';

      await ffmpeg.writeFile('input.webm', await fetchFile(rawBlob));
      await ffmpeg.exec([
        '-i', 'input.webm',
        '-c:v', 'libx264', '-preset', 'fast', '-crf', '23',
        '-c:a', 'aac', '-b:a', '128k',
        '-movflags', '+faststart',
        'output.mp4'
      ]);

      const data    = await ffmpeg.readFile('output.mp4');
      const mp4Blob = new Blob([data.buffer], { type: 'video/mp4' });

      convBar.style.width = '100%';
      dlLink.style.display = '';
      showDownload(mp4Blob, 'mp4');

    } catch(err) {
      console.error('FFmpeg conversion error:', err);
      // Fallback: descargar el WebM original
      convPanel.style.display = 'none';
      dlLink.style.display = '';
      dlInfo.textContent = '⚠️ No se pudo convertir a MP4. Descargando como WebM.';
      showDownload(rawBlob, 'webm');
    }

    startAnimation();
  };

  recorder.start(100);

  // ── Loop de render para grabación ────────────────────
  const recStart = performance.now();
  function recTick(now) {
    const elapsed = now - recStart;
    if (elapsed >= totalMs) {
      render(totalMs - 50);
      recorder.stop();
      return;
    }
    render(elapsed);
    recordBar.style.width  = (elapsed / totalMs * 100) + '%';
    progressBar.style.width= (elapsed / totalMs * 100) + '%';
    requestAnimationFrame(recTick);
  }
  requestAnimationFrame(recTick);
});

// ── Init ──────────────────────────────────────────────────
document.fonts.ready.then(() => {
  const {W,H} = getWH();
  canvas.width  = W;
  canvas.height = H;
  drawIntro(W, H, 1);
});

// Pre-cargar audio (decodificar buffer, sin reproducir aún)
// Usamos fetch directo para no necesitar gesto del usuario en la decodificación
(async function preloadAudio() {
  try {
    const resp = await fetch(AUDIO_URL);
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    const ab = await resp.arrayBuffer();
    // Guardamos el ArrayBuffer para decodificar al primer clic (requiere gesto)
    window._eplAudioAB = ab;
    audioStatus.className = 'cs-audio-status ready';
    audioStatusTxt.textContent = '🎵 Match Point Morning — lista';
    audioReady = true; // marcamos como listo para usarse al primer clic
  } catch(e) {
    audioStatus.className = 'cs-audio-status error';
    audioStatusTxt.textContent = '⚠️ No se pudo cargar la música';
  }
})();

// Sobreescribir initAudio para usar el buffer pre-cargado
initAudio = async function() {
  if (audioCtx && audioBuffer) return;
  try {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const ab = window._eplAudioAB;
    if (!ab) throw new Error('Buffer no disponible');
    audioBuffer = await audioCtx.decodeAudioData(ab.slice(0)); // slice para reusar
    audioReady  = true;
  } catch(e) {
    console.warn('initAudio error:', e);
    audioReady = false;
  }
};

})();
</script>

<?php require_once '../includes/footer.php'; ?>
