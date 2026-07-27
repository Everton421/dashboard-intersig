<?php
require_once 'config/session.php'; 
// Conexão com o banco para popular lista de usuários
require_once 'config/config.php';
// Incluir verificação do "Lembrar-me"
// require_once 'check_remember_me.php';

// Se estiver logado após a verificação do remember me, redirecionar
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Intersig Sistemas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="img/intersig.ico" type="image/x-icon">
</head>

<body>
    <div class="container-fluid vh-100">
        <div class="row h-100">
            <!-- Sidebar decorativa -->
            <div class="col-lg-6 d-none d-lg-block login-sidebar">
                <div class="d-flex align-items-center justify-content-center h-100">
                    <div class="text-center text-white">
                        <div class="logo-container mb-4">
                            <img src="img/logo.png" alt="Logo Intersig Sistemas" class="logo-icon">
                        </div>

                        <p class="lead">Sistema de gestão empresarial</p>
                    </div>
                </div>
            </div>

            <!-- Área do login -->
            <div class="col-lg-6 d-flex align-items-center justify-content-center">
                <div class="login-container">
                    <div class="text-center mb-4 d-lg-none">
                        <i class="bi bi-shield-lock-fill logo-icon text-primary"></i>
                        <h3 class="fw-bold mt-2">Intersig Sistemas</h3>
                    </div>

                    <div class="card login-card animate__animated animate__fadeIn">
                        <div class="card-body p-4">
                            <h4 class="card-title text-center mb-4">Acessar Sistema</h4>

                            <?php if(isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>

                            <?php if(isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>

                            <?php
                                // Busca usuários ativos para popular o select
                                $users = [];
                                $users_q = mysqli_query($dash, "SELECT codigo, nome FROM usuarios WHERE ativo = 'S' ORDER BY nome ASC");
                                if ($users_q) {
                                    while ($r = mysqli_fetch_assoc($users_q)) {
                                        $users[] = $r;
                                    }
                                }
                                $remembered = isset($_COOKIE['remembered_username']) ? $_COOKIE['remembered_username'] : '';
                            ?>

                            <form id="loginForm" action="processa_login.php" method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Selecione seu usuário</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <select class="form-select" id="username" name="username" required>
                                            <option value="">Selecione seu usuário</option>
                                            <?php foreach ($users as $u): ?>
                                                <option value="<?php echo htmlspecialchars($u['nome']); ?>" <?php echo ($remembered === $u['nome']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['nome']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Senha</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password"
                                            required placeholder="Sua senha">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">Lembrar-me</label>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 py-2 login-btn">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
                                </button>
                            </form>

                            <div class="text-center mt-3">
                                <a href="#" class="text-decoration-none" data-bs-toggle="modal"
                                    data-bs-target="#forgotPasswordModal">
                                    Esqueci minha senha
                                </a>
                            </div>

                            <hr class="my-4">

                            <div class="text-center">
                                <button class="btn btn-outline-secondary" id="themeToggle">
                                    <i class="bi bi-moon-stars"></i> Alternar Tema
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-3">
                        <small class="text-muted">&copy; <span id="anoAtual"></span> Intersig Sistemas. Todos os
                            direitos reservados.</small>

                        <script>
                        document.getElementById("anoAtual").textContent = new Date().getFullYear();
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Esqueci Senha -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Recuperar Senha</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="forgotPasswordForm" action="esqueci_senha.php" method="POST">
                        <div class="mb-3">
                            <label for="recoveryEmail" class="form-label">Digite seu e-mail</label>
                            <input type="email" class="form-control" id="recoveryEmail" name="email" required
                                placeholder="seu@email.com">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Enviar Link de Recuperação</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/script.js"></script>

    <script>
    // Mostrar mensagens da sessão com SweetAlert2
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar se há mensagens de sessão
        const errorAlert = document.querySelector('.alert-danger');
        const successAlert = document.querySelector('.alert-success');

        if (errorAlert) {
            const errorText = errorAlert.textContent.trim();
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: errorText,
                timer: 5000
            });
            // Remover o alerta original
            errorAlert.remove();
        }

        if (successAlert) {
            const successText = successAlert.textContent.trim();
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: successText,
                timer: 4000
            });
            // Remover o alerta original
            successAlert.remove();
        }
    });
    </script>
</body>

</html>