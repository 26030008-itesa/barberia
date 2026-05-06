<?php
include("../conexion.php");
session_start();

if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['admin', 'vendedor'])) { 
    header("Location: ../dashboard.php"); 
    exit; 
}

$currentUser = $_SESSION['usuario'];
$rol = $_SESSION['rol'];

// Listar clientes
$result = $conn->query("CALL sp_listar_clientes()");

// Obtener Citas (para el numerito del menú)
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
    <title>Visago - Clientes</title>
    <link rel="stylesheet" href="../css/style.css"> 
</head>
<body class="fondo-clientes">
    <div class="app-container">
        
        <aside class="sidebar collapsed" id="sidebar">
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <svg class="svg-icon-toggle" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <div class="sidebar-logo">VISAGO<br><span style="color:var(--c-text-muted); font-size:0.5em; font-family:'Montserrat', sans-serif; letter-spacing: 4px;"></span></div>
            
            <div class="sidebar-search">
                <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" placeholder="Buscar módulo...">
            </div>
            
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
                <a href="clientes.php" class="nav-link active"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg><span class="nav-link-text">Clientes</span></a>
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

        <main class="main-content">
            
            <header class="topbar">
                <div class="topbar-greeting">Visago Studio <span style="color:var(--c-text-muted); font-weight:normal; font-family:'Montserrat', sans-serif;">/ Clientes</span></div>
                
                <div class="topbar-actions">
                    <div class="notification-wrapper">
                        <svg class="svg-icon notification-icon" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <span class="notification-badge">1</span>
                        <div class="notification-dropdown">
                            <a href="productos.php" class="noti-item">
                                <span class="noti-title">⚠️ Alerta de Inventario</span>
                                <span>Predicción: Shampoo agotado en 4 días.</span>
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

            <div class="crud-container" style="margin-top: 0; flex-grow: 1;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                    <h2>Directorio de Clientes</h2>
                    
                    <div class="sidebar-search" style="margin-bottom: 0; width: 300px; background: rgba(0,0,0,0.3); border: 1px solid #333;">
                        <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" id="inputBusqueda" placeholder="Buscar por nombre o CLV..." onkeyup="filtrarClientes()">
                    </div>

                    <a href="crear_cliente.php" class="btn-new">+ Nuevo Cliente</a>
                </div>
                
                <div class="table-responsive">
                    <table id="tablaClientes">
                        <thead>
                            <tr>
                                <th>CLV</th>
                                <th>Nombre Completo</th>
                                <th>Correo</th>
                                <th>Teléfono</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong style="color: var(--c-accent-gold);"><?= htmlspecialchars($row['cveCliente']) ?></strong></td>
                                <td style="color: var(--c-text-cream);"><?= htmlspecialchars($row['nombre1'] . ' ' . $row['apellidoP']) ?></td>
                                <td><?= htmlspecialchars($row['correo']) ?: '<em style="color:var(--c-text-muted);">No registrado</em>' ?></td>
                                <td><?= htmlspecialchars($row['telefono']) ?: '<em style="color:var(--c-text-muted);">No registrado</em>' ?></td>
                                <td>
                                    <a href="editar_cliente.php?clv=<?= $row['cveCliente'] ?>" class="action-link">Editar</a>
                                    <a href="borrar_cliente.php?clv=<?= $row['cveCliente'] ?>" class="action-delete" onclick="return confirm('¿Seguro que deseas eliminar este cliente?');">Borrar</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
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

        // Lógica para el buscador instantáneo de clientes
        function filtrarClientes() {
            const input = document.getElementById('inputBusqueda');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('tablaClientes');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const tdCLV = tr[i].getElementsByTagName('td')[0];
                const tdNombre = tr[i].getElementsByTagName('td')[1];
                
                if (tdCLV || tdNombre) {
                    const txtCLV = tdCLV.textContent || tdCLV.innerText;
                    const txtNombre = tdNombre.textContent || tdNombre.innerText;
                    
                    if (txtCLV.toLowerCase().indexOf(filter) > -1 || txtNombre.toLowerCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }
    </script>
</body>
</html>