<?php
session_start();

if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) { header("Location: ../index.html"); exit; }
include("../conexion.php"); 

$currentUser = $_SESSION['usuario'];
$rol         = $_SESSION['rol'];

// Obtener Citas de HOY (para el globito dorado del menú)
$citas_hoy = 0;
try {
    if (isset($conn)) {
        while($conn->more_results() && $conn->next_result()){;}
        if ($res_func = $conn->query("CALL sp_obtener_citas_hoy()")) {
            if ($row_func = $res_func->fetch_assoc()) { $citas_hoy = $row_func['total']; }
            $res_func->close();
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
    <style>
        /* ── Módulo IA — diseño Visago Gold Dark ── */
        .ia-header { text-align: center; margin-bottom: 40px; }
        .ia-header h2 { color: var(--c-accent-gold); font-size: 2.8em; margin-bottom: 10px; text-transform: uppercase; font-family: 'Oswald', sans-serif; text-shadow: var(--gold-glow);}
        .ia-header p { color: var(--c-text-muted); font-size: 1.1em; max-width: 700px; margin: 0 auto;}

        .ia-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 40px; align-items: start; }

        /* Upload zone */
        .upload-zone { background: rgba(0,0,0,0.2); border: 2px dashed #333; padding: 40px 20px; text-align: center; border-radius: 12px; transition: 0.3s; display: flex; flex-direction: column; justify-content: center; box-sizing: border-box; min-height: 340px; }
        .upload-zone:hover, .upload-zone.drag-over { border-color: var(--c-accent-gold); background: rgba(201,154,65,0.05); box-shadow: inset 0 0 20px rgba(201,154,65,0.1); }
        .upload-icon { font-size: 4em; color: var(--c-text-muted); margin-bottom: 15px; display: block; transition: transform 0.4s; }
        .upload-zone:hover .upload-icon { transform: scale(1.1); color: var(--c-accent-gold); }

        /* Preview */
        #preview-wrap { display: none; flex-direction: column; align-items: center; gap: 12px; }
        #preview-img  { width: 100%; max-width: 220px; max-height: 220px; object-fit: cover; border-radius: 10px; border: 2px solid var(--c-accent-gold); box-shadow: var(--gold-glow); }
        #btn-cambiar  { background: transparent; border: 1px solid #444; color: var(--c-text-muted); padding: 5px 14px; border-radius: 6px; cursor: pointer; font-size: 0.82em; transition: 0.2s; }
        #btn-cambiar:hover { border-color: var(--c-accent-gold); color: var(--c-accent-gold); }
        #btn-analizar { display: none; margin-top: 4px; background: var(--c-accent-gold); color: #111; font-weight: bold; font-family: 'Oswald', sans-serif; letter-spacing: 1px; padding: 10px 28px; border-radius: 8px; border: none; cursor: pointer; font-size: 1em; transition: 0.2s; }
        #btn-analizar:hover:not(:disabled) { filter: brightness(1.15); }
        #btn-analizar:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Result zone */
        .result-zone { background: rgba(0,0,0,0.3); padding: 30px; border-radius: 12px; border: 1px solid #333; box-shadow: 0 10px 30px rgba(0,0,0,0.5); min-height: 340px; }
        .result-title { color: var(--c-text-cream); font-family: 'Oswald', sans-serif; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; }

        /* Estados */
        #estado-inicial { text-align: center; padding: 50px 20px; color: var(--c-text-muted); }
        #estado-inicial svg { opacity: 0.25; margin-bottom: 14px; display: block; margin-left: auto; margin-right: auto; }
        .spinner-wrap { display: none; text-align: center; padding: 50px 20px; }
        .spinner { width: 46px; height: 46px; border: 4px solid #2a2a2a; border-top-color: var(--c-accent-gold); border-radius: 50%; animation: giro 0.85s linear infinite; margin: 0 auto 14px; }
        @keyframes giro { to { transform: rotate(360deg); } }
        .spinner-wrap p { color: var(--c-text-muted); font-size: 0.95em; line-height: 1.6; }
        .error-box { display: none; background: rgba(160,30,30,0.15); border: 1px solid #7a2020; border-radius: 8px; padding: 14px 18px; color: #ff7070; font-size: 0.9em; margin-bottom: 16px; line-height: 1.6; }
        .error-box code { background: rgba(255,255,255,0.08); padding: 1px 6px; border-radius: 4px; }

        /* Trait boxes — igual al diseño original */
        #panel-resultado { display: none; }
        .trait-box { background: rgba(0,0,0,0.5); padding: 15px; margin-bottom: 15px; border-left: 4px solid var(--c-accent-gold); border-radius: 8px; display: flex; gap: 15px; align-items: center; border: 1px solid #222; transition: 0.3s; }
        .trait-box:hover { background: rgba(201,154,65,0.05); transform: translateX(5px); }
        .trait-text { flex: 1; }
        .trait-label { color: var(--c-text-muted); font-size: 0.85em; text-transform: uppercase; display: block; margin-bottom: 5px; letter-spacing: 1px; }
        .trait-value { color: var(--c-accent-gold); font-weight: bold; font-size: 1.2em; font-family: 'Oswald', sans-serif; letter-spacing: 0.5px; }
        .trait-conf  { font-size: 0.78em; color: #666; margin-left: 6px; }
        .trait-desc  { color: var(--c-text-cream); font-size: 0.85em; margin-top: 5px; margin-bottom: 0; line-height: 1.4; }
        .trait-img   { width: 80px; height: 80px; border-radius: 8px; object-fit: cover; border: 1px solid var(--c-accent-gold); box-shadow: var(--gold-glow); flex-shrink: 0; }
        .conf-bar-wrap { height: 4px; background: #1c1c1c; border-radius: 2px; margin-top: 8px; overflow: hidden; }
        .conf-bar { height: 100%; background: linear-gradient(90deg, var(--c-accent-gold), #f5d07a); border-radius: 2px; width: 0%; transition: width 0.7s ease; }

        /* Galería de cortes */
        #seccion-cortes { display: none; margin-top: 40px; }
        #seccion-cortes h3 { color: var(--c-accent-gold); font-family: 'Oswald', sans-serif; font-size: 1.7em; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #2a2a2a; padding-bottom: 10px; margin-bottom: 24px; }
        .galeria { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 18px; }
        .galeria-card { background: rgba(0,0,0,0.45); border: 1px solid #242424; border-radius: 10px; overflow: hidden; transition: 0.3s; }
        .galeria-card:hover { border-color: var(--c-accent-gold); transform: translateY(-5px); box-shadow: 0 8px 28px rgba(201,154,65,0.15); }
        .galeria-img { width: 100%; aspect-ratio: 1/1; object-fit: cover; display: block; background: #111; transition: transform 0.4s; }
        .galeria-card:hover .galeria-img { transform: scale(1.06); }
        .galeria-body { padding: 11px 13px; }
        .galeria-titulo { color: var(--c-text-cream); font-size: 0.86em; font-weight: bold; margin-bottom: 7px; line-height: 1.35; }
        .galeria-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 8px; }
        .gtag { background: rgba(201,154,65,0.1); border: 1px solid rgba(201,154,65,0.28); color: var(--c-accent-gold); font-size: 0.7em; padding: 2px 7px; border-radius: 4px; }
        .galeria-link { color: var(--c-text-muted); font-size: 0.76em; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; }
        .galeria-link:hover { color: var(--c-accent-gold); }
        .badge-sim { display: inline-block; background: rgba(180,100,0,0.2); border: 1px solid rgba(201,154,65,0.35); color: var(--c-accent-gold); font-size: 0.72em; padding: 2px 10px; border-radius: 10px; margin-left: 10px; vertical-align: middle; font-family: 'Montserrat', sans-serif; }

        @media (max-width: 768px) {
            .ia-grid { grid-template-columns: 1fr; }
            .trait-box { flex-direction: column; text-align: center; align-items: center; }
            .trait-img { width: 100%; height: 150px; margin-top: 10px; }
            .galeria { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body class="fondo-imagen-premium">
<div class="app-container">

    <!-- ════════ SIDEBAR (intacto del original) ════════ -->
    <aside class="sidebar collapsed" id="sidebar">
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <svg class="svg-icon-toggle" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
        <div class="sidebar-logo">VISAGO<br><span style="color:var(--c-text-muted); font-size:0.5em; font-family:'Montserrat', sans-serif; letter-spacing: 4px;">ESTUDIO INTELIGENTE</span></div>

        <a href="../dashboard.php" class="nav-link">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span class="nav-link-text">Inicio</span>
        </a>
        <a href="reservaciones.php" class="nav-link">
            <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span class="nav-link-text">Reservaciones</span>
            <span class="nav-link-text" style="background:var(--c-accent-gold); color:#111; padding:2px 8px; border-radius:10px; font-size:0.8em; margin-left:auto; font-weight:bold;"><?= $citas_hoy ?></span>
        </a>

        <?php if (in_array($rol, ['admin', 'vendedor'])): ?>
            <a href="clientes.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg><span class="nav-link-text">Clientes</span></a>
            <a href="ventas.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg><span class="nav-link-text">Ventas</span></a>
            <a href="productos.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg><span class="nav-link-text">Productos (Inventario)</span></a>
        <?php endif; ?>

        <?php if ($rol == 'admin' || $rol == 'empleado'): ?>
            <a href="servicios.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg><span class="nav-link-text">Servicios</span></a>
        <?php endif; ?>

        <?php if ($rol == 'admin'): ?>
            <a href="empleados.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="8" y="2" width="8" height="20" rx="1"></rect><path d="M8 6h8M8 10h8M8 14h8M8 18h8"></path></svg><span class="nav-link-text">Barberos</span></a>
            <a href="proveedores.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg><span class="nav-link-text">Proveedores</span></a>
            <a href="usuarios.php" class="nav-link" style="margin-top: 30px; border-top: 1px solid #222; padding-top: 20px;"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg><span class="nav-link-text">Usuarios</span></a>
        <?php endif; ?>
    </aside>

    <!-- ════════ MAIN ════════ -->
    <main class="main-content">

        <!-- TOPBAR (intacto del original) -->
        <header class="topbar">
            <div class="topbar-greeting">Visago Studio <span style="color:var(--c-text-muted); font-weight:normal; font-family:'Montserrat', sans-serif;">/ Inteligencia Artificial</span></div>
            <div class="topbar-actions">
                <div class="notification-wrapper">
                    <svg class="svg-icon notification-icon" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    <span class="notification-badge">1</span>
                    <div class="notification-dropdown">
                        <a href="productos.php" class="noti-item">
                            <span class="noti-title">⚠️ Alerta de Inventario</span>
                            <span>El Shampoo Matizador se agotará en 4 días según la predicción.</span>
                        </a>
                    </div>
                </div>
                <div class="user-dropdown-wrapper">
                    <div class="user-profile">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($currentUser) ?>&background=222222&color=F5EEDB&bold=true" alt="Avatar" class="user-avatar">
                        <div class="user-info-desktop" style="display: block;">
                            <div style="font-weight: bold;"><?= htmlspecialchars(ucfirst($currentUser)) ?></div>
                            <div style="font-size: 0.8em; text-transform: uppercase; color: var(--c-accent-gold);"><?= htmlspecialchars($rol) ?></div>
                        </div>
                        <svg class="svg-icon" viewBox="0 0 24 24" style="width: 16px; height: 16px;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                    <div class="user-menu">
                        <a href="../index.html">🔄 Cambiar de Usuario</a>
                        <a href="../logout.php" style="color: #D9534F;">🚪 Cerrar Sesión</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- ════════ CONTENIDO ════════ -->
        <div class="crud-container" style="margin-top: 0; flex-grow: 1; max-width: 100%;">

            <div class="ia-header">
                <h2>Visión Computacional & Estilo</h2>
                <p>Sube una fotografía frontal de tu cliente para que nuestra Inteligencia Artificial analice la forma del rostro y el tipo de cabello, y recomiende los cortes perfectos.</p>
            </div>

            <!-- ── Grid principal ── -->
            <div class="ia-grid">

                <!-- Panel izquierdo: upload -->
                <div class="upload-zone" id="upload-zone"
                     ondragover="event.preventDefault(); this.classList.add('drag-over')"
                     ondragleave="this.classList.remove('drag-over')"
                     ondrop="onDrop(event)">

                    <!-- Estado inicial -->
                    <div id="upload-inicial">
                        <span class="upload-icon">
                            <svg class="svg-icon" viewBox="0 0 24 24" style="width:60px;height:60px;stroke:currentColor;">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                        </span>
                        <h3 style="color:var(--c-text-cream);font-family:'Oswald',sans-serif;margin-bottom:5px;">Cargar Fotografía</h3>
                        <p style="color:var(--c-text-muted);font-size:0.9em;margin-top:0;">Formatos soportados: JPG, PNG (Máx 5MB)</p>
                        <input type="file" id="foto_ia" accept="image/jpeg,image/png,image/webp" style="display:none;">
                        <button class="btn-new" onclick="document.getElementById('foto_ia').click();"
                                style="margin:20px auto 0 auto;display:inline-block;">Seleccionar Archivo</button>
                    </div>

                    <!-- Preview + botones (se muestra al cargar foto) -->
                    <div id="preview-wrap">
                        <img id="preview-img" src="" alt="Preview">
                        <button id="btn-cambiar" onclick="document.getElementById('foto_ia').click();">🔄 Cambiar foto</button>
                        <button id="btn-analizar" onclick="analizarFoto()">✨ Analizar con IA</button>
                    </div>
                </div>

                <!-- Panel derecho: resultados -->
                <div class="result-zone">
                    <h3 class="result-title">Resultados del Análisis IA</h3>

                    <!-- Estado vacío -->
                    <div id="estado-inicial">
                        <svg width="64" height="64" viewBox="0 0 64 64" fill="none" stroke="#555" stroke-width="1.8">
                            <circle cx="32" cy="32" r="28"/>
                            <circle cx="32" cy="22" r="9"/>
                            <path d="M13 54c0-10.49 8.51-19 19-19s19 8.51 19 19"/>
                        </svg>
                        <p style="margin-top:10px;font-size:0.95em;">Selecciona una foto para comenzar</p>
                    </div>

                    <!-- Spinner -->
                    <div class="spinner-wrap" id="spinner-wrap">
                        <div class="spinner"></div>
                        <p>Analizando con Inteligencia Artificial…<br>
                           <small style="color:#555;">Esto puede tardar unos segundos</small></p>
                    </div>

                    <!-- Error -->
                    <div class="error-box" id="error-box"></div>

                    <!-- Resultados -->
                    <div id="panel-resultado">

                        <!-- Rostro -->
                        <div class="trait-box">
                            <div class="trait-text">
                                <span class="trait-label">Tipo de Rostro Detectado:</span>
                                <span class="trait-value" id="res-rostro">—</span>
                                <span class="trait-conf"  id="res-rostro-conf"></span>
                                <div class="conf-bar-wrap"><div class="conf-bar" id="bar-rostro"></div></div>
                                <p class="trait-desc" id="res-rostro-desc"></p>
                            </div>
                            <img id="img-rostro" src="" alt="Rostro" class="trait-img" style="display:none;">
                        </div>

                        <!-- Cabello -->
                        <div class="trait-box">
                            <div class="trait-text">
                                <span class="trait-label">Tipo de Cabello Detectado:</span>
                                <span class="trait-value" id="res-cabello">—</span>
                                <span class="trait-conf"  id="res-cabello-conf"></span>
                                <div class="conf-bar-wrap"><div class="conf-bar" id="bar-cabello"></div></div>
                                <p class="trait-desc" id="res-cabello-top3" style="font-size:0.8em;color:#555;margin-top:6px;"></p>
                            </div>
                            <img id="img-cabello" src="" alt="Cabello" class="trait-img" style="display:none;">
                        </div>

                        <!-- Corte principal recomendado -->
                        <div class="trait-box" id="box-corte" style="display:none;">
                            <div class="trait-text">
                                <span class="trait-label">Corte Principal Recomendado:</span>
                                <span class="trait-value" id="res-corte">—</span>
                                <p class="trait-desc" id="res-corte-desc"></p>
                            </div>
                            <img id="img-corte" src="" alt="Corte" class="trait-img" style="display:none;">
                        </div>

                    </div><!-- /panel-resultado -->
                </div><!-- /result-zone -->
            </div><!-- /ia-grid -->

            <!-- ── Galería de cortes recomendados ── -->
            <div id="seccion-cortes">
                <h3>💈 Cortes que te quedarían mejor <span id="badge-sim"></span></h3>
                <div class="galeria" id="galeria"></div>
            </div>

        </div><!-- /crud-container -->
    </main>
</div><!-- /app-container -->

<script>
// ── Config ──────────────────────────────────────────────────
const API_URL = 'http://localhost:8000/analizar';
let archivoActual = null;

// ── Selección de archivo ─────────────────────────────────────
document.getElementById('foto_ia').addEventListener('change', function () {
    cargarArchivo(this.files[0]);
});

function onDrop(e) {
    e.preventDefault();
    document.getElementById('upload-zone').classList.remove('drag-over');
    cargarArchivo(e.dataTransfer.files[0]);
}

function cargarArchivo(file) {
    if (!file) return;
    if (!file.type.match(/image\/(jpeg|png|webp)/)) {
        mostrarError('Formato no soportado. Usa JPG o PNG.');
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        mostrarError('La imagen supera los 5 MB. Elige una más pequeña.');
        return;
    }

    archivoActual = file;
    const reader  = new FileReader();
    reader.onload = e => {
        document.getElementById('preview-img').src           = e.target.result;
        document.getElementById('upload-inicial').style.display = 'none';
        document.getElementById('preview-wrap').style.display   = 'flex';
        document.getElementById('btn-analizar').style.display   = 'block';
    };
    reader.readAsDataURL(file);
    resetResultados();
}

// ── Llamada a la API ─────────────────────────────────────────
async function analizarFoto() {
    if (!archivoActual) return;

    setLoading(true);
    ocultarError();
    resetResultados();

    const fd = new FormData();
    fd.append('foto', archivoActual);

    try {
        const resp = await fetch(API_URL, { method: 'POST', body: fd });

        if (!resp.ok) {
            const err = await resp.json().catch(() => ({ detail: `HTTP ${resp.status}` }));
            throw new Error(err.detail || `HTTP ${resp.status}`);
        }

        const data = await resp.json();
        setLoading(false);
        renderResultado(data);

    } catch (err) {
        setLoading(false);
        const esConexion = err.message.toLowerCase().includes('fetch') ||
                           err.message.toLowerCase().includes('network');
        if (esConexion) {
            mostrarError(
                'No se pudo conectar con la API de IA.<br>' +
                'Asegúrate de que el servidor esté activo:<br>' +
                '<code>python api_ia.py</code>'
            );
        } else {
            mostrarError('Error al analizar: ' + err.message);
        }
    }
}

// ── Render de resultados ─────────────────────────────────────
function renderResultado(data) {
    const r = data.rostro;
    const c = data.cabello;

    // Rostro
    document.getElementById('res-rostro').textContent      = r.nombre;
    document.getElementById('res-rostro-conf').textContent = `(${r.confianza}% de confianza)`;
    document.getElementById('res-rostro-desc').textContent = r.consejo;
    setTimeout(() => document.getElementById('bar-rostro').style.width = r.confianza + '%', 80);

    // Cabello
    document.getElementById('res-cabello').textContent      = c.nombre;
    document.getElementById('res-cabello-conf').textContent = `(${c.confianza}% de confianza)`;
    const top3 = c.top3.map(t => `${t.nombre} ${t.confianza}%`).join(' · ');
    document.getElementById('res-cabello-top3').textContent = top3;
    setTimeout(() => document.getElementById('bar-cabello').style.width = c.confianza + '%', 80);

    // Corte principal (primer resultado del dataset)
    if (data.recomendaciones && data.recomendaciones.length > 0) {
        const primero = data.recomendaciones[0];
        document.getElementById('res-corte').textContent      = primero.titulo;
        document.getElementById('res-corte-desc').textContent = primero.analisis || '';
        if (primero.img_url) {
            const imgC      = document.getElementById('img-corte');
            imgC.src        = primero.img_url;
            imgC.style.display = 'block';
            imgC.onerror    = () => imgC.style.display = 'none';
        }
        document.getElementById('box-corte').style.display = 'flex';
    }

    // Badge simulación
    if (data.modo === 'simulacion') {
        document.getElementById('badge-sim').innerHTML =
            '<span class="badge-sim">⚠️ Modo simulación — entrena los modelos para resultados reales</span>';
    }

    // Mostrar panel
    document.getElementById('estado-inicial').style.display  = 'none';
    document.getElementById('panel-resultado').style.display = 'block';

    // Galería
    renderGaleria(data.recomendaciones);
}

function renderGaleria(cortes) {
    const sec     = document.getElementById('seccion-cortes');
    const galeria = document.getElementById('galeria');
    galeria.innerHTML = '';

    if (!cortes || cortes.length === 0) {
        galeria.innerHTML = '<p style="color:var(--c-text-muted);grid-column:1/-1;">No se encontraron cortes para este perfil en el dataset.</p>';
        sec.style.display = 'block';
        return;
    }

    cortes.forEach(corte => {
        const tags = [
            ...corte.etiquetas_rostro.map(t  => `<span class="gtag">${t}</span>`),
            ...corte.etiquetas_cabello.map(t => `<span class="gtag">${t}</span>`)
        ].join('');

        const linkHtml = corte.page_url
            ? `<a href="${corte.page_url}" target="_blank" rel="noopener" class="galeria-link">
                   <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                       <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                       <polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
                   </svg> Ver referencia
               </a>` : '';

        const card = document.createElement('div');
        card.className = 'galeria-card';

        if (corte.img_url) {
            card.innerHTML = `
                <img class="galeria-img" src="${corte.img_url}" alt="${corte.titulo}"
                     loading="lazy" onerror="this.style.display='none'">
                <div class="galeria-body">
                    <div class="galeria-titulo">${corte.titulo}</div>
                    <div class="galeria-tags">${tags}</div>
                    ${linkHtml}
                </div>`;
        } else {
            card.innerHTML = `
                <div class="galeria-img" style="background:#111;display:flex;align-items:center;justify-content:center;">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                </div>
                <div class="galeria-body">
                    <div class="galeria-titulo">${corte.titulo}</div>
                    <div class="galeria-tags">${tags}</div>
                    ${linkHtml}
                </div>`;
        }

        galeria.appendChild(card);
    });

    sec.style.display = 'block';
    setTimeout(() => sec.scrollIntoView({ behavior: 'smooth', block: 'start' }), 200);
}

// ── Helpers de estado ────────────────────────────────────────
function setLoading(on) {
    document.getElementById('spinner-wrap').style.display   = on ? 'block' : 'none';
    document.getElementById('estado-inicial').style.display = on ? 'none'  : 'block';
    document.getElementById('btn-analizar').disabled        = on;
    if (on) document.getElementById('panel-resultado').style.display = 'none';
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
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}
</script>
</body>
</html>