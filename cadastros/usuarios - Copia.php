<?php

require_once '../config/config.php';
require_once '../config/session.php'; 

date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION["user_name"])) {
    header("Location: ../index.php");
    exit();
}

$nome_usuario_logado = htmlspecialchars($_SESSION["user_name"]);
$id_usuario_logado = (int) $_SESSION["user_id"];

// --- LOGGING ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$page_url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
$user_agent = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255); 

if (isset($dash) && $stmt = $dash->prepare(
    "INSERT INTO logs (user_id, user_name, pagina_url, acesso, user_agent) VALUES (?, ?, ?, NOW(),  ?)"
)) {
    $stmt->bind_param("isss", $id_usuario_logado, $nome_usuario_logado, $page_url, $user_agent);
    $stmt->execute();
}

$flash_message = '';
$flash_type = '';

// VERIFICAR SE O USUÁRIO LOGADO É SUPERVISOR/ADMIN
$usuario_supervisor = false;
// Atenção: Verifica na tabela de usuarios se quem está logado é ADMIN
$sql_supervisor = "SELECT tipo FROM usuarios WHERE codigo = ?"; 
$stmt_supervisor = mysqli_prepare($dash, $sql_supervisor);
mysqli_stmt_bind_param($stmt_supervisor, 's', $id_usuario_logado);
mysqli_stmt_execute($stmt_supervisor);
$result_supervisor = mysqli_stmt_get_result($stmt_supervisor);

if ($row_supervisor = mysqli_fetch_assoc($result_supervisor)) {
    $usuario_supervisor = ($row_supervisor['tipo'] === 'ADMIN');
}

