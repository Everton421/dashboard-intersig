<?php
// Iniciar sessão apenas se não estiver ativa
require_once 'config/session.php';
require_once 'config/config.php'; // contém $dash (mysqli)
 

if (!isset($_GET['token']) || empty($_GET['token'])) {
    header("Location: index.php");
    exit;
}

$token = $_GET['token'];
$error = '';
$success = '';

// Processar o formulário de redefinição
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Por favor, preencha todos os campos.";
    } elseif ($new_password !== $confirm_password) {
        $error = "As senhas não coincidem.";
    } elseif (strlen($new_password) < 6) {
        $error = "A senha deve ter pelo menos 6 caracteres.";
    } else {

        // Verificar se o token é válido e não expirou
        $query = "SELECT codigo AS id, email, reset_token_validade 
                  FROM usuarios 
                  WHERE reset_token = ?";
        $stmt = mysqli_prepare($dash, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $token);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) === 1) {
                $user = mysqli_fetch_assoc($result);
                $now = date('Y-m-d H:i:s');

                if ($user['reset_token_validade'] > $now) {
                    // Hash da nova senha (compatível com login MD5)
                    $hashed_password = md5($new_password);

                    // Atualizar senha e limpar token
                    $updateQuery = "UPDATE usuarios 
                                    SET senha = ?, reset_token = NULL, reset_token_validade = NULL 
                                    WHERE codigo = ?";
                    $updateStmt = mysqli_prepare($dash, $updateQuery);

                    if ($updateStmt) {
                        mysqli_stmt_bind_param($updateStmt, "si", $hashed_password, $user['id']);
                        $executou = mysqli_stmt_execute($updateStmt);

                        if ($executou) {
                            $success = "Senha redefinida com sucesso! Você já pode fazer login com sua nova senha.";
                            $token = ''; // Limpa o token para evitar reuso
                        } else {
                            $error = "Erro ao atualizar a senha. Tente novamente.";
                        }

                        mysqli_stmt_close($updateStmt);
                    } else {
                        $error = "Erro interno ao preparar atualização.";
                    }
                } else {
                    $error = "Este link de redefinição expirou. Solicite um novo.";
                }
            } else {
                $error = "Token de redefinição inválido.";
            }

            mysqli_stmt_close($stmt);
        } else {
            $error = "Erro ao preparar consulta.";
        }
    }

} else {
    // Verificar se o token é válido ao carregar a página
    $query = "SELECT codigo AS id, reset_token_validade 
              FROM usuarios
              WHERE reset_token = ?";
    $stmt = mysqli_prepare($dash, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (!$result || mysqli_num_rows($result) === 0) {
            $error = "Token de redefinição inválido.";
        } else {
            $user = mysqli_fetch_assoc($result);
            $now = date('Y-m-d H:i:s');
            if ($user['reset_token_validade'] < $now) {
                $error = "Este link de redefinição expirou. Solicite um novo.";
            }
        }

        mysqli_stmt_close($stmt);
    } else {
        $error = "Erro ao verificar o token.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha | Meu Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
    :root {
        --primary-color: #6366f1;
        --primary-hover: #4f46e5;
        --gradient-start: #0f172a;
        --gradient-end: #1e293b;
        --card-bg: #1e293b;
        --text-primary: #f8fafc;
    }

    [data-bs-theme="light"] {
        --gradient-start: #e2e8f0;
        --gradient-end: #cbd5e1;
        --card-bg: #ffffff;
        --text-primary: #1e293b;
    }

    body {
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: var(--text-primary);
        min-height: 100vh;
        display: flex;
        align-items: center;
    }

    .reset-container {
        max-width: 450px;
        width: 100%;
        margin: 0 auto;
        padding: 20px;
    }

    .reset-card {
        background: var(--card-bg);
        border: none;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
    }

    .logo-icon {
        font-size: 3rem;
        color: var(--primary-color);
        filter: drop-shadow(0 0 10px rgba(99, 102, 241, 0.3));
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="reset-container">
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock-fill logo-icon"></i>
                <h2 class="fw-bold mt-3">Meu Sistema</h2>
                <p class="text-muted">Redefinir Senha</p>
            </div>

            <div class="card reset-card animate__animated animate__fadeIn">
                <div class="card-body p-4">
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <div class="text-center mt-3">
                        <a href="index.php" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Fazer Login
                        </a>
                    </div>
                    <?php elseif (empty($error)): ?>
                    <p class="text-muted mb-4">Digite sua nova senha abaixo.</p>

                    <form id="resetForm" method="POST">
                        <div class="mb-3">
                            <label for="password" class="form-label">Nova Senha</label>
                            <input type="password" class="form-control" id="password" name="password" required
                                minlength="6" placeholder="Mínimo 6 caracteres">
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirmar Nova Senha</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                required placeholder="Digite a senha novamente">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-key me-2"></i>Redefinir Senha
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="text-center">
                        <a href="esqueci_senha.php" class="btn btn-primary">
                            <i class="bi bi-arrow-repeat me-2"></i>Solicitar Novo Link
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary ms-2">
                            <i class="bi bi-house me-2"></i>Voltar ao Login
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>