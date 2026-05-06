<?php
session_start();

// --- BLOQUE DE VALIDACIÓN DE SESIÓN Y ROL (CERO SQL) ---
if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) {
    header("Location: ../index.html"); 
    exit;
}
include("../conexion.php"); 
if (!isset($conn)) { die("Error fatal de conexión."); }

$currentUser = $conn->real_escape_string($_SESSION['usuario']);
$rol_actual_sesion = $_SESSION['rol'];

// Validar que solo el administrador pueda borrar
if ($rol_actual_sesion !== 'admin') {
    header("Location: ../dashboard.php"); 
    exit;
}

$cveCliente = $_GET['clv'] ?? null;
$mensaje = '';

if ($cveCliente) {
    try {
        // Limpiar buffer previo
        while($conn->more_results() && $conn->next_result()){;}

        // Llamada directa al SP de Borrado Seguro
        $stmt = $conn->prepare("CALL sp_borrar_cliente_seguro(?, ?)");
        $stmt->bind_param("is", $cveCliente, $currentUser);
        
        if ($stmt->execute()) {
            // Si todo sale bien, la BD lo borró sin problemas
            $mensaje = "Cliente CLV #$cveCliente eliminado correctamente.";
        }
        $stmt->close();

    } catch (mysqli_sql_exception $e) {
        // Capturamos el SIGNAL SQLSTATE '45000' del SP (Ej. "DENEGADO: Editando por...")
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
    <title>Eliminar Cliente</title>
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
        <h2>Eliminar Cliente</h2>
        
        <div class="msg-box" style="color: <?= strpos($mensaje, 'ERROR') === 0 ? '#9E2A2A' : '#B88E3E' ?>; border: 1px solid <?= strpos($mensaje, 'ERROR') === 0 ? '#9E2A2A' : '#3A3A3A' ?>;">
            <?= $mensaje ?>
        </div>

        <a href="clientes.php" class="btn-principal">Volver a la lista</a>
        
        <a href="../dashboard.php" class="btn-secundario">← Ir al Inicio</a>
    </div>
</body>
</html>