// PROCESSA AS SUBMISSÕES DE FORMULÁRIO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$usuario_supervisor) {
        $_SESSION['flash_message'] = "Acesso negado! Você não tem permissão.";
        $_SESSION['flash_type'] = 'error';
        header("Location: setor.php");
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    
    // --- AÇÃO: CADASTRAR NOVO SETOR ---
    if ($action === 'create_setor') {
        $nome = trim($_POST['nome'] ?? '');
        // Checkbox: se marcado vem valor, se não, não vem nada.
        $ativo = isset($_POST['ativo']) ? 'S' : 'N';

        if (empty($nome)) {
            $_SESSION['flash_message'] = "Nome do Setor é obrigatório.";
            $_SESSION['flash_type'] = 'error';
        } else {
            // Verifica duplicidade (usando ID no SELECT não interfere, mas buscamos pelo nome)
            $check_sql = "SELECT id FROM setores WHERE nome = ?";
            $stmt_check = mysqli_prepare($dash, $check_sql);
            mysqli_stmt_bind_param($stmt_check, 's', $nome);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);

            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $_SESSION['flash_message'] = "Erro: Este Setor já está cadastrado.";
                $_SESSION['flash_type'] = 'error';
            } else {
                $sql_insert = "INSERT INTO setores (nome, ativo, criado_em) VALUES (?, ?, NOW())";
                $stmt = mysqli_prepare($dash, $sql_insert);
                mysqli_stmt_bind_param($stmt, 'ss', $nome, $ativo);

                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['flash_message'] = "Novo Setor cadastrado com sucesso!";
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = "Erro ao cadastrar: " . mysqli_error($dash);
                    $_SESSION['flash_type'] = 'error';
                }
            }
        }
    }

    // --- AÇÃO: EDITAR SETOR ---
    elseif ($action === 'edit_setor' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $nome = trim($_POST['nome']); // Nome deve vir do campo 'nome' do modal de edição
        $ativo = isset($_POST['ativo']) ? 'S' : 'N';

        // Atualiza usando ID
        $sql_update = "UPDATE setores SET nome = ?, ativo = ? WHERE id = ?";
        $stmt = mysqli_prepare($dash, $sql_update);
        mysqli_stmt_bind_param($stmt, 'ssi', $nome, $ativo, $id);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_message'] = "Setor atualizado com sucesso!";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = "Erro ao atualizar: " . mysqli_error($dash);
            $_SESSION['flash_type'] = 'error';
        }
    }
    
    // --- AÇÃO: ALTERAR STATUS (BOTÃO INATIVAR/ATIVAR) ---
    elseif ($action === 'toggle_active_setor') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id > 0) {
            // Inverte o status usando ID
            $sql_toggle = "UPDATE setores SET ativo = CASE WHEN ativo = 'S' THEN 'N' ELSE 'S' END WHERE id = ?";
            $stmt = mysqli_prepare($dash, $sql_toggle);
            mysqli_stmt_bind_param($stmt, 'i', $id); // 'i' para integer

            if (mysqli_stmt_execute($stmt)) {
                // Verificar novo status para mensagem
                $check_sql = "SELECT ativo FROM setores WHERE id = $id";
                $res_check = mysqli_query($dash, $check_sql);
                $row_check = mysqli_fetch_assoc($res_check);
                $novo_status = ($row_check['ativo'] == 'S') ? 'ativado' : 'inativado';
                
                $_SESSION['flash_message'] = "Setor {$novo_status} com sucesso!";
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = "Erro ao alterar status: " . mysqli_error($dash);
                $_SESSION['flash_type'] = 'error';
            }
        }
    }

    // --- AÇÃO: VINCULAR VENDEDOR AO SETOR ---
    elseif ($action === 'vincular_vendedor') {
        $vendedor_id = (int)$_POST['vendedor_id'];
        $setor_id = (int)$_POST['setor_id'];

        if ($vendedor_id > 0 && $setor_id > 0) {
            // Verifica se já existe vínculo
            $check_sql = "SELECT id FROM vendedor_setor WHERE vendedor_id = ?";
            $stmt_check = mysqli_prepare($dash, $check_sql);
            mysqli_stmt_bind_param($stmt_check, 'i', $vendedor_id);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);

            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                // Atualiza
                $sql_update = "UPDATE vendedor_setor SET setor_id = ? WHERE vendedor_id = ?";
                $stmt = mysqli_prepare($dash, $sql_update);
                mysqli_stmt_bind_param($stmt, 'ii', $setor_id, $vendedor_id);
            } else {
                // Insere
                $sql_update = "INSERT INTO vendedor_setor (vendedor_id, setor_id) VALUES (?, ?)";
                $stmt = mysqli_prepare($dash, $sql_update);
                mysqli_stmt_bind_param($stmt, 'ii', $vendedor_id, $setor_id);
            }

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_message'] = "Vendedor vinculado com sucesso!";
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = "Erro ao vincular: " . mysqli_error($dash);
                $_SESSION['flash_type'] = 'error';
            }
        }
    }

    // --- AÇÃO: VINCULAR VENDEDOR A VENDEDOR_SISTEMA (multiplo) ---
    elseif ($action === 'vincular_vendedor_sistema') {
        $vendedor_id = (int)($_POST['vendedor_id'] ?? 0);
        $sistemas = $_POST['vendedoresistema_ids'] ?? []; // array de ids

        if ($vendedor_id > 0) {
            // Apaga vinculos antigos
            $del_sql = "DELETE FROM vendedor_vendedoresistema WHERE vendedor_id = ?";
            $stmt_del = mysqli_prepare($dash, $del_sql);
            mysqli_stmt_bind_param($stmt_del, 'i', $vendedor_id);
            mysqli_stmt_execute($stmt_del);

            // Insere novos (se houver)
            if (!empty($sistemas) && is_array($sistemas)) {
                $ins_sql = "INSERT INTO vendedor_vendedoresistema (vendedor_id, vendedoresistema_id) VALUES (?, ?)";
                $stmt_ins = mysqli_prepare($dash, $ins_sql);
                foreach ($sistemas as $sid) {
                    $sid_int = (int)$sid;
                    if ($sid_int <= 0) continue;
                    mysqli_stmt_bind_param($stmt_ins, 'ii', $vendedor_id, $sid_int);
                    mysqli_stmt_execute($stmt_ins);
                }
            }

            $_SESSION['flash_message'] = "Relacionamento com sistemas atualizado!";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = "Vendedor inválido.";
            $_SESSION['flash_type'] = 'error';
        }
    }
    
    header("Location: vincular_setor.php");
    exit();
}

