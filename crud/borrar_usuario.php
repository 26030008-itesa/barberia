<?php
session_start();
// Nota: Usamos la conexión ROOT porque borrar usuarios requiere privilegios elevados
include("../conexion.php"); 

// Validar Admin
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: usuarios.php");
    exit;
}

$user_to_delete = $_GET['user'] ?? null;
$host_to_delete = $_GET['host'] ?? 'localhost'; // Default host
$current_admin = $_SESSION['usuario'];
$mensaje = '';

// Lista de protección
$protected = ['root', 'mysql.infoschema', 'mysql.session', 'mysql.sys', $current_admin];

if ($user_to_delete && !in_array($user_to_delete, $protected)) {
    try {
        // EXCEPCIÓN: SQL Directo necesario para DROP USER
        // Sanitizamos manualmente porque DROP USER no acepta parámetros preparados (?)
        $user_safe = $conn->real_escape_string($user_to_delete);
        $host_safe = $conn->real_escape_string($host_to_delete);
        
        $sql = "DROP USER '$user_safe'@'$host_safe'";
        
        if ($conn->query($sql)) {
            $conn->query("FLUSH PRIVILEGES");
            $_SESSION['mensaje_status'] = "Usuario eliminado.";
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        $_SESSION['mensaje_status_error'] = "Error: " . $e->getMessage();
    }
} else {
    $_SESSION['mensaje_status_error'] = "Acción no permitida o usuario inválido.";
}

header("Location: usuarios.php");
exit;
?>