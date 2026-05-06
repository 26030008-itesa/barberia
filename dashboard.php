<?php
session_start();

if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) { header("Location: index.html"); exit; }
include("conexion.php"); 
if (!isset($conn)) { die("Error fatal: No se pudo establecer la conexión."); }

$currentUser = $_SESSION['usuario'];
$rol_actual_sesion = $_SESSION['rol'];

try {
    while($conn->more_results() && $conn->next_result()){;}
    $stmt = $conn->prepare("CALL sp_validar_usuario_sistema(?)");
    $stmt->bind_param("s", $currentUser);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows == 0) { session_unset(); session_destroy(); header("Location: index.html?error=2"); exit; }
    $db_role_info = $res->fetch_assoc();
    $stmt->close();
    $rol_en_db_app = 'desconocido';
    if ($db_role_info['Role'] == 'ADMINISTRADOR') $rol_en_db_app = 'admin';
    if ($db_role_info['Role'] == 'VENDEDOR_CAJERO') $rol_en_db_app = 'vendedor';
    if ($db_role_info['Role'] == 'BARBERO') $rol_en_db_app = 'empleado';
    if ($rol_actual_sesion != $rol_en_db_app) { session_unset(); session_destroy(); header("Location: index.html?error=3"); exit; }
} catch (Exception $e) { session_unset(); session_destroy(); header("Location: index.html?error=99"); exit; }
$rol = $_SESSION['rol'];

