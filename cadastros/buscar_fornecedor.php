<?php

require_once '../config/config.php';
require_once '../config/session.php'; 




header('Content-Type: application/json');

// Conexão com o banco de dados

mysqli_set_charset($dash, "utf8");

$q = $_GET['term'] ?? $_GET['q'] ?? '';
$q = trim($q);

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

// Evita SQL Injection
$q = mysqli_real_escape_string($dash, $q);

// Busca no banco de dados
$sql = "SELECT codigo, razao_social, nome_fantasia
        FROM " . $banco_publico . ".cad_forn
        WHERE razao_social LIKE '%$q%' OR nome_fantasia LIKE '%$q%'
        ORDER BY razao_social 
        LIMIT 10";

$result = mysqli_query($dash, $sql);

if (!$result) {
    echo json_encode(['error' => mysqli_error($dash)]);
    exit;
}

// Prepara o retorno em JSON
$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $texto = htmlspecialchars($row['codigo']) . " - " . htmlspecialchars($row['razao_social']) 
             . (!empty($row['nome_fantasia']) ? " (" . htmlspecialchars($row['nome_fantasia']) . ")" : '');
    
    $data[] = [
        'id' => $row['codigo'],
        'codigo' => htmlspecialchars($row['codigo']),
        'value' => $texto,
        'label' => $texto,
        'fornecedor' => htmlspecialchars($row['razao_social']) 
    ];
}

echo json_encode($data);