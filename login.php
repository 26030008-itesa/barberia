<?php
session_start();

$host = "localhost";

$usuario_input = $_POST['usuario'] ?? '';
$password_input = $_POST['password'] ?? '';

$port = 3307;

// 1. MANEJO DE ERRORES DE CONEXIÓN
// En lugar de suprimir el error con @, lo capturamos con try...catch
try {
    // Intentar conectar al servidor MySQL como el usuario que se está logueando
    // No especificamos la base de datos para evitar errores de "Access denied to database"
    $conn_login = new mysqli($host, $usuario_input, $password_input,"", $port);

    // Si $conn_login->connect_error tiene un valor, la conexión falló (contraseña incorrecta)
    if ($conn_login->connect_error) {
        // Forzamos una excepción para ser capturada
        throw new Exception("Conexión fallida: " . $conn_login->connect_error);
    }

} catch (Exception $e) {
    // Si la conexión falla (usuario no existe O contraseña incorrecta)
    // Capturamos la excepción y redirigimos con un código de error amigable.
    header("Location: index.html?error=1");
    exit;
}

// 2. AUTENTICACIÓN EXITOSA - OBTENER EL ROL ASIGNADO USANDO SHOW GRANTS
$sql_grants = "SHOW GRANTS FOR CURRENT_USER()"; 
$result_grants = $conn_login->query($sql_grants);

$rol_app = 'desconocido'; 

if ($result_grants && $result_grants->num_rows > 0) {
    while ($row = $result_grants->fetch_assoc()) {
        $grant_line = reset($row);
        
        // Mapeo de roles de MySQL a roles de la aplicación
        if (strpos($grant_line, 'ADMINISTRADOR') !== false) {
            $rol_app = 'admin';
            break; 
        } elseif (strpos(strtoupper($grant_line), 'VENDEDOR_CAJERO') !== false) {
            $rol_app = 'vendedor';
            break;
        } elseif (strpos(strtoupper($grant_line), 'BARBERO') !== false) {
            $rol_app = 'empleado';
            break;
        }
    }
}

$conn_login->close();

// 3. INICIO DE SESIÓN EN LA APLICACIÓN
if ($rol_app != 'desconocido') {
    $_SESSION['usuario'] = $usuario_input;
    $_SESSION['rol'] = $rol_app; 
    
    // Incluir la conexión de root/administrador para que el resto de la aplicación (CRUD) funcione.
    include("conexion.php"); 
    header("Location: dashboard.php");
    exit;
} else {
    // El usuario existe en MySQL pero no tiene un rol de app válido.
    // Lo tratamos como un error de login.
    header("Location: index.html?error=1");
    exit;
}
?>