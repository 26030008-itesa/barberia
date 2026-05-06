<?php
include("../conexion.php");
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$nombre1 = ''; $nombre2 = ''; $apellidoP = ''; $apellidoM = ''; 
$correo = ''; $telefono = ''; $mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre1 = $_POST['nombre1'];
    $nombre2 = $_POST['nombre2'];
    $apellidoP = $_POST['apellidoP'];
    $apellidoM = $_POST['apellidoM'];
    $correo = $_POST['correo'];
    $telefono = $_POST['telefono'];
    
    try {
        while($conn->more_results() && $conn->next_result()){;}

        // MAGIA PHP: Calculamos el ID automáticamente
        $query_id = $conn->query("SELECT IFNULL(MAX(cveCliente), 0) + 1 AS nuevo_id FROM clientes");
        $row = $query_id->fetch_assoc();
        $id_calculado = $row['nuevo_id'];

        $stmt = $conn->prepare("CALL sp_registrar_cliente_completo(?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $id_calculado, $nombre1, $nombre2, $apellidoP, $apellidoM, $correo, $telefono);

        if ($stmt->execute()) {
            $_SESSION['mensaje_status'] = "Cliente agregado exitosamente (Clave: $id_calculado).";
            header("Location: clientes.php");
            exit;
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) { 
            $error_msg = $e->getMessage();
            if (stripos($error_msg, 'correo') !== false) {
                $mensaje = "ERROR: El correo '$correo' ya está en uso.";
            } else {
                $mensaje = "ERROR: Dato duplicado detectado.";
            }
        } else {
            $mensaje = "ERROR DE BD: " . $e->getMessage();
        }
    } catch (Exception $e) {
        $mensaje = "ERROR: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Cliente - Barbería Premium</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <style>
        .login-container { max-width: 500px; padding: 40px; margin-top: 50px; }
        .login-container label { margin-top: 15px; }
        
        /* Sistema de 2 columnas para nombres y apellidos en pantallas grandes */
        .row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        
        .msg-error {
            color: #FDFEFE; font-weight: bold; background: #9E2A2A; 
            padding: 12px; border-radius: 2px; margin-bottom: 20px; font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .row-grid { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
</head>
<body class="fondo-crud">
    <div class="login-container">
        <h2>Nuevo Cliente</h2>
        
        <?php if (!empty($mensaje)): ?>
            <p class="msg-error"><?= $mensaje ?></p>
        <?php endif; ?>

        <form method="POST" action="crear_cliente.php">
            <div class="row-grid">
                <div>
                    <label>Primer Nombre*:</label>
                    <input type="text" name="nombre1" value="<?= htmlspecialchars($nombre1) ?>" required placeholder="Ej. Juan">
                </div>
                <div>
                    <label>Segundo Nombre:</label>
                    <input type="text" name="nombre2" value="<?= htmlspecialchars($nombre2) ?>" placeholder="Opcional">
                </div>
            </div>
            
            <div class="row-grid">
                <div>
                    <label>Apellido Paterno*:</label>
                    <input type="text" name="apellidoP" value="<?= htmlspecialchars($apellidoP) ?>" required placeholder="Ej. Pérez">
                </div>
                <div>
                    <label>Apellido Materno:</label>
                    <input type="text" name="apellidoM" value="<?= htmlspecialchars($apellidoM) ?>" placeholder="Opcional">
                </div>
            </div>

            <label>Correo Electrónico:</label>
            <input type="email" name="correo" value="<?= htmlspecialchars($correo) ?>" placeholder="ejemplo@correo.com">

            <label>Teléfono:</label>
            <input type="text" name="telefono" value="<?= htmlspecialchars($telefono) ?>" placeholder="10 dígitos">

            <button type="submit">Guardar Cliente</button>
            <a href="clientes.php" style="display:block; margin-top:20px; color:#A99F92; text-decoration:none; font-weight:600;">← Volver al directorio</a>
        </form>
    </div>
</body>
</html>