if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// BUSCA DE DADOS (Usando ID)
$setores = [];
$sql_managed = "SELECT id, nome, ativo FROM setores ORDER BY nome ASC";
$res_managed = mysqli_query($dash, $sql_managed);
if ($res_managed) {
    while ($row = mysqli_fetch_assoc($res_managed)) {
        $setores[] = $row;
    }
}

$unlinked_users = [];
if ($usuario_supervisor) { 
    $sql_unlinked = "
        SELECT 
            s.id, 
            s.vendedor_id, 
            s.setor_id, 
            v.nome, 
            v.id as id_vendedor,
            s2.nome AS setor_atual
        FROM vendedores v 
        LEFT JOIN vendedor_setor s ON s.vendedor_id = v.id
        LEFT JOIN setores s2 ON s2.id = s.setor_id
        ORDER BY v.nome ASC
    ";
    $res_unlinked = mysqli_query($dash, $sql_unlinked);
    if ($res_unlinked) {
        while ($row = mysqli_fetch_assoc($res_unlinked)) {
            // garantir chaves esperadas pela view
            $unlinked_users[] = [
                'id' => $row['id_vendedor'],
                'nome' => $row['nome'],
                'setor_id' => $row['setor_id'],
                'setor_atual' => $row['setor_atual']
            ];
        }
    }
}

// -- NOVO: buscar vendedores_sistema (todas as entradas) --
$vendedores_sistema = [];
$sql_vs = "SELECT id, nome, origem, ativo, cod_intersig FROM vendedores_sistema ORDER BY nome ASC";
$res_vs = mysqli_query($dash, $sql_vs);
if ($res_vs) {
    while ($row = mysqli_fetch_assoc($res_vs)) {
        $vendedores_sistema[] = $row;
    }
}

// -- NOVO: mapa de vínculos existente (vendedor_id => [vendedoresistema_id,...]) --
$vendedor_sistema_map = [];
$sql_map = "SELECT vendedor_id, vendedoresistema_id FROM vendedor_vendedoresistema";
$res_map = mysqli_query($dash, $sql_map);
if ($res_map) {
    while ($r = mysqli_fetch_assoc($res_map)) {
        $vid = (int)$r['vendedor_id'];
        $sid = (int)$r['vendedoresistema_id'];
        if (!isset($vendedor_sistema_map[$vid])) $vendedor_sistema_map[$vid] = [];
        $vendedor_sistema_map[$vid][] = $sid;
    }
}
?>


