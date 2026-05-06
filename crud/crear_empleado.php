<?php
include("../conexion.php");
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') { header("Location: ../dashboard.php"); exit; }

$nombre1 = ''; $nombre2 = ''; $apellidoP = ''; $apellidoM = ''; $mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre1 = $_POST['nombre1']; $nombre2 = $_POST['nombre2'];
    $apellidoP = $_POST['apellidoP']; $apellidoM = $_POST['apellidoM'];

    try {
        while($conn->more_results() && $conn->next_result()){;}
        // MAGIA PHP: Autocalcular ID
        $query_id = $conn->query("SELECT IFNULL(MAX(cveEmpleado), 0) + 1 AS nuevo_id FROM empleados");
        $id_calculado = $query_id->fetch_assoc()['nuevo_id'];

        $stmt = $conn->prepare("CALL sp_crear_empleado(?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $id_calculado, $nombre1, $nombre2, $apellidoP, $apellidoM);
        
        if ($stmt->execute()) { 
            $_SESSION['mensaje_status'] = "Empleado creado exitosamente (Clave: $id_calculado).";
            header("Location: empleados.php"); exit;
        }
    } catch (Exception $e) { $mensaje = "ERROR: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Empleado</title><link rel="stylesheet" href="../css/style.css"> 
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
        .row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media (max-width: 768px) { .row-grid { grid-template-columns: 1fr; gap: 0; } .login-container { margin: 20px 10px; padding: 25px 20px; } }
    </style>
</head>
<body class="fondo-crud">
    <div class="login-container">
        <h2>Nuevo Empleado</h2>
        <?php if (!empty($mensaje)): ?><p class="msg-box"><?= $mensaje ?></p><?php endif; ?>
        <form method="POST" action="crear_empleado.php">
            <div class="row-grid">
                <div><label>Primer Nombre*:</label><input type="text" name="nombre1" value="<?= htmlspecialchars($nombre1) ?>" required></div>
                <div><label>Segundo Nombre:</label><input type="text" name="nombre2" value="<?= htmlspecialchars($nombre2) ?>"></div>
            </div>
            <div class="row-grid">
                <div><label>Apellido Paterno*:</label><input type="text" name="apellidoP" value="<?= htmlspecialchars($apellidoP) ?>" required></div>
                <div><label>Apellido Materno:</label><input type="text" name="apellidoM" value="<?= htmlspecialchars($apellidoM) ?>"></div>
            </div>
            <button type="submit" class="btn-principal">Guardar Empleado</button>
            <a href="empleados.php" class="btn-secundario">← Volver a Empleados</a>
        </form>
    </div>
</body>
</html>