<?php
session_start();

// --- BLOQUE DE VALIDACIÓN DE SESIÓN Y ROL ---
if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) { header("Location: ../index.html"); exit; }
include("../conexion.php"); 
if (!isset($conn)) { die("Error fatal de conexión."); }
if ($_SESSION['rol'] !== 'admin') { header("Location: ../dashboard.php"); exit; }

$cveCliente = $_GET['clv'] ?? $_POST['clv'] ?? null; $currentUser = $_SESSION['usuario'];
$mensaje = ''; $can_edit = false;

// Variables
$nombre1 = ''; $nombre2 = ''; $apellidoP = ''; $apellidoM = ''; $correo = ''; $telefono = ''; $current_version = 0;

if (!$cveCliente) { die("Error: CLV no especificada."); }

// --- A) PROCESAR (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cveCliente = $_POST['clv']; $version_enviada = intval($_POST['version']);
    $nombre1 = $_POST['nombre1']; $nombre2 = $_POST['nombre2'];
    $apellidoP = $_POST['apellidoP']; $apellidoM = $_POST['apellidoM'];
    $correo = $_POST['correo']; $telefono = $_POST['telefono'];

    while($conn->more_results() && $conn->next_result()){;}
    $conn->begin_transaction();
    try {
        // 1. Verificar bloqueo exclusivo en la BD
        $stmt = $conn->prepare("CALL sp_check_lock_cliente(?, ?, ?)");
        $stmt->bind_param("isi", $cveCliente, $currentUser, $version_enviada); $stmt->execute();
        $res = $stmt->get_result(); $has_lock = $res->num_rows > 0; $stmt->close();
        while($conn->more_results() && $conn->next_result()){;}

        if (!$has_lock) { throw new Exception("El bloqueo expiró o fue modificado. Recargue.", 1337); }

        // 2. Ejecutar la actualización
        $stmt = $conn->prepare("CALL sp_editar_cliente(?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $cveCliente, $nombre1, $nombre2, $apellidoP, $apellidoM, $correo, $telefono);
        
        if ($stmt->execute()) {
            $conn->commit(); $mensaje = "Cliente actualizado correctamente."; $can_edit = false;
            header("Refresh: 2; URL=clientes.php");
        } else { throw new Exception("Error: " . $stmt->error); }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback(); while($conn->more_results() && $conn->next_result()){;}
        // Liberar el bloqueo si falla
        $stmt = $conn->prepare("CALL sp_liberar_bloqueo_cliente(?, ?)");
        $stmt->bind_param("is", $cveCliente, $currentUser); $stmt->execute(); $stmt->close();
        $mensaje = $e->getCode() == 1337 ? $e->getMessage() : "ERROR: " . $e->getMessage();
    }
}

