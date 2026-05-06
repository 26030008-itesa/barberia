<?php
include("../conexion.php");
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') { header("Location: ../dashboard.php"); exit; }

$cveCliente = ''; $cveServicio = ''; $cveEmpleado = ''; $fechaInicio = ''; $mensaje = '';

$clientes = []; $servicios = []; $empleados = [];
if ($res = $conn->query("CALL sp_listar_clientes_simple()")) { while ($row = $res->fetch_assoc()) $clientes[] = $row; $res->close(); $conn->next_result(); }
if ($res = $conn->query("CALL sp_listar_servicios_simple()")) { while ($row = $res->fetch_assoc()) $servicios[] = $row; $res->close(); $conn->next_result(); }
if ($res = $conn->query("CALL sp_listar_empleados_simple()")) { while ($row = $res->fetch_assoc()) $empleados[] = $row; $res->close(); $conn->next_result(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cveCliente = intval($_POST['cveCliente']); $cveServicio = intval($_POST['cveServicio']);
    $cveEmpleado = intval($_POST['cveEmpleado']); $fechaInicioCompleta = $_POST['fechaInicio'];

    if (empty($fechaInicioCompleta)) { $mensaje = "ERROR: Debes seleccionar fecha y hora."; } 
    else {
        try {
            $timestamp = strtotime($fechaInicioCompleta);
            $fechaSolo = date('Y-m-d', $timestamp); $horaSolo  = date('H:i:s', $timestamp);

            $query_id = $conn->query("SELECT IFNULL(MAX(cveReservacion), 0) + 1 AS nuevo_id FROM reservaciones");
            $id_calculado = $query_id->fetch_assoc()['nuevo_id'];

            $stmt = $conn->prepare("CALL sp_agendar_cita_manual(?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiiss", $id_calculado, $cveCliente, $cveServicio, $cveEmpleado, $fechaSolo, $horaSolo);

            if ($stmt->execute()) {
                $_SESSION['mensaje_status'] = "Reservación creada exitosamente (Clave: $id_calculado).";
                header("Location: reservaciones.php"); exit;
            }
        } catch (Exception $e) { $mensaje = "ERROR BD: " . $e->getMessage(); }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Reservación</title><link rel="stylesheet" href="../css/style.css"> 
    <style>
        .login-container { max-width: 500px; padding: 40px; margin: 40px auto; background: rgba(30,30,30,0.85); backdrop-filter: blur(10px); border-top: 3px solid #B88E3E; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); color: #FDFEFE; }
        h2 { color: #B88E3E; text-transform: uppercase; font-family: 'Oswald', sans-serif; text-align: center; margin-bottom: 25px; }
        label { display: block; margin-top: 15px; color: #A99F92; font-weight: 600; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; }
        input, select { width: 100%; padding: 12px; margin-top: 5px; background: #111111; color: #FDFEFE; border: 1px solid #3A3A3A; border-radius: 2px; box-sizing: border-box; font-family: 'Montserrat', sans-serif; transition: 0.3s; }
        input:focus, select:focus { border-color: #B88E3E; outline: none; box-shadow: 0 0 8px rgba(184,142,62,0.3); }
        .btn-principal { width: 100%; padding: 14px; margin-top: 25px; background-color: #B88E3E; color: #111111; border: none; border-radius: 2px; cursor: pointer; font-family: 'Oswald', sans-serif; font-weight: bold; font-size: 1.1em; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; }
        .btn-principal:hover { background-color: #A37D35; transform: translateY(-2px); }
        .btn-secundario { display: block; text-align: center; margin-top: 15px; color: #A99F92; text-decoration: none; font-weight: 600; transition: 0.3s; }
        .btn-secundario:hover { color: #FDFEFE; }
        .msg-box { padding: 12px; font-weight: bold; border-radius: 2px; margin-bottom: 20px; font-size: 0.9em; background: #9E2A2A; color: #FDFEFE; }
        @media (max-width: 768px) { .login-container { margin: 20px 10px; padding: 25px 20px; } }
    </style>
</head>
<body class="fondo-crud">
    <div class="login-container">
        <h2>Agendar Cita</h2>
        <?php if (!empty($mensaje)): ?><p class="msg-box"><?= $mensaje ?></p><?php endif; ?>
        <form method="POST" action="crear_reservacion.php">
            <label>Cliente*:</label>
            <select name="cveCliente" required>
                <option value="">-- Seleccionar --</option>
                <?php foreach ($clientes as $row): ?><option value="<?= $row['cveCliente'] ?>" <?= ($cveCliente == $row['cveCliente']) ? 'selected' : '' ?>><?= htmlspecialchars($row['nombre1'] . ' ' . $row['apellidoP']) ?></option><?php endforeach; ?>
            </select>
            <label>Servicio*:</label>
            <select name="cveServicio" required>
                <option value="">-- Seleccionar --</option>
                <?php foreach ($servicios as $row): ?><option value="<?= $row['cveServicio'] ?>" <?= ($cveServicio == $row['cveServicio']) ? 'selected' : '' ?>><?= htmlspecialchars($row['nombre']) . ' ($' . $row['precio'] . ')' ?></option><?php endforeach; ?>
            </select>
            <label>Barbero*:</label>
            <select name="cveEmpleado" required>
                <option value="">-- Seleccionar --</option>
                <?php foreach ($empleados as $row): ?><option value="<?= $row['cveEmpleado'] ?>" <?= ($cveEmpleado == $row['cveEmpleado']) ? 'selected' : '' ?>><?= htmlspecialchars($row['nombre1'] . ' ' . $row['apellidoP']) ?></option><?php endforeach; ?>
            </select>
            <label>Fecha y Hora*:</label>
            <input type="datetime-local" name="fechaInicio" value="<?= htmlspecialchars($fechaInicio) ?>" required>
            <button type="submit" class="btn-principal">Crear Reservación</button>
            <a href="reservaciones.php" class="btn-secundario">← Volver a la agenda</a>
        </form>
    </div>
</body>
</html>