<?php
// --- NO CADASTRO DA SERIE ELE PEGA OS VALORES SOMENTE O QUE ESTIVER MARCADA PARA ENVIAR SPED ---
require_once '../config/config.php';
require_once '../config/session.php'; 

date_default_timezone_set('America/Sao_Paulo');


if (!isset($_SESSION["user_name"])) {
    // Redireciona para o login se não estiver logado
    header("Location: ../index.php");
    exit();
}


$nome_usuario_logado = htmlspecialchars($_SESSION["user_name"]);
$id_usuario_logado = (int) $_SESSION["user_id"];

// --- montar URL completa da página atual ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$page_url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');


$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$user_agent = mb_substr($user_agent, 0, 255); 


if (isset($dash) && $stmt = $dash->prepare(
    "INSERT INTO logs (user_id, user_name, pagina_url, acesso, user_agent) VALUES (?, ?, ?, NOW(),  ?)"
)) {
    $stmt->bind_param("isss",
        $id_usuario_logado,
        $nome_usuario_logado,
        $page_url,
        $user_agent
    );
    $stmt->execute();
    // opcional: checar erro
   //  if ($stmt->errno) { error_log("Log insert error: " . $stmt->error); }
   // $stmt->close();
} else {
    // opcional: tratar erro de prepare (por exemplo registrar em log de erro)
    // error_log("Prepare failed for log insert: " . ($dash->error ?? 'no conn'));
}




function convert_date_br_to_sql($date_input) {
    if (empty($date_input)) return '';
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date_input, $matches)) {
        return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
    }
    return $date_input;
}


function array_column_old($array, $column_name) {
    $result = array();
    if (!is_array($array)) return $result; 
    foreach ($array as $row) {
        if (isset($row[$column_name])) {
            $result[] = $row[$column_name];
        }
    }
    return $result;
}
function mysqli_fetch_all_old($result) {
    $all = array();
    if (!$result) return $all; 
    while ($row = mysqli_fetch_assoc($result)) {
        $all[] = $row;
    }
    return $all;
}

