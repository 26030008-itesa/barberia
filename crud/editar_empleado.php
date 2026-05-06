<?php
session_start();
include("../conexion.php");

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') { header("Location: ../dashboard.php"); exit; }

$cveEmpleado = $_GET['clv'] ?? $_POST['clv'] ?? null; $currentUser = $_SESSION['usuario'];
$mensaje = ''; $can_edit = false;
$nombre1 = ''; $nombre2 = ''; $apellidoP = ''; $apellidoM = ''; $current_version = 0;

if (!$cveEmpleado) { die("Error: No se especificó la CLV."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cveEmpleado = $_POST['clv']; $version_enviada = intval($_POST['version']);
    $nombre1 = $_POST['nombre1']; $nombre2 = $_POST['nombre2']; $apellidoP = $_POST['apellidoP']; $apellidoM = $_POST['apellidoM'];

    try {
        $stmt = $conn->prepare("CALL sp_editar_empleado(?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $cveEmpleado, $nombre1, $nombre2, $apellidoP, $apellidoM);
        if ($stmt->execute()) {
            $mensaje = "Empleado actualizado correctamente."; $stmt->close();
            while($conn->more_results() && $conn->next_result()){;}
            header("Refresh: 2; URL=empleados.php"); $can_edit = false;
        } else { throw new Exception("Error al actualizar."); }
    } catch (Exception $e) { $mensaje = "ERROR: " . $e->getMessage(); }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' || !empty($mensaje)) {
    try {
        $stmt = $conn->prepare("CALL sp_iniciar_edicion_empleado(?, ?)");
        $stmt->bind_param("is", $cveEmpleado, $currentUser);
        if ($stmt->execute()) {
            $result = $stmt->get_result(); $row = $result->fetch_assoc();
            if ($row) {
                if ($row['status'] === 'OK') {
                    $can_edit = true; $nombre1 = $row['nombre1']; $nombre2 = $row['nombre2'];
                    $apellidoP = $row['apellidoP']; $apellidoM = $row['apellidoM']; $current_version = $row['version'] ?? 1;
                } elseif ($row['status'] === 'LOCKED') {
                    $mensaje = "BLOQUEADO: Editando por " . $row['locked_by']; $can_edit = false;
                }
            } else { $mensaje = "Empleado no encontrado."; }
        }
        $stmt->close(); while($conn->more_results() && $conn->next_result()){;}
    } catch (Exception $e) { $mensaje = "Error al iniciar edición: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Empleado</title><link rel="stylesheet" href="../css/style.css">
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
        .msg-success { background: #B88E3E; color: #111111; }
        .row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        fieldset { border: none; padding: 0; margin: 0; } fieldset:disabled { opacity: 0.5; pointer-events: none; }
        @media (max-width: 768px) { .row-grid { grid-template-columns: 1fr; gap: 0; } .login-container { margin: 20px 10px; padding: 25px 20px; } }
    </style>
</head>
<body class="fondo-crud">
    <div class="login-container">
        <h2>Editar Empleado #<?= htmlspecialchars($cveEmpleado) ?></h2>
        <?php if (!empty($mensaje)): ?>
            <p class="msg-box <?= strpos($mensaje, 'correctamente') !== false ? 'msg-success' : '' ?>"><?= $mensaje ?></p>
            <?php if (!$can_edit && strpos($mensaje, 'actualizado') === false): ?>
                <a href="empleados.php" class="btn-secundario">← Volver a la lista</a>
            <?php endif; ?>
        <?php endif; ?>
        <form method="POST" action="editar_empleado.php">
            <fieldset <?= !$can_edit ? 'disabled' : '' ?>>
                <input type="hidden" name="clv" value="<?= htmlspecialchars($cveEmpleado) ?>">
                <input type="hidden" name="version" value="<?= htmlspecialchars($current_version) ?>">
                <div class="row-grid">
                    <div><label>Primer Nombre*:</label><input type="text" name="nombre1" value="<?= htmlspecialchars($nombre1) ?>" required></div>
                    <div><label>Segundo Nombre:</label><input type="text" name="nombre2" value="<?= htmlspecialchars($nombre2) ?>"></div>
                </div>
                <div class="row-grid">
                    <div><label>Apellido Paterno*:</label><input type="text" name="apellidoP" value="<?= htmlspecialchars($apellidoP) ?>" required></div>
                    <div><label>Apellido Materno:</label><input type="text" name="apellidoM" value="<?= htmlspecialchars($apellidoM) ?>"></div>
                </div>
                <button type="submit" class="btn-principal">Actualizar Empleado</button>
            </fieldset>
            <a href="empleados.php" class="btn-secundario">← Volver a Empleados</a>
        </form>
    </div>
</body>
</html>