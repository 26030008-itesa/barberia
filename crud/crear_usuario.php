<?php
include("../conexion.php");
session_start(); unset($_SESSION['mensaje_status']);

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') { header("Location: ../dashboard.php"); exit; }

$mensaje = ''; $nuevo_usuario = ''; $nuevo_rol = 'BARBERO'; 
$roles_disponibles = ['ADMINISTRADOR', 'VENDEDOR_CAJERO', 'BARBERO'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_usuario = trim($_POST['nuevo_usuario']); $nuevo_password = $_POST['nuevo_password']; $nuevo_rol = $_POST['nuevo_rol'];

    if (empty($nuevo_usuario) || empty($nuevo_password)) { $mensaje = "ERROR: Todos los campos son obligatorios."; } 
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $nuevo_usuario)) { $mensaje = "ERROR: Solo letras, números y guion bajo (_)."; } 
    elseif (!in_array($nuevo_rol, $roles_disponibles)) { $mensaje = "ERROR: Rol no válido."; } 
    elseif (strlen($nuevo_password) < 8) { $mensaje = "ERROR: Mínimo 8 caracteres en la contraseña."; } 
    else {
        try {
            while($conn->more_results() && $conn->next_result()){;}
            $stmt = $conn->prepare("CALL sp_crear_usuario_sistema(?, ?, ?)");
            $stmt->bind_param("sss", $nuevo_usuario, $nuevo_password, $nuevo_rol);
            if ($stmt->execute()) {
                $_SESSION['mensaje_status'] = "Usuario '$nuevo_usuario' creado con rol '$nuevo_rol'.";
                $stmt->close(); header("Location: usuarios.php"); exit;
            }
        } catch (Exception $e) {
            $err_msg = $e->getMessage();
            if (strpos($err_msg, 'Operation CREATE USER failed') !== false) { $mensaje = "ERROR: El usuario '$nuevo_usuario' ya existe."; } 
            else { $mensaje = "ERROR SISTEMA: " . $err_msg; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Usuario</title><link rel="stylesheet" href="../css/style.css">
    <style>
        .login-container { max-width: 450px; padding: 40px; margin: 40px auto; background: rgba(30,30,30,0.85); backdrop-filter: blur(10px); border-top: 3px solid #B88E3E; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); color: #FDFEFE; }
        h2 { color: #B88E3E; text-transform: uppercase; font-family: 'Oswald', sans-serif; text-align: center; margin-bottom: 25px; }
        label { display: block; margin-top: 15px; color: #A99F92; font-weight: 600; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; }
        input, select { width: 100%; padding: 12px; margin-top: 5px; background: #111111; color: #FDFEFE; border: 1px solid #3A3A3A; border-radius: 2px; box-sizing: border-box; font-family: 'Montserrat', sans-serif; transition: 0.3s; }
        input:focus, select:focus { border-color: #B88E3E; outline: none; box-shadow: 0 0 8px rgba(184,142,62,0.3); }
        small { color: #A99F92; font-size: 0.8em; }
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
        <h2>Crear Acceso</h2>
        <?php if (!empty($mensaje)): ?><p class="msg-box"><?= $mensaje ?></p><?php endif; ?>
        <form method="POST" action="crear_usuario.php">
            <label>Nombre de Usuario*:</label>
            <input type="text" name="nuevo_usuario" value="<?= htmlspecialchars($nuevo_usuario) ?>" required pattern="[a-zA-Z0-9_]+" title="Letras, números y guion bajo">
            <small>Ej: carlos_barbero, caja_01</small>
            
            <label>Contraseña*:</label>
            <input type="password" name="nuevo_password" required minlength="8">
            <small>Mínimo 8 caracteres</small>
            
            <label>Nivel de Permisos*:</label>
            <select name="nuevo_rol" required>
                <?php foreach ($roles_disponibles as $rol_db): ?>
                    <option value="<?= $rol_db ?>" <?= ($nuevo_rol == $rol_db) ? 'selected' : '' ?>><?= htmlspecialchars($rol_db) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-principal">Otorgar Acceso</button>
            <a href="usuarios.php" class="btn-secundario">← Cancelar y Volver</a>
        </form>
    </div>
</body>
</html>