<?php
// NÃO iniciar sessão aqui - deixe que os arquivos que incluem este arquivo façam isso

require_once 'config/database.php';
require_once 'config/session.php'; 
function checkRememberMe() {
    // Se já estiver logado, não fazer nada
    if(isset($_SESSION['user_id'])) {
        return true;
    }
    
    if(!isset($_COOKIE['remember_me'])) {
        return false;
    }
    
    $cookie_data = $_COOKIE['remember_me'];
    $parts = explode(':', $cookie_data);
    
    if(count($parts) !== 3) {
        // Cookie inválido, remover
        setcookie('remember_me', '', time() - 3600, "/");
        return false;
    }
    
    list($user_id, $token, $hash) = $parts;
    
    // Verificar integridade do cookie
    if(hash('sha256', $user_id . ':' . $token) !== $hash) {
        // Cookie corrompido, remover
        setcookie('remember_me', '', time() - 3600, "/");
        return false;
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Verificar token no banco
        $query = "SELECT id, user_name, email_user, type, remember_token, token_expiry 
                  FROM users 
                  WHERE id = :id AND remember_token = :token AND token_expiry > NOW()";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Logar usuário automaticamente
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['user_name'];
            $_SESSION['user_email'] = $user['email_user'];
            $_SESSION['user_type'] = $user['type'];
            
            // Renovar o token (opcional - para manter logado)
            $new_token = bin2hex(random_bytes(32));
            $new_expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $updateQuery = "UPDATE users SET remember_token = :token, token_expiry = :expiry WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':token', $new_token);
            $updateStmt->bindParam(':expiry', $new_expiry);
            $updateStmt->bindParam(':id', $user['id']);
            $updateStmt->execute();
            
            // Atualizar cookie
            $cookie_value = $user['id'] . ':' . $new_token;
            $cookie_hash = hash('sha256', $cookie_value);
            setcookie('remember_me', $cookie_value . ':' . $cookie_hash, time() + (30 * 24 * 60 * 60), "/", "", false, true);
            
            return true;
        } else {
            // Token inválido ou expirado, remover cookie
            setcookie('remember_me', '', time() - 3600, "/");
            return false;
        }
    } catch(PDOException $e) {
        error_log("Erro ao verificar remember me: " . $e->getMessage());
        return false;
    }
}

// Executar a verificação apenas se não estiver logado
if (!isset($_SESSION['user_id'])) {
    checkRememberMe();
}
?>