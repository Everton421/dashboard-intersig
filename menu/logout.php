<?php
require_once '../config/session.php'; 

// Limpar todas as variáveis de sessão
$_SESSION = array();

// Destruir a sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Limpar cookie do "Lembrar-me"
if(isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, "/");
}

header("Location: index.php");
exit;
?>