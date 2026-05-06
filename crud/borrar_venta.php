<?php
session_start();
include("../conexion.php");

// Validaciones de sesión estándar (Admin o Vendedor)
if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['admin', 'vendedor'])) {
    header("Location: ../dashboard.php");
    exit;
}

$cveVenta = $_GET['clv'] ?? null;
$currentUser = $_SESSION['usuario'];
$mensaje = '';

if ($cveVenta) {
    try {
        // CERO SQL PURO: Llamada al SP Seguro
        $stmt = $conn->prepare("CALL sp_borrar_venta_seguro(?, ?)");
        $stmt->bind_param("is", $cveVenta, $currentUser);
        
        if ($stmt->execute()) {
            $mensaje = "Venta anulada correctamente.";
        } else {
            throw new Exception("Error de ejecución.");
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $mensaje = "ERROR DE BD: " . $e->getMessage();
    } catch (Exception $e) {
        $mensaje = "ERROR: " . $e->getMessage();
    }
} else {
    $mensaje = "ERROR: CLV no proporcionada.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Registro - Barbería Premium</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <style>
        .login-container { max-width: 450px; padding: 40px; margin-top: 100px; } 
        .btn-secundario { 
            display: block; text-align: center; margin-top: 15px; 
            color: #A99F92; text-decoration: none; font-weight: 600; transition: 0.3s;
        }
        .btn-secundario:hover { color: #B88E3E; }
        .btn-principal {
            display: block; text-align: center; margin-top: 25px; padding: 14px;
            background-color: #B88E3E; color: #111111; text-decoration: none;
            font-family: 'Oswald', sans-serif; text-transform: uppercase; font-weight: bold;
            border-radius: 2px; transition: 0.3s; font-size: 1.1em;
        }
        .btn-principal:hover { background-color: #A37D35; transform: translateY(-2px); }
        
        .msg-box {
            font-weight: bold; font-size: 1.1em; padding: 15px; 
            background: #111111; border-radius: 2px; margin-top: 20px;
        }
    </style>
</head>
<body class="fondo-crud">
    <div class="login-container">
        <h2>Eliminar Registro</h2>
        
        <div class="msg-box" style="color: <?= strpos($mensaje, 'ERROR') === 0 ? '#9E2A2A' : '#B88E3E' ?>; border: 1px solid <?= strpos($mensaje, 'ERROR') === 0 ? '#9E2A2A' : '#3A3A3A' ?>;">
            <?= $mensaje ?>
        </div>

        <a href="ventas.php" class="btn-principal">Volver a la lista</a>
        
        <a href="../dashboard.php" class="btn-secundario">← Ir al Inicio</a>
    </div>
</body>
</html>