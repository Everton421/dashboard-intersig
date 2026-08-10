<?php
// --- NO CADASTRO DA SERIE ELE PEGA OS VALORES SOMENTE O QUE ESTIVER MARCADA PARA ENVIAR SPED ---
require_once '../config/config.php';
require_once '../config/session.php'; 

date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION["user_name"])) {
    // Redireciona para o login se não estiver logado
    header("Location: ../index.php");
    exit();
}


$nome_usuario_logado = htmlspecialchars($_SESSION["user_name"]);
$id_usuario_logado = (int) $_SESSION["user_id"];

// --- montar URL completa da página atual ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$page_url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');


$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$user_agent = mb_substr($user_agent, 0, 255); 


if (isset($dash) && $stmt = $dash->prepare(
    "INSERT INTO logs (user_id, user_name, pagina_url, acesso, user_agent) VALUES (?, ?, ?, NOW(),  ?)"
)) {
    $stmt->bind_param("isss",
        $id_usuario_logado,
        $nome_usuario_logado,
        $page_url,
        $user_agent
    );
    $stmt->execute();
    // opcional: checar erro
   //  if ($stmt->errno) { error_log("Log insert error: " . $stmt->error); }
   // $stmt->close();
} else {
    // opcional: tratar erro de prepare (por exemplo registrar em log de erro)
    // error_log("Prepare failed for log insert: " . ($dash->error ?? 'no conn'));
};


$flash_message = '';
$flash_type = '';

// VERIFICAR SE O USUÁRIO LOGADO É SUPERVISOR
$usuario_supervisor = false;
$sql_supervisor = "SELECT supervisor FROM {$banco_publico}.cad_vend WHERE codigo = ?";
$stmt_supervisor = mysqli_prepare($dash, $sql_supervisor);
mysqli_stmt_bind_param($stmt_supervisor, 'i', $id_usuario_logado);
mysqli_stmt_execute($stmt_supervisor);
$result_supervisor = mysqli_stmt_get_result($stmt_supervisor);

if ($row_supervisor = mysqli_fetch_assoc($result_supervisor)) {
    $usuario_supervisor = ($row_supervisor['supervisor'] === 'S');
}

// PROCESSA AS SUBMISSÕES DE FORMULÁRIO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Bloqueio de segurança: Apenas supervisores podem processar alterações.
    if (!$usuario_supervisor) {
        $_SESSION['flash_message'] = "Acesso negado! Você não tem permissão para realizar alterações.";
        $_SESSION['flash_type'] = 'error';
        header("Location: usuarios.php");
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    
    // --- AÇÃO: EDITAR USUÁRIO EXISTENTE ---
    if ($action === 'edit_user' && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $new_email = mysqli_real_escape_string($dash, $_POST['new_email']);
        $new_password = $_POST['new_password'];

        $update_fields = ["email = ?"];
        $params = [$new_email];
        $types = "s";

        if (!empty($new_password)) {
            $update_fields[] = "password = ?";
            $params[] = md5($new_password); // Lembre-se que md5 é inseguro para senhas novas.
            $types .= "s";
        }
        $params[] = $user_id;
        $types .= "i";
        
        $sql_update = "UPDATE {$banco_publico}.cad_vend SET " . implode(', ', $update_fields) . " WHERE codigo = ?";
        $stmt = mysqli_prepare($dash, $sql_update);
        mysqli_stmt_bind_param($stmt, $types, ...$params);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_message'] = "Usuário atualizado com sucesso!";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = "Erro ao atualizar: " . mysqli_error($dash);
            $_SESSION['flash_type'] = 'error';
        }
    }
    
    // --- AÇÃO: VINCULAR NOVO USUÁRIO ---
    elseif ($action === 'add_user' && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $new_email = mysqli_real_escape_string($dash, $_POST['new_email']);
        $new_password = $_POST['new_password'];

        if (empty($new_email) || empty($new_password)) {
            $_SESSION['flash_message'] = "Email e senha são obrigatórios para vincular um usuário.";
            $_SESSION['flash_type'] = 'error';
        } else {
            $password_md5 = md5($new_password);
            
            $sql_add = "UPDATE {$banco_publico}.cad_vend SET email = ?, password = ?, type = 'contabil' WHERE codigo = ?";
            $stmt = mysqli_prepare($dash, $sql_add);
            mysqli_stmt_bind_param($stmt, 'ssi', $new_email, $password_md5, $user_id);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_message'] = "Usuário vinculado com sucesso!";
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = "Erro ao vincular usuário: " . mysqli_error($dash);
                $_SESSION['flash_type'] = 'error';
            }
        }
    }

    // --- AÇÃO: INATIVAR USUÁRIO ---
    elseif ($action === 'inactivate_user') {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

        if ($user_id > 0) {
            $sql_inactivate = "UPDATE {$banco_publico}.cad_vend SET type = NULL WHERE codigo = ?";
            $stmt = mysqli_prepare($dash, $sql_inactivate);
            mysqli_stmt_bind_param($stmt, 'i', $user_id);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_message'] = "Usuário inativado com sucesso! Ele pode ser vinculado novamente.";
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = "Erro ao inativar usuário: " . mysqli_error($dash);
                $_SESSION['flash_type'] = 'error';
            }
        } else {
            $_SESSION['flash_message'] = "Erro: ID do usuário inválido para inativação.";
            $_SESSION['flash_type'] = 'error';
        }
    }
    
    header("Location: usuarios.php");
    exit();
}

