<?php
session_start();
if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) { header("Location: ../index.html"); exit; }
include("../conexion.php");
$currentUser = $_SESSION['usuario'];
$rol         = $_SESSION['rol'];

$citas_hoy = 0;
try {
    if (isset($conn)) {
        while($conn->more_results() && $conn->next_result()){;}
        if ($res = $conn->query("CALL sp_obtener_citas_hoy()")) {
            if ($row = $res->fetch_assoc()) { $citas_hoy = $row['total']; }
            $res->close();
        }
    }
} catch (Exception $e) { $citas_hoy = 0; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visago IA Stylist</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- MediaPipe para el modo cámara -->
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/face_mesh.js"        crossorigin="anonymous"></script>
    <style>
        /* ══ Módulo IA — Visago Gold Dark ══ */
        .ia-header { text-align: center; margin-bottom: 32px; }
        .ia-header h2 { color: var(--c-accent-gold); font-size: 2.8em; margin-bottom: 8px; text-transform: uppercase; font-family: 'Oswald', sans-serif; text-shadow: var(--gold-glow); }
        .ia-header p  { color: var(--c-text-muted); font-size: 1.05em; max-width: 680px; margin: 0 auto; }

        .ia-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 36px; align-items: start; }

        /* ── Panel izquierdo (input) ── */
        .input-panel { background: rgba(0,0,0,0.2); border: 2px dashed #333; border-radius: 12px; box-sizing: border-box; min-height: 360px; display: flex; flex-direction: column; transition: 0.3s; }
        .input-panel:hover { border-color: rgba(201,154,65,0.4); }

        /* Menú de modos */
        .modo-menu { display: flex; border-bottom: 1px solid #2a2a2a; }
        .modo-btn { flex: 1; padding: 14px 10px; background: transparent; border: none; cursor: pointer; color: var(--c-text-muted); font-family: 'Oswald', sans-serif; font-size: 0.95em; letter-spacing: 1px; text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; border-bottom: 3px solid transparent; margin-bottom: -1px; }
        .modo-btn:hover  { color: var(--c-text-cream); }
        .modo-btn.active { color: var(--c-accent-gold); border-bottom-color: var(--c-accent-gold); }
        .modo-btn svg    { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 2; }

        /* Contenidos de modo */
        .modo-content { padding: 28px 22px; flex: 1; display: none; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
        .modo-content.active { display: flex; }

        /* ── Modo Foto ── */
        .upload-icon { color: var(--c-text-muted); margin-bottom: 12px; transition: 0.3s; }
        .input-panel:hover .upload-icon { color: var(--c-accent-gold); transform: scale(1.08); }
        .upload-icon svg { width: 56px; height: 56px; stroke: currentColor; fill: none; stroke-width: 1.5; }

        #preview-img { width: 100%; max-width: 210px; max-height: 210px; object-fit: cover; border-radius: 10px; border: 2px solid var(--c-accent-gold); box-shadow: var(--gold-glow); display: none; }
        #btn-cambiar { background: transparent; border: 1px solid #444; color: var(--c-text-muted); padding: 5px 14px; border-radius: 6px; cursor: pointer; font-size: 0.82em; margin-top: 8px; transition: 0.2s; display: none; }
        #btn-cambiar:hover { border-color: var(--c-accent-gold); color: var(--c-accent-gold); }
        #btn-analizar-foto { display: none; margin-top: 12px; background: var(--c-accent-gold); color: #111; font-weight: bold; font-family: 'Oswald', sans-serif; letter-spacing: 1px; padding: 10px 28px; border-radius: 8px; border: none; cursor: pointer; font-size: 1em; transition: 0.2s; }
        #btn-analizar-foto:hover:not(:disabled) { filter: brightness(1.12); }
        #btn-analizar-foto:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ── Modo Cámara ── */
        .camera-wrapper { position: relative; width: 224px; height: 224px; border-radius: 12px; border: 2px solid var(--c-accent-gold); overflow: hidden; box-shadow: var(--gold-glow); background: #000; margin-bottom: 14px; }
        #camVideo   { display: none; }
        #camCanvas  { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
        #cropCanvas { display: none; }

        .cam-status { background: rgba(0,0,0,0.4); border: 1px solid #333; color: #888; padding: 7px 14px; border-radius: 6px; font-size: 0.82em; display: inline-flex; align-items: center; gap: 7px; transition: 0.3s; margin-bottom: 10px; }
        .cam-status.ok    { color: var(--c-accent-gold); border-color: var(--c-accent-gold); }
        .cam-status.error { color: #ff5555; border-color: #ff5555; }

        .cam-intervalo { color: var(--c-text-muted); font-size: 0.8em; }
        #cam-countdown { color: var(--c-accent-gold); font-weight: bold; }

        /* ── Panel derecho (resultados) ── */
        .result-zone { background: rgba(0,0,0,0.3); padding: 28px; border-radius: 12px; border: 1px solid #2a2a2a; box-shadow: 0 10px 30px rgba(0,0,0,0.5); min-height: 360px; }
        .result-title { color: var(--c-text-cream); font-family: 'Oswald', sans-serif; border-bottom: 1px solid #2a2a2a; padding-bottom: 10px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; }

        #estado-inicial { text-align: center; padding: 50px 20px; color: var(--c-text-muted); }
        #estado-inicial svg { opacity: 0.22; display: block; margin: 0 auto 14px; }
        .spinner-wrap { display: none; text-align: center; padding: 50px 20px; }
        .spinner { width: 44px; height: 44px; border: 4px solid #222; border-top-color: var(--c-accent-gold); border-radius: 50%; animation: giro 0.85s linear infinite; margin: 0 auto 14px; }
        @keyframes giro { to { transform: rotate(360deg); } }
        .spinner-wrap p { color: var(--c-text-muted); font-size: 0.92em; line-height: 1.6; }
        .error-box { display: none; background: rgba(160,30,30,0.15); border: 1px solid #7a2020; border-radius: 8px; padding: 13px 16px; color: #ff7070; font-size: 0.88em; line-height: 1.6; margin-bottom: 14px; }
        .error-box code { background: rgba(255,255,255,0.08); padding: 1px 6px; border-radius: 4px; }

        #panel-resultado { display: none; }
        .trait-box { background: rgba(0,0,0,0.5); padding: 14px; margin-bottom: 14px; border-left: 4px solid var(--c-accent-gold); border-radius: 8px; display: flex; gap: 14px; align-items: center; border: 1px solid #1e1e1e; transition: 0.3s; }
        .trait-box:hover { background: rgba(201,154,65,0.05); transform: translateX(4px); }
        .trait-text { flex: 1; }
        .trait-label { color: var(--c-text-muted); font-size: 0.82em; text-transform: uppercase; display: block; margin-bottom: 4px; letter-spacing: 1px; }
        .trait-value { color: var(--c-accent-gold); font-weight: bold; font-size: 1.2em; font-family: 'Oswald', sans-serif; }
        .trait-conf  { font-size: 0.76em; color: #555; margin-left: 6px; }
        .trait-desc  { color: var(--c-text-cream); font-size: 0.83em; margin-top: 5px; margin-bottom: 0; line-height: 1.4; }
        .trait-img   { width: 76px; height: 76px; border-radius: 8px; object-fit: cover; border: 1px solid var(--c-accent-gold); flex-shrink: 0; }
        .conf-bar-bg { height: 4px; background: #1a1a1a; border-radius: 2px; margin-top: 7px; overflow: hidden; }
        .conf-bar    { height: 100%; background: linear-gradient(90deg, var(--c-accent-gold), #f5d07a); border-radius: 2px; width: 0%; transition: width 0.7s ease; }

        /* ── Galería de cortes ── */
        #seccion-cortes { display: none; margin-top: 36px; }
        #seccion-cortes h3 { color: var(--c-accent-gold); font-family: 'Oswald', sans-serif; font-size: 1.6em; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #222; padding-bottom: 10px; margin-bottom: 22px; }
        .galeria { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; }
        .galeria-card { background: rgba(0,0,0,0.45); border: 1px solid #222; border-radius: 10px; overflow: hidden; transition: 0.3s; }
        .galeria-card:hover { border-color: var(--c-accent-gold); transform: translateY(-5px); box-shadow: 0 8px 24px rgba(201,154,65,0.15); }
        .galeria-img { width: 100%; aspect-ratio: 1/1; object-fit: cover; display: block; background: #111; transition: transform 0.4s; }
        .galeria-card:hover .galeria-img { transform: scale(1.06); }
        .galeria-body { padding: 10px 12px; }
        .galeria-titulo { color: var(--c-text-cream); font-size: 0.84em; font-weight: bold; margin-bottom: 6px; line-height: 1.3; }
        .galeria-tags { display: flex; flex-wrap: wrap; gap: 3px; margin-bottom: 7px; }
        .gtag { background: rgba(201,154,65,0.1); border: 1px solid rgba(201,154,65,0.25); color: var(--c-accent-gold); font-size: 0.68em; padding: 2px 7px; border-radius: 4px; }
        .galeria-link { color: var(--c-text-muted); font-size: 0.74em; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; transition: 0.2s; }
        .galeria-link:hover { color: var(--c-accent-gold); }
        .badge-sim { display: inline-block; background: rgba(180,100,0,0.2); border: 1px solid rgba(201,154,65,0.3); color: var(--c-accent-gold); font-size: 0.7em; padding: 2px 9px; border-radius: 10px; margin-left: 8px; vertical-align: middle; }

        @media (max-width: 768px) {
            .ia-grid { grid-template-columns: 1fr; }
            .galeria { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body class="fondo-imagen-premium">
<div class="app-container">

    <!-- ══════ SIDEBAR ══════ -->
    <aside class="sidebar collapsed" id="sidebar">
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <svg class="svg-icon-toggle" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
        <div class="sidebar-logo">VISAGO<br><span style="color:var(--c-text-muted);font-size:.5em;font-family:'Montserrat',sans-serif;letter-spacing:4px;">ESTUDIO INTELIGENTE</span></div>

        <a href="../dashboard.php" class="nav-link">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span class="nav-link-text">Inicio</span>
        </a>
        <a href="reservaciones.php" class="nav-link">
            <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span class="nav-link-text">Reservaciones</span>
            <span class="nav-link-text" style="background:var(--c-accent-gold);color:#111;padding:2px 8px;border-radius:10px;font-size:.8em;margin-left:auto;font-weight:bold;"><?= $citas_hoy ?></span>
        </a>
        <?php if (in_array($rol, ['admin','vendedor'])): ?>
            <a href="clientes.php"  class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg><span class="nav-link-text">Clientes</span></a>
            <a href="ventas.php"    class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg><span class="nav-link-text">Ventas</span></a>
            <a href="productos.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg><span class="nav-link-text">Productos</span></a>
        <?php endif; ?>
        <?php if ($rol=='admin'||$rol=='empleado'): ?>
            <a href="servicios.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg><span class="nav-link-text">Servicios</span></a>
        <?php endif; ?>
        <?php if ($rol=='admin'): ?>
            <a href="empleados.php"   class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="8" y="2" width="8" height="20" rx="1"></rect><path d="M8 6h8M8 10h8M8 14h8M8 18h8"></path></svg><span class="nav-link-text">Barberos</span></a>
            <a href="proveedores.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg><span class="nav-link-text">Proveedores</span></a>
            <a href="usuarios.php"    class="nav-link" style="margin-top:28px;border-top:1px solid #222;padding-top:18px;"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg><span class="nav-link-text">Usuarios</span></a>
        <?php endif; ?>
    </aside>

    <!-- ══════ MAIN ══════ -->
    <main class="main-content">

        <header class="topbar">
            <div class="topbar-greeting">Visago Studio <span style="color:var(--c-text-muted);font-weight:normal;font-family:'Montserrat',sans-serif;">/ Inteligencia Artificial</span></div>
            <div class="topbar-actions">
                <div class="notification-wrapper">
                    <svg class="svg-icon notification-icon" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    <span class="notification-badge">1</span>
                    <div class="notification-dropdown">
                        <a href="productos.php" class="noti-item">
                            <span class="noti-title">⚠️ Alerta de Inventario</span>
                            <span>El Shampoo Matizador se agotará en 4 días.</span>
                        </a>
                    </div>
                </div>
                <div class="user-dropdown-wrapper">
                    <div class="user-profile">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($currentUser) ?>&background=222222&color=F5EEDB&bold=true" alt="Avatar" class="user-avatar">
                        <div class="user-info-desktop" style="display:block;">
                            <div style="font-weight:bold;"><?= htmlspecialchars(ucfirst($currentUser)) ?></div>
                            <div style="font-size:.8em;text-transform:uppercase;color:var(--c-accent-gold);"><?= htmlspecialchars($rol) ?></div>
                        </div>
                        <svg class="svg-icon" viewBox="0 0 24 24" style="width:16px;height:16px;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                    <div class="user-menu">
                        <a href="../index.html">🔄 Cambiar de Usuario</a>
                        <a href="../logout.php" style="color:#D9534F;">🚪 Cerrar Sesión</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- ══════ CONTENIDO ══════ -->
        <div class="crud-container" style="margin-top:0;flex-grow:1;max-width:100%;">

            <div class="ia-header">
                <h2>Visión Computacional & Estilo</h2>
                <p>Sube una foto o activa la cámara. La IA analizará la forma del rostro y tipo de cabello para recomendarte el corte perfecto.</p>
            </div>

            <div class="ia-grid">

                <!-- ── Panel izquierdo ── -->
                <div class="input-panel" id="input-panel">

                    <!-- Menú de modos -->
                    <div class="modo-menu">
                        <button class="modo-btn active" id="btn-modo-foto" onclick="cambiarModo('foto')">
                            <!-- Ícono imagen -->
                            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            Foto
                        </button>
                        <button class="modo-btn" id="btn-modo-camara" onclick="cambiarModo('camara')">
                            <!-- Ícono cámara -->
                            <svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                            Cámara en vivo
                        </button>
                    </div>

                    <!-- ─── Modo Foto ─── -->
                    <div class="modo-content active" id="panel-foto">
                        <div class="upload-icon">
                            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </div>
                        <h3 style="color:var(--c-text-cream);font-family:'Oswald',sans-serif;margin-bottom:4px;">Cargar Fotografía</h3>
                        <p style="color:var(--c-text-muted);font-size:.88em;margin-top:0;">JPG, PNG · Máx 5MB · Arrastra o selecciona</p>

                        <input type="file" id="foto_ia" accept="image/jpeg,image/png,image/webp" style="display:none;">
                        <button class="btn-new" id="btn-seleccionar"
                                onclick="document.getElementById('foto_ia').click()"
                                style="margin:16px auto 0;display:inline-block;">
                            Seleccionar Archivo
                        </button>

                        <img id="preview-img" src="" alt="Preview">
                        <button id="btn-cambiar" onclick="document.getElementById('foto_ia').click()">🔄 Cambiar foto</button>
                        <button id="btn-analizar-foto" onclick="analizarFoto()">✨ Analizar con IA</button>
                    </div>

                    <!-- ─── Modo Cámara ─── -->
                    <div class="modo-content" id="panel-camara">
                        <div class="camera-wrapper">
                            <video id="camVideo" autoplay playsinline></video>
                            <canvas id="camCanvas" width="640" height="480"></canvas>
                        </div>
                        <canvas id="cropCanvas" width="224" height="224"></canvas>

                        <div class="cam-status" id="cam-status">
                            <span>📡</span> Esperando rostro...
                        </div>
                        <div class="cam-intervalo">
                            Próximo análisis en <span id="cam-countdown">—</span>s
                        </div>
                    </div>

                </div><!-- /input-panel -->

                <!-- ── Panel derecho: resultados ── -->
                <div class="result-zone">
                    <h3 class="result-title">Resultados del Análisis IA</h3>

                    <div id="estado-inicial">
                        <svg width="60" height="60" viewBox="0 0 64 64" fill="none" stroke="#555" stroke-width="1.8">
                            <circle cx="32" cy="32" r="28"/><circle cx="32" cy="22" r="9"/>
                            <path d="M13 54c0-10.49 8.51-19 19-19s19 8.51 19 19"/>
                        </svg>
                        <p style="margin-top:10px;font-size:.93em;">Selecciona una foto o activa la cámara</p>
                    </div>

                    <div class="spinner-wrap" id="spinner-wrap">
                        <div class="spinner"></div>
                        <p>Analizando con IA…<br><small style="color:#444;">Puede tardar unos segundos</small></p>
                    </div>

                    <div class="error-box" id="error-box"></div>

                    <div id="panel-resultado">
                        <!-- Rostro -->
                        <div class="trait-box">
                            <div class="trait-text">
                                <span class="trait-label">Tipo de Rostro Detectado:</span>
                                <span class="trait-value" id="res-rostro">—</span>
                                <span class="trait-conf"  id="res-rostro-conf"></span>
                                <div class="conf-bar-bg"><div class="conf-bar" id="bar-rostro"></div></div>
                                <p class="trait-desc" id="res-rostro-desc"></p>
                            </div>
                            <img id="img-rostro" src="" alt="" class="trait-img" style="display:none;">
                        </div>
                        <!-- Cabello -->
                        <div class="trait-box">
                            <div class="trait-text">
                                <span class="trait-label">Tipo de Cabello Detectado:</span>
                                <span class="trait-value" id="res-cabello">—</span>
                                <span class="trait-conf"  id="res-cabello-conf"></span>
                                <div class="conf-bar-bg"><div class="conf-bar" id="bar-cabello"></div></div>
                                <p class="trait-desc" id="res-cabello-top3" style="font-size:.78em;color:#555;margin-top:5px;"></p>
                            </div>
                        </div>
                        <!-- Corte principal -->
                        <div class="trait-box" id="box-corte" style="display:none;">
                            <div class="trait-text">
                                <span class="trait-label">Corte Principal Recomendado:</span>
                                <span class="trait-value" id="res-corte">—</span>
                                <p class="trait-desc" id="res-corte-desc"></p>
                            </div>
                            <img id="img-corte" src="" alt="" class="trait-img" style="display:none;">
                        </div>
                    </div>
                </div>
            </div><!-- /ia-grid -->

            <!-- ── Galería de cortes ── -->
            <div id="seccion-cortes">
                <h3>💈 Cortes que te quedarían mejor <span id="badge-sim"></span></h3>
                <div class="galeria" id="galeria"></div>
            </div>

        </div><!-- /crud-container -->
    </main>
</div>

<script>
// ════════════════════════════════════════════════════════════
// CONFIG
// ════════════════════════════════════════════════════════════
const API_URL             = 'http://localhost:8000/analizar'; // ← cambia a tu IP
const INTERVALO_CAMARA_MS = 15000;  // cada 15s desde la cámara
const IMG_SIZE            = 224;

// ════════════════════════════════════════════════════════════
// ESTADO
// ════════════════════════════════════════════════════════════
let modoActual         = 'foto';
let archivoFoto        = null;
let camaraActiva       = false;
let peticionEnCurso    = false;
let tiempoUltimoEnvio  = 0;
let intervaloCamara    = null;
let faceMeshInst       = null;
let cameraInst         = null;

// ════════════════════════════════════════════════════════════
// CAMBIO DE MODO (foto ↔ cámara)
// ════════════════════════════════════════════════════════════
function cambiarModo(modo) {
    modoActual = modo;

    document.getElementById('btn-modo-foto').classList.toggle('active',   modo === 'foto');
    document.getElementById('btn-modo-camara').classList.toggle('active', modo === 'camara');
    document.getElementById('panel-foto').classList.toggle('active',      modo === 'foto');
    document.getElementById('panel-camara').classList.toggle('active',    modo === 'camara');

    resetResultados();

    if (modo === 'camara') {
        iniciarCamara();
    } else {
        detenerCamara();
    }
}

// ════════════════════════════════════════════════════════════
// MODO A — FOTO
// ════════════════════════════════════════════════════════════
document.getElementById('foto_ia').addEventListener('change', function () {
    cargarFoto(this.files[0]);
});

// Drag & drop sobre el panel
const panel = document.getElementById('input-panel');
panel.addEventListener('dragover',  e => { e.preventDefault(); panel.style.borderColor = 'var(--c-accent-gold)'; });
panel.addEventListener('dragleave', () => { panel.style.borderColor = ''; });
panel.addEventListener('drop', e => {
    e.preventDefault();
    panel.style.borderColor = '';
    if (modoActual === 'foto') cargarFoto(e.dataTransfer.files[0]);
});

function cargarFoto(file) {
    if (!file) return;
    if (!file.type.match(/image\/(jpeg|png|webp)/)) { mostrarError('Formato no soportado. Usa JPG o PNG.'); return; }
    if (file.size > 5 * 1024 * 1024)               { mostrarError('La imagen supera los 5 MB.'); return; }

    archivoFoto = file;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('preview-img').src = e.target.result;
        document.getElementById('preview-img').style.display     = 'block';
        document.getElementById('btn-seleccionar').style.display = 'none';
        document.getElementById('btn-cambiar').style.display     = 'inline-block';
        document.getElementById('btn-analizar-foto').style.display = 'block';
        // Ocultar ícono y textos
        document.querySelector('#panel-foto .upload-icon').style.display = 'none';
        document.querySelector('#panel-foto h3').style.display           = 'none';
        document.querySelector('#panel-foto p').style.display            = 'none';
    };
    reader.readAsDataURL(file);
    resetResultados();
}

async function analizarFoto() {
    if (!archivoFoto || peticionEnCurso) return;

    const fd = new FormData();
    fd.append('foto', archivoFoto);

    await enviarAAPI(fd, 'multipart');
}

// ════════════════════════════════════════════════════════════
// MODO B — CÁMARA EN TIEMPO REAL
// ════════════════════════════════════════════════════════════
function iniciarCamara() {
    if (camaraActiva) return;
    camaraActiva = true;

    const video     = document.getElementById('camVideo');
    const canvas    = document.getElementById('camCanvas');
    const canvasCtx = canvas.getContext('2d');

    faceMeshInst = new FaceMesh({
        locateFile: f => `https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/${f}`
    });
    faceMeshInst.setOptions({ maxNumFaces: 1, minDetectionConfidence: 0.5, minTrackingConfidence: 0.5 });
    faceMeshInst.onResults(resultados => onFrameFaceMesh(resultados, canvas, canvasCtx));

    cameraInst = new Camera(video, {
        onFrame: async () => { await faceMeshInst.send({ image: video }); },
        width: 640, height: 480
    });
    cameraInst.start();

    // Countdown visual
    intervaloCamara = setInterval(actualizarCountdown, 500);
}

function detenerCamara() {
    if (!camaraActiva) return;
    camaraActiva = false;
    if (cameraInst)    { try { cameraInst.stop(); } catch(e){} }
    if (faceMeshInst)  { try { faceMeshInst.close(); } catch(e){} }
    if (intervaloCamara) clearInterval(intervaloCamara);
    document.getElementById('cam-countdown').textContent = '—';
}

function actualizarCountdown() {
    if (!camaraActiva) return;
    const restante = Math.max(0, Math.ceil((INTERVALO_CAMARA_MS - (Date.now() - tiempoUltimoEnvio)) / 1000));
    document.getElementById('cam-countdown').textContent = peticionEnCurso ? '…' : restante;
}

function onFrameFaceMesh(results, canvas, ctx) {
    ctx.save();
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(results.image, 0, 0, canvas.width, canvas.height);

    if (results.multiFaceLandmarks && results.multiFaceLandmarks.length > 0) {
        const lm = results.multiFaceLandmarks[0];

        // Dibujar malla facial (igual que el compañero)
        drawConnectors(ctx, lm, FACEMESH_TESSELATION,  { color: '#e5bc5340', lineWidth: 1 });
        drawConnectors(ctx, lm, FACEMESH_FACE_OVAL,    { color: '#C99A41',   lineWidth: 2 });

        setCamStatus('📡 Monitoreando...', '');

        const ahora = Date.now();
        if (!peticionEnCurso && (ahora - tiempoUltimoEnvio) >= INTERVALO_CAMARA_MS) {
            tiempoUltimoEnvio = ahora;
            enviarRostroDesideCamara(results.image, lm, canvas);
        }
    } else {
        if (!peticionEnCurso) setCamStatus('📡 Esperando rostro...', '');
    }
    ctx.restore();
}

async function enviarRostroDesideCamara(imagenCamara, landmarks, canvas) {
    // Recortar zona del rostro (igual que el compañero)
    const cropCanvas = document.getElementById('cropCanvas');
    const cropCtx    = cropCanvas.getContext('2d');

    let xMin = canvas.width, yMin = canvas.height, xMax = 0, yMax = 0;
    landmarks.forEach(p => {
        const x = p.x * canvas.width, y = p.y * canvas.height;
        if (x < xMin) xMin = x; if (x > xMax) xMax = x;
        if (y < yMin) yMin = y; if (y > yMax) yMax = y;
    });

    cropCtx.clearRect(0, 0, IMG_SIZE, IMG_SIZE);
    cropCtx.drawImage(imagenCamara, xMin - 20, yMin - 40, (xMax - xMin) + 40, (yMax - yMin) + 80, 0, 0, IMG_SIZE, IMG_SIZE);

    const b64 = cropCanvas.toDataURL('image/jpeg', 0.9);

    setCamStatus('⏳ Enviando al IA...', '');
    await enviarAAPI(JSON.stringify({ imagen: b64 }), 'json');
}

function setCamStatus(texto, clase) {
    const el = document.getElementById('cam-status');
    el.innerHTML = texto;
    el.className = 'cam-status ' + clase;
}

// ════════════════════════════════════════════════════════════
// ENVÍO UNIFICADO A LA API
// ════════════════════════════════════════════════════════════
async function enviarAAPI(body, tipo) {
    if (peticionEnCurso) return;
    peticionEnCurso = true;

    setLoading(true);
    ocultarError();

    const opciones = {
        method: 'POST',
        body:   body
    };
    if (tipo === 'json') {
        opciones.headers = { 'Content-Type': 'application/json' };
    }
    // multipart: no poner Content-Type, el browser lo hace con el boundary

    try {
        const resp = await fetch(API_URL, opciones);

        if (resp.status === 429) {
            // Throttle — esperar silenciosamente, no es error del usuario
            const info = await resp.json().catch(() => ({}));
            if (tipo === 'json') setCamStatus('⏱️ ' + (info.detail || 'Espera un momento...'), '');
            return;
        }

        if (!resp.ok) {
            const err = await resp.json().catch(() => ({ detail: `HTTP ${resp.status}` }));
            throw new Error(err.detail || `HTTP ${resp.status}`);
        }

        const data = await resp.json();
        setLoading(false);
        renderResultado(data);

        if (tipo === 'json') setCamStatus('✅ Análisis exitoso', 'ok');

    } catch (err) {
        setLoading(false);
        const esConexion = err.message.toLowerCase().includes('fetch') || err.message.toLowerCase().includes('network');
        if (esConexion) {
            mostrarError('No se pudo conectar con la API.<br>Verifica que esté activa: <code>python api_ia.py</code>');
            if (tipo === 'json') setCamStatus('❌ API inaccesible', 'error');
        } else {
            mostrarError('Error: ' + err.message);
        }
    } finally {
        peticionEnCurso = false;
        if (tipo === 'json') {
            setTimeout(() => {
                if (document.getElementById('cam-status').classList.contains('ok'))
                    setCamStatus('📡 Monitoreando...', '');
            }, 2500);
        }
    }
}

// ════════════════════════════════════════════════════════════
// RENDER DE RESULTADOS
// ════════════════════════════════════════════════════════════
function renderResultado(data) {
    const r = data.rostro, c = data.cabello;

    document.getElementById('res-rostro').textContent      = r.nombre;
    document.getElementById('res-rostro-conf').textContent = `(${r.confianza}% de confianza)`;
    document.getElementById('res-rostro-desc').textContent = r.consejo;
    setTimeout(() => document.getElementById('bar-rostro').style.width = r.confianza + '%', 80);

    document.getElementById('res-cabello').textContent      = c.nombre;
    document.getElementById('res-cabello-conf').textContent = `(${c.confianza}% de confianza)`;
    document.getElementById('res-cabello-top3').textContent = c.top3.map(t => `${t.nombre} ${t.confianza}%`).join(' · ');
    setTimeout(() => document.getElementById('bar-cabello').style.width = c.confianza + '%', 80);

    if (data.recomendaciones && data.recomendaciones.length > 0) {
        const p = data.recomendaciones[0];
        document.getElementById('res-corte').textContent      = p.titulo;
        document.getElementById('res-corte-desc').textContent = p.analisis || '';
        if (p.img_url) {
            const i = document.getElementById('img-corte');
            i.src = p.img_url; i.style.display = 'block';
            i.onerror = () => i.style.display = 'none';
        }
        document.getElementById('box-corte').style.display = 'flex';
    }

    if (data.modo === 'simulacion') {
        document.getElementById('badge-sim').innerHTML =
            '<span class="badge-sim">⚠️ Modo simulación</span>';
    }

    document.getElementById('estado-inicial').style.display  = 'none';
    document.getElementById('panel-resultado').style.display = 'block';

    renderGaleria(data.recomendaciones);
}

function renderGaleria(cortes) {
    const sec = document.getElementById('seccion-cortes');
    const gal = document.getElementById('galeria');
    gal.innerHTML = '';

    if (!cortes || !cortes.length) {
        gal.innerHTML = '<p style="color:var(--c-text-muted);grid-column:1/-1;">Sin cortes en el dataset para este perfil.</p>';
        sec.style.display = 'block';
        return;
    }

    cortes.forEach(c => {
        const tags = [
            ...c.etiquetas_rostro.map(t  => `<span class="gtag">${t}</span>`),
            ...c.etiquetas_cabello.map(t => `<span class="gtag">${t}</span>`)
        ].join('');
        const link = c.page_url
            ? `<a href="${c.page_url}" target="_blank" rel="noopener" class="galeria-link">
                   <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                       <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                       <polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
                   </svg> Ver referencia</a>` : '';

        const card = document.createElement('div');
        card.className = 'galeria-card';
        card.innerHTML = c.img_url
            ? `<img class="galeria-img" src="${c.img_url}" alt="${c.titulo}" loading="lazy" onerror="this.style.display='none'">
               <div class="galeria-body"><div class="galeria-titulo">${c.titulo}</div><div class="galeria-tags">${tags}</div>${link}</div>`
            : `<div class="galeria-body"><div class="galeria-titulo">${c.titulo}</div><div class="galeria-tags">${tags}</div>${link}</div>`;
        gal.appendChild(card);
    });

    sec.style.display = 'block';
    setTimeout(() => sec.scrollIntoView({ behavior: 'smooth', block: 'start' }), 200);
}

// ════════════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════════════
function setLoading(on) {
    document.getElementById('spinner-wrap').style.display   = on ? 'block' : 'none';
    document.getElementById('estado-inicial').style.display = (on || document.getElementById('panel-resultado').style.display === 'block') ? 'none' : 'block';
    const btnFoto = document.getElementById('btn-analizar-foto');
    if (btnFoto) btnFoto.disabled = on;
}

function mostrarError(html) {
    const el = document.getElementById('error-box');
    el.innerHTML = '⚠️ ' + html;
    el.style.display = 'block';
    document.getElementById('estado-inicial').style.display = 'block';
}

function ocultarError() {
    document.getElementById('error-box').style.display = 'none';
}

function resetResultados() {
    document.getElementById('panel-resultado').style.display  = 'none';
    document.getElementById('seccion-cortes').style.display   = 'none';
    document.getElementById('box-corte').style.display        = 'none';
    document.getElementById('badge-sim').innerHTML            = '';
    document.getElementById('bar-rostro').style.width         = '0%';
    document.getElementById('bar-cabello').style.width        = '0%';
    document.getElementById('galeria').innerHTML              = '';
    document.getElementById('estado-inicial').style.display   = 'block';
    ocultarError();
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}
</script>
</body>
</html>