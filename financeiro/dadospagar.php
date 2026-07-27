<?php
require_once '../config/config.php';
require_once '../config/session.php'; 

date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION["user_name"])) {
    header("Location: ../index.php");
    exit();
}

// CORREÇÃO: Pegar os valores corretamente. 
// O erro anterior era: $var = isset(...) ? isset(...) : ... (isso retornava true/false)
$dataVencimento = !empty($_REQUEST['data_vencimento']) ? $_REQUEST['data_vencimento'] : date('Y-m-d');
$dataVencimentoAte = !empty($_REQUEST['data_vencimentoate']) ? $_REQUEST['data_vencimentoate'] : date('Y-m-d');
$tipoPagamento = isset($_REQUEST['tipo_pagamento']) ? $_REQUEST['tipo_pagamento'] : 'todos';
$filtroPago = isset($_REQUEST['filtro_pago']) ? $_REQUEST['filtro_pago'] : 'todos';
$filtroFilial = isset($_REQUEST['filtro_filial']) ? $_REQUEST['filtro_filial'] : 'todos';
$filtroOrigem = isset($_REQUEST['filtro_origem']) ? $_REQUEST['filtro_origem'] : 'todos';
$codigo_pagamento = !empty($_REQUEST['codigo_pagamento']) ? $_REQUEST['codigo_pagamento'] : null;
$num_documento = !empty($_REQUEST['num_documento']) ? $_REQUEST['num_documento'] : null; 
$fornecedor = !empty($_REQUEST['fornecedor']) ? $_REQUEST['fornecedor'] : null; 
$valor_inicial = !empty($_REQUEST['valor_inicial']) ? $_REQUEST['valor_inicial'] : 0; 
$valor_final = !empty($_REQUEST['valor_final']) ? $_REQUEST['valor_final'] : 100000000; 

$sql = "SELECT re.codigo AS codigo, CONCAT(re.filial,'-', em.nome_reduz) AS empresa, re.documento AS documento ,
  CONCAT(re.fornecedor,' - ', cl.razao_social) AS cliente, re.valor AS valor, re.fornecedor as fornecedor,
  re.vencimento AS vencimento, re.data_pgto AS pagamento, cl.telefone AS telefone,  
  CONCAT(re.centro_custo,'-',ce.nome) AS centrocusto , re.valor_pago as valor_pago, rc.nome as nome_recebimento ,
  re.data_lancamento as data_lancamento
   FROM " . $banco_financeiro . ".ct_pagar re 
    LEFT JOIN " . $banco_vendas . ".empresas em ON (em.filial = re.filial)
    LEFT JOIN " . $banco_publico . ".cen_cust ce ON (ce.codigo = re.centro_custo)
    LEFT JOIN " . $banco_publico . ".cad_forn cl ON (cl.codigo = re.fornecedor)
    LEFT JOIN " . $banco_financeiro . ".tip_rece rc ON (rc.codigo = re.tipo_pagamento)
  WHERE em.ativa = 'S' AND re.agrup_destino = 0";

if ($filtroFilial !== 'todos') {
    $sql .= " AND re.filial = '$filtroFilial'";
}
if ($tipoPagamento !== 'todos') {
    $sql .= " AND re.tipo_pagamento = '$tipoPagamento'";
}
if($codigo_pagamento != null){
    $sql.= " AND re.CODIGO = '$codigo_pagamento' "; 
}
if($num_documento != null ) {
    $sql.= " AND re.DOCUMENTO LIKE '%$num_documento%' ";
}
if($fornecedor != null ) {
    // Busca por código ou nome se for o caso, aqui mantendo a lógica de código
    $sql.= " AND (re.FORNECEDOR = '$fornecedor' OR cl.razao_social LIKE '%$fornecedor%') ";
}
if ($filtroPago === 'sim') {
    $sql .= " AND re.valor_pago > 0";
} elseif ($filtroPago === 'nao') {
    $sql .= " AND re.valor_pago = 0";
}
if ($filtroOrigem === 'faturamento') {
    $sql .= " AND (re.chave_mvto IS NOT NULL AND re.chave_mvto <> '')";
} elseif ($filtroOrigem === 'manual') {
    $sql .= " AND (re.chave_mvto IS NULL OR re.chave_mvto = '')";
}

$tipoData = isset($_REQUEST['tipo_data']) ? $_REQUEST['tipo_data'] : 'lancamento';
$colunaData = ($tipoData === 'vencimento') ? 're.vencimento' : 're.data_lancamento';

$sql .= " AND $colunaData >= '$dataVencimento' AND $colunaData <= '$dataVencimentoAte'";
$sql .= " AND re.valor >= '$valor_inicial' AND re.valor <= '$valor_final' ";


// Executa a consulta SQL
$results = $dash->query($sql);

// Verifica se a consulta retornou algum resultado
if ($results !== false && $results->num_rows > 0) {
    // Cria um array para armazenar os resultados da consulta
    $orders = array();

    // Percorre os resultados da consulta
    while ($row = $results->fetch_assoc()) {
        $orders[] = $row;
    }

    // Define o tipo de conteúdo como JSON
    header('Content-Type: application/json');

    // Converte o array para JSON
    echo json_encode($orders);
} else {
    // Se não houver dados, retorna um JSON vazio
    echo json_encode(array());
}



// Fecha a conexão com o banco de dados
$dash->close();
?>