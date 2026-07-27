<?php
require_once '../config/session.php';
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['count' => 0, 'html' => '<a href="#" class="dropdown-item text-center text-muted">Sessão expirada</a>']);
    exit;
}

$userId = $_SESSION['user_id'];

// 1. Contar total de notificações não lidas
$queryContar = "
    SELECT COUNT(*) as total_nao_lidas 
    FROM notificacoes 
    WHERE (usuario_destino IS NULL OR usuario_destino = ?) 
      AND lida = 'N' AND ativo = 'S' AND (data_envio IS NULL OR data_envio <= NOW())
";
$stmtContar = mysqli_prepare($dash, $queryContar);
mysqli_stmt_bind_param($stmtContar, 'i', $userId);
mysqli_stmt_execute($stmtContar);
$resultContar = mysqli_stmt_get_result($stmtContar);
$rowContar = mysqli_fetch_assoc($resultContar);
$totalNotificacoes = $rowContar['total_nao_lidas'];

// 2. Buscar as notificações para gerar o HTML do dropdown
$html = '';
$queryListar = "
    SELECT id, titulo, mensagem, data_criacao 
    FROM notificacoes 
    WHERE (usuario_destino IS NULL OR usuario_destino = ?) 
      AND lida = 'N' AND ativo = 'S' AND (data_envio IS NULL OR data_envio <= NOW())
    ORDER BY data_criacao DESC
    LIMIT 5
";
$stmtListar = mysqli_prepare($dash, $queryListar);
mysqli_stmt_bind_param($stmtListar, 'i', $userId);
mysqli_stmt_execute($stmtListar);
$resultListar = mysqli_stmt_get_result($stmtListar);

if (mysqli_num_rows($resultListar) > 0) {
    while ($not = mysqli_fetch_assoc($resultListar)) {
        $html .= '
        <a href="#" class="dropdown-item notificacao-lida" data-id="' . $not['id'] . '">
            <div class="d-flex">
                <div class="flex-grow-1">
                    <h6 class="fw-bold mb-0">' . htmlspecialchars($not['titulo']) . '</h6>
                    <p class="fs-7 text-muted mb-0">' . substr(htmlspecialchars($not['mensagem']), 0, 50) . '...</p>
                    <small class="text-secondary">' . date('d/m H:i', strtotime($not['data_criacao'])) . '</small>
                </div>
            </div>
        </a>
        <div class="dropdown-divider"></div>
        ';
    }
} else {
    $html = '<a href="#" class="dropdown-item text-center text-muted">Nenhuma notificação nova</a>';
}

// 3. Retornar os dados como JSON
echo json_encode([
    'count' => $totalNotificacoes,
    'html'  => $html
]);
?>