<?php
// 1. Iniciar la sesión
session_start();

// 2. Destruir todas las variables de sesión.
$_SESSION = array();

// 3. Si se desea destruir la cookie de sesión, también se debe hacer esto.
// Nota: Esto borrará la cookie de sesión y hará que la sesión sea no válida.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finalmente, destruir la sesión.
session_destroy();

// 5. Redirigir al usuario a la página de inicio de sesión.
header("Location: index.html");
exit;
?>