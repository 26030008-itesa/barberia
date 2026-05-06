<?php
session_start();
include("../conexion.php");

if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['admin', 'empleado'])) { header("Location: ../dashboard.php"); exit; }

$cveReservacion = $_GET['clv'] ?? $_POST['clv'] ?? null; $currentUser = $_SESSION['usuario'];
$mensaje = ''; $can_edit = false;
$cveCliente = ''; $cveServicio = ''; $cveEmpleado = ''; $fecha_reserva = ''; $hora_reserva = ''; $estado = '';

if (!$cveReservacion) { die("Error: CLV no especificada."); }

function cargar_lista($conn, $sp) {
    $lista = []; while($conn->more_results() && $conn->next_result()){;}
    if ($res = $conn->query("CALL $sp")) { while ($row = $res->fetch_assoc()) $lista[] = $row; $res->close(); }
    return $lista;
}
$clientes = cargar_lista($conn, "sp_listar_clientes_simple()");
$servicios = cargar_lista($conn, "sp_listar_servicios_simple()");
$empleados = cargar_lista($conn, "sp_listar_empleados_simple()");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cveReservacion = $_POST['clv']; $cveCliente = $_POST['cveCliente']; $cveServicio = $_POST['cveServicio'];
    $cveEmpleado = $_POST['cveEmpleado']; $fecha_reserva = $_POST['fecha_reserva']; $hora_reserva = $_POST['hora_reserva']; $estado = $_POST['estado'];

    while($conn->more_results() && $conn->next_result()){;}
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("CALL sp_check_lock_reservacion(?, ?)");
        $stmt->bind_param("is", $cveReservacion, $currentUser); $stmt->execute();
        $has_lock = $stmt->get_result()->num_rows > 0; $stmt->close();
        while($conn->more_results() && $conn->next_result()){;}

        if (!$has_lock) throw new Exception("El bloqueo expiró. Recargue.", 1337);

        $stmt = $conn->prepare("CALL sp_editar_reservacion(?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiisss", $cveReservacion, $cveCliente, $cveServicio, $cveEmpleado, $fecha_reserva, $hora_reserva, $estado);
        if ($stmt->execute()) {
            $conn->commit(); $mensaje = "Cita actualizada correctamente."; $can_edit = false;
            header("Refresh: 2; URL=reservaciones.php");
        } else { throw new Exception("Error SP: " . $stmt->error); }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback(); while($conn->more_results() && $conn->next_result()){;}
        $stmt = $conn->prepare("CALL sp_liberar_bloqueo_reservacion(?, ?)");
        $stmt->bind_param("is", $cveReservacion, $currentUser); $stmt->execute(); $stmt->close();
        $mensaje = ($e->getCode() == 1337) ? $e->getMessage() : "ERROR: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' || !empty($mensaje)) {
    try {
        while($conn->more_results() && $conn->next_result()){;}
        $stmt = $conn->prepare("CALL sp_iniciar_edicion_reservacion(?, ?)");
        $stmt->bind_param("is", $cveReservacion, $currentUser);
        if($stmt->execute()){
            $lock = $stmt->get_result()->fetch_assoc();
            if($lock['status'] === 'OK') $can_edit = true;
            elseif($lock['status'] === 'LOCKED') { $mensaje = "BLOQUEADO por " . $lock['locked_by']; $can_edit = false; }
        }
        $stmt->close();

        while($conn->more_results() && $conn->next_result()){;}
        $stmt = $conn->prepare("CALL sp_obtener_reservacion(?)");
        $stmt->bind_param("i", $cveReservacion); $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc(); $stmt->close();

        if($data) {
            $cveCliente = $data['cveCliente']; $cveServicio = $data['cveServicio']; $cveEmpleado = $data['cveEmpleado'];
            $fecha_reserva = $data['fecha_reserva']; $hora_reserva = substr($data['hora_reserva'], 0, 5); $estado = $data['estado'];
        } else { $mensaje = "Cita no encontrada."; }
    } catch (Exception $e) { $mensaje = "Error: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cita</title><link rel="stylesheet" href="../css/style.css">
    <style>
        .login-container { max-width: 500px; padding: 40px; margin: 40px auto; background: rgba(30,30,30,0.85); backdrop-filter: blur(10px); border-top: 3px solid #B88E3E; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); color: #FDFEFE; }
        h2 { color: #B88E3E; text-transform: uppercase; font-family: 'Oswald', sans-serif; text-align: center; margin-bottom: 25px; }
        label { display: block; margin-top: 15px; color: #A99F92; font-weight: 600; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; }
        input, select { width: 100%; padding: 12px; margin-top: 5px; background: #111111; color: #FDFEFE; border: 1px solid #3A3A3A; border-radius: 2px; box-sizing: border-box; font-family: 'Montserrat', sans-serif; transition: 0.3s; color-scheme: dark; }
        input:focus, select:focus { border-color: #B88E3E; outline: none; box-shadow: 0 0 8px rgba(184,142,62,0.3); }
        .btn-principal { width: 100%; padding: 14px; margin-top: 25px; background-color: #B88E3E; color: #111111; border: none; border-radius: 2px; cursor: pointer; font-family: 'Oswald', sans-serif; font-weight: bold; font-size: 1.1em; text-transform: uppercase; transition: 0.3s; }
        .btn-principal:hover { background-color: #A37D35; transform: translateY(-2px); }
        .btn-secundario { display: block; text-align: center; margin-top: 15px; color: #A99F92; text-decoration: none; font-weight: 600; transition: 0.3s; }
        .btn-secundario:hover { color: #FDFEFE; }
        .msg-box { padding: 12px; font-weight: bold; border-radius: 2px; margin-bottom: 20px; font-size: 0.9em; background: #9E2A2A; color: #FDFEFE; }
        .msg-success { background: #B88E3E; color: #111111; }
        fieldset { border: none; padding: 0; margin: 0; } fieldset:disabled { opacity: 0.5; pointer-events: none; }
        .row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media (max-width: 768px) { .row-grid { grid-template-columns: 1fr; gap: 0; } .login-container { margin: 20px 10px; padding: 25px 20px; } }
    </style>
</head>
<body class="fondo-crud">
    <div class="login-container">
        <h2>Editar Cita #<?= htmlspecialchars($cveReservacion) ?></h2>
        <?php if (!empty($mensaje)): ?>
            <p class="msg-box <?= strpos($mensaje, 'correctamente') !== false ? 'msg-success' : '' ?>"><?= $mensaje ?></p>
            <?php if (!$can_edit && strpos($mensaje, 'actualizada') === false): ?>
                <a href="reservaciones.php" class="btn-secundario">← Volver a la agenda</a>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="editar_reservacion.php">
            <fieldset <?= !$can_edit ? 'disabled' : '' ?>>
                <input type="hidden" name="clv" value="<?= htmlspecialchars($cveReservacion) ?>">
                
                <label>Cliente*:</label>
                <select name="cveCliente" required>
                    <?php foreach ($clientes as $c): ?><option value="<?= $c['cveCliente'] ?>" <?= ($c['cveCliente'] == $cveCliente) ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre1'] . ' ' . $c['apellidoP']) ?></option><?php endforeach; ?>
                </select>

                <label>Servicio*:</label>
                <select name="cveServicio" required>
                    <?php foreach ($servicios as $s): ?><option value="<?= $s['cveServicio'] ?>" <?= ($s['cveServicio'] == $cveServicio) ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option><?php endforeach; ?>
                </select>

                <label>Barbero*:</label>
                <select name="cveEmpleado" required>
                    <?php foreach ($empleados as $e): ?><option value="<?= $e['cveEmpleado'] ?>" <?= ($e['cveEmpleado'] == $cveEmpleado) ? 'selected' : '' ?>><?= htmlspecialchars($e['nombre1'] . ' ' . $e['apellidoP']) ?></option><?php endforeach; ?>
                </select>
                
                <div class="row-grid">
                    <div><label>Fecha:</label><input type="date" name="fecha_reserva" value="<?= htmlspecialchars($fecha_reserva) ?>" required></div>
                    <div><label>Hora:</label><input type="time" name="hora_reserva" value="<?= htmlspecialchars($hora_reserva) ?>" required></div>
                </div>

                <label>Estado:</label>
                <select name="estado">
                    <option value="pendiente" <?= ($estado == 'pendiente') ? 'selected' : '' ?>>Pendiente</option>
                    <option value="confirmada" <?= ($estado == 'confirmada') ? 'selected' : '' ?>>Confirmada</option>
                    <option value="cancelada" <?= ($estado == 'cancelada') ? 'selected' : '' ?>>Cancelada</option>
                    <option value="realizada" <?= ($estado == 'realizada') ? 'selected' : '' ?>>Realizada</option>
                </select>

                <button type="submit" class="btn-principal">Actualizar Cita</button>
            </fieldset>
            <a href="reservaciones.php" class="btn-secundario">← Volver a la agenda</a>
        </form>
    </div>
</body>
</html>