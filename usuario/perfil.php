<?php
// Iniciar sessão
require_once '../config/session.php'; 
// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Conectar ao banco de dados
require_once '../config/config.php'; 

date_default_timezone_set('America/Sao_Paulo');

$user_id = $_SESSION['user_id'];
$mensagem = '';
$tipo_mensagem = ''; // success, danger, warning

// Buscar dados do usuário
$query_user = "SELECT codigo, nome, apelido, data_nasc, endereco, numero, complemento, bairro, cidade, estado, cep, telefone, celular, email, cpf, rg, funcao, ultimo_login 
          FROM {$banco_publico}.cad_vend 
          WHERE codigo = '$user_id'";
$result_user = mysqli_query($dash, $query_user);
$usuario = mysqli_fetch_assoc($result_user);

if (!$usuario) {
    die("Usuário não encontrado!");
}

// Processar atualização dos dados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_dados'])) {
    $nome = mysqli_real_escape_string($dash, $_POST['nome']);
    $apelido = mysqli_real_escape_string($dash, $_POST['apelido']);
    $data_nasc = mysqli_real_escape_string($dash, $_POST['data_nasc']);
    $endereco = mysqli_real_escape_string($dash, $_POST['endereco']);
    $numero = mysqli_real_escape_string($dash, $_POST['numero']);
    $complemento = mysqli_real_escape_string($dash, $_POST['complemento']);
    $bairro = mysqli_real_escape_string($dash, $_POST['bairro']);
    $cidade = mysqli_real_escape_string($dash, $_POST['cidade']);
    $estado = mysqli_real_escape_string($dash, $_POST['estado']);
    $cep = mysqli_real_escape_string($dash, $_POST['cep']);
    $telefone = mysqli_real_escape_string($dash, $_POST['telefone']);
    $celular = mysqli_real_escape_string($dash, $_POST['celular']);
    $email = mysqli_real_escape_string($dash, $_POST['email']);
    $cpf = mysqli_real_escape_string($dash, $_POST['cpf']);
    $rg = mysqli_real_escape_string($dash, $_POST['rg']);
    $funcao = mysqli_real_escape_string($dash, $_POST['funcao']);

    $query_update = "UPDATE {$banco_publico}.cad_vend SET 
                    nome = '$nome',
                    apelido = '$apelido',
                    data_nasc = '$data_nasc',
                    endereco = '$endereco',
                    numero = '$numero',
                    complemento = '$complemento',
                    bairro = '$bairro',
                    cidade = '$cidade',
                    estado = '$estado',
                    cep = '$cep',
                    telefone = '$telefone',
                    celular = '$celular',
                    email = '$email',
                    cpf = '$cpf',
                    rg = '$rg',
                    funcao = '$funcao'
                    WHERE codigo = '$user_id'";

    if (mysqli_query($dash, $query_update)) {
        $mensagem = "Dados atualizados com sucesso!";
        $tipo_mensagem = "success";
        
        // Atualizar dados na sessão
        $_SESSION['user_name'] = $nome;
        
        // Recarregar dados do usuário
        $result_user = mysqli_query($dash, $query_user);
        $usuario = mysqli_fetch_assoc($result_user);
    } else {
        $mensagem = "Erro ao atualizar dados: " . mysqli_error($dash);
        $tipo_mensagem = "danger";
    }
}

// Processar alteração de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_senha'])) {
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    // Verificar se a senha atual está correta
    $senha_atual_md5 = md5($senha_atual);
    if ($senha_atual_md5 !== $usuario['password']) {
        $mensagem = "Senha atual incorreta!";
        $tipo_mensagem = "danger";
    } elseif ($nova_senha !== $confirmar_senha) {
        $mensagem = "Nova senha e confirmação não coincidem!";
        $tipo_mensagem = "danger";
    } elseif (strlen($nova_senha) < 6) {
        $mensagem = "A nova senha deve ter pelo menos 6 caracteres!";
        $tipo_mensagem = "danger";
    } else {
        // Atualizar senha
        $nova_senha_md5 = md5($nova_senha);
        $query_senha = "UPDATE {$banco_publico}.cad_vend SET password = '$nova_senha_md5' WHERE codigo = '$user_id'";
        
        if (mysqli_query($dash, $query_senha)) {
            $mensagem = "Senha alterada com sucesso!";
            $tipo_mensagem = "success";
        } else {
            $mensagem = "Erro ao alterar senha: " . mysqli_error($dash);
            $tipo_mensagem = "danger";
        }
    }
}
?>

