<?php
require_once 'config/session.php'; 

require_once 'config/config.php'; 

require_once 'enviar_email.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    // Verificar se o e-mail foi informado
    if (empty($email)) {
        $_SESSION['error'] = 'Informe seu e-mail.';
        header("Location: index.php");
        exit;
    }

    // Preparar consulta para verificar se o e-mail existe
    $query = "SELECT codigo AS id, nome AS user_name FROM usuarios WHERE email = ?";
    $stmt = mysqli_prepare($dash, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);

            // Gerar token único
            $token = bin2hex(random_bytes(32));
            $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Atualizar token no banco
            $updateQuery = "UPDATE usuarios 
                            SET reset_token = ?, reset_token_validade = ? 
                            WHERE codigo = ?";
            $updateStmt = mysqli_prepare($dash, $updateQuery);

            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, "ssi", $token, $token_expiry, $user['id']);
                $executou = mysqli_stmt_execute($updateStmt);

                if ($executou) {
                    // Enviar e-mail
                    $emailSent = sendResetEmail($email, $user['user_name'], $token);

                    if ($emailSent) {
                        $_SESSION['success'] = 'Link de recuperação enviado para seu e-mail.';
                    } else {
                        $_SESSION['error'] = 'Erro ao enviar e-mail. Tente novamente mais tarde.';
                    }
                } else {
                    $_SESSION['error'] = 'Erro ao gerar token de recuperação.';
                }

                mysqli_stmt_close($updateStmt);
            } else {
                $_SESSION['error'] = 'Erro interno ao preparar atualização.';
            }
        } else {
            $_SESSION['error'] = 'E-mail não encontrado em nosso sistema.';
        }

        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = 'Erro interno ao preparar consulta.';
    }

    // Redirecionar para a tela inicial
    header("Location: index.php");
    exit;

} else {
    // Se acessado diretamente, redirecionar
    header("Location: index.php");
    exit;
}
?>