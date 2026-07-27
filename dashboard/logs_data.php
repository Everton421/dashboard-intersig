<?php
require_once '../config/config.php';
require_once '../config/session.php'; 

$usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$dataInicio = $_GET['dataInicio'] ?? '';
$dataFim = $_GET['dataFim'] ?? '';

$query = "SELECT * FROM logs WHERE 1=1";
$params = [];
$types = "";

// Filtro por usuário
if ($usuario > 0) {
    $query .= " AND user_id = ?";
    $params[] = $usuario;
    $types .= "i";
}

// Filtro por data - CORREÇÃO PRINCIPAL AQUI
if (!empty($dataInicio) && !empty($dataFim)) {
    $query .= " AND DATE(acesso) BETWEEN ? AND ?";
    $params[] = $dataInicio;
    $params[] = $dataFim;
    $types .= "ss";
} elseif (!empty($dataInicio)) {
    $query .= " AND DATE(acesso) >= ?";
    $params[] = $dataInicio;
    $types .= "s";
} elseif (!empty($dataFim)) {
    $query .= " AND DATE(acesso) <= ?";
    $params[] = $dataFim;
    $types .= "s";
}

$query .= " ORDER BY id DESC";

$stmt = $dash->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$dados = [];

while ($row = $result->fetch_assoc()) {
    $dados[] = $row;
}

echo json_encode($dados);
?>