<!doctype html>
<html lang="pt-br">
<!--begin::Head-->

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Intersig | Dashboard</title>
    <!--begin::Accessibility Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <meta name="supported-color-schemes" content="light dark" />
    <link rel="preload" href="../css/adminlte.css" as="style" />
    <!--end::Accessibility Features-->
    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.1/index.css"
        integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q=" crossorigin="anonymous" media="print"
        onload="this.media='all'" />
    <!--end::Fonts-->
    <!--begin::Third Party Plugin(OverlayScrollbars)-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css"
        crossorigin="anonymous" />
    <!--end::Third Party Plugin(OverlayScrollbars)-->
    <!--begin::Third Party Plugin(Bootstrap Icons)-->


    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        crossorigin="anonymous" />
    <!--end::Third Party Plugin(Bootstrap Icons)-->
    <!--begin::Required Plugin(AdminLTE)-->
    <link rel="stylesheet" href="../css/adminlte.css" />
    <!--end::Required Plugin(AdminLTE)-->
    <!-- apexcharts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css"
        integrity="sha256-4MX+61mt9NVvvuPjUWdUdyfZfxSB1/Rf9WtqRHgG5S0=" crossorigin="anonymous" />
    <!-- jsvectormap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css"
        integrity="sha256-+uGLJmmTKOqBr+2E6KDYs/NRsHxSkONXFHUL0fy2O/4=" crossorigin="anonymous" />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">

    <style>
    h1 {
        text-align: center;
        color: #2c3e50;
        margin-bottom: 20px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #cec5c5ff;
    }

    input[type=text] {
        width: 100%;
        padding: 12px;
        margin-bottom: 20px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 12px;
    }

    input[type=text]:focus {
        border-color: #007bff;
        outline: none;
        box-shadow: 0 0 0 2px rgba(0, 123, 255, .25);
    }


    .ui-menu-item {
        padding: 8px 12px;
        cursor: pointer;
        font-size: 15px;
    }

    /* Estilo do item selecionado com o mouse ou teclado */
    .ui-state-active,
    .ui-menu-item:hover {
        background-color: #007bff;
        color: #fff;
        border: none;
    }

    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border: 1px solid rgba(0, 0, 0, 0.125);
    }

    .required::after {
        content: " *";
        color: red;
    }

    .cardconsta {
        color: #0ee3e3;
    }
    </style>
</head>
<!--end::Head-->
<!--begin::Body-->

