<?php
require_once '../config/session.php';
require_once '../config/config.php';

// Definir o cabeçalho da resposta como JSON
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $notId = intval($_POST['id']);
    $userId = $_SESSION['user_id'];

    // Esta consulta é para garantir que a notificação é para o usuário ou é geral (broadcast)
    $queryVerificar = "
        SELECT id FROM notificacoes 
        WHERE id = ? 
          AND (usuario_destino IS NULL OR usuario_destino = ?)
          AND lida = 'N'
    ";
    $stmtVerificar = mysqli_prepare($dash, $queryVerificar);
    mysqli_stmt_bind_param($stmtVerificar, 'ii', $notId, $userId);
    mysqli_stmt_execute($stmtVerificar);
    $resultVerificar = mysqli_stmt_get_result($stmtVerificar);

    if (mysqli_num_rows($resultVerificar) > 0) {
        // A notificação existe e pode ser marcada como lida por este usuário.
        $queryMarcar = "UPDATE notificacoes SET lida = 'S', data_leitura = NOW() WHERE id = ?";
        $stmtMarcar = mysqli_prepare($dash, $queryMarcar);
        mysqli_stmt_bind_param($stmtMarcar, 'i', $notId);

        if (mysqli_stmt_execute($stmtMarcar)) {
            // Recalcular o total de notificações não lidas para retornar ao front-end
            $queryContar = "
                SELECT COUNT(*) as total_nao_lidas 
                FROM notificacoes 
                WHERE (usuario_destino IS NULL OR usuario_destino = ?) 
                  AND lida = 'N' AND ativo = 'S' AND (data_envio IS NULL OR data_envio <= NOW())";
            $stmtContar = mysqli_prepare($dash, $queryContar);
            mysqli_stmt_bind_param($stmtContar, 'i', $userId);
            mysqli_stmt_execute($stmtContar);
            $resultContar = mysqli_stmt_get_result($stmtContar);
            $rowContar = mysqli_fetch_assoc($resultContar);
            
            echo json_encode(['success' => true, 'new_count' => $rowContar['total_nao_lidas']]);

        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao marcar como lida']);
        }
    } else {
        // Notificação não encontrada, já lida, ou não pertence ao usuário.
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Notificação não encontrada ou já lida']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requisição inválida']);
}
?>