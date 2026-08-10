<?php

require_once '../config/config.php';
require_once '../config/session.php'; 

header('Content-Type: application/json; charset=utf-8');

$filtros = $_POST['filtros'] ?? [];
$colunas = $_POST['colunas'] ?? [];
$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 25);

$colunasPermitidas = [
  'codigo', 'tipo',  'nome', 'serie', 'mod_sintegra', 'modelo', 'origem', 'ult_nf', 'filial', 'consid_sped', 'ativa'
];

$colunasValidas = array_values(array_intersect($colunas, $colunasPermitidas));
if (empty($colunasValidas)) {
    $colunasValidas = ['codigo', 'nome', 'serie', 'mod_sintegra', 'modelo', 'origem', 'ult_nf', 'filial', 'consid_sped', 'ativa'];
}

$selectCampos = [
    "codigo",
    "nome",
    "serie",
    "mod_sintegra",
    "modelo",
    "CASE origem 
        WHEN 'P' THEN 'PROPRIO'
        WHEN 'T' THEN 'TERCEIRO'
        ELSE 'NÃO ESPC.' END AS origem",
    "ult_nf",
    "filial",
    "CASE consid_sped WHEN 'N' THEN 'NÃO' WHEN 'S' THEN 'SIM' ELSE 'NÃO' END AS consid_sped",
    "ativa"
];

$select = implode(', ', $selectCampos);

// WHERE
$where = "1=1";
$params = [];
$types = "";


if (!empty($filtros['consid_sped'])) {
  $where .= " AND consid_sped = ?";
  $params[] = $filtros['consid_sped'];
  $types .= "s";
}

if (!empty($filtros['filial'])) {
  $where .= " AND filial = ?";
  $params[] = $filtros['filial'];
  $types .= "s";
}

if (!empty($filtros['origem'])) {
  $where .= " AND origem = ?";
  $params[] = $filtros['origem'];
  $types .= "s";
}

if (!empty($filtros['ativa'])) {
  $where .= " AND ativa = ?";
  $params[] = $filtros['ativa'];
  $types .= "s";
}

if (!empty($filtros['nome'])) {
  $where .= " AND nome LIKE ?";
  $params[] = "%" . $filtros['nome'] . "%";
  $types .= "s";
}


// Total
$queryTotal = "SELECT COUNT(*) AS total FROM {$banco_vendas}.series";
$resultTotal = $dash->query($queryTotal);
$rowTotal = $resultTotal->fetch_assoc();
$total = $rowTotal['total'];

// Total filtrado
$queryFiltrada = "SELECT COUNT(*) AS total FROM {$banco_vendas}.series WHERE $where";
$stmtCount = $dash->prepare($queryFiltrada);
if ($types && !empty($params)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$rowCount = $resultCount->fetch_assoc();
$totalFiltrado = $rowCount['total'];

// Query principal
$query = "
  SELECT $select
  FROM {$banco_vendas}.series
  WHERE $where
  ORDER BY nome ASC
  LIMIT ?, ?
";
$params[] = $start;
$params[] = $length;
$types .= "ii";

$stmt = $dash->prepare($query);
if ($types && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
  $data[] = $row;
}

echo json_encode([
  'draw' => $draw,
  'recordsTotal' => $total,
  'recordsFiltered' => $totalFiltrado,
  'data' => $data
], JSON_UNESCAPED_UNICODE);
?>