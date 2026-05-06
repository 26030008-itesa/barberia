<?php
session_start();

if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) { header("Location: ../index.html"); exit; }
include("../conexion.php"); 
if (!isset($conn)) { die("Error fatal de conexión."); }
if ($_SESSION['rol'] !== 'admin') { header("Location: ../dashboard.php"); exit; }

$cveProducto = $_GET['clv'] ?? $_POST['clv'] ?? null; $currentUser = $_SESSION['usuario'];
$mensaje = ''; $can_edit = false;
$nombre = ''; $descripcion = ''; $precio = ''; $stock = ''; $cveProveedor = ''; $current_version = 0;

if (!$cveProducto) { die("Error: CLV no especificada."); }

$proveedores = [];
while($conn->more_results() && $conn->next_result()){;}
if ($res = $conn->query("CALL sp_listar_proveedores()")) {
    while ($row = $res->fetch_assoc()) $proveedores[] = $row; $res->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cveProducto = $_POST['clv']; $version_enviada = intval($_POST['version']);
    $nombre = $_POST['nombre']; $descripcion = $_POST['descripcion']; $precio = floatval($_POST['precio']);
    $stock = intval($_POST['stock']); $cveProveedor = intval($_POST['cveProveedor']);

    while($conn->more_results() && $conn->next_result()){;}
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("CALL sp_check_lock_producto(?, ?, ?)");
        $stmt->bind_param("isi", $cveProducto, $currentUser, $version_enviada); $stmt->execute();
        $res = $stmt->get_result(); $has_lock = $res->num_rows > 0; $stmt->close();
        while($conn->more_results() && $conn->next_result()){;}

        if (!$has_lock) { throw new Exception("El bloqueo expiró o fue modificado. Recargue.", 1337); }

        $stmt = $conn->prepare("CALL sp_editar_producto(?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdii", $cveProducto, $nombre, $descripcion, $precio, $stock, $cveProveedor);
        
        if ($stmt->execute()) {
            $conn->commit(); $mensaje = "Producto actualizado correctamente."; $can_edit = false;
            header("Refresh: 2; URL=productos.php");
        } else { throw new Exception("Error: " . $stmt->error); }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback(); while($conn->more_results() && $conn->next_result()){;}
        $stmt = $conn->prepare("CALL sp_liberar_bloqueo_producto(?, ?)");
        $stmt->bind_param("is", $cveProducto, $currentUser); $stmt->execute(); $stmt->close();
        $mensaje = $e->getCode() == 1337 ? $e->getMessage() : "ERROR: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' || !empty($mensaje)) {
    try {
        while($conn->more_results() && $conn->next_result()){;}
        $stmt = $conn->prepare("CALL sp_iniciar_edicion_producto(?, ?)");
        $stmt->bind_param("is", $cveProducto, $currentUser);
        if ($stmt->execute()) {
            $res = $stmt->get_result(); $data = $res->fetch_assoc();
            if ($data) {
                if ($data['status'] === 'OK') {
                    $can_edit = true; $nombre = $data['nombre']; $descripcion = $data['descripcion'];
                    $precio = $data['precio']; $stock = $data['stock']; $cveProveedor = $data['cveProveedor']; $current_version = $data['version'];
                } elseif ($data['status'] === 'LOCKED') {
                    $mensaje = "BLOQUEADO: Editando por " . $data['locked_by']; $can_edit = false;
                }
            } else { $mensaje = "Producto no encontrado."; }
        }
        $stmt->close(); while($conn->more_results() && $conn->next_result()){;} 
    } catch (Exception $e) { $mensaje = "Error al cargar: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto</title><link rel="stylesheet" href="../css/style.css">
    <style>
        .login-container { max-width: 500px; padding: 40px; margin: 40px auto; background: rgba(30,30,30,0.85); backdrop-filter: blur(10px); border-top: 3px solid #B88E3E; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); color: #FDFEFE; }
        h2 { color: #B88E3E; text-transform: uppercase; font-family: 'Oswald', sans-serif; text-align: center; margin-bottom: 25px; }
        label { display: block; margin-top: 15px; color: #A99F92; font-weight: 600; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; }
        input, select, textarea { width: 100%; padding: 12px; margin-top: 5px; background: #111111; color: #FDFEFE; border: 1px solid #3A3A3A; border-radius: 2px; box-sizing: border-box; font-family: 'Montserrat', sans-serif; transition: 0.3s; }
        input:focus, select:focus, textarea:focus { border-color: #B88E3E; outline: none; box-shadow: 0 0 8px rgba(184,142,62,0.3); }
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
        <h2>Editar Producto #<?= htmlspecialchars($cveProducto) ?></h2>
        <?php if (!empty($mensaje)): ?>
            <p class="msg-box <?= strpos($mensaje, 'correctamente') !== false ? 'msg-success' : '' ?>"><?= $mensaje ?></p>
            <?php if (!$can_edit && strpos($mensaje, 'actualizado') === false): ?>
                <a href="productos.php" class="btn-secundario">← Volver a la lista</a>
            <?php endif; ?>
        <?php endif; ?>
        <form method="POST" action="editar_producto.php">
            <fieldset <?= !$can_edit ? 'disabled' : '' ?>>
                <input type="hidden" name="clv" value="<?= htmlspecialchars($cveProducto) ?>">
                <input type="hidden" name="version" value="<?= htmlspecialchars($current_version) ?>">
                <label>Nombre*:</label><input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" required>
                <label>Descripción:</label><textarea name="descripcion"><?= htmlspecialchars($descripcion) ?></textarea>
                <div class="row-grid">
                    <div><label>Precio (M.N.)*:</label><input type="number" name="precio" step="0.01" min="0" value="<?= htmlspecialchars($precio) ?>" required></div>
                    <div><label>Stock*:</label><input type="number" name="stock" value="<?= htmlspecialchars($stock) ?>" required></div>
                </div>
                <label>Proveedor*:</label>
                <select name="cveProveedor" required>
                    <?php foreach ($proveedores as $p): ?>
                        <option value="<?= $p['cveProveedor'] ?>" <?= ($p['cveProveedor'] == $cveProveedor) ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-principal">Actualizar Producto</button>
            </fieldset>
            <a href="productos.php" class="btn-secundario">← Volver a Productos</a>
        </form>
    </div>
</body>
</html>