// --- B) CARGAR Y BLOQUEAR (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' || !empty($mensaje)) {
    try {
        while($conn->more_results() && $conn->next_result()){;}
        // El SP devuelve el estatus del bloqueo Y los datos del cliente
        $stmt = $conn->prepare("CALL sp_iniciar_edicion_cliente(?, ?)");
        $stmt->bind_param("is", $cveCliente, $currentUser);
        if ($stmt->execute()) {
            $res = $stmt->get_result(); $data = $res->fetch_assoc();
            if ($data) {
                if ($data['status'] === 'OK') {
                    $can_edit = true; 
                    $nombre1 = $data['nombre1']; $nombre2 = $data['nombre2'];
                    $apellidoP = $data['apellidoP']; $apellidoM = $data['apellidoM'];
                    $correo = $data['correo'] ?? ''; $telefono = $data['telefono'] ?? ''; 
                    $current_version = $data['version'] ?? 1;
                } elseif ($data['status'] === 'LOCKED') {
                    $mensaje = "BLOQUEADO: Editando por " . $data['locked_by']; $can_edit = false;
                }
            } else { $mensaje = "Cliente no encontrado."; }
        }
        $stmt->close(); while($conn->more_results() && $conn->next_result()){;} 
    } catch (Exception $e) { $mensaje = "Error al cargar: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente</title><link rel="stylesheet" href="../css/style.css">
    <style>
        .login-container { max-width: 500px; padding: 40px; margin: 40px auto; background: rgba(30,30,30,0.85); backdrop-filter: blur(10px); border-top: 3px solid #B88E3E; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); color: #FDFEFE; }
        h2 { color: #B88E3E; text-transform: uppercase; font-family: 'Oswald', sans-serif; text-align: center; margin-bottom: 25px; }
        label { display: block; margin-top: 15px; color: #A99F92; font-weight: 600; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; }
        input { width: 100%; padding: 12px; margin-top: 5px; background: #111111; color: #FDFEFE; border: 1px solid #3A3A3A; border-radius: 2px; box-sizing: border-box; font-family: 'Montserrat', sans-serif; transition: 0.3s; }
        input:focus { border-color: #B88E3E; outline: none; box-shadow: 0 0 8px rgba(184,142,62,0.3); }
        .btn-principal { width: 100%; padding: 14px; margin-top: 25px; background-color: #B88E3E; color: #111111; border: none; border-radius: 2px; cursor: pointer; font-family: 'Oswald', sans-serif; font-weight: bold; font-size: 1.1em; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; }
        .btn-principal:hover { background-color: #A37D35; transform: translateY(-2px); }
        .btn-secundario { display: block; text-align: center; margin-top: 15px; color: #A99F92; text-decoration: none; font-weight: 600; transition: 0.3s; }
        .btn-secundario:hover { color: #FDFEFE; }
        .msg-box { padding: 12px; font-weight: bold; border-radius: 2px; margin-bottom: 20px; font-size: 0.9em; background: #9E2A2A; color: #FDFEFE; text-align: center;}
        .msg-success { background: #B88E3E; color: #111111; }
        fieldset { border: none; padding: 0; margin: 0; } fieldset:disabled { opacity: 0.5; pointer-events: none; }
        .row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media (max-width: 768px) { .row-grid { grid-template-columns: 1fr; gap: 0; } .login-container { margin: 20px 10px; padding: 25px 20px; } }
    </style>
</head>
<body class="fondo-crud">
    <div class="login-container">
        <h2>Editar Cliente #<?= htmlspecialchars($cveCliente) ?></h2>
        <?php if (!empty($mensaje)): ?>
            <p class="msg-box <?= strpos($mensaje, 'correctamente') !== false ? 'msg-success' : '' ?>"><?= $mensaje ?></p>
            <?php if (!$can_edit && strpos($mensaje, 'actualizado') === false): ?>
                <a href="clientes.php" class="btn-secundario">← Volver a la lista</a>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="editar_cliente.php">
            <fieldset <?= !$can_edit ? 'disabled' : '' ?>>
                <input type="hidden" name="clv" value="<?= htmlspecialchars($cveCliente) ?>">
                <input type="hidden" name="version" value="<?= htmlspecialchars($current_version) ?>">
                
                <div class="row-grid">
                    <div><label>Primer Nombre*:</label><input type="text" name="nombre1" value="<?= htmlspecialchars($nombre1) ?>" required></div>
                    <div><label>Segundo Nombre:</label><input type="text" name="nombre2" value="<?= htmlspecialchars($nombre2) ?>"></div>
                </div>
                <div class="row-grid">
                    <div><label>Apellido Paterno*:</label><input type="text" name="apellidoP" value="<?= htmlspecialchars($apellidoP) ?>" required></div>
                    <div><label>Apellido Materno:</label><input type="text" name="apellidoM" value="<?= htmlspecialchars($apellidoM) ?>"></div>
                </div>
                <label>Correo Electrónico:</label><input type="email" name="correo" value="<?= htmlspecialchars($correo) ?>">
                <label>Teléfono:</label><input type="text" name="telefono" value="<?= htmlspecialchars($telefono) ?>">
                
                <button type="submit" class="btn-principal">Actualizar Cliente</button>
            </fieldset>
            <a href="clientes.php" class="btn-secundario">← Volver al Directorio</a>
        </form>
    </div>
</body>
</html>