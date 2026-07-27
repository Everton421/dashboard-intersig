<?php
// Inicia a sessão
require_once 'config/session.php'; 
require_once 'config/config.php';


// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
$password = $_POST['password'] ?? ''; // Usa o operador ?? para evitar erro se não for enviado

// Validação básica
if (!$username || empty($password)) {
    $_SESSION['error'] = "Por favor, preencha todos os campos corretamente.";
    header("Location: index.php");
    exit;
}

try {
    
        $query = "SELECT 
                                codigo, 
                                email, 
                                senha AS hashed_password, 
                                tipo, 
                                nome,
                                ativo
                            FROM  usuarios 
                            WHERE nome = ? LIMIT 1";

    $stmt = $dash->prepare($query);

    if ($stmt === false) {
        throw new Exception("Erro ao preparar a consulta: " . $dash->error);
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

            // Verifica se o usuário está ativo
        if ($user['ativo'] !== 'S') {
            $_SESSION['error'] = "Usuário inativo. Entre em contato com o suporte.";
        } 
        // Verifica a senha (mantendo MD5 conforme legacy)
        elseif (md5($password) === $user['hashed_password']) {
            
            // SUCESSO NO LOGIN
            
            // CORREÇÃO: Regenera o ID da sessão para prevenir session fixation
            session_regenerate_id(true);

            // CORREÇÃO: Define as variáveis de sessão usando a variável correta '$user'
            $_SESSION["user_id"] = $user["codigo"];
            $_SESSION["user_name"] = $user["nome"];
            $_SESSION["user_type"] = $user["tipo"];
            $_SESSION["user_email"] = $user["email"];
            
            // MELHORIA: Implementação do "Lembrar-me"
            if (!empty($_POST['remember'])) {
                // Lógica para criar e armazenar o cookie de "Lembrar-me"
                
                $selector = bin2hex(random_bytes(16));
                $validator = bin2hex(random_bytes(32));
                $hashed_validator = password_hash($validator, PASSWORD_DEFAULT);
                $expires = time() + (86400 * 30); // Expira em 30 dias

                setcookie('remember_me', $selector . ':' . $validator, $expires, '/', '', false, true);
                // Também armazenar o nome de usuário para preencher o campo de login
                setcookie('remembered_username', $username, $expires, '/', '', false, true);

                // Armazenar no banco de dados
                $tokenQuery = "INSERT INTO auth_tokens (selector, hashed_validator, codigo, expires) VALUES (?, ?, ?, FROM_UNIXTIME(?))";
                $tokenStmt = $dash->prepare($tokenQuery);
                $tokenStmt->bind_param('ssis', $selector, $hashed_validator, $user['codigo'], $expires);
                $tokenStmt->execute();
                $tokenStmt->close();
            } else {
                // Limpa possível cookie de username se não solicitou "lembrar"
                if (isset($_COOKIE['remembered_username'])) {
                    setcookie('remembered_username', '', time() - 3600, '/', '', false, true);
                }
            }

            $updateQuery = "UPDATE usuarios SET ultimo_login = NOW() WHERE codigo = ?";
            $updateStmt = $dash->prepare($updateQuery);
            $updateStmt->bind_param('i', $user['codigo']);
            $updateStmt->execute();
            $updateStmt->close();

            // Redireciona para o painel principal
            header("Location: ./dashboard/dashboard.php");
            exit;

        } else {
            // Senha incorreta
            $_SESSION['error'] = "Usuário ou senha incorretos.";
        }
    } else {
        // Usuário não encontrado
        $_SESSION['error'] = "Usuário ou senha incorretos.";
    }
    
    // CORREÇÃO: Redireciona de volta para a PÁGINA DE LOGIN em caso de falha
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    // Em produção, você poderia logar o erro em um arquivo em vez de exibi-lo
    // error_log($e->getMessage());
    $_SESSION['error'] = "Ocorreu um erro no servidor. Tente novamente mais tarde.";
    header("Location: index.php");
    exit;
} finally {
    // Fecha a conexão
    if (isset($stmt)) $stmt->close();
    $dash->close();
}