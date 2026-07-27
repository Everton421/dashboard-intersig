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
  'filial', 'codigo', 'nome', 'tipo', 'gera_financeiro', 'comissao',  'setor', 'produtos', 'servicos', 'ent_sai', 'ativa'
];

$colunasValidas = array_values(array_intersect($colunas, $colunasPermitidas));
if (empty($colunasValidas)) {
    $colunasValidas = ['filial', 'codigo', 'nome', 'tipo', 'gera_financeiro', 'comissao',  'setor', 'produtos', 'servicos', 'ent_sai', 'ativa'];
}

$selectCampos = [
    "filial",
    "codigo",
    "nome",
    "CASE gera_financeiro WHEN 'N' THEN 'NÃO' WHEN 'S' THEN 'SIM' ELSE 'NÃO ESPC.' END AS gera_financeiro",
    "CASE comissao WHEN 'N' THEN 'NÃO' WHEN 'S' THEN 'SIM' ELSE 'NÃO ESPC.' END AS comissao",
    "CASE tipo WHEN 'L' THEN 'COMPLEMENTO' 
    WHEN 'C' THEN 'COMPRA/AQUISIÇÃO' 
    WHEN 'H' THEN 'CONHECIMENTO DE TRANSPORTE' 
    WHEN 'D' THEN 'DEVOLUÇÃO' 
    WHEN 'O' THEN 'OUTRAS ENTRADAS/SAÍDAS' 
    WHEN 'T' THEN 'TRANSFERÊNCIA' 
    WHEN 'V' THEN 'VENDA/PRESTAÇÃO' 
    WHEN 'P' THEN 'PERMUTA' 
    WHEN 'A' THEN 'AJUSTE' 
    WHEN 'B' THEN 'NOTA DÉBITO' 
    WHEN 'R' THEN 'NOTA CRÉDITO' 
    ELSE 'NÃO ESPC.' END AS tipo",
    "setor",
    "CASE produtos WHEN 'N' THEN 'NÃO' WHEN 'S' THEN 'SIM' ELSE 'NÃO ESPC.' END AS produtos",
    "CASE servicos WHEN 'N' THEN 'NÃO' WHEN 'S' THEN 'SIM' ELSE 'NÃO ESPC.' END AS servicos",
    "CASE ent_sai WHEN 'E' THEN 'ENTRADA' WHEN 'S' THEN 'SAIDA' ELSE 'NÃO ESPC.' END AS ent_sai",
    "CASE ativa WHEN 'N' THEN 'NÃO' WHEN 'S' THEN 'SIM' ELSE 'NÃO ESPC.' END AS ativa"
];

$select = implode(', ', $selectCampos);

// WHERE
$where = "1=1";
$params = [];
$types = "";


if (!empty($filtros['gera_financeiro'])) {
  $where .= " AND gera_financeiro = ?";
  $params[] = $filtros['gera_financeiro'];
  $types .= "s";
}

if (!empty($filtros['filial'])) {
  $where .= " AND filial = ?";
  $params[] = $filtros['filial'];
  $types .= "s";
}

if (!empty($filtros['tipo'])) {
  $where .= " AND tipo = ?";
  $params[] = $filtros['tipo'];
  $types .= "s";
}

if (!empty($filtros['ent_sai'])) {
  $where .= " AND ent_sai = ?";
  $params[] = $filtros['ent_sai'];
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
$queryTotal = "SELECT COUNT(*) AS total FROM ".$banco_vendas.".transacoes";
$resultTotal = $dash->query($queryTotal);
$rowTotal = $resultTotal->fetch_assoc();
$total = $rowTotal['total'];

// Total filtrado
$queryFiltrada = "SELECT COUNT(*) AS total FROM ".$banco_vendas.".transacoes WHERE $where";
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
  FROM ".$banco_vendas.".transacoes
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