<?php
session_start();
include("../conexion.php");

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') { header("Location: ../dashboard.php"); exit; }

$cveServicio = $_GET['clv'] ?? $_POST['clv'] ?? null; $currentUser = $_SESSION['usuario'];
$mensaje = ''; $can_edit = false; $nombre = ''; $descripcion = ''; $precio = ''; $current_version = 0;

if (!$cveServicio) { die("Error: CLV no especificada."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cveServicio = $_POST['clv']; $nombre = $_POST['nombre']; $descripcion = $_POST['descripcion']; $precio = floatval($_POST['precio']);

    while($conn->more_results() && $conn->next_result()){;}
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("CALL sp_check_lock_servicio(?, ?)");
        $stmt->bind_param("is", $cveServicio, $currentUser); $stmt->execute();
        $has_lock = $stmt->get_result()->num_rows > 0; $stmt->close();
        while($conn->more_results() && $conn->next_result()){;}

        if (!$has_lock) throw new Exception("El bloqueo expiró. Recargue.", 1337);

        $stmt = $conn->prepare("CALL sp_editar_servicio(?, ?, ?, ?)");
        $stmt->bind_param("isss", $cveServicio, $nombre, $descripcion, $precio);
        if ($stmt->execute()) {
            $conn->commit(); $mensaje = "Servicio actualizado correctamente."; $can_edit = false;
            header("Refresh: 2; URL=servicios.php");
        } else { throw new Exception("Error SP: " . $stmt->error); }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback(); while($conn->more_results() && $conn->next_result()){;}
        $stmt = $conn->prepare("CALL sp_liberar_bloqueo_servicio(?, ?)");
        $stmt->bind_param("is", $cveServicio, $currentUser); $stmt->execute(); $stmt->close();
        $mensaje = ($e->getCode() == 1337) ? $e->getMessage() : "ERROR: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' || !empty($mensaje)) {
    try {
        while($conn->more_results() && $conn->next_result()){;}
        $stmt = $conn->prepare("CALL sp_iniciar_edicion_servicio(?, ?)");
        $stmt->bind_param("is", $cveServicio, $currentUser);
        if($stmt->execute()){
            $lock = $stmt->get_result()->fetch_assoc();
            if($lock['status'] === 'OK') $can_edit = true;
            elseif($lock['status'] === 'LOCKED') { $mensaje = "BLOQUEADO por " . $lock['locked_by']; $can_edit = false; }
        }
        $stmt->close();
        
        while($conn->more_results() && $conn->next_result()){;}
        $stmt = $conn->prepare("CALL sp_obtener_servicio(?)");
        $stmt->bind_param("i", $cveServicio); $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc(); $stmt->close();

        if($data) { $nombre = $data['nombre']; $descripcion = $data['descripcion']; $precio = $data['precio']; $current_version = $data['version'] ?? 1; } 
        else { $mensaje = "Servicio no encontrado."; }
    } catch (Exception $e) { $mensaje = "Error: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Servicio</title><link rel="stylesheet" href="../css/style.css">
    <style>
        .login-container { max-width: 450px; padding: 40px; margin: 40px auto; background: rgba(30,30,30,0.85); backdrop-filter: blur(10px); border-top: 3px solid #B88E3E; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); color: #FDFEFE; }
        h2 { color: #B88E3E; text-transform: uppercase; font-family: 'Oswald', sans-serif; text-align: center; margin-bottom: 25px; }
        label { display: block; margin-top: 15px; color: #A99F92; font-weight: 600; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; }
        input, textarea { width: 100%; padding: 12px; margin-top: 5px; background: #111111; color: #FDFEFE; border: 1px solid #3A3A3A; border-radius: 2px; box-sizing: border-box; font-family: 'Montserrat', sans-serif; transition: 0.3s; }
        input:focus, textarea:focus { border-color: #B88E3E; outline: none; box-shadow: 0 0 8px rgba(184,142,62,0.3); }
        textarea { resize: vertical; min-height: 100px; }
        .btn-principal { width: 100%; padding: 14px; margin-top: 25px; background-color: #B88E3E; color: #111111; border: none; border-radius: 2px; cursor: pointer; font-family: 'Oswald', sans-serif; font-weight: bold; font-size: 1.1em; text-transform: uppercase; transition: 0.3s; }
        .btn-principal:hover { background-color: #A37D35; transform: translateY(-2px); }
        .btn-secundario { display: block; text-align: center; margin-top: 15px; color: #A99F92; text-decoration: none; font-weight: 600; transition: 0.3s; }
        .btn-secundario:hover { color: #FDFEFE; }
        .msg-box { padding: 12px; font-weight: bold; border-radius: 2px; margin-bottom: 20px; font-size: 0.9em; background: #9E2A2A; color: #FDFEFE; }
        .msg-success { background: #B88E3E; color: #111111; }
        fieldset { border: none; padding: 0; margin: 0; } fieldset:disabled { opacity: 0.5; pointer-events: none; }
        @media (max-width: 768px) { .login-container { margin: 20px 10px; padding: 25px 20px; } }
    </style>
</head>
<body class="fondo-crud">
    <div class="login-container">
        <h2>Editar Servicio #<?= htmlspecialchars($cveServicio) ?></h2>
        <?php if (!empty($mensaje)): ?>
            <p class="msg-box <?= strpos($mensaje, 'correctamente') !== false ? 'msg-success' : '' ?>"><?= $mensaje ?></p>
            <?php if (!$can_edit && strpos($mensaje, 'actualizado') === false): ?>
                <a href="servicios.php" class="btn-secundario">← Volver al catálogo</a>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="editar_servicio.php">
            <fieldset <?= !$can_edit ? 'disabled' : '' ?>>
                <input type="hidden" name="clv" value="<?= htmlspecialchars($cveServicio) ?>">
                <label>Nombre:</label><input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" required>
                <label>Descripción:</label><textarea name="descripcion"><?= htmlspecialchars($descripcion) ?></textarea>
                <label>Precio (M.N.):</label><input type="number" name="precio" step="0.01" min="0" value="<?= htmlspecialchars($precio) ?>" required>
                <button type="submit" class="btn-principal">Actualizar Servicio</button>
            </fieldset>
            <a href="servicios.php" class="btn-secundario">← Volver al catálogo</a>
        </form>
    </div>
</body>
</html>