<?php
require_once '../config/session.php';
require_once '../config/config.php';

// Restrição de acesso (ex: só user_id = 10)
if ($_SESSION['user_id'] != 10) {
    die("Acesso negado.");
}

$erro = '';
$sucesso = '';
date_default_timezone_set('America/Sao_Paulo');

// Manter os valores do post para preencher o formulário em caso de erro
$titulo_post = $_POST['titulo'] ?? '';
$mensagem_post = $_POST['mensagem'] ?? '';
$destino_post = $_POST['destino'] ?? null;
$data_envio_post = $_POST['data_envio'] ?? '';

// Buscar lista de usuários para o select (precisamos disso antes do POST para o caso de "enviar a todos")
$queryUsuarios = "SELECT codigo, nome, apelido 
                  FROM {$banco_publico}.cad_vend 
                  WHERE codigo > 0 AND ativo = 'S' AND type IS NOT NULL
                  ORDER BY nome";
$resultUsuarios = mysqli_query($dash, $queryUsuarios);
$usuarios = [];
while ($row = mysqli_fetch_assoc($resultUsuarios)) {
    $usuarios[] = $row;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($titulo_post);
    $mensagem = trim($mensagem_post);
    $destino = !empty($destino_post) ? (int)$destino_post : null;
    $data_envio = !empty($data_envio_post) ? $data_envio_post : null;

    // Validação
    if (empty($titulo) || empty($mensagem)) {
        $erro = "Título e mensagem são obrigatórios.";
    } elseif ($data_envio && !strtotime($data_envio)) {
        $erro = "Data/hora de envio inválida.";
    } else {
        // Preparar data_envio no formato MySQL (Y-m-d H:i:s)
        $data_envio_mysql = $data_envio ? date('Y-m-d H:i:s', strtotime($data_envio)) : null;

        // --- INÍCIO DA LÓGICA MODIFICADA ---

        if ($destino !== null) {
            // CASO 1: ENVIAR PARA UM USUÁRIO ESPECÍFICO (Lógica original)
            $query = "INSERT INTO notificacoes (titulo, mensagem, usuario_destino, data_envio) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($dash, $query);
            mysqli_stmt_bind_param($stmt, 'ssis', $titulo, $mensagem, $destino, $data_envio_mysql);

            if (mysqli_stmt_execute($stmt)) {
                $sucesso = "Notificação enviada para o usuário selecionado!";
            } else {
                $erro = "Erro ao salvar para usuário específico: " . mysqli_error($dash);
            }
        } else {
            // CASO 2: ENVIAR PARA TODOS OS USUÁRIOS (Nova lógica)
            
            // Usar transação para garantir que todos os inserts sejam bem-sucedidos ou nenhum seja.
            mysqli_begin_transaction($dash);

            try {
                // Preparar a query UMA VEZ fora do loop para melhor performance
                $query = "INSERT INTO notificacoes (titulo, mensagem, usuario_destino, data_envio) VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($dash, $query);

                $usuarios_enviados = 0;
                foreach ($usuarios as $usuario) {
                    $id_usuario_atual = $usuario['codigo'];
                    mysqli_stmt_bind_param($stmt, 'ssis', $titulo, $mensagem, $id_usuario_atual, $data_envio_mysql);
                    
                    if (!mysqli_stmt_execute($stmt)) {
                        // Se um único insert falhar, lança uma exceção para cancelar tudo
                        throw new Exception(mysqli_stmt_error($stmt));
                    }
                    $usuarios_enviados++;
                }

                // Se o loop terminou sem erros, confirma a transação
                mysqli_commit($dash);
                $sucesso = "Notificação enviada com sucesso para {$usuarios_enviados} usuários!";

            } catch (Exception $e) {
                // Se ocorreu algum erro, desfaz todas as operações
                mysqli_rollback($dash);
                $erro = "Erro ao enviar notificações em massa. Nenhuma notificação foi enviada. Detalhe: " . $e->getMessage();
            }
        }

        // --- FIM DA LÓGICA MODIFICADA ---

        // Se deu sucesso, limpa os campos para um novo cadastro
        if ($sucesso) {
            $titulo_post = $mensagem_post = $data_envio_post = '';
            $destino_post = null;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Cadastrar Notificação</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-bell"></i> Cadastrar Notificação</h2>
            <a href="notificacoes_lista.php" class="btn btn-outline-secondary">
                <i class="bi bi-list"></i> Gerenciar
            </a>
        </div>

        <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($erro) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($sucesso) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <!-- No seu formulário, use as variáveis com sufixo _post para manter os dados -->
                <form method="POST">
                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título</label>
                        <input type="text" name="titulo" id="titulo" class="form-control"
                            value="<?= htmlspecialchars($titulo_post) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="mensagem" class="form-label">Mensagem</label>
                        <textarea name="mensagem" id="mensagem" class="form-control" rows="5"
                            required><?= htmlspecialchars($mensagem_post) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="destino" class="form-label">Destinatário</label>
                        <select name="destino" id="destino" class="form-select select2-usuarios">
                            <option value=""></option> <!-- obrigatório para allowClear -->
                            <?php foreach ($usuarios as $u): 
                            $nomeExib = !empty($u['apelido']) ? $u['apelido'] : $u['nome'];
                            $nomeExib = htmlspecialchars($nomeExib . " (ID: {$u['codigo']})");
                        ?>
                            <option value="<?= (int)$u['codigo'] ?>"
                                <?= ($destino_post == $u['codigo']) ? 'selected' : '' ?>>
                                <?= $nomeExib ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Deixe em branco para enviar a todos os usuários ativos.</div>
                    </div>
                    <!-- ... resto do form ... -->
                    <div class="mb-3">
                        <label for="data_envio" class="form-label">Programar envio (opcional)</label>
                        <input type="datetime-local" name="data_envio" id="data_envio" class="form-control"
                            value="<?= htmlspecialchars($data_envio_post) ?>">
                        <div class="form-text">Se deixado em branco, a notificação será exibida imediatamente.</div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-secondary me-md-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Enviar Notificação
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    $(document).ready(function() {
        $('.select2-usuarios').select2({
            placeholder: "Selecione um destinatário (ou deixe em branco para todos)",
            allowClear: true,
            theme: "bootstrap-5"
        });
    });
    </script>

</body>

</html>