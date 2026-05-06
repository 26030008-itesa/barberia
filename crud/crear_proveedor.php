<?php
include("../conexion.php");
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') { header("Location: ../dashboard.php"); exit; }

$nombre = ''; $mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    try {
        $query_id = $conn->query("SELECT IFNULL(MAX(cveProveedor), 0) + 1 AS nuevo_id FROM proveedores");
        $id_calculado = $query_id->fetch_assoc()['nuevo_id'];

        $stmt = $conn->prepare("CALL sp_crear_proveedor(?, ?)");
        $stmt->bind_param("is", $id_calculado, $nombre);
        
        if ($stmt->execute()) { 
            $_SESSION['mensaje_status'] = "Proveedor creado exitosamente (Clave: $id_calculado).";
            header("Location: proveedores.php"); exit;
        }
    } catch (Exception $e) { $mensaje = "ERROR: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Proveedor</title><link rel="stylesheet" href="../css/style.css"> 
    <style>
        .login-container { max-width: 450px; padding: 40px; margin: 60px auto; background: rgba(30,30,30,0.85); backdrop-filter: blur(10px); border-top: 3px solid #B88E3E; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); color: #FDFEFE; }
        h2 { color: #B88E3E; text-transform: uppercase; font-family: 'Oswald', sans-serif; text-align: center; margin-bottom: 25px; }
        label { display: block; margin-top: 15px; color: #A99F92; font-weight: 600; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; }
        input { width: 100%; padding: 12px; margin-top: 5px; background: #111111; color: #FDFEFE; border: 1px solid #3A3A3A; border-radius: 2px; box-sizing: border-box; font-family: 'Montserrat', sans-serif; transition: 0.3s; }
        input:focus { border-color: #B88E3E; outline: none; box-shadow: 0 0 8px rgba(184,142,62,0.3); }
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
        <h2>Agregar Proveedor</h2>
        <?php if (!empty($mensaje)): ?><p class="msg-box"><?= $mensaje ?></p><?php endif; ?>
        <form method="POST" action="crear_proveedor.php">
            <label>Nombre de la Empresa*:</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" required>
            <button type="submit" class="btn-principal">Guardar Proveedor</button>
            <a href="proveedores.php" class="btn-secundario">← Volver a Proveedores</a>
        </form>
    </div>
</body>
</html>