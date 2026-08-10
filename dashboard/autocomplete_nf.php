<?php
require_once '../config/config.php'; // Ajuste o caminho se necessário

header('Content-Type: application/json');

// Pega o termo de busca enviado pelo jQuery UI Autocomplete
$term = $_GET['term'] ?? '';

if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

$suggestions = [];
$searchTerm = $term . '%'; // Busca por números de nota que começam com o termo digitado

$sql = "SELECT NUMERO_NF, DATA_EMISSAO 
        FROM {$banco_vendas}.cad_nf 
        WHERE NUMERO_NF LIKE ? 
        ORDER BY DATA_EMISSAO DESC, NUMERO_NF DESC
        LIMIT 10";

if ($stmt = mysqli_prepare($dash, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $searchTerm);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        // Formata a data para exibição
        $data_formatada = date('d/m/Y', strtotime($row['DATA_EMISSAO']));

        // O formato 'label' e 'value' é padrão para o jQuery UI Autocomplete
        $suggestions[] = [
            'label' => 'NF: ' . $row['NUMERO_NF'] . '  (Emissão: ' . $data_formatada . ')',
            'value' => $row['NUMERO_NF']
        ];
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($dash);

echo json_encode($suggestions);