<body class="layout-fixed sidebar-expand-lg sidebar-open" data-bs-theme="dark">

    <!--begin::App Wrapper-->
    <div class="app-wrapper">
        <!--begin::Header-->

        <?php 
        require_once '../menu/nav_dash.php';
        ?>


        <?php 
        require_once '../menu/sidebar.php';
        ?>

        <?php 
        require_once '../menu/menu_dash.php';
        ?>
    </div>



    <!--end::Sidebar Wrapper-->
    </aside>
    <!--end::Sidebar-->
    <!--begin::App Main-->
    <main class="app-main">
        <!--begin::App Content Header-->
        <div class="app-content-header">
            <!--begin::Container-->
            <div class="container-fluid">
                <!--begin::Row-->
                <div class="row">
                    <div class="col-sm-6">
                        <h3 class="mb-0">Perfil</h3>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                        </ol>
                    </div>
                </div>
                <!--end::Row-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::App Content Header-->

        <!--begin::App Content-->
        <div class="app-content">
            <!--begin::Container-->
            <div class="container-fluid">




                <div class="container">
                    <!-- Mensagens -->
                    <?php if ($mensagem): ?>
                    <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                        <?php echo $mensagem; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Dados Pessoais -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0"><i class="bi bi-person-fill me-2"></i>Dados Pessoais
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label required">Nome Completo</label>
                                                <input type="text" class="form-control" name="nome"
                                                    value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Apelido</label>
                                                <input type="text" class="form-control" name="apelido"
                                                    value="<?php echo htmlspecialchars($usuario['apelido']); ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Data de Nascimento</label>
                                                <input type="date" class="form-control" name="data_nasc"
                                                    value="<?php echo htmlspecialchars($usuario['data_nasc']); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Função</label>
                                                <input type="text" class="form-control" name="funcao"
                                                    value="<?php echo htmlspecialchars($usuario['funcao']); ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label required">CPF</label>
                                                <input type="text" class="form-control" name="cpf"
                                                    value="<?php echo htmlspecialchars($usuario['cpf']); ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">RG</label>
                                                <input type="text" class="form-control" name="rg"
                                                    value="<?php echo htmlspecialchars($usuario['rg']); ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label required">E-mail</label>
                                                <input type="email" class="form-control" name="email"
                                                    value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Telefone</label>
                                                <input type="text" class="form-control" name="telefone"
                                                    value="<?php echo htmlspecialchars($usuario['telefone']); ?>">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Celular</label>
                                            <input type="text" class="form-control" name="celular"
                                                value="<?php echo htmlspecialchars($usuario['celular']); ?>">
                                        </div>

                                        <h6 class="mt-4 mb-3"><i class="bi bi-geo-alt-fill me-2"></i>Endereço</h6>

                                        <div class="row">
                                            <div class="col-md-8 mb-3">
                                                <label class="form-label">Endereço</label>
                                                <input type="text" class="form-control" name="endereco"
                                                    value="<?php echo htmlspecialchars($usuario['endereco']); ?>">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Número</label>
                                                <input type="text" class="form-control" name="numero"
                                                    value="<?php echo htmlspecialchars($usuario['numero']); ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Complemento</label>
                                                <input type="text" class="form-control" name="complemento"
                                                    value="<?php echo htmlspecialchars($usuario['complemento']); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Bairro</label>
                                                <input type="text" class="form-control" name="bairro"
                                                    value="<?php echo htmlspecialchars($usuario['bairro']); ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Cidade</label>
                                                <input type="text" class="form-control" name="cidade"
                                                    value="<?php echo htmlspecialchars($usuario['cidade']); ?>">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Estado</label>
                                                <input type="text" class="form-control" name="estado"
                                                    value="<?php echo htmlspecialchars($usuario['estado']); ?>">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">CEP</label>
                                                <input type="text" class="form-control" name="cep"
                                                    value="<?php echo htmlspecialchars($usuario['cep']); ?>">
                                            </div>
                                        </div>

                                        <div class="d-grid gap-2">
                                            <button type="submit" name="atualizar_dados" class="btn btn-primary">
                                                <i class="bi bi-check-circle me-2"></i>Atualizar Dados
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Alterar Senha -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="card-title mb-0"><i class="bi bi-shield-lock-fill me-2"></i>Alterar Senha
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label class="form-label required">Senha Atual</label>
                                            <input type="password" class="form-control" name="senha_atual" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">Nova Senha</label>
                                            <input type="password" class="form-control" name="nova_senha" required
                                                minlength="6">
                                            <div class="form-text">Mínimo 6 caracteres</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">Confirmar Nova Senha</label>
                                            <input type="password" class="form-control" name="confirmar_senha" required
                                                minlength="6">
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="alterar_senha" class="btn btn-warning">
                                                <i class="bi bi-key-fill me-2"></i>Alterar Senha
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Informações da Conta -->
                            <div class="card mt-4">
                                <div class="card-header bg-info text-white">
                                    <h5 class="card-title mb-0"><i class="bi bi-info-circle-fill me-2"></i>Informações
                                        da Conta</h5>
                                </div>
                                <div class="card-body cardconsta">
                                    <div class="mb-2">
                                        <strong><i class="bi bi-person-badge me-2"></i>Código:</strong>
                                        <span
                                            class="float-end"><?php echo htmlspecialchars($usuario['codigo']); ?></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong><i class="bi bi-calendar-event me-2"></i>Último acesso:</strong>
                                        <span class="float-end"><?php echo date($usuario['ultimo_login']); ?></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong><i class="bi bi-envelope me-2"></i>E-mail:</strong>
                                        <span
                                            class="float-end"><?php echo htmlspecialchars($usuario['email']); ?></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong><i class="bi bi-telephone me-2"></i>Telefone:</strong>
                                        <span
                                            class="float-end"><?php echo htmlspecialchars($usuario['telefone']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


    </main>
    <!--end::App Main-->
    <!--begin::Footer-->
    <?php 
        require_once '../menu/footer.php';
        ?>
    <!--end::Footer-->
    </div>
    <!--end::App Wrapper-->
    <!--begin::Script-->
    <!--begin::Third Party Plugin(OverlayScrollbars)-->
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"
        crossorigin="anonymous"></script>
    <!--end::Third Party Plugin(OverlayScrollbars)-->
    <!--begin::Required Plugin(popperjs for Bootstrap 5)-->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous">
    </script>
    <!--end::Required Plugin(popperjs for Bootstrap 5)-->
    <!--begin::Required Plugin(Bootstrap 5)-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous">
    </script>
    <!--end::Required Plugin(Bootstrap 5)-->
    <!--begin::Required Plugin(AdminLTE)-->
    <script src="../js/adminlte.js"></script>
    <!--end::Required Plugin(AdminLTE)-->
    <!--begin::OverlayScrollbars Configure-->

    <!--end::OverlayScrollbars Configure-->
    <!-- OPTIONAL SCRIPTS -->
    <!-- sortablejs -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" crossorigin="anonymous"></script>
    <!-- sortablejs -->
    <script>
    new Sortable(document.querySelector('.connectedSortable'), {
        group: 'shared',
        handle: '.card-header',
    });

    const cardHeaders = document.querySelectorAll('.connectedSortable .card-header');
    cardHeaders.forEach((cardHeader) => {
        cardHeader.style.cursor = 'move';
    });
    </script>


    <script>
    // Formatação automática para CPF e CEP
    document.addEventListener('DOMContentLoaded', function() {
        // Formatar CPF
        const cpfInput = document.querySelector('input[name="cpf"]');
        if (cpfInput) {
            cpfInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 11) {
                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                }
                e.target.value = value;
            });
        }

        // Formatar CEP
        const cepInput = document.querySelector('input[name="cep"]');
        if (cepInput) {
            cepInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 8) {
                    value = value.replace(/(\d{5})(\d)/, '$1-$2');
                }
                e.target.value = value;
            });
        }

        // Formatar Telefone/Celular
        const phoneInputs = document.querySelectorAll('input[name="telefone"], input[name="celular"]');
        phoneInputs.forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length === 11) {
                    value = value.replace(/(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{5})(\d)/, '$1-$2');
                } else if (value.length === 10) {
                    value = value.replace(/(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{4})(\d)/, '$1-$2');
                }
                e.target.value = value;
            });
        });
    });
    </script>