<!doctype html>
<html lang="pt-br">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>SETORES</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="../css/adminlte.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">

    <style>
    :root {
        --primary-color: #0056b3;
        --light-gray: #f4f6f9;
        --dark-gray: #343a40;
        --text-color: #495057;
        --card-bg: #ffffff;
        --border-color: #dee2e6;
        --success-bg: #d4edda;
        --success-color: #155724;
        --error-bg: #f8d7da;
        --error-color: #721c24;
        --add-btn-bg: #28a745;
        --add-btn-hover: #218838;
    }

    [data-bs-theme="dark"] {
        --light-gray: #212529;
        --dark-gray: #f8f9fa;
        --text-color: #dee2e6;
        --card-bg: #343a40;
        --border-color: #495057;
        --success-bg: #1e4620;
        --success-color: #75b798;
        --error-bg: #5c1e23;
        --error-color: #ea868f;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background-color: var(--light-gray);
        color: var(--text-color);
    }

    .main-header {
        /* Estilo do cabeçalho ... */
    }

    .container {
        padding: 1.5rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .section-title {
        font-size: 1.8rem;
        font-weight: 600;
        color: var(--dark-gray);
        margin-bottom: 1.5rem;
    }

    .users-table-card {
        background-color: var(--card-bg);
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .table-wrapper {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 600px;
    }

    th,
    td {
        text-align: left;
        padding: 0.9rem 1rem;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-color);
    }

    th {
        font-weight: 600;
    }

    .action-btn {
        color: white;
        border: none;
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .edit-btn {
        background-color: var(--primary-color);
    }

    .edit-btn:hover {
        background-color: #004494;
    }

    .add-btn {
        background-color: var(--add-btn-bg);
    }

    .add-btn:hover {
        background-color: var(--add-btn-hover);
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background-color: var(--card-bg);
        margin: auto;
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        width: 90%;
        max-width: 500px;
        color: var(--text-color);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .modal-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .close-btn {
        color: #aaa;
        font-size: 2rem;
        font-weight: bold;
        cursor: pointer;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: var(--text-color);
    }

    .modal-body .form-group {
        margin-bottom: 1rem;
    }

    .modal-body label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }

    .modal-body .form-input {
        width: 100%;
        padding: 0.8rem;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        background-color: var(--card-bg);
        color: var(--text-color);
    }

    .modal-footer {
        text-align: right;
        margin-top: 1.5rem;
    }

    .modal-footer .btn {
        padding: 0.6rem 1.2rem;
        border-radius: 6px;
        border: none;
        cursor: pointer;
    }

    .btn-save {
        background-color: var(--primary-color);
        color: white;
    }

    .btn-cancel {
        background-color: #6c757d;
        color: white;
        margin-right: 0.5rem;
    }

    /* Flash Messages */
    .flash-message {
        padding: 1rem;
        margin-bottom: 1.5rem;
        border-radius: 8px;
        border: 1px solid transparent;
    }

    .flash-success {
        background-color: var(--success-bg);
        color: var(--success-color);
        border-color: #c3e6cb;
    }

    .flash-error {
        background-color: var(--error-bg);
        color: var(--error-color);
        border-color: #f5c6cb;
    }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open" data-bs-theme="dark">

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

    <main class="app-main">


        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h3 class="mb-0">Setores</h3>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Setores</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                <?php if ($flash_message): ?>
                <div class="flash-message <?php echo $flash_type === 'success' ? 'flash-success' : 'flash-error'; ?>">
                    <?php echo htmlspecialchars($flash_message); ?>
                </div>
                <?php endif; ?>

                <?php if (!$usuario_supervisor): ?>
                <div class="alert alert-warning" role="alert">
                    <strong>Atenção:</strong> Você tem permissão apenas para visualização. Alterações são restritas a
                    supervisores.
                </div>
                <?php endif; ?>

                <?php if ($usuario_supervisor): ?>
                <!-- Novo Setor -->
                <div style="margin-bottom:1rem;">
                    <button id="createSetorBtn" class="action-btn add-btn"
                        style="padding:0.6rem 1rem;border-radius:6px;">
                        <i class="fa-solid fa-plus"></i> Novo Setor
                    </button>
                </div>
                <?php endif; ?>

                <div class="users-table-card">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Ativo</th>
                                    <?php if ($usuario_supervisor): ?>
                                    <th>Ação</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="setoresTable">
                                <?php foreach ($setores as $setor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($setor['nome']); ?></td>
                                    <td>
                                        <?php if($setor['ativo'] == 'S'): ?>
                                        <span class="badge text-bg-success">Sim</span>
                                        <?php else: ?>
                                        <span class="badge text-bg-danger">Não</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($usuario_supervisor): ?>
                                    <td>
                                        <!-- Editar -->
                                        <button class="action-btn edit-btn" data-id="<?php echo $setor['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($setor['nome']); ?>"
                                            data-ativo="<?php echo htmlspecialchars($setor['ativo']); ?>">
                                            <i class="fa-solid fa-pen-to-square"></i> Editar
                                        </button>
                                        <!-- Alternar ativo/inativo -->
                                        <button class="action-btn" style="background:#6c757d; margin-left:6px;"
                                            data-id="<?php echo $setor['id']; ?>"
                                            data-ativo="<?php echo $setor['ativo']; ?>" class="toggle-btn">
                                            <i class="fa-solid fa-toggle-on"></i>
                                            <?php echo ($setor['ativo'] == 'S') ? 'Inativar' : 'Ativar'; ?>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($usuario_supervisor): ?>
                <h2 class="section-title"
                    style="margin-top: 2.5rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">Vincular
                    Vendedores</h2>
                <div class="users-table-card">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nome</th>
                                    <th>Setor Atual</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody id="unlinkedUsersTable">
                                <?php if (empty($unlinked_users)): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center;">Nenhum vendedor para vincular.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($unlinked_users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($user['setor_atual'] ?: 'Não definido'); ?></td>
                                    <td>
                                        <button class="action-btn add-btn" data-id="<?php echo $user['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($user['nome']); ?>">
                                            <i class="fa-solid fa-plus"></i> Vincular
                                        </button>

                                        <!-- Novo: botão para relacionar vendedores_sistema -->
                                        <button class="action-btn" style="background:#17a2b8; margin-left:6px;"
                                            data-id="<?php echo $user['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($user['nome']); ?>"
                                            class="relacionar-sistemas-btn">
                                            <i class="fa-solid fa-link"></i> Relacionar Sistemas
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php if ($usuario_supervisor): ?>
    <!-- Modal EDITAR SETOR -->
    <div id="editSetorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle" class="modal-title">Editar Setor</h2>
                <span class="close-btn">&times;</span>
            </div>
            <form id="editSetorForm" method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_setor">
                    <input type="hidden" id="editSetorId" name="id">
                    <div class="form-group">
                        <label for="editNome">Nome</label>
                        <input type="text" id="editNome" name="nome" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" id="editAtivo" name="ativo"> Ativo</label>
                    </div>
                </div>
                <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <button type="button" class="btn btn-secondary btn-cancel"
                            style="margin-right:0.5rem;">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal CRIAR SETOR -->
    <div id="createSetorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="addModalTitle" class="modal-title">Novo Setor</h2>
                <span class="close-btn">&times;</span>
            </div>
            <form id="createSetorForm" method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_setor">
                    <div class="form-group">
                        <label for="createNome">Nome (Obrigatório)</label>
                        <input type="text" id="createNome" name="nome" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" id="createAtivo" name="ativo" checked> Ativo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-cancel">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Cadastrar Setor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal VINCULAR VENDEDOR -->
    <div id="vincularVendedorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Vincular Vendedor</h2>
                <span class="close-btn">&times;</span>
            </div>
            <form id="vincularVendedorForm" method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="vincular_vendedor">
                    <input type="hidden" id="vincularVendedorId" name="vendedor_id">
                    <div class="form-group">
                        <label>Vendedor</label>
                        <input type="text" id="vincularVendedorNome" class="form-input" readonly>
                    </div>
                    <div class="form-group">
                        <label for="vincularSetorId">Selecionar Setor</label>
                        <select id="vincularSetorId" name="setor_id" class="form-input" required>
                            <option value="">Selecione um setor...</option>
                            <?php foreach ($setores as $s): ?>
                            <?php if ($s['ativo'] === 'S'): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nome']); ?></option>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-cancel">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar Vínculo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal RELACIONAR SISTEMAS -->
    <div id="relacionarSistemaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Relacionar Vendedor a Sistemas</h2>
                <span class="close-btn">&times;</span>
            </div>
            <form id="relacionarSistemaForm" method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="vincular_vendedor_sistema">
                    <input type="hidden" id="relacionarVendedorId" name="vendedor_id">
                    <div class="form-group">
                        <label>Vendedor</label>
                        <input type="text" id="relacionarVendedorNome" class="form-input" readonly>
                    </div>
                    <div class="form-group">
                        <label>Selecione os sistemas:</label>
                        <div id="sistemasCheckboxList"
                            style="max-height:300px; overflow:auto; border:1px solid #ddd; padding:8px; border-radius:6px;">
                            <?php foreach ($vendedores_sistema as $vs): ?>
                            <div style="margin-bottom:6px;">
                                <label style="font-weight:normal;">
                                    <input type="checkbox" class="sistema-checkbox" name="vendedoresistema_ids[]"
                                        value="<?php echo $vs['id']; ?>">
                                    <?php echo htmlspecialchars($vs['nome'] . ' — ' . $vs['origem'] . ' (' . $vs['cod_intersig'] . ')'); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-cancel">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Relações</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const editModal = document.getElementById('editSetorModal');
        const createModal = document.getElementById('createSetorModal');
        const vincularModal = document.getElementById('vincularVendedorModal');
        const relacionarModal = document.getElementById('relacionarSistemaModal');
        const setoresTable = document.getElementById('setoresTable');
        const createBtn = document.getElementById('createSetorBtn');
        const unlinkedTable = document.getElementById('unlinkedUsersTable');

        const modals = [editModal, createModal, vincularModal, relacionarModal];

        const closeAllModals = () => {
            modals.forEach(m => {
                if (m) m.style.display = 'none';
            });
        };

        // fechar ao clicar em X ou botao cancelar (para todos os modais)
        document.querySelectorAll('.close-btn, .btn-cancel').forEach(btn => {
            btn.addEventListener('click', closeAllModals);
        });

        // fechar ao clicar no overlay (qualquer modal)
        window.addEventListener('click', (e) => {
            if (e.target.classList && e.target.classList.contains('modal')) {
                closeAllModals();
            }
        });

        // Abrir modal de criação
        if (createBtn) {
            createBtn.addEventListener('click', function() {
                if (createModal) {
                    createModal.querySelector('#createNome').value = '';
                    createModal.querySelector('#createAtivo').checked = true;
                    createModal.style.display = 'flex';
                }
            });
        }

        // Abrir modal de edição ao clicar no botão editar da tabela
        if (setoresTable) {
            setoresTable.addEventListener('click', function(e) {
                const btn = e.target.closest('.edit-btn');
                if (btn && editModal) {
                    editModal.querySelector('#editSetorId').value = btn.dataset.id;
                    editModal.querySelector('#editNome').value = btn.dataset.name || '';
                    editModal.querySelector('#editAtivo').checked = (btn.dataset.ativo === 'S');
                    editModal.style.display = 'flex';
                }

                // Alternar ativo/inativo (botões que NÃO têm .edit-btn)
                const toggleBtn = e.target.closest('button[data-id][data-ativo]:not(.edit-btn)');
                if (toggleBtn) {
                    const id = toggleBtn.dataset.id;
                    const atual = toggleBtn.dataset.ativo;
                    const acao = (atual === 'S') ? 'inativar' : 'ativar';
                    Swal.fire({
                        title: 'Tem certeza?',
                        text: `Deseja ${acao} este setor?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sim'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.style.display = 'none';
                            const inputAction = document.createElement('input');
                            inputAction.name = 'action';
                            inputAction.value = 'toggle_active_setor';
                            const inputId = document.createElement('input');
                            inputId.name = 'id';
                            inputId.value = id;
                            form.appendChild(inputAction);
                            form.appendChild(inputId);
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                }
            });
        }

        // Lógica para vincular vendedores
        if (unlinkedTable) {
            unlinkedTable.addEventListener('click', function(e) {
                const btn = e.target.closest('.add-btn');
                if (btn && vincularModal) {
                    vincularModal.querySelector('#vincularVendedorId').value = btn.dataset.id;
                    vincularModal.querySelector('#vincularVendedorNome').value = btn.dataset.name || '';
                    vincularModal.style.display = 'flex';
                }

                // Novo: abrir modal de relacionar sistemas
                const relacionarBtn = e.target.closest('.relacionar-sistemas-btn');
                if (relacionarBtn && relacionarModal) {
                    const vendedorId = relacionarBtn.dataset.id;
                    const vendedorNome = relacionarBtn.dataset.name;

                    // Preencher campos do modal
                    relacionarModal.querySelector('#relacionarVendedorId').value = vendedorId;
                    relacionarModal.querySelector('#relacionarVendedorNome').value = vendedorNome;

                    // Desmarcar todos os checkboxes
                    relacionarModal.querySelectorAll('.sistema-checkbox').forEach(ch => ch.checked =
                        false);

                    // Marcar os que já estão vinculados
                    const sistemasVinculados = <?php echo json_encode($vendedor_sistema_map); ?>;
                    const idsVinculados = sistemasVinculados[vendedorId] || [];
                    idsVinculados.forEach(sid => {
                        const checkbox = relacionarModal.querySelector(
                            '.sistema-checkbox[value="' + sid + '"]');
                        if (checkbox) checkbox.checked = true;
                    });

                    relacionarModal.style.display = 'flex';
                }
            });
        }

        // fechar modais ao clicar fora (overlay)
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeAllModals();
            }
        });
    });
    </script>
    <?php endif; ?>

    <?php require_once '../menu/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
    <script src="../js/adminlte.js"></script>
</body>

</html>