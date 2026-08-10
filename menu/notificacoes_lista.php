<?php
require_once '../config/session.php';
require_once '../config/config.php';

// Restrição de acesso
if ($_SESSION['user_id'] != 10) {
    die("Acesso negado.");
}

// Ação: Excluir
if (isset($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    mysqli_query($dash, "DELETE FROM notificacoes WHERE id = $id");
    header("Location: notificacoes_lista.php?msg=excluida");
    exit;
}

// Ação: Editar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_id'])) {
    $id = (int)$_POST['editar_id'];
    $titulo = trim($_POST['titulo'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');
    $destino = !empty($_POST['destino']) ? (int)$_POST['destino'] : null;
    $data_envio_input = $_POST['data_envio'] ?? null;
    $data_envio = $data_envio_input ? date('Y-m-d H:i:s', strtotime($data_envio_input)) : null;

    if ($titulo && $mensagem) {
        $stmt = mysqli_prepare($dash, "UPDATE notificacoes SET titulo = ?, mensagem = ?, usuario_destino = ?, data_envio = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ssisi', $titulo, $mensagem, $destino, $data_envio, $id);
        mysqli_stmt_execute($stmt);
        header("Location: notificacoes_lista.php?msg=editada");
        exit;
    }
}

// Ação: Alternar lida/não lida
if (isset($_GET['toggle_lida'])) {
    $id = (int)$_GET['toggle_lida'];
    $res = mysqli_query($dash, "SELECT lida FROM notificacoes WHERE id = $id");
    if ($row = mysqli_fetch_assoc($res)) {
        $novo = $row['lida'] == 'S' ? 'N' : 'S';
        $data_leitura = $novo == 'S' ? 'NOW()' : 'NULL';
        mysqli_query($dash, "UPDATE notificacoes SET lida = '$novo', data_leitura = $data_leitura WHERE id = $id");
    }
    header("Location: notificacoes_lista.php");
    exit;
}

// Buscar notificações
$query = "
    SELECT n.id, n.titulo, n.mensagem, n.usuario_destino, n.lida, n.data_criacao, n.data_envio, n.ativo,
           v.nome, v.apelido
    FROM notificacoes n
    LEFT JOIN {$banco_publico}.cad_vend v ON v.codigo = n.usuario_destino
    ORDER BY n.data_criacao DESC
";
$result = mysqli_query($dash, $query);

// Buscar todos os usuários para os selects
$usuariosRes = mysqli_query($dash, "SELECT codigo, nome, apelido FROM {$banco_publico}.cad_vend ORDER BY nome");
$listaUsuarios = [];
while ($u = mysqli_fetch_assoc($usuariosRes)) {
    $listaUsuarios[] = $u;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Gerenciar Notificações</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
</head>

<body class="bg-light">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-bell-fill"></i> Gerenciar Notificações</h2>
            <a href="notificacoes_cadastro.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nova Notificação
            </a>
        </div>

        <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php
            $msgs = [
                'excluida' => 'Notificação excluída com sucesso!',
                'editada' => 'Notificação atualizada com sucesso!'
            ];
            echo htmlspecialchars($msgs[$_GET['msg']] ?? 'Operação realizada.');
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Mensagem</th>
                        <th>Destino</th>
                        <th>Envio</th>
                        <th>Criação</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($not = mysqli_fetch_assoc($result)): ?>
                    <tr class="<?= $not['lida'] == 'S' ? 'text-muted' : '' ?>">
                        <td><?= $not['id'] ?></td>
                        <td><strong><?= htmlspecialchars($not['titulo']) ?></strong></td>
                        <td><?= htmlspecialchars(substr($not['mensagem'], 0, 50)) . (strlen($not['mensagem']) > 50 ? '...' : '') ?>
                        </td>
                        <td>
                            <?php if ($not['usuario_destino'] === null): ?>
                            <span class="badge bg-secondary">Todos</span>
                            <?php else: ?>
                            <?= htmlspecialchars(!empty($not['apelido']) ? $not['apelido'] : $not['nome']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($not['data_envio']): ?>
                            <span class="badge bg-info"><?= date('d/m H:i', strtotime($not['data_envio'])) ?></span>
                            <?php else: ?>
                            <span class="badge bg-success">Imediato</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($not['data_criacao'])) ?></td>
                        <td>
                            <?php
                        $now = new DateTime();
                        $data_envio = $not['data_envio'] ? new DateTime($not['data_envio']) : null;

                        if ($not['ativo'] != 'S') {
                            echo '<span class="badge bg-secondary">Desativada</span>';
                        } elseif ($data_envio && $data_envio > $now) {
                            echo '<span class="badge bg-warning text-dark">Agendada</span>';
                        } else {
                            echo '<span class="badge bg-success">Ativa</span>';
                        }
                        ?>
                        </td>
                        <td class="d-flex gap-1">
                            <a href="?toggle_lida=<?= $not['id'] ?>"
                                class="btn btn-sm <?= $not['lida'] == 'S' ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                title="<?= $not['lida'] == 'S' ? 'Marcar como não lida' : 'Marcar como lida' ?>">
                                <i class="bi <?= $not['lida'] == 'S' ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#editModal<?= $not['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="?excluir=<?= $not['id'] ?>" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Tem certeza que deseja excluir esta notificação?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>

                    <!-- Modal de Edição -->
                    <div class="modal fade" id="editModal<?= $not['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Editar Notificação #<?= $not['id'] ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="editar_id" value="<?= $not['id'] ?>">

                                    <div class="mb-3">
                                        <label>Título</label>
                                        <input type="text" name="titulo" class="form-control"
                                            value="<?= htmlspecialchars($not['titulo']) ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label>Mensagem</label>
                                        <textarea name="mensagem" class="form-control" rows="4"
                                            required><?= htmlspecialchars($not['mensagem']) ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label>Destino</label>
                                        <select name="destino" class="form-select select2-usuarios">
                                            <option value=""></option>
                                            <?php foreach ($listaUsuarios as $u): 
                                            $nomeExib = !empty($u['apelido']) ? $u['apelido'] : $u['nome'];
                                            $selected = ($u['codigo'] == $not['usuario_destino']) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $u['codigo'] ?>" <?= $selected ?>>
                                                <?= htmlspecialchars($nomeExib . " (ID: {$u['codigo']})") ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label>Programar envio (opcional)</label>
                                        <input type="datetime-local" name="data_envio" class="form-control"
                                            value="<?= $not['data_envio'] ? date('Y-m-d\TH:i', strtotime($not['data_envio'])) : '' ?>">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <?php if (mysqli_num_rows($result) == 0): ?>
            <div class="text-center py-4 text-muted">Nenhuma notificação cadastrada.</div>
            <?php endif; ?>
        </div>

        <div class="mt-4">
            <a href="index.php" class="btn btn-secondary">← Voltar ao Dashboard</a>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    $(document).ready(function() {
        // Inicializa Select2 nos modais quando abertos
        $('.modal').on('shown.bs.modal', function() {
            var select = $(this).find('.select2-usuarios');
            if (!select.hasClass('select2-hidden-accessible')) {
                select.select2({
                    placeholder: "Selecione um destinatário",
                    allowClear: true,
                    theme: "bootstrap-5"
                });
            }
        });

        // Destroi ao fechar para evitar conflitos
        $('.modal').on('hidden.bs.modal', function() {
            $(this).find('.select2-usuarios').select2('destroy');
        });
    });
    </script>

</body>

</html>