function executar_consulta($conexao, $sql, $params = array()) {
    // Se não há parâmetros, executa diretamente
    if (empty($params)) {
        $resultado = mysqli_query($conexao, $sql);
        if (!$resultado) {
            error_log("Erro SQL: " . mysqli_error($conexao));
        }
        return $resultado;
    }
    
    // Prepara a statement
    $stmt = mysqli_prepare($conexao, $sql);
    if (!$stmt) {
        error_log("Erro ao preparar statement: " . mysqli_error($conexao));
        return false;
    }
    
    // Monta os tipos dos parâmetros
    $types = '';
    foreach ($params as $param) {
        if (is_int($param)) {
            $types .= 'i';
        } elseif (is_float($param)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    
    // Vincula os parâmetros
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    // Executa
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Erro ao executar statement: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    
    // Obtém o resultado
    $resultado = mysqli_stmt_get_result($stmt);
    
    // Fecha o statement
    mysqli_stmt_close($stmt);
    
    return $resultado;
}


$join_series = " left join {$banco_vendas}.series s on (s.codigo = n.serie and s.filial = n.filial) ";
$condicoes_fixas = " s.consid_sped = 'S' and n.cancelada = 'N'";

// --- FILTROS DINÂMICOS ---
$filial = isset($_GET['filial']) ? $_GET['filial'] : '';
$data_inicio_raw = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$data_fim_raw = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$uf = isset($_GET['uf']) ? $_GET['uf'] : '';

// Período padrão (mês atual) se não houver filtros de data
if (empty($data_inicio_raw) && empty($data_fim_raw)) {
    $hoje = time();
    $data_inicio_sql_padrao = date('Y-m-01', $hoje);
    $data_fim_sql_padrao = date('Y-m-t', $hoje);
    $data_inicio_raw = date('d/m/Y', strtotime($data_inicio_sql_padrao));
    $data_fim_raw = date('d/m/Y', strtotime($data_fim_sql_padrao));
}

$data_inicio = convert_date_br_to_sql($data_inicio_raw);
$data_fim = convert_date_br_to_sql($data_fim_raw);

// --- CONSTRUÇÃO DA CLÁUSULA WHERE E PARÂMETROS ---
$filtros_dinamicos = array(); 
$params = array();

if ($filial) {
    $filtros_dinamicos[] = "n.filial = ?";
    $params[] = $filial;
}

if ($data_inicio) {
    $filtros_dinamicos[] = "n.data_saida_entrada >= ?"; 
    $params[] = $data_inicio;
}
if ($data_fim) {
    $filtros_dinamicos[] = "n.data_saida_entrada <= ?"; 
    $params[] = $data_fim;
}



if (empty($filtros_dinamicos)) {
    $where = "WHERE " . $condicoes_fixas;
} else {
    $where = "WHERE " . $condicoes_fixas . " AND " . implode(' AND ', $filtros_dinamicos);
}

// Obter lista de filiais
$result_filial = mysqli_query($dash, "select filial, nome_fantasia from {$banco_vendas}.empresas where ativa = 'S' order by filial asc"); 
$filiais = array(); 
while ($row = mysqli_fetch_assoc($result_filial)) { 
    $filiais[] = array( 
        'codigo' => $row['filial'],
        'nome' => $row['nome_fantasia']
    );
}

// --- GRÁFICO DIÁRIO DE SAÍDAS ---
$sql_diario = "
     SELECT 
        DATE_FORMAT(n.data_saida_entrada, '%d/%m/%Y') AS data_nf,
        SUM(n.TOTAL_NF) as total_nf,
        SUM(n.VALOR_ICMS) AS total_icms,
        SUM(n.VALOR_IPI) AS total_ipi,
        SUM(n.PIS + n.COFINS) AS total_pis_cofins,
        SUM(n.VALOR_ICMS_SUB) AS total_icms_sub,
        SUM(n.VALOR_FRETE + n.VALOR_SEGURO + n.VALOR_OUTRAS_DESPESAS) AS total_outras,
        SUM(n.VALOR_ICMS_UF_DEST) AS total_difal,
        CASE n.cli_for
            WHEN 'C' THEN c.estado
            WHEN 'F' THEN f.estado
        END AS uf
    FROM {$banco_vendas}.cad_nf n
    {$join_series}
    LEFT JOIN {$banco_vendas}.transacoes tr ON tr.codigo = n.transacao AND tr.filial = n.filial
    LEFT JOIN {$banco_publico}.cad_clie c ON (n.codigo_cli_for = c.codigo AND n.cli_for = 'C')
    LEFT JOIN {$banco_publico}.cad_forn f ON (n.codigo_cli_for = f.codigo AND n.cli_for = 'F')
    {$where} AND tr.ent_sai = 'S'";

if (!empty($uf)) {$sql_diario .= " AND (
        (n.cli_for = 'C' AND c.estado = '$uf')
        OR (n.cli_for = 'F' AND f.estado = '$uf')
    )";}
  
$sql_diario .= "
    GROUP BY DATE_FORMAT(n.data_saida_entrada, '%Y-%m-%d')
    ORDER BY n.data_saida_entrada
";

$result_diario = executar_consulta($dash, $sql_diario, $params);

if (!$result_diario) {
    echo "<div style='background: red; color: white; padding: 10px;'>";
    echo "<strong>Erro na consulta SQL:</strong><br>";
    echo "SQL: " . htmlspecialchars($sql_diario) . "<br>";
    echo "Parâmetros: " . implode(', ', $params) . "<br>";
    echo "Erro MySQL: " . mysqli_error($dash);
    echo "</div>";
    exit;
}
$labels = array();
$val_totaldanf = array();
$val_icms = array();
$val_ipi = array();
$val_pis_cofins = array();
$val_icms_sub = array();
$val_outras = array();
$val_difal = array();


if ($result_diario) {
    while ($row = mysqli_fetch_assoc($result_diario)) {
        $labels[] = $row['data_nf'];
        $val_totaldanf[] = round($row['total_nf'], 2);
        $val_icms[] = round($row['total_icms'], 2);
        $val_ipi[] = round($row['total_ipi'], 2);
        $val_pis_cofins[] = round($row['total_pis_cofins'], 2);
        $val_icms_sub[] = round($row['total_icms_sub'], 2);
        $val_outras[] = round($row['total_outras'], 2);
        $val_difal[] = round($row['total_difal'], 2);
    }
}

// --- GRÁFICO DIÁRIO DE ENTRADAS ---
$sql_diario_entradas = "
    SELECT 
        DATE_FORMAT(n.data_saida_entrada, '%d/%m/%Y') AS data_nf,
        SUM(n.TOTAL_NF) as total_nf,
        SUM(n.VALOR_ICMS) AS total_icms,
        SUM(n.VALOR_IPI) AS total_ipi,
        SUM(n.PIS + n.COFINS) AS total_pis_cofins,
        SUM(n.VALOR_ICMS_SUB) AS total_icms_sub,
        SUM(n.VALOR_FRETE + n.VALOR_SEGURO + n.VALOR_OUTRAS_DESPESAS) AS total_outras
    FROM {$banco_vendas}.cad_nf n
    {$join_series}
    LEFT JOIN {$banco_vendas}.transacoes tr ON tr.codigo = n.transacao AND tr.filial = n.filial
      LEFT JOIN {$banco_publico}.cad_clie c ON (n.codigo_cli_for = c.codigo AND n.cli_for = 'C')
    LEFT JOIN {$banco_publico}.cad_forn f ON (n.codigo_cli_for = f.codigo AND n.cli_for = 'F')
    {$where} AND tr.ent_sai = 'E'";

    if (!empty($uf)) {$sql_diario_entradas .= " AND (
        (n.cli_for = 'C' AND c.estado = '$uf')
        OR (n.cli_for = 'F' AND f.estado = '$uf')
    )";}
    
    $sql_diario_entradas .= "
    GROUP BY DATE_FORMAT(n.data_saida_entrada, '%Y-%m-%d')
    ORDER BY n.data_saida_entrada
";


$result_diario_entradas = executar_consulta($dash, $sql_diario_entradas, $params);

$labels_entradas = array();
$val_totaldanf_entradas = array();
$val_icms_entradas = array();
$val_ipi_entradas = array();
$val_pis_cofins_entradas = array();
$val_icms_sub_entradas = array();
$val_outras_entradas = array();

if ($result_diario_entradas) {
    while ($row = mysqli_fetch_assoc($result_diario_entradas)) {
        $labels_entradas[] = $row['data_nf'];
        $val_totaldanf_entradas[] = round($row['total_nf'], 2);
        $val_icms_entradas[] = round($row['total_icms'], 2);
        $val_ipi_entradas[] = round($row['total_ipi'], 2);
        $val_pis_cofins_entradas[] = round($row['total_pis_cofins'], 2);
        $val_icms_sub_entradas[] = round($row['total_icms_sub'], 2);
        $val_outras_entradas[] = round($row['total_outras'], 2);
    }
}

// --- RESUMO GERAL DE SAÍDAS (CARDS) ---
$sql_resumo = "SELECT 
    COUNT(n.CODIGO) AS total_nf, 
    SUM(n.TOTAL_NF) AS total_vendas, 
    SUM(n.TOTAL_PRODUTOS) AS total_produtos_geral, 
    SUM(n.DESC_PROD) AS total_descontos_geral, 
    SUM(n.VALOR_FRETE) AS total_frete_geral, 
    SUM(n.VALOR_SEGURO) AS total_seguro_geral, 
    SUM(n.VALOR_OUTRAS_DESPESAS) AS total_outras_geral, 
    SUM(n.VALOR_ICMS) AS total_icms, 
    SUM(n.VALOR_IPI) AS total_ipi, 
    SUM(n.PIS) AS total_pis, 
    SUM(n.COFINS) AS total_cofins, 
    SUM(n.VALOR_ICMS_UF_DEST) AS total_difal, 
    SUM(n.VALOR_FCP_UF_DEST) AS total_fcp, 
    SUM(n.VALOR_ICMS_SUB) AS total_icms_sub 
FROM {$banco_vendas}.cad_nf n 
LEFT JOIN {$banco_vendas}.transacoes tr ON tr.codigo = n.transacao AND tr.filial = n.filial 
{$join_series} 
LEFT JOIN {$banco_publico}.cad_clie c ON (n.codigo_cli_for = c.codigo AND n.cli_for = 'C')
    LEFT JOIN {$banco_publico}.cad_forn f ON (n.codigo_cli_for = f.codigo AND n.cli_for = 'F')
{$where} AND tr.ent_sai = 'S' ";

if (!empty($uf)) {$sql_resumo .= " AND (
        (n.cli_for = 'C' AND c.estado = '$uf')
        OR (n.cli_for = 'F' AND f.estado = '$uf')
    )";}
  
 

$result_resumo = executar_consulta($dash, $sql_resumo, $params);
$resumo_geral = mysqli_fetch_assoc($result_resumo);

$total_outras_despesas = (isset($resumo_geral['total_frete_geral']) ? $resumo_geral['total_frete_geral'] : 0) + 
                        (isset($resumo_geral['total_seguro_geral']) ? $resumo_geral['total_seguro_geral'] : 0) + 
                        (isset($resumo_geral['total_outras_geral']) ? $resumo_geral['total_outras_geral'] : 0);
$total_icms_cabecalho = isset($resumo_geral['total_icms']) ? $resumo_geral['total_icms'] : 0;
$total_ipi_cabecalho = isset($resumo_geral['total_ipi']) ? $resumo_geral['total_ipi'] : 0;
$total_pis_cabecalho = isset($resumo_geral['total_pis']) ? $resumo_geral['total_pis'] : 0;
$total_cofins_cabecalho = isset($resumo_geral['total_cofins']) ? $resumo_geral['total_cofins'] : 0;
$total_fcp_cabecalho = isset($resumo_geral['total_fcp']) ? $resumo_geral['total_fcp'] : 0;
$total_difal_cabecalho = isset($resumo_geral['total_difal']) ? $resumo_geral['total_difal'] : 0;
$total_icms_sub_cabecalho = isset($resumo_geral['total_icms_sub']) ? $resumo_geral['total_icms_sub'] : 0; 
$venda_total_cabecalho = isset($resumo_geral['total_vendas']) ? $resumo_geral['total_vendas'] : 0; 
$total_produtos_geral = isset($resumo_geral['total_produtos_geral']) ? $resumo_geral['total_produtos_geral'] : 0;
$total_descontos_geral = isset($resumo_geral['total_descontos_geral']) ? abs($resumo_geral['total_descontos_geral']) : 0;
$total_pis_cofins = $total_pis_cabecalho + $total_cofins_cabecalho;
    
// --- RESUMO GERAL DE ENTRADAS (CARDS) ---
$sql_resumoentrada = "SELECT COUNT(n.CODIGO) AS total_nf, SUM(n.TOTAL_NF) AS total_entradas, SUM(n.TOTAL_PRODUTOS) AS total_produtos_geral,
 SUM(n.DESC_PROD) AS total_descontos_geral, SUM(n.VALOR_FRETE) AS total_frete_geral, SUM(n.VALOR_SEGURO) AS total_seguro_geral, 
SUM(n.VALOR_OUTRAS_DESPESAS) AS total_outras_geral, SUM(n.VALOR_ICMS) AS total_icms, SUM(n.VALOR_IPI) AS total_ipi, SUM(n.PIS) AS total_pis, 
SUM(n.COFINS) AS total_cofins,  SUM(n.VALOR_ICMS_SUB) AS total_icms_sub,    SUM(n.VALOR_ICMS_UF_DEST) AS total_difal, 
    SUM(n.VALOR_FCP_UF_DEST) AS total_fcp  
FROM {$banco_vendas}.cad_nf n
  {$join_series} left join {$banco_vendas}.transacoes tr 
on tr.codigo = n.transacao and tr.filial = n.filial 
LEFT JOIN {$banco_publico}.cad_clie c ON (n.codigo_cli_for = c.codigo AND n.cli_for = 'C')
    LEFT JOIN {$banco_publico}.cad_forn f ON (n.codigo_cli_for = f.codigo AND n.cli_for = 'F')
{$where} and tr.ent_sai = 'E'
";

if (!empty($uf)) {$sql_resumoentrada .= " AND (
        (n.cli_for = 'C' AND c.estado = '$uf')
        OR (n.cli_for = 'F' AND f.estado = '$uf')
    )";}

  
   
$result_resumoentrada = executar_consulta($dash, $sql_resumoentrada, $params);
$resumo_geral_entrada = mysqli_fetch_assoc($result_resumoentrada);

$total_outras_despesas_e = (isset($resumo_geral_entrada['total_frete_geral']) ? $resumo_geral_entrada['total_frete_geral'] : 0) + 
                          (isset($resumo_geral_entrada['total_seguro_geral']) ? $resumo_geral_entrada['total_seguro_geral'] : 0) + 
                          (isset($resumo_geral_entrada['total_outras_geral']) ? $resumo_geral_entrada['total_outras_geral'] : 0);
$total_icms_cabecalho_e = isset($resumo_geral_entrada['total_icms']) ? $resumo_geral_entrada['total_icms'] : 0;
$total_ipi_cabecalho_e = isset($resumo_geral_entrada['total_ipi']) ? $resumo_geral_entrada['total_ipi'] : 0;
$total_pis_cabecalho_e = isset($resumo_geral_entrada['total_pis']) ? $resumo_geral_entrada['total_pis'] : 0;
$total_cofins_cabecalho_e = isset($resumo_geral_entrada['total_cofins']) ? $resumo_geral_entrada['total_cofins'] : 0;
$total_issqn_cabecalho_e = isset($resumo_geral_entrada['total_issqn']) ? $resumo_geral_entrada['total_issqn'] : 0;
$total_icms_sub_cabecalho_e = isset($resumo_geral_entrada['total_icms_sub']) ? $resumo_geral_entrada['total_icms_sub'] : 0; 
$venda_total_cabecalho_e = isset($resumo_geral_entrada['total_entradas']) ? $resumo_geral_entrada['total_entradas'] : 0;
$total_produtos_geral_e = isset($resumo_geral_entrada['total_produtos_geral']) ? $resumo_geral_entrada['total_produtos_geral'] : 0;
$total_fcp_cabecalho_e = isset($resumo_geral_entrada['total_fcp']) ? $resumo_geral_entrada['total_fcp'] : 0;
$total_difal_cabecalho_e = isset($resumo_geral_entrada['total_difal']) ? $resumo_geral_entrada['total_difal'] : 0;
$total_descontos_geral_e = isset($resumo_geral_entrada['total_descontos_geral']) ? abs($resumo_geral_entrada['total_descontos_geral']) : 0;
$total_pis_cofins_e = $total_pis_cabecalho_e + $total_cofins_cabecalho_e;


// --- GRÁFICO ICMS POR FILIAL (ENTRADA VS SAÍDA) ---
$sql_icms_ent_sai = "
    SELECT  
        n.FILIAL, 
        SUM(CASE WHEN tr.ent_sai = 'S' THEN t.valor_icms ELSE 0 END) AS TOTAL_ICMS_VENDAS,
        SUM(CASE WHEN tr.ent_sai = 'E' THEN t.valor_icms ELSE 0 END) AS TOTAL_ICMS_ENTRADAS
    FROM {$banco_vendas}.cad_nf n
    LEFT JOIN {$banco_vendas}.transacoes tr ON tr.codigo = n.transacao AND tr.filial = n.filial 
    INNER JOIN {$banco_vendas}.mvto_produtos p 
        ON p.CHAVE_MVTO = n.CODIGO
    LEFT JOIN {$banco_vendas}.mvto_tributos t 
        ON t.CHAVE_MVTO = n.CODIGO 
        AND t.item = p.item 
    {$join_series} 
    LEFT JOIN {$banco_publico}.cad_clie c ON (n.codigo_cli_for = c.codigo AND n.cli_for = 'C')
    LEFT JOIN {$banco_publico}.cad_forn f ON (n.codigo_cli_for = f.codigo AND n.cli_for = 'F')
    
    {$where} 
    ";

if (!empty($uf)) {$sql_icms_ent_sai .= " AND (
        (n.cli_for = 'C' AND c.estado = '$uf')
        OR (n.cli_for = 'F' AND f.estado = '$uf')
    )";}
    
    $sql_icms_ent_sai .= " GROUP BY n.FILIAL 
    ORDER BY n.FILIAL ASC
";
$result_icms = executar_consulta($dash, $sql_icms_ent_sai, $params);

$filial_labels_icms = array();
$icms_vendas   = array();
$icms_entradas = array();
$icms_diferenca = array();

if ($result_icms) {
    while ($row = mysqli_fetch_assoc($result_icms)) {
        $filial_labels_icms[]  = $row['FILIAL'];
        $icms_venda   = isset($row['TOTAL_ICMS_VENDAS']) ? $row['TOTAL_ICMS_VENDAS'] : 0;
        $icms_entrada = isset($row['TOTAL_ICMS_ENTRADAS']) ? $row['TOTAL_ICMS_ENTRADAS'] : 0;
        $diferenca    = $icms_entrada - $icms_venda; // Diferença é Débito - Crédito

        $icms_vendas[]    = round($icms_venda, 2);
        $icms_entradas[]  = round($icms_entrada, 2);
        $icms_diferenca[] = round($diferenca, 2);
    }
}



$sql_apuracao = "SELECT 
    DATE_FORMAT(n.DATA_SAIDA_ENTRADA, '%b %Y') AS PERIODO,
    SUM(CASE WHEN p.ent_sai = 'S' THEN t.valor_icms ELSE 0 END) AS ICMS_DEBITO,
    SUM(CASE WHEN p.ent_sai = 'E' THEN t.valor_icms ELSE 0 END) AS ICMS_CREDITO,
    SUM(CASE WHEN p.ent_sai = 'S' THEN (n.total_nf) ELSE 0 END) AS TOTAL_SAIDAS
FROM {$banco_vendas}.cad_nf n
INNER JOIN {$banco_vendas}.mvto_produtos p 
    ON p.CHAVE_MVTO = n.CODIGO
LEFT JOIN {$banco_vendas}.mvto_tributos t 
    ON t.CHAVE_MVTO = n.CODIGO 
   AND t.item = p.item
{$join_series} 
LEFT JOIN {$banco_publico}.cad_clie c ON (n.codigo_cli_for = c.codigo AND n.cli_for = 'C')
    LEFT JOIN {$banco_publico}.cad_forn f ON (n.codigo_cli_for = f.codigo AND n.cli_for = 'F')
{$where}";

if (!empty($uf)) {$sql_apuracao .= " AND (
        (n.cli_for = 'C' AND c.estado = '$uf')
        OR (n.cli_for = 'F' AND f.estado = '$uf')
    )";}
    
    $sql_apuracao .= "
GROUP BY DATE_FORMAT(n.DATA_SAIDA_ENTRADA, '%Y-%m')
ORDER BY MIN(n.DATA_SAIDA_ENTRADA);
";



$result_apuracao = executar_consulta($dash, $sql_apuracao, $params);

$periodos = array();
$icms_debito = array();
$icms_credito = array();
$aliquota_efetiva = array();

if ($result_apuracao) {
    while ($row = mysqli_fetch_assoc($result_apuracao)) {
        $periodos[] = $row['PERIODO'];
        $debito = $row['ICMS_DEBITO'];
        $credito = $row['ICMS_CREDITO'];
        $total_saidas = $row['TOTAL_SAIDAS'];

        $icms_debito[] = round($debito, 2);
        $icms_credito[] = round($credito, 2);

        $aliquota = ($total_saidas > 0) ? (($debito - $credito) / $total_saidas) * 100 : 0;
        $aliquota_efetiva[] = round($aliquota, 2);
    }
}


// --- GRÁFICO EVOLUÇÃO MENSAL ---
$sql_mensal = "SELECT DATE_FORMAT(n.DATA_SAIDA_ENTRADA, '%Y-%m') AS MES, SUM(n.TOTAL_NF) AS TOTAL
 FROM {$banco_vendas}.cad_nf n {$join_series} 
 LEFT JOIN {$banco_vendas}.transacoes tr ON tr.codigo = n.transacao AND tr.filial = n.filial
 LEFT JOIN {$banco_publico}.cad_clie c ON (n.codigo_cli_for = c.codigo AND n.cli_for = 'C')
    LEFT JOIN {$banco_publico}.cad_forn f ON (n.codigo_cli_for = f.codigo AND n.cli_for = 'F')
 {$where} AND tr.ent_sai = 'S'
 ";

if (!empty($uf)) {$sql_mensal .= " AND (
        (n.cli_for = 'C' AND c.estado = '$uf')
        OR (n.cli_for = 'F' AND f.estado = '$uf')
    )";}
    
    $sql_mensal .= "
 GROUP BY MES ORDER BY MES ASC";


$result_mensal = executar_consulta($dash, $sql_mensal, $params);
$dados_mensal_array = mysqli_fetch_all_old($result_mensal); 


$meses_labels = array_column_old($dados_mensal_array, 'MES');
$meses_valores = array_column_old($dados_mensal_array, 'TOTAL');




// --- FILTRO DINÂMICO (SOMENTE FILIAL) ---
$filial = isset($_GET['filial']) ? $_GET['filial'] : '';

$filtros_dinamicos = [];
$params = [];

if ($filial && $filial !== 'todos') {
    $filtros_dinamicos[] = "n.filial = ?";
    $params[] = $filial;
}

// --- CONSTRUÇÃO DA CLÁUSULA WHERE ---
if (empty($filtros_dinamicos)) {
    $where = "WHERE {$condicoes_fixas}";
} else {
    $where = "WHERE {$condicoes_fixas} AND " . implode(' AND ', $filtros_dinamicos);
}

// --- LISTA DE FILIAIS ---
$result_filial = mysqli_query($dash, "
    SELECT filial, nome_fantasia 
    FROM {$banco_vendas}.empresas 
    WHERE ativa = 'S' 
    ORDER BY filial ASC
"); 

$filiais = [];
while ($row = mysqli_fetch_assoc($result_filial)) { 
    $filiais[] = [
        'codigo' => $row['filial'],
        'nome'   => $row['nome_fantasia']
    ];
}

// --- CONSULTA PRINCIPAL ---




$sqlgraficogeral = "
SELECT 
    MONTH(n.DATA_SAIDA_ENTRADA) AS mes_num,
    DATE_FORMAT(n.DATA_SAIDA_ENTRADA, '%b %Y') AS mes_ano,
    SUM(CASE WHEN tr.ent_sai = 'S' THEN n.VALOR_ICMS ELSE 0 END) AS debito_icms,
    SUM(CASE WHEN tr.ent_sai = 'E' THEN n.VALOR_ICMS ELSE 0 END) AS credito_icms,
    SUM(CASE WHEN tr.ent_sai = 'S' THEN n.BASE_ICMS ELSE 0 END) AS base_calculo_saida
FROM {$banco_vendas}.cad_nf n 
LEFT JOIN {$banco_vendas}.transacoes tr ON tr.codigo = n.transacao AND tr.filial = n.filial 
{$join_series} 
LEFT JOIN {$banco_publico}.cad_clie c ON (n.codigo_cli_for = c.codigo AND n.cli_for = 'C')
    LEFT JOIN {$banco_publico}.cad_forn f ON (n.codigo_cli_for = f.codigo AND n.cli_for = 'F')
 {$where} 
  AND YEAR(n.DATA_SAIDA_ENTRADA) = YEAR(NOW())
   ";

if (!empty($uf)) {$sqlgraficogeral .= " AND (
        (n.cli_for = 'C' AND c.estado = '$uf')
        OR (n.cli_for = 'F' AND f.estado = '$uf')
    )";}
    
    $sqlgraficogeral .= "
GROUP BY mes_num, mes_ano
ORDER BY mes_num;
";

$result_graficogeral = executar_consulta($dash, $sqlgraficogeral, $params, []);

$meses = [];
$val_debito = [];
$val_credito = [];
$aliquota_efetiva = [];

for ($i = 1; $i <= 12; $i++) {
    $meses[] = date('M', mktime(0, 0, 0, $i, 1));
    $val_debito[$i] = 0;
    $val_credito[$i] = 0;
    $aliquota_efetiva[$i] = 0;
}

foreach ($result_graficogeral as $row) {
    $index = (int)$row['mes_num'];
    $val_debito[$index] = (float)$row['debito_icms'];
    $val_credito[$index] = (float)$row['credito_icms'];
    
    // Alíquota efetiva = (ICMS Débito - Crédito) / Base de cálculo de saída
    if ($row['base_calculo_saida'] > 0) {
        $aliquota_efetiva[$index] = (($row['debito_icms'] - $row['credito_icms']) / $row['base_calculo_saida']) * 100;
    }
}

// Reindexa arrays para Chart.js
$val_debito = array_values($val_debito);
$val_credito = array_values($val_credito);
$aliquota_efetiva = array_values($aliquota_efetiva);


?>


<!doctype html>
<html lang="pt-br">


<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Intersig | Dashboard</title>
    <!--begin::Accessibility Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <meta name="supported-color-schemes" content="light dark" />
    <link rel="preload" href="../css/adminlte.css" as="style" />

    <!--end::Accessibility Features-->
    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css"
        integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q=" crossorigin="anonymous" media="print"
        onload="this.media='all'" />
    <!--end::Fonts-->
    <!--begin::Third Party Plugin(OverlayScrollbars)-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css"
        crossorigin="anonymous" />
    <!--end::Third Party Plugin(OverlayScrollbars)-->
    <!--begin::Third Party Plugin(Bootstrap Icons)-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        crossorigin="anonymous" />
    <!--end::Third Party Plugin(Bootstrap Icons)-->
    <!--begin::Required Plugin(AdminLTE)-->
    <link rel="stylesheet" href="../css/adminlte.css" />
    <!--end::Required Plugin(AdminLTE)-->
    <!-- apexcharts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css"
        integrity="sha256-4MX+61mt9NVvvuPjUWdUdyfZfxSB1/Rf9WtqRHgG5S0=" crossorigin="anonymous" />
    <!-- jsvectormap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css"
        integrity="sha256-+uGLJmmTKOqBr+2E6KDYs/NRsHxSkONXFHUL0fy2O/4=" crossorigin="anonymous" />



    <!-- ================== END core-css ================== -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>


    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">


</head>
<!--end::Head-->
<!--begin::Body-->

<body class="layout-fixed sidebar-expand-lg sidebar-open" data-bs-theme="dark">

    <!--begin::App Wrapper-->
    <div class="app-wrapper">
        <!--begin::Header-->

        <?php 
        require_once '../menu/nav_dash.php';
        ?>

        <?php 
        require_once '../menu/sidebar.php';
        ?>

        <?php 
        require_once '../menu/menu_dash.php';
        ?>



        <!--end::Sidebar Wrapper-->
        </aside>
        <!--end::Sidebar-->


        <!--begin::App Main-->
        <main class="app-main">
            <!--begin::App Content Header-->
            <div class="app-content-header">
                <!--begin::Container-->
                <div class="container-fluid">
                    <!--begin::Row-->
                    <div class="row">
                        <div class="col-sm-6">
                            <h3 class="mb-0">Dashboard</h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                <li class="breadcrumb-item"><a href="#">Home</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                            </ol>
                        </div>
                    </div>
                    <!--end::Row-->
                </div>
                <!--end::Container-->
            </div>
            <!--end::App Content Header-->

            <!--begin::App Content-->
            <div class="app-content">
                <!--begin::Container-->
                <div class="container-fluid">
                    <!--begin::Row-->
                    <div class="row">
                        <!-- Start col -->
                        <div class="col-lg-12 connectedSortable">
                            <div class="row">
                                <div class=" col-md-12">
                                    <div class="card filter-card">
                                        <div class="card-header">
                                            <strong>Filtros</strong>
                                        </div>
                                        <div class="card-body">
                                            <form method="GET" id="form-filtros">
                                                <div class="row g-3 align-items-end">
                                                    <!-- Coluna da Filial -->
                                                    <div class="col-md-2">
                                                        <label class="form-label">Filial</label>
                                                        <select name="filial" class="select-filial form-control-sm">
                                                            <option value="">Todas</option>
                                                            <?php foreach ($filiais as $f): ?>
                                                            <option value="<?= $f['codigo'] ?>"
                                                                <?= ($f['codigo'] == $filial ? 'selected' : '') ?>>
                                                                <?= $f['codigo'] ?> -
                                                                <?= htmlspecialchars($f['nome']) ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <!-- Coluna da Data de Início -->
                                                    <div class="col-md-2">
                                                        <label class="form-label">Data Início</label>
                                                        <input type="text" name="data_inicio" id="data_inicio"
                                                            class="form-control form-control-sm"
                                                            value="<?= htmlspecialchars($data_inicio_raw) ?>"
                                                            placeholder="dd/mm/aaaa" maxlength="10">
                                                    </div>

                                                    <!-- Coluna da Data Fim -->
                                                    <div class="col-md-2">
                                                        <label class="form-label">Data Fim</label>
                                                        <input type="text" name="data_fim" id="data_fim"
                                                            class="form-control form-control-sm"
                                                            value="<?= htmlspecialchars($data_fim_raw) ?>"
                                                            placeholder="dd/mm/aaaa" maxlength="10">
                                                    </div>

                                                    <!-- Coluna da UF -->
                                                    <div class="col-md-2">
                                                        <label class="form-label">UF</label>
                                                        <select name="uf" class="select-filial form-control-sm">
                                                            <option value="">Todas</option>
                                                            <option value="AC" <?= ($uf == 'AC' ? 'selected' : '') ?>>
                                                                AC
                                                            </option>
                                                            <option value="AL" <?= ($uf == 'AL' ? 'selected' : '') ?>>AL
                                                            </option>
                                                            <option value="AP" <?= ($uf == 'AP' ? 'selected' : '') ?>>AP
                                                            </option>
                                                            <option value="AM" <?= ($uf == 'AM' ? 'selected' : '') ?>>AM
                                                            </option>
                                                            <option value="BA" <?= ($uf == 'BA' ? 'selected' : '') ?>>BA
                                                            </option>
                                                            <option value="CE" <?= ($uf == 'CE' ? 'selected' : '') ?>>CE
                                                            </option>
                                                            <option value="DF" <?= ($uf == 'DF' ? 'selected' : '') ?>>DF
                                                            </option>
                                                            <option value="ES" <?= ($uf == 'ES' ? 'selected' : '') ?>>ES
                                                            </option>
                                                            <option value="GO" <?= ($uf == 'GO' ? 'selected' : '') ?>>GO
                                                            </option>
                                                            <option value="MA" <?= ($uf == 'MA' ? 'selected' : '') ?>>MA
                                                            </option>
                                                            <option value="MT" <?= ($uf == 'MT' ? 'selected' : '') ?>>MT
                                                            </option>
                                                            <option value="MS" <?= ($uf == 'MS' ? 'selected' : '') ?>>MS
                                                            </option>
                                                            <option value="MG" <?= ($uf == 'MG' ? 'selected' : '') ?>>MG
                                                            </option>
                                                            <option value="PA" <?= ($uf == 'PA' ? 'selected' : '') ?>>PA
                                                            </option>
                                                            <option value="PB" <?= ($uf == 'PB' ? 'selected' : '') ?>>PB
                                                            </option>
                                                            <option value="PR" <?= ($uf == 'PR' ? 'selected' : '') ?>>PR
                                                            </option>
                                                            <option value="PE" <?= ($uf == 'PE' ? 'selected' : '') ?>>PE
                                                            </option>
                                                            <option value="PI" <?= ($uf == 'PI' ? 'selected' : '') ?>>PI
                                                            </option>
                                                            <option value="RJ" <?= ($uf == 'RJ' ? 'selected' : '') ?>>RJ
                                                            </option>
                                                            <option value="RN" <?= ($uf == 'RN' ? 'selected' : '') ?>>RN
                                                            </option>
                                                            <option value="RS" <?= ($uf == 'RS' ? 'selected' : '') ?>>RS
                                                            </option>
                                                            <option value="RO" <?= ($uf == 'RO' ? 'selected' : '') ?>>RO
                                                            </option>
                                                            <option value="RR" <?= ($uf == 'RR' ? 'selected' : '') ?>>RR
                                                            </option>
                                                            <option value="SC" <?= ($uf == 'SC' ? 'selected' : '') ?>>SC
                                                            </option>
                                                            <option value="SP" <?= ($uf == 'SP' ? 'selected' : '') ?>>SP
                                                            </option>
                                                            <option value="SE" <?= ($uf == 'SE' ? 'selected' : '') ?>>SE
                                                            </option>
                                                            <option value="TO" <?= ($uf == 'TO' ? 'selected' : '') ?>>TO
                                                            </option>
                                                        </select>
                                                    </div>

                                                    <!-- Coluna dos Botões -->
                                                    <div class="col-md-2 d-flex gap-2">
                                                        <button type="submit"
                                                            class="btn btn-primary btn-sm-custom flex-grow-1">Filtrar</button>
                                                        <?php if ($filial || $data_inicio_raw || $data_fim_raw): ?>
                                                        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>"
                                                            class="btn btn-outline-secondary btn-sm-custom flex-grow-1">Limpar</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- Botões de Filtro Rápido -->
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <span
                                                                class="text-muted small align-self-center me-2">Período
                                                                rápido:</span>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setPeriodo('hoje')">Hoje</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setPeriodo('semana')">Esta Semana</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setPeriodo('mes')">Este Mês</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setPeriodo('primeiro_trimestre')">1º
                                                                Trimestre</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setPeriodo('segundo_trimestre')">2º
                                                                Trimestre</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setPeriodo('terceiro_trimestre')">3º
                                                                Trimestre</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setPeriodo('quarto_trimestre')">4º
                                                                Trimestre</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setPeriodo('primeiro_semestre')">1º
                                                                Semestre</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setPeriodo('segundo_semestre')">2º
                                                                Semestre</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setPeriodo('ano')">Este Ano</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (true): ?>
                        <!-- ================================== -->
                        <!-- NOVO RESUMO GERAL COM CARDS        -->
                        <!-- ================================== -->

                        <!-- Título da Seção -->
                        <div class="row">
                            <div class="col-md-12">
                                <h3 class="section-title">Resumo Geral</h3>
                            </div>
                        </div>



                        <!-- Seção de Saídas -->
                        <div class="card mb-4" style="background-color: #1e2125;">
                            <div
                                class="card-header d-flex justify-content-between align-items-center bg-transparent border-secondary">
                                <h5 class="mb-0">Saídas</h5>
                                <span class="badge bg-primary">NFs de Venda</span>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <!-- Card: NFs Emitidas -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card saida">
                                            <div class="info-card-value">
                                                <?php echo isset($resumo_geral['total_nf']) ? number_format($resumo_geral['total_nf'], 0, ',', '.') : 0; ?>
                                            </div>
                                            <div class="info-card-label">NFs Emitidas</div>
                                        </div>
                                    </div>
                                    <!-- Card: Total Vendas -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card saida">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($venda_total_cabecalho, 2, ',', '.') ?></div>
                                            <div class="info-card-label">Total Saídas</div>
                                        </div>
                                    </div>
                                    <!-- Card: ICMS Total -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card saida">
                                            <div class="info-card-value">R$
                                                <?= number_format($resumo_geral['total_icms'] ?? 0, 2, ',', '.') ?>
                                            </div>
                                            <div class="info-card-label">ICMS Total</div>
                                        </div>
                                    </div>
                                    <!-- Card: IPI Total -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card saida">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($resumo_geral['total_ipi'], 2, ',', '.') ?>
                                            </div>
                                            <div class="info-card-label">IPI Total</div>
                                        </div>
                                    </div>
                                    <!-- Card: ICMS-SUB Total -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card saida">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($total_icms_sub_cabecalho, 2, ',', '.') ?>
                                            </div>
                                            <div class="info-card-label">ICMS-SUB Total</div>
                                        </div>
                                    </div>
                                    <!-- Card: Outras -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card saida">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($total_outras_despesas, 2, ',', '.') ?></div>
                                            <div class="info-card-label">Outras Desp.</div>
                                        </div>
                                    </div>
                                    <!-- Card: Pis/Cofins-->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card saida">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($total_pis_cabecalho, 2, ',', '.') ?></div>
                                            <div class="info-card-label">Pis</div>
                                        </div>
                                    </div>
                                    <!-- Card: Cofins -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card saida">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($total_cofins_cabecalho, 2, ',', '.') ?></div>
                                            <div class="info-card-label">Cofins</div>
                                        </div>
                                    </div>

                                    <!-- Card: Difal -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card saida">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($total_difal_cabecalho, 2, ',', '.') ?></div>
                                            <div class="info-card-label">Difal</div>
                                        </div>
                                    </div>
                                    <!-- Card: Fcp -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card saida">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($total_fcp_cabecalho, 2, ',', '.') ?></div>
                                            <div class="info-card-label">Fcp</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Seção de Entradas -->
                        <div class="card mb-4" style="background-color: #1e2125;">
                            <div
                                class="card-header d-flex justify-content-between align-items-center bg-transparent border-secondary">
                                <h5 class="mb-0">Entradas</h5>
                                <span class="badge bg-success">NFs de Compra</span>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <!-- Card: NFs Entradas -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card entrada">
                                            <div class="info-card-value">
                                                <?php echo isset($resumo_geral_entrada['total_nf']) ? number_format($resumo_geral_entrada['total_nf'], 0, ',', '.') : 0; ?>
                                            </div>
                                            <div class="info-card-label">NFs Entradas</div>
                                        </div>
                                    </div>
                                    <!-- Card: Total Entradas -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card entrada">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($venda_total_cabecalho_e, 2, ',', '.') ?></div>
                                            <div class="info-card-label">Total Entradas</div>
                                        </div>
                                    </div>
                                    <!-- Card: ICMS Total -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card entrada">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($total_icms_cabecalho_e, 2, ',', '.') ?></div>
                                            <div class="info-card-label">ICMS Total</div>
                                        </div>
                                    </div>
                                    <!-- Card: IPI Total -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card entrada">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($total_ipi_cabecalho_e, 2, ',', '.') ?></div>
                                            <div class="info-card-label">IPI Total</div>
                                        </div>
                                    </div>
                                    <!-- Card: ICMS-SUB Total -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card entrada">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($total_icms_sub_cabecalho_e, 2, ',', '.') ?>
                                            </div>
                                            <div class="info-card-label">ICMS-SUB Total</div>
                                        </div>
                                    </div>
                                    <!-- Card: Pis/Cofins -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card entrada">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($total_outras_despesas_e, 2, ',', '.') ?></div>
                                            <div class="info-card-label">Outras Desp.</div>
                                        </div>
                                    </div>

                                    <!-- Card: Pis -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card entrada">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($total_pis_cabecalho_e, 2, ',', '.') ?></div>
                                            <div class="info-card-label">Pis</div>
                                        </div>
                                    </div>
                                    <!-- Card: Pis/Cofins -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card entrada">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($total_cofins_cabecalho_e, 2, ',', '.') ?>
                                            </div>
                                            <div class="info-card-label">Cofins</div>
                                        </div>
                                    </div>

                                    <!-- Card: Pis/Cofins -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card entrada">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($total_difal_cabecalho_e, 2, ',', '.') ?>
                                            </div>
                                            <div class="info-card-label">Difal</div>
                                        </div>
                                    </div>

                                    <!-- Card: Pis/Cofins -->
                                    <div class="col-lg-2 col-md-4 col-6">
                                        <div class="info-card entrada">
                                            <div class="info-card-value">R$
                                                <?php echo number_format($total_fcp_cabecalho_e, 2, ',', '.') ?>
                                            </div>
                                            <div class="info-card-label">Fcp</div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- Gráficos Gerais -->
                        <div class="row">
                            <div class="col-md-12">
                                <h3 class="section-title">Análises Gráficas</h3>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Distribuição de Tributos (Período)</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-chart-container">
                                            <canvas id="graficoTributosGeral"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Icms por Filial (Débito x Crédito)</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-chart-container">
                                            <canvas id="icmsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Evolução Mensal de Saídas
                                            (<?php echo $data_inicio_raw || $data_fim_raw ? 'Período Selecionado' : 'Últimos Meses' ?>)</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-chart-container">
                                            <canvas id="graficoMensal"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Composição das Notas de Saída</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-chart-container">
                                            <canvas id="graficoComposicaoNotas"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12 mt-4">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Evolução Diária - Saídas</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-icms-container">
                                            <canvas id="dailyChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12 mt-4">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Evolução Diária - Entradas</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-icms-container">
                                            <canvas id="dailyCharte"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12 mt-4">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Apuração Mensal de Icms e Alíquota Efetiva</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-aliquotaefetiva-container">
                                            <canvas id="icmsEfetivoChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12 mt-4">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Apuração Mensal de Icms e Alíquota Efetiva</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-graficoICMS-container">
                                            <canvas id="graficoICMS"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Scripts dos Gráficos -->
                            <script>
                            // Dados para gráficos gerais
                            const total_icms_geral =
                                <?php echo isset($resumo_geral['total_icms']) ? $resumo_geral['total_icms'] : 0 ?>;
                            const total_ipi_geral =
                                <?php echo isset($resumo_geral['total_ipi']) ? $resumo_geral['total_ipi'] : 0 ?>;
                            const total_pis_geral =
                                <?php echo isset($resumo_geral['total_pis']) ? $resumo_geral['total_pis'] : 0 ?>;
                            const total_cofins_geral =
                                <?php echo isset($resumo_geral['total_cofins']) ? $resumo_geral['total_cofins'] : 0 ?>;
                            const total_issqn_geral =
                                <?php echo isset($resumo_geral['total_difal']) ? $resumo_geral['total_difal'] : 0 ?>;
                            const total_icms_sub_geral =
                                <?php echo isset($total_icms_sub_cabecalho) ? $total_icms_sub_cabecalho : 0 ?>;
                            const total_outrasdesp_geral =
                                <?php echo isset($resumo_geral['total_outras_geral']) ? $resumo_geral['total_outras_geral'] : 0 ?>;

                            const total_impostos_composicao = total_icms_geral + total_ipi_geral + total_pis_geral +
                                total_cofins_geral + total_issqn_geral + total_icms_sub_geral + total_outrasdesp_geral;

                            // Gráfico 1: Distribuição de Tributos (Geral)
                            const ctx7 = document.getElementById('graficoTributosGeral').getContext('2d');
                            new Chart(ctx7, {
                                type: 'pie',
                                data: {
                                    labels: ['ICMS', 'IPI', 'PIS', 'COFINS', 'ICMS-SUB', 'DIFAL',
                                        'OUTRAS DESP.'
                                    ],
                                    datasets: [{
                                        label: 'Valor (R$)',
                                        data: [total_icms_geral, total_ipi_geral, total_pis_geral,
                                            total_cofins_geral, total_icms_sub_geral,
                                            total_issqn_geral, total_outrasdesp_geral
                                        ],
                                        backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545',
                                            '#6f42c1', '#3d139eff', '#504c4dff'
                                        ],
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        title: {
                                            display: true,
                                            text: 'Distribuição de Tributos',
                                            color: '#e0e0e0',
                                            font: {
                                                size: 14
                                            }
                                        },
                                        legend: {
                                            labels: {
                                                color: '#f5f5f5',
                                                font: {
                                                    size: 12
                                                }
                                            }
                                        },
                                        tooltip: {
                                            titleColor: '#ffffff',
                                            bodyColor: '#ffffff',
                                            callbacks: {
                                                label: function(context) {
                                                    const total = context.dataset.data.reduce(function(a,
                                                        b) {
                                                        return a + b;
                                                    }, 0);
                                                    const value = context.parsed;
                                                    const percentage = total > 0 ? ((value / total) * 100)
                                                        .toFixed(1) : 0;
                                                    return context.label + ': R$ ' + value.toLocaleString(
                                                        'pt-BR', {
                                                            style: 'currency',
                                                            currency: 'BRL'
                                                        }) + ' (' + percentage + '%)';
                                                }
                                            }
                                        }
                                    }
                                }
                            });

                            // Gráfico 4: Margem por Filial
                            const ctx5 = document.getElementById('icmsChart').getContext('2d');

                            new Chart(ctx5, {
                                type: 'bar',
                                data: {
                                    labels: <?php echo json_encode($filial_labels_icms); ?>,
                                    color: '#f5f5f5',
                                    datasets: [{
                                            label: 'ICMS Saídas (Débito)',
                                            data: <?php echo json_encode($icms_vendas); ?>,
                                            backgroundColor: 'rgba(220, 53, 69, 0.7)',
                                        },
                                        {
                                            label: 'ICMS Entradas (Crédito)',
                                            data: <?php echo json_encode($icms_entradas); ?>,
                                            backgroundColor: 'rgba(40, 167, 69, 0.7)',
                                        },
                                        {
                                            label: 'Saldo (Débito - Crédito)',
                                            data: <?php echo json_encode($icms_diferenca); ?>,
                                            backgroundColor: 'rgba(255, 193, 7, 0.7)',
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            labels: {
                                                color: '#f5f5f5'
                                            },
                                            position: 'top',
                                        },
                                        title: {
                                            display: true,
                                            color: '#e0e0e0',
                                            text: 'ICMS por Filial'
                                        }
                                    },
                                    scales: {
                                        x: {
                                            ticks: {
                                                color: '#f0f0f0',
                                            }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                color: '#f0f0f0',
                                                callback: function(value) {
                                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                                }
                                            }
                                        }
                                    }
                                }
                            });

                            // Gráfico 5: Composição das Notas
                            const ctx9 = document.getElementById('graficoComposicaoNotas').getContext('2d');
                            new Chart(ctx9, {
                                type: 'pie',
                                data: {
                                    labels: ['Total Produtos', 'ICMS', 'ICMS-SUB', 'IPI', 'PIS', 'COFINS',
                                        'Outras Despesas', 'Descontos', 'Difal'
                                    ],
                                    datasets: [{
                                        label: 'Proporção no Total da Nota (R$)',
                                        data: [
                                            <?php echo isset($total_produtos_geral) ? $total_produtos_geral : 0 ?>,
                                            <?php echo isset($total_icms_cabecalho) ? $total_icms_cabecalho : 0 ?>,
                                            <?php echo isset($total_icms_sub_cabecalho) ? $total_icms_sub_cabecalho : 0 ?>,
                                            <?php echo isset($total_ipi_cabecalho) ? $total_ipi_cabecalho : 0 ?>,
                                            <?php echo isset($total_pis_cabecalho) ? $total_pis_cabecalho : 0 ?>,
                                            <?php echo isset($total_cofins_cabecalho) ? $total_cofins_cabecalho : 0 ?>,
                                            <?php echo isset($total_outras_despesas) ? $total_outras_despesas : 0 ?>,
                                            <?php echo isset($total_descontos_geral) ? $total_descontos_geral : 0 ?>,
                                            <?php echo isset($total_difal_cabecalho) ? $total_difal_cabecalho : 0 ?>
                                        ],
                                        backgroundColor: ['#343a40', '#007bff', '#28a745', '#ffc107',
                                            '#dc3545', '#17a2b8', '#6c757d', '#fd7e14', '#a414fdff'
                                        ],
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        title: {
                                            display: true,
                                            color: '#e0e0e0',
                                            text: 'Composição Total da Venda'
                                        },
                                        legend: {
                                            labels: {
                                                color: '#f5f5f5',
                                            }
                                        },
                                        tooltip: {
                                            titleColor: '#ffffff',
                                            bodyColor: '#ffffff',
                                            callbacks: {
                                                label: function(context) {
                                                    const total =
                                                        <?php echo isset($venda_total_cabecalho) ? $venda_total_cabecalho : 0 ?>;
                                                    const value = context.parsed;
                                                    const percentage = total > 0 ? ((value / total) * 100)
                                                        .toFixed(1) : 0;
                                                    return context.label + ': R$ ' + value.toLocaleString(
                                                        'pt-BR', {
                                                            style: 'currency',
                                                            currency: 'BRL'
                                                        }) + ' (' + percentage + '%)';
                                                }
                                            }
                                        }
                                    }
                                }
                            });

                            // Gráfico 6: Evolução Mensal
                            const ctx4 = document.getElementById('graficoMensal').getContext('2d');
                            new Chart(ctx4, {
                                type: 'line',
                                data: {
                                    labels: <?php echo json_encode($meses_labels); ?>,
                                    datasets: [{
                                        color: '#f0f0f0',
                                        label: 'Total Vendas Mensal (R$)',
                                        data: <?php echo json_encode($meses_valores); ?>,
                                        fill: false,
                                        borderColor: 'rgb(75, 192, 192)',
                                        tension: 0.1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        x: {
                                            ticks: {
                                                color: '#f0f0f0',
                                            }
                                        },
                                        y: {
                                            ticks: {
                                                color: '#f0f0f0',
                                            },
                                            beginAtZero: true
                                        }
                                    },
                                    plugins: {
                                        legend: {
                                            labels: {
                                                color: '#f0f0f0'
                                            }
                                        }
                                    }
                                }
                            });

                            // Gráfico Diário - Saídas
                            const dailyLabels = <?php echo json_encode($labels); ?>;
                            //  const dailyValues = <?php echo json_encode($val_totaldanf); ?>;
                            const dailyICMS = <?php echo json_encode($val_icms); ?>;
                            const dailyIPI = <?php echo json_encode($val_ipi); ?>;
                            const dailyPISCOFINS = <?php echo json_encode($val_pis_cofins); ?>;
                            const dailyvalor_icms_sub = <?php echo json_encode($val_icms_sub); ?>;
                            const dailyOutras = <?php echo json_encode($val_outras); ?>;
                            const dailyDifal = <?php echo json_encode($val_difal); ?>;

                            const dailyCtx = document.getElementById('dailyChart').getContext('2d');
                            new Chart(dailyCtx, {
                                type: 'line',
                                data: {
                                    labels: dailyLabels,
                                    datasets: [
                                        //{
                                        //  label: 'Valor Total NF',
                                        //  data: dailyValues,
                                        //         borderColor: '#3498db',
                                        //       backgroundColor: 'rgba(52, 152, 219, 0.1)',
                                        //     tension: 0.3,
                                        //   fill: true,
                                        //            borderWidth: 2
                                        //      },
                                        {
                                            label: 'ICMS',
                                            data: dailyICMS,
                                            borderColor: '#e74c3c',
                                            backgroundColor: 'rgba(231, 76, 60, 0.1)',
                                            tension: 0.3,
                                            fill: true,
                                            borderWidth: 2
                                        },
                                        {
                                            label: 'IPI',
                                            data: dailyIPI,
                                            borderColor: '#f1c40f',
                                            backgroundColor: 'rgba(241, 196, 15, 0.1)',
                                            tension: 0.3,
                                            fill: true,
                                            borderWidth: 2
                                        },
                                        {
                                            label: 'PIS/COFINS',
                                            data: dailyPISCOFINS,
                                            borderColor: '#2ecc71',
                                            backgroundColor: 'rgba(46, 204, 113, 0.1)',
                                            tension: 0.3,
                                            fill: true,
                                            borderWidth: 2
                                        },
                                        {
                                            label: 'ICMS ST',
                                            data: dailyvalor_icms_sub,
                                            borderColor: '#9b59b6',
                                            backgroundColor: 'rgba(155, 89, 182, 0.1)',
                                            tension: 0.3,
                                            fill: true,
                                            borderWidth: 2
                                        },
                                        {
                                            label: 'Outras Despesas',
                                            data: dailyOutras,
                                            borderColor: '#34495e',
                                            backgroundColor: 'rgba(52, 73, 94, 0.1)',
                                            tension: 0.3,
                                            fill: true,
                                            borderWidth: 2
                                        },
                                        {
                                            label: 'Difal',
                                            data: dailyDifal,
                                            borderColor: '#260541ff',
                                            backgroundColor: 'rgba(182, 10, 191, 0.1)',
                                            tension: 0.3,
                                            fill: true,
                                            borderWidth: 2
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'top',
                                            labels: {
                                                color: '#f5f5f5',
                                                font: {
                                                    size: 13
                                                }
                                            }
                                        },
                                        title: {
                                            display: true,
                                            text: 'Evolução Diária de Tributos',
                                            color: '#e0e0e0',
                                            font: {
                                                size: 16,
                                                weight: 'bold'
                                            },
                                            padding: {
                                                top: 10,
                                                bottom: 20
                                            }
                                        },
                                        tooltip: {
                                            titleColor: '#ffffff',
                                            bodyColor: '#ffffff',
                                            backgroundColor: 'rgba(50, 50, 50, 0.9)',
                                            borderColor: '#888',
                                            borderWidth: 1,
                                            callbacks: {
                                                label: function(context) {
                                                    return context.dataset.label + ': R$ ' + context.raw
                                                        .toLocaleString('pt-BR', {
                                                            minimumFractionDigits: 2
                                                        });
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            ticks: {
                                                color: '#f0f0f0',
                                                font: {
                                                    size: 12
                                                }
                                            },
                                            grid: {
                                                color: 'rgba(255, 255, 255, 0.1)'
                                            }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                color: '#f0f0f0',
                                                callback: function(value) {
                                                    return 'R$ ' + value.toLocaleString('pt-BR', {
                                                        minimumFractionDigits: 2
                                                    });
                                                }
                                            },
                                            grid: {
                                                color: 'rgba(255, 255, 255, 0.1)'
                                            }
                                        }
                                    }
                                }
                            });

                            // Gráfico Diário - Entradas
                            const dailyLabelse = <?php echo json_encode($labels_entradas); ?>;
                            //    const dailyValuese = <?php echo json_encode($val_totaldanf_entradas); ?>;
                            const dailyICMSe = <?php echo json_encode($val_icms_entradas); ?>;
                            const dailyIPIe = <?php echo json_encode($val_ipi_entradas); ?>;
                            const dailyPISCOFINSe = <?php echo json_encode($val_pis_cofins_entradas); ?>;
                            const dailyvalor_icms_sube = <?php echo json_encode($val_icms_sub_entradas); ?>;
                            const dailyOutrase = <?php echo json_encode($val_outras_entradas); ?>;

                            const dailyCtxe = document.getElementById('dailyCharte').getContext('2d');
                            new Chart(dailyCtxe, {
                                type: 'line',
                                data: {
                                    labels: dailyLabelse,
                                    datasets: [
                                        //{
                                        //        label: 'Valor Total NF',
                                        //      data: dailyValuese,
                                        //    borderColor: '#3498db',
                                        //  backgroundColor: 'rgba(52, 152, 219, 0.1)',
                                        //            tension: 0.3,
                                        //          fill: true,
                                        //        borderWidth: 2
                                        //  },
                                        {
                                            label: 'ICMS',
                                            data: dailyICMSe,
                                            borderColor: '#e74c3c',
                                            backgroundColor: 'rgba(231, 76, 60, 0.1)',
                                            tension: 0.3,
                                            fill: true,
                                            borderWidth: 2
                                        },
                                        {
                                            label: 'IPI',
                                            data: dailyIPIe,
                                            borderColor: '#f1c40f',
                                            backgroundColor: 'rgba(241, 196, 15, 0.1)',
                                            tension: 0.3,
                                            fill: true,
                                            borderWidth: 2
                                        },
                                        {
                                            label: 'PIS/COFINS',
                                            data: dailyPISCOFINSe,
                                            borderColor: '#2ecc71',
                                            backgroundColor: 'rgba(46, 204, 113, 0.1)',
                                            tension: 0.3,
                                            fill: true,
                                            borderWidth: 2
                                        },
                                        {
                                            label: 'ICMS ST',
                                            data: dailyvalor_icms_sube,
                                            borderColor: '#9b59b6',
                                            backgroundColor: 'rgba(155, 89, 182, 0.1)',
                                            tension: 0.3,
                                            fill: true,
                                            borderWidth: 2
                                        },
                                        {
                                            label: 'Outras Despesas',
                                            data: dailyOutrase,
                                            borderColor: '#34495e',
                                            backgroundColor: 'rgba(52, 73, 94, 0.1)',
                                            tension: 0.3,
                                            fill: true,
                                            borderWidth: 2
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'top',
                                            labels: {
                                                color: '#f5f5f5',
                                                font: {
                                                    size: 13
                                                }
                                            }
                                        },
                                        title: {
                                            display: true,
                                            text: 'Evolução Diária de Tributos - Entradas',
                                            color: '#e0e0e0',
                                            font: {
                                                size: 16,
                                                weight: 'bold'
                                            },
                                            padding: {
                                                top: 10,
                                                bottom: 20
                                            }
                                        },
                                        tooltip: {
                                            titleColor: '#ffffff',
                                            bodyColor: '#ffffff',
                                            backgroundColor: 'rgba(50, 50, 50, 0.9)',
                                            borderColor: '#888',
                                            borderWidth: 1,
                                            callbacks: {
                                                label: function(context) {
                                                    return context.dataset.label + ': R$ ' + context.raw
                                                        .toLocaleString('pt-BR', {
                                                            minimumFractionDigits: 2
                                                        });
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            ticks: {
                                                color: '#f0f0f0',
                                                font: {
                                                    size: 12
                                                }
                                            },
                                            grid: {
                                                color: 'rgba(255, 255, 255, 0.1)'
                                            }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                color: '#f0f0f0',
                                                callback: function(value) {
                                                    return 'R$ ' + value.toLocaleString('pt-BR', {
                                                        minimumFractionDigits: 2
                                                    });
                                                }
                                            },
                                            grid: {
                                                color: 'rgba(255, 255, 255, 0.1)'
                                            }
                                        }
                                    }
                                }
                            });

                            // Gráfico Alíquota Efetiva de ICMS
                            const ctx = document.getElementById('icmsEfetivoChart').getContext('2d');
                            new Chart(ctx, {
                                data: {
                                    labels: <?php echo json_encode($periodos); ?>,
                                    datasets: [{
                                            type: 'bar',
                                            label: 'Débito (Saída)',
                                            data: <?php echo json_encode($icms_debito); ?>,
                                            backgroundColor: 'rgba(220, 53, 69, 0.8)',
                                            yAxisID: 'y',
                                            barPercentage: 0.4,
                                            categoryPercentage: 0.6,
                                        },
                                        {
                                            type: 'bar',
                                            label: 'Crédito (Entrada)',
                                            data: <?php echo json_encode($icms_credito); ?>,
                                            backgroundColor: 'rgba(40, 167, 69, 0.8)',
                                            yAxisID: 'y',
                                            barPercentage: 0.4,
                                            categoryPercentage: 0.6,
                                        },
                                        {
                                            type: 'line',
                                            label: 'Alíquota Efetiva (%)',
                                            data: <?php echo json_encode($aliquota_efetiva); ?>,
                                            borderColor: 'rgba(255, 206, 86, 1)',
                                            borderWidth: 2,
                                            fill: false,
                                            yAxisID: 'y1',
                                            datalabels: {
                                                align: 'top',
                                                anchor: 'end',
                                                formatter: function(value) {
                                                    return value.toFixed(2) + '%';
                                                },
                                                color: '#e9dfdfff',
                                                font: {
                                                    weight: 'bold'
                                                }
                                            }
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'top',
                                            labels: {
                                                color: '#e0e0e0'
                                            }
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    if (context.dataset.yAxisID === 'y1') {
                                                        return context.dataset.label + ': ' + context
                                                            .formattedValue + '%';
                                                    } else {
                                                        return context.dataset.label + ': R$ ' + Number(
                                                            context.raw).toLocaleString('pt-BR');
                                                    }
                                                }
                                            }
                                        },
                                        datalabels: { // Configuração geral para DataLabels
                                            color: '#fff',
                                            font: {
                                                weight: 'bold'
                                            },
                                            formatter: function(value, context) {
                                                // Mostrar apenas os labels da linha
                                                if (context.dataset.type === 'line') {
                                                    return value.toFixed(2) + '%';
                                                }
                                                return null; // Ocultar para as barras
                                            }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            ticks: {
                                                color: '#e0e0e0'
                                            }
                                        },
                                        y: {
                                            type: 'linear',
                                            position: 'left',
                                            beginAtZero: true,
                                            ticks: {
                                                color: '#e0e0e0',
                                                callback: function(value) {
                                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                                }
                                            }
                                        },
                                        y1: {
                                            type: 'linear',
                                            position: 'right',
                                            beginAtZero: true,
                                            grid: {
                                                drawOnChartArea: false
                                            },
                                            ticks: {
                                                color: '#e0e0e0',
                                                callback: function(value) {
                                                    return value + '%';
                                                }
                                            }
                                        }
                                    }
                                },
                                plugins: [ChartDataLabels]
                            });



                            const ctx10 = document.getElementById('graficoICMS').getContext('2d');

                            const graficoICMS = new Chart(ctx10, {
                                type: 'bar',
                                data: {
                                    labels: <?= json_encode($meses) ?>,
                                    datasets: [{
                                            label: 'Débito (Saída)',
                                            data: <?= json_encode($val_debito) ?>,
                                            backgroundColor: 'rgba(220, 53, 69, 0.8)',
                                            borderColor: 'rgba(220, 53, 69, 1)',
                                            borderWidth: 1
                                        },
                                        {
                                            label: 'Crédito (Entrada)',
                                            data: <?= json_encode($val_credito) ?>,
                                            backgroundColor: 'rgba(40, 167, 69, 0.8)',
                                            borderColor: 'rgba(40, 167, 69, 1)',
                                            borderWidth: 1
                                        },
                                        {
                                            label: 'Alíquota Efetiva (%)',
                                            data: <?= json_encode($aliquota_efetiva) ?>,
                                            type: 'line',
                                            yAxisID: 'y1',
                                            borderColor: 'rgba(255, 193, 7, 1)',
                                            backgroundColor: 'rgba(255, 193, 7, 0.1)',
                                            borderWidth: 2,
                                            tension: 0.3,
                                            pointRadius: 5,
                                            pointBackgroundColor: 'rgba(255, 193, 7, 1)',
                                            pointStyle: 'circle'
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    interaction: {
                                        mode: 'index',
                                        intersect: false
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            title: {
                                                display: true,
                                                text: 'ICMS (R$)'
                                            },
                                            ticks: {
                                                callback: value => 'R$ ' + value.toLocaleString('pt-BR')
                                            }
                                        },
                                        y1: {
                                            position: 'right',
                                            title: {
                                                display: true,
                                                text: 'Alíquota Efetiva (%)'
                                            },
                                            grid: {
                                                drawOnChartArea: false
                                            },
                                            ticks: {
                                                callback: value => value.toFixed(2) + '%'
                                            }
                                        }
                                    },
                                    plugins: {
                                        legend: {
                                            labels: {
                                                color: '#fff'
                                            }
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(ctx) {
                                                    if (ctx.dataset.label.includes('%'))
                                                        return ctx.parsed.y.toFixed(2) + '%';
                                                    else
                                                        return 'R$ ' + ctx.parsed.y.toLocaleString(
                                                            'pt-BR', {
                                                                minimumFractionDigits: 2
                                                            });
                                                }
                                            }
                                        },
                                        title: {
                                            display: true,
                                            text: 'Apuração Mensal do ICMS e Alíquota Efetiva',
                                            color: '#fff'
                                        }
                                    }
                                }
                            });
                            </script>







                            </script>
                            <?php endif; ?>

                            <!-- BEGIN theme-panel -->
                        </div>
                        <!-- END card-body -->
                    </div>
                    <!-- END #content -->
                </div>
            </div>

    </div>

    </div>
    <!-- /.row (main row) -->
    </div>
    <!--end::Container-->
    </div>
    <!--end::App Content-->
    </main>
    <!--end::App Main-->
    <!--begin::Footer-->
    <?php 
        require_once '../menu/footer.php';
        ?>
    <!--end::Footer-->
    </div>
    <!--end::App Wrapper-->
    <!--begin::Script-->
    <!--begin::Third Party Plugin(OverlayScrollbars)-->
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"
        crossorigin="anonymous"></script>
    <!--end::Third Party Plugin(OverlayScrollbars)-->
    <!--begin::Required Plugin(popperjs for Bootstrap 5)-->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous">
    </script>
    <!--end::Required Plugin(popperjs for Bootstrap 5)-->
    <!--begin::Required Plugin(Bootstrap 5)-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous">
    </script>
    <!--end::Required Plugin(Bootstrap 5)-->
    <!--begin::Required Plugin(AdminLTE)-->
    <script src="../js/adminlte.js"></script>
    <!--end::Required Plugin(AdminLTE)-->
    <!--begin::OverlayScrollbars Configure-->
    <script>
    const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
    const Default = {
        scrollbarTheme: 'os-theme-light',
        scrollbarAutoHide: 'leave',
        scrollbarClickScroll: true,
    };
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
        if (sidebarWrapper && OverlayScrollbarsGlobal?.OverlayScrollbars !== undefined) {
            OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
                scrollbars: {
                    theme: Default.scrollbarTheme,
                    autoHide: Default.scrollbarAutoHide,
                    clickScroll: Default.scrollbarClickScroll,
                },
            });
        }
    });
    </script>
    <!--end::OverlayScrollbars Configure-->
    <!-- OPTIONAL SCRIPTS -->
    <!-- sortablejs -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" crossorigin="anonymous"></script>
    <!-- sortablejs -->
    <script>
    new Sortable(document.querySelector('.connectedSortable'), {
        group: 'shared',
        handle: '.card-header',
    });

    const cardHeaders = document.querySelectorAll('.connectedSortable .card-header');
    cardHeaders.forEach((cardHeader) => {
        cardHeader.style.cursor = 'move';
    });
    </script>

    <script>
    function setPeriodo(tipo) {
        const hoje = new Date();
        let dataInicio, dataFim;

        switch (tipo) {
            case 'hoje':
                dataInicio = dataFim = formatDate(hoje);
                break;
            case 'semana':
                const inicioSemana = new Date(hoje);
                inicioSemana.setDate(hoje.getDate() - hoje.getDay());
                dataInicio = formatDate(inicioSemana);
                dataFim = formatDate(hoje);
                break;
            case 'mes':
                dataInicio = formatDate(new Date(hoje.getFullYear(), hoje.getMonth(), 1));
                dataFim = formatDate(new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0));
                break;
            case 'primeiro_trimestre':
                dataInicio = formatDate(new Date(hoje.getFullYear(), 0, 1));
                dataFim = formatDate(new Date(hoje.getFullYear(), 2, 31));
                break;
            case 'segundo_trimestre':
                dataInicio = formatDate(new Date(hoje.getFullYear(), 3, 1));
                dataFim = formatDate(new Date(hoje.getFullYear(), 5, 30));
                break;
            case 'terceiro_trimestre':
                dataInicio = formatDate(new Date(hoje.getFullYear(), 6, 1));
                dataFim = formatDate(new Date(hoje.getFullYear(), 8, 30));
                break;
            case 'quarto_trimestre':
                dataInicio = formatDate(new Date(hoje.getFullYear(), 9, 1));
                dataFim = formatDate(new Date(hoje.getFullYear(), 11, 31));
                break;
            case 'primeiro_semestre':
                dataInicio = formatDate(new Date(hoje.getFullYear(), 0, 1));
                dataFim = formatDate(new Date(hoje.getFullYear(), 5, 30));
                break;
            case 'segundo_semestre':
                dataInicio = formatDate(new Date(hoje.getFullYear(), 6, 1));
                dataFim = formatDate(new Date(hoje.getFullYear(), 11, 31));
                break;
            case 'ano':
                dataInicio = formatDate(new Date(hoje.getFullYear(), 0, 1));
                dataFim = formatDate(new Date(hoje.getFullYear(), 11, 31));
                break;
        }

        document.getElementById('data_inicio').value = dataInicio;
        document.getElementById('data_fim').value = dataFim;
    }

    function formatDate(date) {
        const dia = String(date.getDate()).padStart(2, '0');
        const mes = String(date.getMonth() + 1).padStart(2, '0');
        const ano = date.getFullYear();
        return `${dia}/${mes}/${ano}`;
    }
    </script>

    <script>
    function aplicarMascaraData(input) {
        // Remove tudo que não for número
        let valor = input.value.replace(/\D/g, '');

        // Limita a 8 dígitos (ddmmyyyy)
        if (valor.length > 8) {
            valor = valor.substring(0, 8);
        }

        // Aplica a máscara conforme o comprimento
        if (valor.length >= 5) {
            valor = valor.replace(/(\d{2})(\d{2})(\d{0,4})/, '$1/$2/$3');
        } else if (valor.length >= 3) {
            valor = valor.replace(/(\d{2})(\d{0,2})/, '$1/$2');
        }

        input.value = valor;
    }

    // Aplica a máscara aos campos de data
    document.getElementById('data_inicio').addEventListener('input', function(e) {
        aplicarMascaraData(e.target);
    });

    document.getElementById('data_fim').addEventListener('input', function(e) {
        aplicarMascaraData(e.target);
    });
    </script>
</body>

</html>