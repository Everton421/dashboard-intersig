<?php
require_once '../config/config.php';
require_once '../config/session.php'; 

date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION["user_name"])) {
    header("Location: ../index.php");
    exit();
}

// Função para limpar valores monetários vindos do front (Ex: 1.250,00 -> 1250.00)
function limparValorSGBD($valor) {
    if (empty($valor)) return null;
    $valor = str_replace('.', '', $valor); // Remove ponto de milhar
    $valor = str_replace(',', '.', $valor); // Troca vírgula decimal por ponto
    return (float) $valor;
}

// Captura e sanitização dos filtros
$tipoData          = isset($_REQUEST['tipo_data']) ? $_REQUEST['tipo_data'] : 'lancamento';
$dataVencimento    = !empty($_REQUEST['data_vencimento']) ? $_REQUEST['data_vencimento'] : date('Y-m-01'); // Início do mês
$dataVencimentoAte = !empty($_REQUEST['data_vencimentoate']) ? $_REQUEST['data_vencimentoate'] : date('Y-m-t'); // Fim do mês
$tipoPagamento     = isset($_REQUEST['tipo_pagamento']) ? $_REQUEST['tipo_pagamento'] : 'todos';
$filtroPago        = isset($_REQUEST['filtro_pago']) ? $_REQUEST['filtro_pago'] : 'todos';
$filtroFilial      = isset($_REQUEST['filtro_filial']) ? $_REQUEST['filtro_filial'] : 'todos';
$filtroOrigem      = isset($_REQUEST['filtro_origem']) ? $_REQUEST['filtro_origem'] : 'todos';

$codigo_recebimento = !empty($_REQUEST['codigo_recebimento']) ? $_REQUEST['codigo_recebimento'] : null;
$num_documento      = !empty($_REQUEST['num_documento']) ? $_REQUEST['num_documento'] : null; 
$cliente            = !empty($_REQUEST['cliente']) ? $_REQUEST['cliente'] : null; 

// Tratamento dos valores numéricos para o SQL
$valor_inicial = limparValorSGBD($_REQUEST['valor_inicial'] ?? 0) ?? 0;
$valor_final   = limparValorSGBD($_REQUEST['valor_final'] ?? 0) ?? 999999999;

// Query Base
$sql = "SELECT re.codigo AS codigo, 
               CONCAT(re.filial,'-', em.nome_reduz) AS empresa, 
               re.documento AS documento,
               CONCAT(re.cliente,' - ', cl.nome) AS cliente, 
               re.valor AS valor, 
               re.cliente as client,
               re.vencimento AS vencimento, 
               re.data_pgto AS pagamento, 
               CONCAT(re.centro_custo,'-',ce.nome) AS centrocusto, 
               re.valor_pago as valor_pago,
               re.data_lancamento as data_lancamento, 
               rc.nome as nome_recebimento, 
               re.chave_mvto as chave_mvto
        FROM ".$banco_financeiro.".ct_receb re
        LEFT JOIN ".$banco_vendas.".empresas em ON (em.filial = re.filial)
        LEFT JOIN ".$banco_publico.".cen_cust ce ON (ce.codigo = re.centro_custo)
        LEFT JOIN ".$banco_publico.".cad_clie cl ON (cl.codigo = re.cliente)
        LEFT JOIN ".$banco_financeiro.".tip_rece rc ON (rc.codigo = re.tipo_recebimento)
        WHERE em.ativa = 'S' 
          AND re.agrup_destino = 0 
          AND re.caucao = '0000-00-00' 
          AND re.inadimplencia = '0000-00-00' 
          AND re.cartorio = '0000-00-00' 
          AND re.advogado = '0000-00-00'";

// Aplicação Dinâmica dos Filtros
if ($filtroFilial !== 'todos') {
    $sql .= " AND re.filial = '" . $dash->real_escape_string($filtroFilial) . "'";
}

if ($tipoPagamento !== 'todos') {
    $sql .= " AND re.tipo_recebimento = '" . $dash->real_escape_string($tipoPagamento) . "'";
}

if ($filtroPago === 'sim') {
    $sql .= " AND re.valor_pago > 0";
} elseif ($filtroPago === 'nao') {
    $sql .= " AND re.valor_pago = 0";
}

if ($codigo_recebimento) {
    $sql .= " AND re.codigo = '" . $dash->real_escape_string($codigo_recebimento) . "'"; 
}

if ($num_documento) {
    $sql .= " AND re.documento LIKE '%" . $dash->real_escape_string($num_documento) . "%'";
}

if ($cliente) {
    // Busca por código OU nome/razão social
    $sql .= " AND (re.cliente = '" . $dash->real_escape_string($cliente) . "' 
               OR cl.nome LIKE '%" . $dash->real_escape_string($cliente) . "%'
                )";
}

if ($filtroOrigem === 'faturamento') {
    $sql .= " AND (re.chave_mvto IS NOT NULL AND re.chave_mvto <> '')";
} elseif ($filtroOrigem === 'manual') {
    $sql .= " AND (re.chave_mvto IS NULL OR re.chave_mvto = '')";
}

// Filtro de Data
$colunaData = ($tipoData === 'vencimento') ? 're.vencimento' : 're.data_lancamento';
$sql .= " AND $colunaData BETWEEN '$dataVencimento' AND '$dataVencimentoAte'";

// Filtro de Valor (Já tratados pela função limparValorSGBD)
$sql .= " AND re.valor BETWEEN $valor_inicial AND $valor_final";


$results = $dash->query($sql);
$orders = array();

if ($results) {
    while ($row = $results->fetch_assoc()) {
        $orders[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($orders);
$dash->close();