$citas_hoy = 0;
try {
    while($conn->more_results() && $conn->next_result()){;}
    if ($res_func = $conn->query("CALL sp_obtener_citas_hoy()")) {
        if ($row_func = $res_func->fetch_assoc()) { $citas_hoy = $row_func['total']; }
        $res_func->close();
    }
} catch (Exception $e) { $citas_hoy = 0; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visago - Inicio</title>
    <link rel="stylesheet" href="css/style.css"> 
</head>
<body class="fondo-dashboard">
    <div class="app-container">
        
        <aside class="sidebar" id="sidebar">
           <button class="sidebar-toggle" onclick="toggleSidebar()">
                <svg class="svg-icon-toggle" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <div class="sidebar-logo">VISAGO<br><span style="color:var(--c-text-muted); font-size:0.5em; font-family:'Montserrat', sans-serif; letter-spacing: 4px;"></span></div>
            
            <div class="sidebar-search">
                <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="buscadorSidebar" class="nav-link-text" placeholder="Buscar módulo..." onkeyup="filtrarModulos()">
            </div>
            
            <a href="dashboard.php" class="nav-link active">
                <svg class="svg-icon" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span class="nav-link-text">Inicio</span>
            </a>
            <a href="crud/reservaciones.php" class="nav-link">
                <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <span class="nav-link-text">Reservaciones</span> <span class="nav-link-text" style="background:var(--c-accent-gold); color:#111; padding:2px 8px; border-radius:10px; font-size:0.8em; margin-left:auto; font-weight:bold;"><?= $citas_hoy ?></span>
            </a>
            
            <?php if (in_array($rol, ['admin', 'vendedor'])): ?>
                <a href="crud/clientes.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg><span class="nav-link-text">Clientes</span></a>
                <a href="crud/ventas.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg><span class="nav-link-text">Ventas</span></a>
                <a href="crud/productos.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg><span class="nav-link-text">Productos (Inventario)</span></a>
            <?php endif; ?>
            
            <?php if ($rol == 'admin' || $rol == 'empleado'): ?>
                <a href="crud/servicios.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg><span class="nav-link-text">Servicios</span></a>
            <?php endif; ?>

            <?php if ($rol == 'admin'): ?>
                <a href="crud/empleados.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="8" y="2" width="8" height="20" rx="1"></rect><path d="M8 6h8M8 10h8M8 14h8M8 18h8"></path></svg><span class="nav-link-text">Barberos</span></a>
                <a href="crud/proveedores.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg><span class="nav-link-text">Proveedores</span></a>
                <a href="crud/usuarios.php" class="nav-link" style="margin-top: 30px; border-top: 1px solid #222; padding-top: 20px;"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg><span class="nav-link-text">Usuarios</span></a>
            <?php endif; ?>
        </aside>

        <main class="main-content">
            
            <header class="topbar">
                <div class="topbar-greeting">Visago Studio</div>
                
                <div class="topbar-actions">
                    <div class="notification-wrapper">
                        <svg class="svg-icon notification-icon" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <span class="notification-badge">1</span>
                        <div class="notification-dropdown">
                            <a href="crud/productos.php" class="noti-item">
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
                            <a href="index.html">🔄 Cambiar de Usuario</a>
                            <a href="logout.php" style="color: #D9534F;">🚪 Cerrar Sesión</a>
                        </div>
                    </div>
                </div>
            </header>

            <section class="hero-ia" data-nombre="ia stylist escáner facial inteligencia artificial">
                <div class="hero-ia-content">
                    <div style="font-weight: bold; letter-spacing: 2px; margin-bottom: 10px; font-size: 0.9em; color: var(--c-accent-gold); text-shadow: var(--gold-glow);">✨ VISAGO TECHNOLOGY</div>
                    <h1>Visago IA Stylist</h1>
                    <p>Utiliza nuestra Inteligencia Artificial de Visión Computacional para escanear el rostro de tus clientes y recomendarles el corte y estilo de barba perfectos en segundos.</p>
                    <a href="crud/ia_recomendacion.php" class="hero-btn">Iniciar Escáner Facial</a>
                </div>
                <svg class="svg-icon hero-icon-bg" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="10" rx="2"></rect><circle cx="12" cy="5" r="2"></circle><path d="M12 7v4M8 16h.01M16 16h.01"></path></svg>
            </section>

            <h2 style="color: var(--c-text-cream); font-family: 'Oswald', sans-serif; border-bottom: 2px solid #222; padding-bottom: 10px; margin-bottom: 25px;">Tus Módulos</h2>
            
            <div class="modules-grid" id="contenedorModulos">
                
                <a href="crud/reservaciones.php" class="module-card">
                    <div class="module-img" style="background-image: url('img/fondo-reservaciones.jpg');"></div>
                    <div class="module-content">
                        <div class="card-header">
                            <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        </div>
                        <div>
                            <h3>Reservaciones</h3>
                            <p>Gestión de la agenda y citas.</p>
                        </div>
                    </div>
                </a>

                <?php if (in_array($rol, ['admin', 'vendedor'])): ?>
                    <a href="crud/clientes.php" class="module-card">
                        <div class="module-img" style="background-image: url('img/fondo-clientes.jpg');"></div>
                        <div class="module-content">
                            <div class="card-header">
                                <svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            </div>
                            <div>
                                <h3>Clientes</h3>
                                <p>Directorio y perfiles.</p>
                            </div>
                        </div>
                    </a>
                    
                    <a href="crud/ventas.php" class="module-card">
                        <div class="module-img" style="background-image: url('img/fondo-ventas.jpg');"></div>
                        <div class="module-content">
                            <div class="card-header">
                                <svg class="svg-icon" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg>
                            </div>
                            <div>
                                <h3>Ventas</h3>
                                <p>Registro de tickets y cobros.</p>
                            </div>
                        </div>
                    </a>

                    <a href="crud/productos.php" class="module-card">
                        <div class="module-img" style="background-image: url('img/fondo-productos.jpg');"></div>
                        <div class="module-content">
                            <div class="card-header">
                                <svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                            </div>
                            <div>
                                <h3>Productos</h3>
                                <p>Inventario inteligente.</p>
                            </div>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if ($rol == 'admin' || $rol == 'empleado'): ?>
                    <a href="crud/servicios.php" class="module-card">
                        <div class="module-img" style="background-image: url('img/fondo-servicios.jpg');"></div>
                        <div class="module-content">
                            <div class="card-header">
                                <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg>
                            </div>
                            <div>
                                <h3>Servicios</h3>
                                <p>Catálogo de cortes y precios.</p>
                            </div>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if ($rol == 'admin'): ?>
                    <a href="crud/empleados.php" class="module-card">
                        <div class="module-img" style="background-image: url('img/fondo-empleados.jpg');"></div>
                        <div class="module-content">
                            <div class="card-header">
                                <svg class="svg-icon" viewBox="0 0 24 24"><rect x="8" y="2" width="8" height="20" rx="1"></rect><path d="M8 6h8M8 10h8M8 14h8M8 18h8"></path></svg>
                            </div>
                            <div>
                                <h3>Barberos</h3>
                                <p>Gestión de la plantilla.</p>
                            </div>
                        </div>
                    </a>

                    <a href="crud/proveedores.php" class="module-card">
                        <div class="module-img" style="background-image: url('img/fondo-proveedores.jpg');"></div>
                        <div class="module-content">
                            <div class="card-header">
                                <svg class="svg-icon" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                            </div>
                            <div>
                                <h3>Proveedores</h3>
                                <p>Contactos de suministro.</p>
                            </div>
                        </div>
                    </a>

                    <a href="crud/usuarios.php" class="module-card">
                        <div class="module-img" style="background-image: url('img/fondo-usuarios.jpg');"></div>
                        <div class="module-content">
                            <div class="card-header">
                                <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            </div>
                            <div>
                                <h3>Usuarios</h3>
                                <p>Seguridad y roles del sistema.</p>
                            </div>
                        </div>
                    </a>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }

        function filtrarModulos() {
            const textoBusqueda = document.getElementById('buscadorSidebar').value.toLowerCase();
            const tarjetas = document.querySelectorAll('.module-card');
            const heroIA = document.querySelector('.hero-ia');
            
            tarjetas.forEach(tarjeta => {
                const textoTarjeta = tarjeta.innerText.toLowerCase();
                if (textoTarjeta.includes(textoBusqueda)) {
                    tarjeta.style.display = ''; 
                } else {
                    tarjeta.style.display = 'none'; 
                }
            });

            if (heroIA) {
                const tagsHero = heroIA.getAttribute('data-nombre').toLowerCase();
                if (tagsHero.includes(textoBusqueda) || "visago ia stylist".includes(textoBusqueda)) {
                    heroIA.style.display = '';
                } else {
                    heroIA.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>