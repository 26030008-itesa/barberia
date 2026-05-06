<?php
session_start();

if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) { header("Location: ../index.html"); exit; }
include("../conexion.php"); 
if (!isset($conn)) { die("Error fatal."); }
if ($_SESSION['rol'] !== 'admin') { header("Location: ../dashboard.php"); exit; }

$mensaje_exito = $_SESSION['mensaje_status'] ?? ''; unset($_SESSION['mensaje_status']); 
$mensaje_error = $_SESSION['mensaje_status_error'] ?? ''; unset($_SESSION['mensaje_status_error']); 
$current_admin_user = $_SESSION['usuario'];
$rol = $_SESSION['rol'];
$protected_users = ['root', 'mysql.infoschema', 'mysql.session', 'mysql.sys'];
$search_user = $_GET['search_user'] ?? '';

// Obtener Citas de HOY (para el globito dorado del menú)
$citas_hoy = 0;
try {
    while($conn->more_results() && $conn->next_result()){;}
    if ($res_func = $conn->query("CALL sp_obtener_citas_hoy()")) {
        if ($row_func = $res_func->fetch_assoc()) { $citas_hoy = $row_func['total']; }
        $res_func->close();
    }
} catch (Exception $e) { $citas_hoy = 0; }

// Buscar Usuarios
try {
    while($conn->more_results() && $conn->next_result()){;}
    $stmt = $conn->prepare("CALL sp_listar_usuarios_sistema(?)");
    $stmt->bind_param("s", $search_user);
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Exception $e) { die("Error al listar usuarios: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visago - Seguridad y Accesos</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="fondo-usuarios">
    <div class="app-container">
        
        <aside class="sidebar collapsed" id="sidebar">
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <svg class="svg-icon-toggle" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <div class="sidebar-logo">VISAGO<br><span style="color:var(--c-text-muted); font-size:0.5em; font-family:'Montserrat', sans-serif; letter-spacing: 4px;"></span></div>
            
            <a href="../dashboard.php" class="nav-link">
                <svg class="svg-icon" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span class="nav-link-text">Inicio</span>
            </a>
            <a href="reservaciones.php" class="nav-link">
                <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <span class="nav-link-text">Reservaciones</span> 
                <span class="nav-link-text" style="background:var(--c-accent-gold); color:#111; padding:2px 8px; border-radius:10px; font-size:0.8em; margin-left:auto; font-weight:bold;"><?= $citas_hoy ?></span>
            </a>
            
            <a href="clientes.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg><span class="nav-link-text">Clientes</span></a>
            <a href="ventas.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg><span class="nav-link-text">Ventas</span></a>
            <a href="productos.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg><span class="nav-link-text">Productos (Inventario)</span></a>
            <a href="servicios.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg><span class="nav-link-text">Servicios</span></a>
            <a href="empleados.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="8" y="2" width="8" height="20" rx="1"></rect><path d="M8 6h8M8 10h8M8 14h8M8 18h8"></path></svg><span class="nav-link-text">Barberos</span></a>
            <a href="proveedores.php" class="nav-link"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg><span class="nav-link-text">Proveedores</span></a>
            
            <a href="usuarios.php" class="nav-link active" style="margin-top: 30px; border-top: 1px solid rgba(201, 154, 65, 0.2); padding-top: 20px;"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg><span class="nav-link-text">Usuarios</span></a>
        </aside>

        <main class="main-content">
            
            <header class="topbar">
                <div class="topbar-greeting">Visago Studio <span style="color:var(--c-text-muted); font-weight:normal; font-family:'Montserrat', sans-serif;">/ Seguridad y Accesos</span></div>
                
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
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($current_admin_user) ?>&background=222222&color=F5EEDB&bold=true" alt="Avatar" class="user-avatar">
                            <div class="user-info-desktop" style="display: block;">
                                <div style="font-weight: bold;"><?= htmlspecialchars(ucfirst($current_admin_user)) ?></div>
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

            <div class="crud-container" style="margin-top: 0; flex-grow: 1;">
                <h2>Seguridad y Accesos</h2>
                
                <?php if (!empty($mensaje_exito)): ?>
                    <div class="msg-success" style="margin-bottom: 20px;">✓ <?= htmlspecialchars($mensaje_exito) ?></div>
                <?php endif; ?>
                <?php if (!empty($mensaje_error)): ?>
                    <div class="msg-box" style="margin-bottom: 20px;">⚠️ <?= htmlspecialchars($mensaje_error) ?></div>
                <?php endif; ?>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                    
                    <div class="search-container" style="margin: 0; padding: 0; background: transparent; border: none; flex-grow: 1;">
                        <form method="GET" action="usuarios.php" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                            <input type="text" name="search_user" placeholder="Buscar por Nombre de Usuario..." value="<?= htmlspecialchars($search_user) ?>" style="padding: 12px; border: 1px solid #333; border-radius: 8px; background: rgba(0,0,0,0.3); color: var(--c-text-cream); outline: none; width: 280px;">
                            
                            <button type="submit" style="padding: 10px 20px; background-color: rgba(201, 154, 65, 0.1); color: var(--c-accent-gold); border: 1px solid rgba(201, 154, 65, 0.3); border-radius: 8px; cursor: pointer; font-weight: bold; text-transform: uppercase; transition: 0.3s;">Buscar</button>
                            <a href="usuarios.php" style="color: var(--c-text-muted); text-decoration: none; font-weight: bold; font-size: 0.9em; transition: 0.3s;">Limpiar Filtros</a>
                        </form>
                    </div>

                    <a href="crear_usuario.php" class="btn-new">+ Nuevo Usuario</a>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Usuario (BD)</th>
                                <th>Rol Asignado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): 
                                    $user_name = $row['User'];
                                    $user_host = $row['Host']; 
                                    // Lógica para proteger la cuenta actual y del sistema
                                    $is_protected = in_array($user_name, $protected_users) || ($user_name == $current_admin_user && $user_host == 'localhost');
                                ?>
                                <tr>
                                    <td><strong style="color: var(--c-accent-gold); font-size: 1.1em;"><?= htmlspecialchars($user_name) ?></strong></td>
                                    <td style="color: var(--c-text-cream); font-weight: 600; text-transform: uppercase; font-size: 0.9em;"><?= htmlspecialchars($row['Rol']) ?></td>
                                    <td>
                                        <?php if ($is_protected): ?>
                                            <span style="color: var(--c-text-muted); font-style: italic; font-size: 0.9em;">(Cuenta Protegida)</span>
                                        <?php else: ?>
                                            <a href="borrar_usuario.php?user=<?= urlencode($user_name) ?>&host=<?= urlencode($user_host) ?>" 
                                               onclick="return confirm('¡ADVERTENCIA! ¿Estás seguro de revocar definitivamente el acceso a <?= htmlspecialchars($user_name) ?>?');" 
                                               style="color: #D9534F; display: flex; align-items: center; gap: 5px;">
                                               <svg class="svg-icon" viewBox="0 0 24 24" style="width: 16px; height: 16px; stroke: #D9534F;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                               Revocar Acceso
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" style="text-align: center; padding: 40px; color: var(--c-text-muted);">No se encontraron usuarios.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }
    </script>
</body>
</html>
<?php 
if (isset($stmt)) { $stmt->close(); } 
?>