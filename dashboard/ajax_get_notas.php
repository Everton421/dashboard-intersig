<?php
// ajax_get_notas.php

// --- Conexão com o Banco de Dados (copie do seu arquivo principal) ---
$servidor_vendas = '192.168.8.3';
$usuario_vendas  = 'intersig';
$senha_vendas    = 'ewqiop2017';
$dbname_vendas   = 'mesquita_vendas';

$dash_vendas = mysqli_connect($servidor_vendas, $usuario_vendas, $senha_vendas, $dbname_vendas);
if (!$dash_vendas) {
    die("Falha na conexão com o banco de vendas: " . mysqli_connect_error());
}
mysqli_set_charset($dash_vendas, "utf8");

// --- Funções auxiliares (copie do seu arquivo principal) ---
function convert_date_br_to_sql($date_input) {
    if (empty($date_input)) return null;
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date_input, $matches)) {
        return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
    }
    return null;
}

// --- Parâmetros recebidos do DataTables ---
$draw = $_POST['draw'] ?? 0;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';
$orderColumnIndex = $_POST['order'][0]['column'] ?? 2; // Default to date column
$orderDir = $_POST['order'][0]['dir'] ?? 'desc'; // Default to descending

// --- Filtros personalizados ---
$filial = $_POST['filial'] ?? '';
$data_inicio_raw = $_POST['data_inicio'] ?? '';
$data_fim_raw = $_POST['data_fim'] ?? '';

// Mapeamento de índice de coluna para nome de coluna no DB para ordenação
$columns = [
    0 => 'n.CODIGO',
    1 => 'n.FILIAL',
    2 => 'n.DATA_SAIDA_ENTRADA',
    3 => 'n.NUMERO_NF',
    4 => 'n.TOTAL_NF'
];
$orderColumn = $columns[$orderColumnIndex] ?? 'n.DATA_SAIDA_ENTRADA';


// --- Construção da Query ---
$baseQuery = "FROM cad_nf n LEFT JOIN series s ON (s.codigo = n.serie AND s.filial = n.filial)";
$where = " WHERE s.consid_sped = 'S' AND n.cancelada = 'N' ";
$params = [];
$types = '';

// Adiciona filtros personalizados
if (!empty($filial)) {
    $where .= " AND n.FILIAL = ? ";
    $params[] = $filial;
    $types .= 's';
}
$data_inicio = convert_date_br_to_sql($data_inicio_raw);
if ($data_inicio) {
    $where .= " AND n.DATA_SAIDA_ENTRADA >= ? ";
    $params[] = $data_inicio;
    $types .= 's';
}
$data_fim = convert_date_br_to_sql($data_fim_raw);
if ($data_fim) {
    $where .= " AND n.DATA_SAIDA_ENTRADA <= ? ";
    $params[] = $data_fim;
    $types .= 's';
}

// Adiciona filtro de busca global
if (!empty($searchValue)) {
    $searchString = "%" . $searchValue . "%";
    $where .= " AND (n.CODIGO LIKE ? OR n.FILIAL LIKE ? OR n.NUMERO_NF LIKE ?) ";
    $params = array_merge($params, [$searchString, $searchString, $searchString]);
    $types .= 'sss';
}


// --- Contagem de Registros ---
// Total de registros sem filtro
$stmtTotal = $dash_vendas->prepare("SELECT COUNT(n.CODIGO) as total " . $baseQuery . " WHERE s.consid_sped = 'S' AND n.cancelada = 'N'");
$stmtTotal->execute();
$recordsTotal = $stmtTotal->get_result()->fetch_assoc()['total'];

// Total de registros com filtro (para a paginação)
$sqlFiltered = "SELECT COUNT(n.CODIGO) as total " . $baseQuery . $where;
$stmtFiltered = $dash_vendas->prepare($sqlFiltered);
if (!empty($params)) {
    $stmtFiltered->bind_param($types, ...$params);
}
$stmtFiltered->execute();
$recordsFiltered = $stmtFiltered->get_result()->fetch_assoc()['total'];


// --- Busca dos Dados Paginados ---
$sqlData = "SELECT n.CODIGO, n.FILIAL, n.DATA_SAIDA_ENTRADA, n.NUMERO_NF, n.TOTAL_NF " . $baseQuery . $where . " ORDER BY " . $orderColumn . " " . $orderDir . " LIMIT ? OFFSET ?";
$stmtData = $dash_vendas->prepare($sqlData);

$dataParams = $params;
$dataParams[] = $length;
$dataParams[] = $start;
$dataTypes = $types . 'ii'; // adiciona dois inteiros para LIMIT e OFFSET

if (!empty($dataParams)) {
    $stmtData->bind_param($dataTypes, ...$dataParams);
}
$stmtData->execute();
$resultData = $stmtData->get_result();
$data = $resultData->fetch_all(MYSQLI_ASSOC);


// --- Monta a Resposta JSON ---
$response = [
    "draw" => intval($draw),
    "recordsTotal" => intval($recordsTotal),
    "recordsFiltered" => intval($recordsFiltered),
    "data" => $data
];

header('Content-Type: application/json');
echo json_encode($response);