// Pega e limpa a flash message da sessão para exibição
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// BUSCA DE DADOS PARA AS TABELAS
$managed_users = [];
$sql_managed = "SELECT codigo, apelido, email,
                CASE 
                    WHEN type = 'user' THEN 'Admin'
                    ELSE type
                END as type 
              FROM {$banco_publico}.cad_vend 
              WHERE type IN ('user', 'estoque', 'consulta' , 'contabil') 
              ORDER BY apelido ASC";
$res_managed = mysqli_query($dash, $sql_managed);
if ($res_managed) while ($row = mysqli_fetch_assoc($res_managed)) $managed_users[] = $row;

$unlinked_users = [];
if ($usuario_supervisor) { // Apenas busca usuários não vinculados se for supervisor
    $sql_unlinked = "SELECT codigo, apelido, email FROM {$banco_publico}.cad_vend 
                     WHERE (type NOT IN ('user', 'estoque', 'consulta' , 'contabil') OR type IS NULL) AND ativo = 'S' AND tipo <> 'V' ORDER BY apelido ASC";
    $res_unlinked = mysqli_query($dash, $sql_unlinked);
    if ($res_unlinked) while ($row = mysqli_fetch_assoc($res_unlinked)) $unlinked_users[] = $row;
}
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>USUÁRIO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="../css/adminlte.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">

    <style>
    /* Reutiliza o estilo da Dashboard para consistência */
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
        background-color: #fff;
        margin: auto;
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        width: 90%;
        max-width: 500px;
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
        color: #000000ff;
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

    <main class="app-main">


        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h3 class="mb-0">Usuários</h3>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Usuários</li>
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

                <div class="users-table-card">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nome (Apelido)</th>
                                    <th>Email</th>
                                    <th>Tipo</th>
                                    <?php if ($usuario_supervisor): ?>
                                    <th>Ação</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="managedUsersTable">
                                <?php foreach ($managed_users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['apelido']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($user['type'])); ?></td>
                                    <?php if ($usuario_supervisor): ?>
                                    <td>
                                        <button class="action-btn edit-btn" data-id="<?php echo $user['codigo']; ?>"
                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                            data-name="<?php echo htmlspecialchars($user['apelido']); ?>">
                                            <i class="fa-solid fa-pen-to-square"></i> Editar
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
                    Usuários</h2>
                <div class="users-table-card">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Apelido</th>
                                    <th>Email Atual</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody id="unlinkedUsersTable">
                                <?php if (empty($unlinked_users)): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center;">Nenhum usuário para vincular.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($unlinked_users as $user): ?>
                                <tr>
                                    <td><?php echo $user['codigo']; ?></td>
                                    <td><?php echo htmlspecialchars($user['apelido']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email'] ?: 'Não definido'); ?></td>
                                    <td>
                                        <button class="action-btn add-btn" data-id="<?php echo $user['codigo']; ?>"
                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                            data-name="<?php echo htmlspecialchars($user['apelido']); ?>">
                                            <i class="fa-solid fa-plus"></i> Vincular
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
    <!-- Modal de Edição -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle" class="modal-title">Editar Usuário</h2>
                <span class="close-btn">&times;</span>
            </div>
            <form id="editUserForm" method="POST" action="usuarios.php">
                <div class="modal-body">
                    <input type="hidden" id="editAction" name="action" value="edit_user">
                    <input type="hidden" id="userId" name="user_id">
                    <div class="form-group">
                        <label for="newEmail">Email</label>
                        <input type="email" id="newEmail" name="new_email" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="newPassword">Nova Senha</label>
                        <input type="password" id="newPassword" name="new_password" class="form-input"
                            placeholder="Deixe em branco para não alterar">
                    </div>
                </div>
                <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" id="inactivateBtn" class="btn btn-danger"
                        style="background-color:#dc3545; color:white;">Inativar Usuário</button>
                    <div>
                        <button type="button" class="btn btn-secondary btn-cancel"
                            style="margin-right: 0.5rem;">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Adição/Vínculo -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="addModalTitle" class="modal-title">Vincular Usuário</h2>
                <span class="close-btn">&times;</span>
            </div>
            <form id="addUserForm" method="POST" action="usuarios.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    <input type="hidden" id="addUserId" name="user_id">
                    <div class="form-group">
                        <label for="addEmail">Email (Obrigatório)</label>
                        <input type="email" id="addEmail" name="new_email" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="addPassword">Senha (Obrigatória)</label>
                        <input type="password" id="addPassword" name="new_password" class="form-input" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-cancel">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Vincular Usuário</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const editModal = document.getElementById('editUserModal');
        const addModal = document.getElementById('addUserModal');
        const managedTable = document.getElementById('managedUsersTable');
        const unlinkedTable = document.getElementById('unlinkedUsersTable');

        const closeAllModals = () => {
            if (editModal) editModal.style.display = 'none';
            if (addModal) addModal.style.display = 'none';
        };

        document.querySelectorAll('.close-btn, .btn-cancel').forEach(btn => {
            btn.addEventListener('click', closeAllModals);
        });

        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                closeAllModals();
            }
        });

        if (managedTable) {
            managedTable.addEventListener('click', function(e) {
                const button = e.target.closest('.edit-btn');
                if (button) {
                    editModal.querySelector('#userId').value = button.dataset.id;
                    editModal.querySelector('#newEmail').value = button.dataset.email;
                    editModal.querySelector('#modalTitle').textContent = 'Editar Usuário: ' + button
                        .dataset.name;
                    editModal.querySelector('#newPassword').value = '';
                    editModal.querySelector('#editAction').value = 'edit_user';
                    editModal.style.display = 'flex';
                }
            });
        }

        const inactivateBtn = document.getElementById('inactivateBtn');
        if (inactivateBtn) {
            inactivateBtn.addEventListener('click', function() {
                Swal.fire({
                    title: 'Tem certeza?',
                    text: "O usuário será inativado e perderá o acesso. Esta ação pode ser revertida.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sim, inativar!',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.getElementById('editUserForm');
                        form.querySelector('#editAction').value = 'inactivate_user';
                        form.submit();
                    }
                });
            });
        }

        if (unlinkedTable) {
            unlinkedTable.addEventListener('click', function(e) {
                const button = e.target.closest('.add-btn');
                if (button) {
                    addModal.querySelector('#addUserId').value = button.dataset.id;
                    addModal.querySelector('#addEmail').value = button.dataset.email;
                    addModal.querySelector('#addModalTitle').textContent = 'Vincular: ' + button.dataset
                        .name;
                    addModal.querySelector('#addPassword').value = '';
                    addModal.style.display = 'flex';
                }
            });
        }
    });
    </script>
    <?php endif; ?>

    <?php require_once '../menu/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
    <script src="../js/adminlte.js"></script>
</body>

</html>