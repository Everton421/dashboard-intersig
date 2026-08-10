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
};


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
    foreach ($params as $param) {
        $escaped_param = "'" . mysqli_real_escape_string($conexao, $param) . "'";
        $sql = preg_replace('/\?/', $escaped_param, $sql, 1);
    }
    
    $resultado = mysqli_query($conexao, $sql);
    
    if (!$resultado) {
    
        return false;
    }
    
    return $resultado;
}



$join_series = " left join {$banco_vendas}.series s on (s.codigo = n.serie and s.filial = n.filial) ";
$condicao_fixas = "agrup_destino = 0 
            AND caucao = '0000-00-00' 
            AND inadimplencia = '0000-00-00' 
            AND cartorio = '0000-00-00' 
            AND advogado = '0000-00-00' 
            AND cobradora = '0000-00-00' 
            AND correio = '0000-00-00' 
            AND spc_serasa = '0000-00-00'";

// --- FILTROS DINÂMICOS ---
$filial = isset($_GET['filial']) ? $_GET['filial'] : '';
$data_inicio_raw = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$data_fim_raw = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';

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

$filial_esc = mysqli_real_escape_string($dash, $filial);
$data_inicio_esc = mysqli_real_escape_string($dash, $data_inicio);
$data_fim_esc = mysqli_real_escape_string($dash, $data_fim);

$filtro_filial = $filial ? " AND filial = '{$filial_esc}'" : "";
$filtro_data = "";
if ($data_inicio) $filtro_data .= " AND vencimento >= '{$data_inicio_esc}'";
if ($data_fim) $filtro_data .= " AND vencimento <= '{$data_fim_esc}'";

// Filtros compostos para serem usados nas queries
$filtro_receb = " {$filtro_data} {$filtro_filial} AND {$condicao_fixas} ";
$filtro_receb1 = " {$filtro_filial} AND {$condicao_fixas} ";
$filtro_pagar = " {$filtro_data} {$filtro_filial} AND agrup_destino = 0 ";

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
$sql_diario = " SELECT 
        data_mvto,
        SUM(valor_a_receber) as valor_a_receber,
        SUM(valor_recebido) as valor_recebido
    FROM (
        SELECT 
            vencimento as data_mvto,
            SUM(valor) as valor_a_receber,
            0 as valor_recebido
        FROM 
         {$banco_financeiro}.ct_receb 
        WHERE 
            1=1 {$filtro_receb}
        GROUP BY 
            vencimento

        UNION ALL

        SELECT 
            vencimento as data_mvto,
            0 as valor_a_receber,
            SUM(valor) as valor_recebido
        FROM 
          {$banco_financeiro}.ct_receb 
        WHERE 
            1=1 {$filtro_receb}
            AND valor_pago > 0
        GROUP BY 
            vencimento
    ) AS combined_data
    GROUP BY 
        data_mvto
";

$result_diario = executar_consulta($dash, $sql_diario, []);


$labels = array();
$valor_a_receber = array();
$valor_recebido = array();


if ($result_diario) {
    while ($row = mysqli_fetch_assoc($result_diario)) {
        $labels[] = $row['data_mvto'];
        $valor_a_receber[] = round($row['valor_a_receber'], 2);
        $valor_recebido[] = round($row['valor_recebido'], 2);
        
    }
}

// --- GRÁFICO DIÁRIO DE ENTRADAS ---
$sql_diario_entradas = "SELECT 
    data,
    SUM(valor_a_receber) as valor_a_receber,
    SUM(valor_a_pagar) as valor_a_pagar
FROM (
    SELECT 
        vencimento as data,
        SUM(valor) as valor_a_receber,
        0 as valor_a_pagar
    FROM 
        {$banco_financeiro}.ct_receb 
    WHERE 
        1=1 {$filtro_receb}
    GROUP BY 
        vencimento
    
    UNION ALL
    
    SELECT 
        vencimento as data,
        0 as valor_a_receber,
        SUM(valor) as valor_a_pagar
    FROM 
        {$banco_financeiro}.ct_pagar 
    WHERE 
        1=1 {$filtro_pagar}
    GROUP BY 
        vencimento
) AS combined_data
GROUP BY 
    data
";


$result_diario_entradas = executar_consulta($dash, $sql_diario_entradas, []);

$labels_entradas = array();
$val_totaldanf_entradas = array();
$val_icms_entradas = array();


if ($result_diario_entradas) {
    while ($row = mysqli_fetch_assoc($result_diario_entradas)) {
        $labels_entradas[] = $row['data'];
        $val_totaldanf_entradas[] = round($row['valor_a_receber'], 2);
        $val_icms_entradas[] = round($row['valor_a_pagar'], 2);
    }
}



// --- GRÁFICO ICMS POR FILIAL (receber VS recebido) ---
$sql_receber_recebido = " SELECT 
        f.nome_fantasia as filial_nome,
        SUM(valor_a_receber) as valor_a_receber,
        SUM(valor_recebido) as valor_recebido
    FROM (
        SELECT 
            filial,
            SUM(valor) as valor_a_receber,
            0 as valor_recebido
        FROM 
           {$banco_financeiro}.ct_receb 
        WHERE 
            1=1 {$filtro_receb}
        GROUP BY 
            filial

        UNION ALL

        SELECT 
            filial,
            0 as valor_a_receber,
            SUM(valor) as valor_recebido
        FROM 
           {$banco_financeiro}.ct_receb 
        WHERE 
            1=1 {$filtro_receb}
            AND valor_pago > 0
        GROUP BY 
            filial
    ) AS combined_data
    LEFT JOIN {$banco_vendas}.empresas f ON (f.filial = combined_data.filial)
    GROUP BY 
        combined_data.filial, f.nome_fantasia
";
$result_receber_recebido = executar_consulta($dash, $sql_receber_recebido, []);

$filial_labels_icms = array();
$v_arecebers   = array();
$v_recebidos = array();
$diferencas = array();

if ($result_receber_recebido) {
    while ($row = mysqli_fetch_assoc($result_receber_recebido)) {
        $name_label = !empty($row['filial_nome']) ? $row['filial_nome'] : "Filial " . $row['filial'];
        $filial_labels_icms[]  = $name_label;
        $v_areceber   = isset($row['valor_a_receber']) ? $row['valor_a_receber'] : 0;
        $v_recebido = isset($row['valor_recebido']) ? $row['valor_recebido'] : 0;
        $diferenca    = $v_recebido - $v_areceber; 

        $v_arecebers[]    = round($v_areceber, 2);
        $v_recebidos[]  = round($v_recebido, 2);
        $diferencas[] = round($diferenca, 2);
    }
}


// --- GRÁFICO pagar VS pago POR FILIAL (receber VS recebido) ---
$sql_pagar_pago = " SELECT 
        f.nome_fantasia as filial_nome,
       SUM(r.valor) AS valor_a_pagar,
    SUM(r.valor_pago) AS valor_pago
        FROM  {$banco_financeiro}.ct_pagar r
        LEFT JOIN {$banco_vendas}.empresas f ON (f.filial = r.filial)
    WHERE  1=1 {$filtro_pagar}
    GROUP BY r.filial, f.nome_fantasia
";
$result_pagar_pago = executar_consulta($dash, $sql_pagar_pago, []);

$filial_labels_pagar = array();
$v_apagers   = array();
$v_pagos = array();
$diferencasp = array();

if ($result_pagar_pago) {
    while ($row = mysqli_fetch_assoc($result_pagar_pago)) {
        $name_label = !empty($row['filial_nome']) ? $row['filial_nome'] : "Filial " . $row['filial'];
        $filial_labels_pagar[]  = $name_label;
        $v_apagar   = isset($row['valor_a_pagar']) ? $row['valor_a_pagar'] : 0;
        $v_pago = isset($row['valor_pago']) ? $row['valor_pago'] : 0;
        $diferenca_p    = $v_pago - $v_apagar; 

        $v_apagers[]    = round($v_apagar, 2);
        $v_pagos[]  = round($v_pago, 2);
        $diferencasp[] = round($diferenca_p, 2);
    }
}





$sqlgraficogeral = "   SELECT 
                MONTH(vencimento) AS mes_num,
				 DATE_FORMAT(vencimento, '%b %Y') AS mes_ano,
               COALESCE(SUM(receber),0) AS receber,
                COALESCE(SUM(pagar),0) AS pagar
            FROM (
                SELECT vencimento, valor AS receber, NULL AS pagar
                FROM  {$banco_financeiro}.ct_receb
                WHERE 1=1 {$filtro_receb1}
                and YEAR(vencimento) = YEAR(NOW()) 
               
                UNION ALL
                SELECT vencimento, NULL AS receber, valor AS pagar
                FROM  {$banco_financeiro}.ct_pagar
                WHERE 1=1 {$filtro_filial}
                and YEAR(vencimento) = YEAR(NOW()) 
            ) AS subquery
            GROUP BY mes_num, mes_ano
";

$result_graficogeral = executar_consulta($dash, $sqlgraficogeral, []);

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
    $val_debito[$index] = (float)$row['pagar'];
    $val_credito[$index] = (float)$row['receber'];
    
    // Alíquota efetiva = (ICMS Débito - Crédito) / Base de cálculo de saída
    if ($row['pagar'] > 0) {
        $aliquota_efetiva[$index] = (($row['pagar'] - $row['receber']) / $row['pagar']) * 100;
    }
}

// Reindexa arrays para Chart.js
$val_debito = array_values($val_debito);
$val_credito = array_values($val_credito);
$aliquota_efetiva = array_values($aliquota_efetiva);



$querypagtotal = "SELECT COALESCE(SUM(valor),0) AS valor_pagar_total
FROM  {$banco_financeiro}.ct_pagar 
WHERE valor_pago >= 0 
AND valor > 0.01 {$filtro_pagar} AND provisao = 'N'
AND inadimplencia = '0000-00-00' ";
$resultpagtotal = mysqli_query($dash, $querypagtotal);

if (mysqli_num_rows($resultpagtotal) > 0) {
	$row = mysqli_fetch_assoc($resultpagtotal);


	$valor_pagar_total = $row['valor_pagar_total'];
}


$queryrectotal = "	SELECT SUM(valor) AS valor_receber_total 
FROM {$banco_financeiro}.ct_receb 
WHERE valor_pago >= 0 
{$filtro_filial} 
 AND {$condicao_fixas}
AND 1=1 {$filtro_receb} "; 
$resultrectotal = mysqli_query($dash, $queryrectotal);

if (mysqli_num_rows($resultrectotal) > 0) {
	$row = mysqli_fetch_assoc($resultrectotal);


	$valor_receber_total = $row['valor_receber_total'];
}

$queryrecdia = "	SELECT SUM(valor) AS valor_receber_dia
FROM {$banco_financeiro}.ct_receb 
WHERE vencimento = CURRENT_DATE
{$filtro_filial} AND {$condicao_fixas} ";
$resultrecdia = mysqli_query($dash, $queryrecdia);

if (mysqli_num_rows($resultrecdia) > 0) {
	$row = mysqli_fetch_assoc($resultrecdia);


	$valor_receber_dia = $row['valor_receber_dia'];
}

           if ($valor_receber_dia  > 0) {

	$perc_finreceb_dia = ($valor_receber_dia / $valor_receber_total) * 100;
	$perc_finreceb_dia = number_format($perc_finreceb_dia, 2);
} else {

	$perc_finreceb_dia = "0";
}


$querypagdia = "SELECT COALESCE(SUM(valor),0) AS valor_pagar_dia
FROM {$banco_financeiro}.ct_pagar 
WHERE vencimento =  CURRENT_DATE
{$filtro_filial} AND agrup_destino = 0 AND provisao = 'N' ";
$resultpagdia = mysqli_query($dash, $querypagdia);

if (mysqli_num_rows($resultpagdia) > 0) {
	$row = mysqli_fetch_assoc($resultpagdia);


	$valor_pagar_dia = $row['valor_pagar_dia'];
}

$queryrec30d = " SELECT SUM(valor) AS valor_receber_proximos30dias, 
count(distinct(cliente)) as totalcli,
count(distinct(codigo)) as totaltitulo
FROM {$banco_financeiro}.ct_receb 
WHERE valor_pago = 0  AND vencimento BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
{$filtro_filial} AND {$condicao_fixas} ";
$resultrec30d = mysqli_query($dash, $queryrec30d);

// Verifica se a consulta retornou algum resultado
if (mysqli_num_rows($resultrec30d) > 0) {
	$row = mysqli_fetch_assoc($resultrec30d);

	// Atribui cada resultado a uma variável
	$totalclirec30 = $row['totalcli'];
	$totaltitulorec30 = $row['totaltitulo'];
	$valor_receber_proximos30dias = $row['valor_receber_proximos30dias'];
}

if ($valor_receber_proximos30dias  > 0) {

	$perc_finreceb_30d = ($valor_receber_proximos30dias / $valor_receber_total) * 100;
	$perc_finreceb_30d = number_format($perc_finreceb_30d, 2);
} else {

	$perc_finreceb_30d = "0";
}


$querypag30d = " SELECT SUM(valor) AS valor_pagar_proximos30dias,
count(distinct(fornecedor)) as totalfor,
 count(distinct(codigo)) as totaltitulop 
FROM {$banco_financeiro}.ct_pagar 
WHERE valor_pago = 0 
AND vencimento BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
AND valor > 0.01 
{$filtro_filial} AND agrup_destino = 0 
AND provisao = 'N' ";
$resultpag30d = mysqli_query($dash, $querypag30d);

// Verifica se a consulta retornou algum resultado
if (mysqli_num_rows($resultpag30d) > 0) {
	$row = mysqli_fetch_assoc($resultpag30d);

	// Atribui cada resultado a uma variável
	$totalforpag30 = $row['totalfor'];
	$totaltitulopag30 = $row['totaltitulop'];
	$valor_pagar_proximos30dias = $row['valor_pagar_proximos30dias'];
}

if ($valor_pagar_proximos30dias  > 0) {

	$perc_finpagar_30d = ($valor_pagar_proximos30dias / $valor_pagar_total) * 100;
	$perc_finpagar_30d = number_format($perc_finpagar_30d, 2);
} else {

	$perc_finpagar_30d = "0";
}


$queryrecvencer = "	SELECT SUM(valor) AS valor_receber_vencer,
count(distinct(cliente)) as totalclivencer,
count(distinct(codigo)) as totaltitulovencer 
FROM {$banco_financeiro}.ct_receb 
WHERE valor_pago = 0 
AND vencimento >= CURRENT_DATE
{$filtro_filial} AND {$condicao_fixas} ";
$resultrecvencer = mysqli_query($dash, $queryrecvencer);

if (mysqli_num_rows($resultrecvencer) > 0) {
	$row = mysqli_fetch_assoc($resultrecvencer);


	$valor_receber_vencer = $row['valor_receber_vencer'];
	$totalclivencer = $row['totalclivencer'];
	$totaltitulovencer = $row['totaltitulovencer'];
}
if ($valor_receber_vencer  > 0) {

	$perc_fin_pend  = ($valor_receber_vencer / $valor_receber_total) * 100;
	$perc_fin_pend  = number_format($perc_fin_pend, 2);
} else {

	$perc_fin_pend  = "0";
}

$queryrecatra = " SELECT SUM(valor) AS valor_receber_vencidos , 
count(distinct(cliente)) as totalcliatra,
count(distinct(codigo)) as totaltituloatra
FROM {$banco_financeiro}.ct_receb 
	WHERE valor_pago = 0 AND vencimento < CURRENT_DATE
	{$filtro_filial} AND {$condicao_fixas} ";
$resultrecatra = mysqli_query($dash, $queryrecatra);

// Verifica se a consulta retornou algum resultado
if (mysqli_num_rows($resultrecatra) > 0) {
	$row = mysqli_fetch_assoc($resultrecatra);

	// Atribui cada resultado a uma variável
	$totalcliatra = $row['totalcliatra'];
	$totaltituloatra = $row['totaltituloatra'];
	$valor_receber_vencidos = $row['valor_receber_vencidos'];
}

if ($valor_receber_vencidos  > 0) {

	$perc_finreceb_atra = ($valor_receber_vencidos / $valor_receber_total) * 100;
	$perc_finreceb_atra = number_format($perc_finreceb_atra, 2);
} else {

	$perc_finreceb_atra = "0";
}

$querypagvencer = " SELECT COALESCE(SUM(valor),0) AS valor_pagar_avencer,
count(distinct(fornecedor)) as totalforvencer,
 count(distinct(codigo)) as totaltitulovencer 
FROM {$banco_financeiro}.ct_pagar 
WHERE valor_pago = 0 
AND vencimento >=  CURRENT_DATE
AND valor > 0.01 {$filtro_pagar} AND provisao = 'N'
AND inadimplencia = '0000-00-00' ";
$resultpagvencer = mysqli_query($dash, $querypagvencer);

if (mysqli_num_rows($resultpagvencer) > 0) {
	$row = mysqli_fetch_assoc($resultpagvencer);
	$valor_pagar_avencer = $row['valor_pagar_avencer'];
	$totalforvencer = $row['totalforvencer'];
	$totaltitulovencer = $row['totaltitulovencer'];
}


if ($valor_pagar_avencer  > 0) {

	$perc_finpagar_vencer = ($valor_pagar_avencer / $valor_pagar_total) * 100;
	$perc_finpagar_vencer = number_format($perc_finpagar_vencer, 2);
} else {

	$perc_finpagar_vencer = "0";
}

$querypagven = " SELECT COALESCE(SUM(valor),0) AS valor_pagar_vencidos, 
count(distinct(fornecedor)) as totalforvencidos,
 count(distinct(codigo)) as totaltitulovencidos 
FROM {$banco_financeiro}.ct_pagar 
WHERE valor_pago = 0 
AND vencimento <  CURRENT_DATE
AND valor > 0.01 {$filtro_pagar} AND provisao = 'N'
AND inadimplencia = '0000-00-00' ";
$resultpagven = mysqli_query($dash, $querypagven);

if (mysqli_num_rows($resultpagven) > 0) {
	$row = mysqli_fetch_assoc($resultpagven);
	$valor_pagar_vencidos = $row['valor_pagar_vencidos'];
	$totalforvencidos = $row['totalforvencidos'];
	$totaltitulovencidos = $row['totaltitulovencidos'];
}


if ($valor_pagar_vencidos  > 0) {

	$perc_finpagar_venc = ($valor_pagar_vencidos / $valor_pagar_total) * 100;
	$perc_finpagar_venc = number_format($perc_finpagar_venc, 2);
} else {

	$perc_finpagar_venc = "0";
}



	$sql_tiporeceb = "SELECT 
    COALESCE(t.nome, 'Não Identificado') as nome,  
    COALESCE(SUM(r.valor), 0) AS valor, 
    COUNT(t.nome) as qtd
FROM {$banco_financeiro}.ct_receb r
LEFT JOIN {$banco_financeiro}.tip_rece t ON (t.codigo = r.tipo_recebimento)
WHERE 
    1=1 {$filtro_receb}
GROUP BY t.nome"; 

$resultfintipo = mysqli_query($dash, $sql_tiporeceb);

$nometiporeceb = array();
$totalvalorreceb = array();
$qtdtiporeceb = array();

if ($resultfintipo) {
    while ($row = mysqli_fetch_assoc($resultfintipo)) {
        $nometiporeceb[] = $row['nome'];
        $totalvalorreceb[] = (float)$row['valor']; // Cast para float para garantir numérico no JS
        $qtdtiporeceb[] = $row['qtd'];
    }
} else {
    // Tratamento de erro ou array vazio para não quebrar o JS
    $nometiporeceb = ["Sem dados"];
    $totalvalorreceb = [0];
}



	$sql_tipopagamen = "SELECT  COALESCE(t.nome, 'NÃO INFORMADO') as nomep,  SUM(r.valor) AS valorp, COUNT(COALESCE(t.nome, 'NÃO INFORMADO')) as qtdp
FROM {$banco_financeiro}.ct_pagar r
LEFT JOIN {$banco_financeiro}.tip_rece t ON (t.codigo = r.tipo_pagamento)
WHERE 
    1=1 {$filtro_pagar}
GROUP BY t.nome"; 

$resultfintipo = mysqli_query($dash, $sql_tipopagamen);

$nometipopagamen = array();
$totalvalorpagamen = array();
$qtdtipopagamen = array();

if ($resultfintipo) {
    while ($row = mysqli_fetch_assoc($resultfintipo)) {
        $nometipopagamen[] = $row['nomep'];
        $totalvalorpagamen[] = (float)$row['valorp']; // Cast para float para garantir numérico no JS
        $qtdtipopagamen[] = $row['qtdp'];
    }
} else {
    // Tratamento de erro ou array vazio para não quebrar o JS
    $nometipopagamen = ["Sem dados"];
    $totalvalorpagamen = [0];
}





// --- MAIORES CLIENTES ATRASADOS ---
$sql_atrasados = "SELECT 
        ROW_NUMBER() OVER (ORDER BY SUM(r.valor) DESC) AS posi,
        r.cliente as codcliente, 
        c.nome as cliente,
        SUM(r.valor) AS t_atrasado,
        COUNT(DISTINCT r.codigo) AS qtd_t_atrasado,
        ROUND((SUM(r.valor) * 100.0) / SUM(SUM(r.valor)) OVER (), 2) AS percentual,
        AVG(DATEDIFF(CURRENT_DATE, r.vencimento)) AS media_dias_atraso
    FROM {$banco_financeiro}.ct_receb r
    LEFT JOIN {$banco_publico}.cad_clie c ON c.codigo = r.cliente
    WHERE r.valor_pago = 0 
      AND r.vencimento < CURRENT_DATE
      {$filtro_filial}
      AND r.agrup_destino = 0 
      AND r.caucao = '0000-00-00' 
      AND r.inadimplencia = '0000-00-00' 
      AND r.cartorio = '0000-00-00' 
      AND r.advogado = '0000-00-00' 
      AND r.cobradora = '0000-00-00' 
      AND r.correio = '0000-00-00' 
      AND r.spc_serasa = '0000-00-00'
    GROUP BY r.cliente, c.nome
    ORDER BY t_atrasado DESC
    LIMIT 10";

$result_atrasados = mysqli_query($dash, $sql_atrasados);
$clientes_atrasados = [];
if ($result_atrasados) {
    while ($row = mysqli_fetch_assoc($result_atrasados)) {
        $clientes_atrasados[] = $row;
    }
}


$sql_despesas = "
SELECT 
    COALESCE(c.nome, 'NÃO INFORMADO') AS centrocusto,
    SUM(p.valor_pago) AS valorpago,
    ROUND(
        (SUM(p.valor_pago) * 100) / 
        (SELECT NULLIF(SUM(valor_pago), 0) FROM {$banco_financeiro}.ct_pagar WHERE 1=1 {$filtro_pagar}),
    2) AS percentualp
FROM {$banco_financeiro}.ct_pagar p
LEFT JOIN {$banco_publico}.cen_cust c 
    ON p.centro_custo = c.codigo
WHERE 1=1 {$filtro_pagar}  
GROUP BY c.nome
ORDER BY valorpago DESC
LIMIT 10
";

$result_despesas = mysqli_query($dash, $sql_despesas);

$labels_despesas = [];
$percentuais_despesas = [];
$valores_despesas = [];

if ($result_despesas) {
    while ($row = mysqli_fetch_assoc($result_despesas)) {
        $labels_despesas[] = $row['centrocusto'];
        $percentuais_despesas[] = (float)$row['percentualp'];
        $valores_despesas[] = (float)$row['valorpago'];
    }
}
$total_percentual_top10 = array_sum($percentuais_despesas);



$sql_topreceitas = "
SELECT 
    COALESCE(c.nome, 'NÃO INFORMADO') AS centrocusto,
    SUM(p.valor_pago) AS valorpago,
    ROUND(
        (SUM(p.valor_pago) * 100) / 
        (SELECT NULLIF(SUM(valor_pago), 0) FROM {$banco_financeiro}.ct_receb WHERE 1=1 {$filtro_receb}),
    2) AS percentualp
FROM {$banco_financeiro}.ct_receb p
LEFT JOIN {$banco_publico}.cen_cust c 
    ON p.centro_custo = c.codigo
WHERE 1=1 {$filtro_receb}  
GROUP BY c.nome
ORDER BY valorpago DESC
LIMIT 10
";

$result_topreceitas = mysqli_query($dash, $sql_topreceitas);

$labels_topreceitas = [];
$percentuais_topreceitas = [];
$valores_topreceitas = [];

if ($result_topreceitas) {
    while ($row = mysqli_fetch_assoc($result_topreceitas)) {
        $labels_topreceitas[] = $row['centrocusto'];
        $percentuais_topreceitas[] = (float)$row['percentualp'];
        $valores_topreceitas[] = (float)$row['valorpago'];
    }
}
$total_perc_top10receitas = array_sum($percentuais_topreceitas);


?>


<!doctype html>
<html lang="pt-br">


<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Dashboard</title>
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


    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />




    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">


    <style>
    /* ================================== */
    /* DEFINIÇÃO DE VARIÁVEIS DE TEMA     */
    /* ================================== */

    :root {
        /* Tema Claro (Padrão) */
        --font-color-main: #212529;
        /* Cor escura para fundo claro */
        --font-color-sec: #6c757d;
        /* Cor secundária escura */
        --bg-color-card: #ffffff;
        /* Fundo do card claro */
        --border-color-card: #dee2e6;
        /* Borda clara */
        --input-bg: #ffffff;
        --input-border: #ced4da;
        --input-text: #495057;
    }

    /* Sobrescreve variáveis quando o tema for escuro */
    [data-bs-theme="dark"] {
        --font-color-main: #f8f9fa;
        /* Cor clara para fundo escuro */
        --font-color-sec: #adb5bd;
        /* Cor secundária clara */
        --bg-color-card: #2a3038;
        /* Fundo do card escuro */
        --border-color-card: #495057;
        /* Borda escura */
        --input-bg: #1e1e1e;
        --input-border: #444;
        --input-text: #fff;
    }

    body {
        color: var(--font-color-main);
    }

    /* ================================== */
    /* CSS PARA OS CARDS DE RESUMO        */
    /* ================================== */
    .info-card {
        background-color: var(--bg-color-card);
        /* Usando a variável de fundo */
        padding: 1.25rem 1rem;
        border-radius: 8px;
        text-align: center;
        border-left: 4px solid;
        /* Borda lateral colorida */
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
    }

    /* Cor da borda para Saídas */
    .info-card.saida {
        border-left-color: #dc3545;
        /* Vermelho */
    }

    /* Cor da borda para Entradas */
    .info-card.entrada {
        border-left-color: #28a745;
        /* Verde */
    }

    .info-card .info-card-value {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
        color: var(--font-color-main);
        /* Alterado para variável */
    }



    /* Ajuste para mobile */
    @media (max-width: 868px) {
        .info-card .info-card-value {
            font-size: 1.25rem;
        }
    }

    .info-card .info-card-label {
        font-size: 0.8rem;
        color: var(--font-color-sec);
        /* Alterado para variável */
        text-transform: uppercase;
        font-weight: 500;
    }

    .card-header {
        margin-bottom: 15px;
        font-size: 14px;
        border-bottom: 2px solid #3498db;
        padding-bottom: 8px;
        color: var(--font-color-main);
        /* Garante contraste no título */
    }

    .card-body {
        padding: 1.25rem;
        color: var(--font-color-main);
    }

    /* Ajuste para o container do resumo que antes tinha cor fixa */
    .card-resumo-container {
        background-color: var(--bg-color-card) !important;
    }

    .stat-card.entrada {
        background: linear-gradient(135deg, var(--success-color), #3a86ff);
    }

    .stat-card.saida {
        background: linear-gradient(135deg, var(--warning-color), var(--danger-color));
    }

    .stat-card .card-title {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .stat-card .card-text {
        font-size: 0.9rem;
        opacity: 0.9;
    }

    .section-title {
        font-weight: 600;
        margin: 1.5rem 0 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid var(--primary-color);
        display: inline-block;
        color: var(--font-color-main);
    }

    h1 {
        text-align: center;
        color: var(--font-color-main);
        /* Alterado de cor fixa para variável */
        margin-bottom: 25px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: var(--font-color-sec);
        /* Alterado para variável */
    }

    input[type=text] {
        width: 100%;
        padding: 8px;
        margin-bottom: 0;
        border: 1px solid var(--input-border);
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 12px;
        background-color: var(--input-bg);
        color: var(--input-text);
    }

    input[type=text]:focus {
        border-color: #007bff;
        outline: none;
        box-shadow: 0 0 0 2px rgba(0, 123, 255, .25);
    }


    /* --- ESTILOS ESSENCIAIS PARA O AUTOCOMPLETE APARECER --- */
    .ui-autocomplete {
        position: absolute;
        z-index: 1000;
        /* Garante que a lista apareça sobre outros elementos */
        max-height: 250px;
        overflow-y: auto;
        overflow-x: hidden;
        background: #fff;
        border: 1px solid #ddd;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        list-style: none;
        padding: 4px 0;
        margin: 0;
    }

    .ui-menu-item {
        padding: 8px 12px;
        cursor: pointer;
        font-size: 15px;
    }

    /* Estilo do item selecionado com o mouse ou teclado */
    .ui-state-active,
    .ui-menu-item:hover {
        background-color: #007bff;
        color: #fff;
        border: none;
    }

    .select-filial {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid var(--input-border);
        border-radius: 8px;
        background-color: var(--input-bg);
        color: var(--input-text);
        font-size: 15px;
        appearance: none;
        outline: none;
        transition: all 0.2s ease;
        background-image: linear-gradient(45deg, transparent 50%, #00bcd4 50%),
            linear-gradient(135deg, #00bcd4 50%, transparent 50%);
        background-position: calc(100% - 20px) calc(1em + 2px),
            calc(100% - 15px) calc(1em + 2px);
        background-size: 5px 5px, 5px 5px;
        background-repeat: no-repeat;
    }

    .select-filial:hover {
        border-color: #00bcd4;
    }

    .select-filial:focus {
        box-shadow: 0 0 0 2px #00bcd440;
        border-color: #00bcd4;
    }

    .card-chart-container {
        height: 300px;
        position: relative;
    }

    .card-icms-container {
        height: 400px;
        width: 100%;
        position: relative;
    }

    .card-icms-container canvas {
        width: 100% !important;
        height: 100% !important;
    }

    .card-aliquotaefetiva-container {
        height: 500px;
        width: 100%;
        position: relative;
    }

    .card-aliquotaefetiva-container canvas {
        width: 100% !important;
        height: 100% !important;
    }

    .btn-sm-custom {
        padding: 6px 12px;
        font-size: 13px;
        height: 36px;
    }

    .form-control-sm {
        padding: 6px 8px;
        font-size: 13px;
    }

    /* Ajustes Adaptativos nos Cards */
    .card-financeiro-custom,
    .card-atrasados-custom {
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    /* Tema Escuro */
    [data-bs-theme="dark"] .card-financeiro-custom,
    [data-bs-theme="dark"] .card-atrasados-custom {
        background-color: #1a1c24 !important;
        color: #ffffff !important;
        border: 1px solid rgba(255, 255, 255, 0.05) !important;
    }

    [data-bs-theme="dark"] .card-financeiro-custom hr,
    [data-bs-theme="dark"] .card-atrasados-custom hr {
        border-top-color: rgba(255, 255, 255, 0.1) !important;
        opacity: 0.2;
    }

    [data-bs-theme="dark"] .card-financeiro-custom .text-gray-500 {
        color: #9ca3af !important;
    }

    /* Tema Claro */
    [data-bs-theme="light"] .card-financeiro-custom,
    [data-bs-theme="light"] .card-atrasados-custom {
        background-color: #ffffff !important;
        color: #212529 !important;
        border: 1px solid rgba(0, 0, 0, 0.1) !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
    }

    [data-bs-theme="light"] .card-financeiro-custom hr,
    [data-bs-theme="light"] .card-atrasados-custom hr {
        border-top-color: rgba(0, 0, 0, 0.1) !important;
        opacity: 0.1;
    }

    [data-bs-theme="light"] .card-financeiro-custom .text-gray-500,
    [data-bs-theme="light"] .card-financeiro-custom .text-muted {
        color: #6b7280 !important;
    }

    .card-body h2 {
        font-size: 20px !important;
        font-weight: 700 !important;
        letter-spacing: -0.5px;
    }

    .card-body h3 {
        font-size: 16px !important;
    }

    .card-body .text-gray-500 {
        font-size: 11px !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    .mb-3.text-blue-500,
    .mb-3.text-red-500,
    .mb-3.text-gray-500 {
        font-size: 11px !important;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    hr {
        margin: 15px 0 !important;
    }
    </style>



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
                                                                onclick="setperiodo('hoje')">Hoje</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setperiodo('semana')">Esta Semana</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setperiodo('mes')">Este Mês</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setperiodo('primeiro_trimestre')">1º
                                                                Trimestre</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setperiodo('segundo_trimestre')">2º
                                                                Trimestre</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setperiodo('terceiro_trimestre')">3º
                                                                Trimestre</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setperiodo('quarto_trimestre')">4º
                                                                Trimestre</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setperiodo('primeiro_semestre')">1º
                                                                Semestre</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setperiodo('segundo_semestre')">2º
                                                                Semestre</button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="setperiodo('ano')">Este Ano</button>
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


                        <div class="row">
                            <!-- BEGIN col-6 -->
                            <div class="col-xl-5">
                                <!-- BEGIN card -->
                                <div class="card border-0 mb-3 overflow-hidden card-financeiro-custom">
                                    <!-- BEGIN card-body -->
                                    <div class="card-body">
                                        <!-- BEGIN row -->
                                        <div class="row">
                                            <!-- BEGIN col-7 -->
                                            <div class="col-xl-12 col-lg-8">
                                                <!-- BEGIN title -->
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <!-- TOTAL VENDAS -->
                                                    <div class="col-xl-5 col-lg-5">
                                                        <!-- BEGIN title -->
                                                        <div class="mb-4 text-blue-500">
                                                            <b>TOTAL RECEBER</b>
                                                            <span class="ms-2">
                                                                <i class="fa fa-info-circle" data-bs-toggle="popover"
                                                                    data-bs-trigger="hover"
                                                                    data-bs-title="Total a receber referente ao periodo selecionado"
                                                                    data-bs-placement="top"
                                                                    data-bs-content="Somatório de todos os titulos a receber, vencidos e a vencer, não somando os perdidos"></i>
                                                            </span>
                                                        </div>
                                                        <!-- END title -->

                                                        <!-- BEGIN total-sales -->
                                                        <div class="d-flex mb-1">
                                                            <h2 class="mb-0 text-blue-500">
                                                                R$:<span><?php echo number_format($valor_receber_total, 2, ',', '.'); ?></span>
                                                            </h2>
                                                        </div>
                                                        <!-- END total-sales -->

                                                        <!-- BEGIN percentage
												<div class="mb-3 text-blue-500">
													<i class="fa fa-caret-up"></i> <span> <?php echo ($porcentagemformatada); ?>%</span> Meta Atingida
												</div> -->
                                                        <!-- END percentage -->
                                                    </div>


                                                    <!-- TOTAL DEVOLUÇÕES -->
                                                    <div class="col-xl-5 col-lg-5">
                                                        <!-- BEGIN title -->
                                                        <div class="mb-4 text-red-500">
                                                            <b>TOTAL PAGAR</b>
                                                            <span class="ms-2">
                                                                <i class="fa fa-info-circle" data-bs-toggle="popover"
                                                                    data-bs-trigger="hover"
                                                                    data-bs-title="Total a Pagar referente ao periodo selecionado"
                                                                    data-bs-placement="top"
                                                                    data-bs-content="Somatório de todos os valores a pagar."></i>
                                                            </span>
                                                        </div>
                                                        <!-- END title -->

                                                        <!-- BEGIN total-returns -->
                                                        <div class="d-flex mb-1">
                                                            <h2 class="mb-0 text-red-500">
                                                                R$:<span><?php echo number_format($valor_pagar_total, 2, ',', '.'); ?></span>
                                                            </h2>
                                                        </div>
                                                        <!-- END total-returns -->

                                                        <!-- BEGIN percentage 
											<div class="mb-3 text-red-500">
												<i class="fa fa-caret-down"></i> <span> <?php echo ($perc_devo_vendas); ?>%</span> Sobre a Venda
											</div> -->
                                                        <!-- END percentage -->
                                                    </div>
                                                </div>




                                                <!-- END percentage -->
                                                <hr class="bg-white bg-opacity-50" />
                                                <!-- BEGIN row -->
                                                <div class="row text-truncate">
                                                    <!-- BEGIN col-6 -->
                                                    <div class="col-4">
                                                        <div class=" text-gray-500">Receber Hoje</div>
                                                        <h3 class="fw-bold">
                                                            <span><?php echo number_format($valor_receber_dia, 2, ',', '.'); ?></span>
                                                        </h3>
                                                        <div class="mb-3 text-red-500">
                                                            <i class="fa fa-caret-up"></i> <span>
                                                                <?php echo ($perc_finreceb_dia); ?>%</span>
                                                            Sobre a Total
                                                        </div>
                                                    </div>
                                                    <!-- END col-6 -->
                                                    <!-- BEGIN col-6 -->
                                                    <div class="col-4">
                                                        <div class=" text-gray-500">Pagar Hoje</div>
                                                        <h3 class="fw-bold">
                                                            <span><?php echo number_format($valor_pagar_dia, 2, ',', '.'); ?></span>
                                                        </h3>
                                                    </div>
                                                    <!-- END col-6 -->
                                                    <!-- BEGIN col-6 -->
                                                    <?php

											$tedfec = $valor_receber_dia - $valor_pagar_dia
										?>

                                                    <div class="col-4">
                                                        <div class=" text-gray-500">Tendência Fechamento:</div>
                                                        <h3 class="fw-bold">
                                                            <span><?php echo number_format($tedfec, 2, ',', '.'); ?></span>
                                                        </h3>
                                                    </div>
                                                    <!-- END col-6 -->
                                                </div>
                                                <!-- END row -->
                                            </div>
                                            <!-- END col-7 -->
                                            <!-- BEGIN col-5 -->
                                            <div
                                                class="col-xl-5 col-lg-4 align-items-center d-flex justify-content-center">
                                                <div class="card-body p-0">



                                                </div>





                                            </div>
                                            <!-- END col-5 -->
                                        </div>
                                        <!-- END row -->
                                    </div>
                                    <!-- END card-body -->
                                </div>
                                <!-- END card -->
                            </div>


                            <!-- BEGIN col-6 -->
                            <div class="col-xl-3">
                                <!-- BEGIN card -->
                                <div class="card border-0 mb-3 overflow-hidden card-financeiro-custom">
                                    <!-- BEGIN card-body -->
                                    <div class="card-body"
                                        style="background: no-repeat bottom right; background-image: url(../dashboard/images/svg/image2.png); background-size: auto 35%;">
                                        <!-- BEGIN row -->

                                        <!-- BEGIN col-7 -->
                                        <div class="col-xl-7 col-lg-8">
                                            <!-- BEGIN title -->
                                            <div class="mb-3 text-gray-500">
                                                <b>RECEBER PRÓXIMOS 30 DIAS</b>
                                                <span class="ms-2">
                                                    <i class="fa fa-info-circle" data-bs-toggle="popover"
                                                        data-bs-trigger="hover" data-bs-title="TOTAL RECEBER"
                                                        data-bs-placement="top"
                                                        data-bs-content="Total a receber nos próximos 30 dias contando a partir da data atual."></i>
                                                </span>
                                            </div>

                                            <!-- END title -->
                                            <!-- BEGIN total-sales -->
                                            <div class="d-flex mb-1">
                                                <h2 class="mb-0">
                                                    R$:<span><?php echo number_format($valor_receber_proximos30dias, 2, ',', '.'); ?></span>
                                                </h2>

                                                <div class="ms-auto mt-n1 mb-n1">
                                                    <div id="total-sales-sparkline"></div>
                                                </div>
                                            </div>
                                            <!-- END total-sales -->
                                            <!-- BEGIN percentage -->
                                            <div class="mb-3 text-gray-500">

                                                <i class="fa fa-caret-up"></i> <span>
                                                    <?php echo ($perc_finreceb_30d); ?>%</span>
                                                Sobre Total
                                            </div>
                                            <!-- END percentage -->
                                            <hr class="bg-white bg-opacity-50" />
                                            <!-- BEGIN row -->
                                            <div class="row text-truncate">
                                                <!-- BEGIN col-6 -->
                                                <div class="col-6">
                                                    <div class=" text-gray-500">Qtd. Clientes</div>
                                                    <h3 class="fw-bold"><span><?php echo ($totalclirec30); ?></span>
                                                    </h3>

                                                </div>
                                                <!-- END col-6 -->
                                                <!-- BEGIN col-6 -->
                                                <div class="col-6">
                                                    <div class=" text-gray-500">Qtd. Títulos</div>
                                                    <h3 class="fw-bold">
                                                        <span><?php echo ($totaltitulorec30); ?></span>
                                                    </h3>

                                                </div>
                                                <!-- END col-6 -->
                                            </div>
                                            <!-- END row -->
                                        </div>
                                        <!-- END col-7 -->


                                    </div>
                                    <!-- END card-body -->
                                </div>
                                <!-- END card -->
                            </div>

                            <!-- BEGIN col-6 -->
                            <div class="col-xl-3">
                                <!-- BEGIN card -->
                                <div class="card border-0 mb-3 overflow-hidden card-financeiro-custom">
                                    <!-- BEGIN card-body -->
                                    <div class="card-body"
                                        style="background: no-repeat bottom right; background-image: url(../dashboard/images/svg/image8.png); background-size: auto 35%;">

                                        <!-- BEGIN row -->

                                        <!-- BEGIN col-7 -->
                                        <div class="col-xl-7 col-lg-8">
                                            <!-- BEGIN title -->
                                            <div class="mb-3 text-gray-500">
                                                <b>PAGAR NOS PRÓXIMOS 30 DIAS</b>
                                                <!--	<img src="../dashboard/images/svg/financeiro1.png" alt="Ícone Clientes" class="me-2" style="width: 30%; "> -->
                                                <span class="ms-2">
                                                    <i class="fa fa-info-circle" data-bs-toggle="popover"
                                                        data-bs-trigger="hover" data-bs-title="TOTAL PAGAR"
                                                        data-bs-placement="top"
                                                        data-bs-content="Total a pagar nos próximos 30 dias contando a partir da data atual."></i>
                                                </span>
                                            </div>

                                            <!-- END title -->
                                            <!-- BEGIN total-sales -->
                                            <div class="d-flex mb-1">
                                                <h2 class="mb-0">
                                                    R$:<span><?php echo number_format($valor_pagar_proximos30dias, 2, ',', '.'); ?></span>
                                                </h2>

                                                <div class="ms-auto mt-n1 mb-n1">
                                                    <div id="total-sales-sparkline"></div>
                                                </div>
                                            </div>
                                            <!-- END total-sales -->
                                            <!-- BEGIN percentage -->
                                            <div class="mb-3 text-gray-500">

                                                <i class="fa fa-caret-up"></i> <span>
                                                    <?php echo ($perc_finpagar_30d); ?>%</span>
                                                Sobre Total
                                            </div>
                                            <!-- END percentage -->
                                            <hr class="bg-white bg-opacity-50" />
                                            <!-- BEGIN row -->
                                            <div class="row text-truncate">
                                                <!-- BEGIN col-6 -->
                                                <div class="col-6">
                                                    <div class=" text-gray-500">Qtd. Clientes</div>
                                                    <h3 class="fw-bold"><span><?php echo ($totalforpag30); ?></span>
                                                    </h3>

                                                </div>
                                                <!-- END col-6 -->
                                                <!-- BEGIN col-6 -->
                                                <div class="col-6">
                                                    <div class=" text-gray-500">Qtd. Pedidos</div>
                                                    <h3 class="fw-bold">
                                                        <span><?php echo ($totaltitulopag30); ?></span>
                                                    </h3>

                                                </div>
                                                <!-- END col-6 -->
                                            </div>
                                            <!-- END row -->
                                        </div>
                                        <!-- END col-7 -->


                                    </div>
                                    <!-- END card-body -->
                                </div>
                                <!-- END card -->
                            </div>
                        </div>


                        <div class="row">



                            <!-- BEGIN col-6 -->
                            <div class="col-xl-3">
                                <!-- BEGIN card -->
                                <div class="card border-0 mb-3 overflow-hidden card-financeiro-custom">
                                    <!-- BEGIN card-body -->
                                    <div class="card-body"
                                        style="background: no-repeat bottom right; background-image: url(../dashboard/images/svg/financeiro1.png); background-size: auto 35%;">

                                        <!-- BEGIN row -->

                                        <!-- BEGIN col-7 -->
                                        <div class="col-xl-7 col-lg-8">
                                            <!-- BEGIN title -->
                                            <div class="mb-3 text-blue-500">
                                                <b>RECEBER A VENCER</b>
                                                <span class="ms-2">
                                                    <i class="fa fa-info-circle" data-bs-toggle="popover"
                                                        data-bs-trigger="hover" data-bs-title="RECEBER"
                                                        data-bs-placement="top"
                                                        data-bs-content="Total a receber a vencer."></i>
                                                </span>
                                            </div>


                                            <!-- END title -->
                                            <!-- BEGIN total-sales -->
                                            <div class="d-flex mb-1">
                                                <h2 class="mb-0">
                                                    R$:<span><?php echo number_format($valor_receber_vencer, 2, ',', '.'); ?></span>
                                                </h2>

                                                <div class="ms-auto mt-n1 mb-n1">
                                                    <div id="total-sales-sparkline"></div>
                                                </div>
                                            </div>
                                            <!-- END total-sales -->
                                            <!-- BEGIN percentage -->


                                            <div class="mb-3 text-blue-500">
                                                <i class="fa fa-caret-up"></i> <span>
                                                    <?php echo ($perc_fin_pend); ?>%</span> Sobre
                                                a Total
                                            </div>

                                        </div>
                                        <!-- END percentage -->
                                        <hr class="bg-white bg-opacity-50" />
                                        <!-- BEGIN row -->
                                        <div class="row text-truncate">
                                            <!-- BEGIN col-6 -->
                                            <div class="col-5">
                                                <div class=" text-gray-500">Qtd. Clientes</div>
                                                <h3 class="fw-bold"><span><?php echo ($totalclivencer); ?></span>
                                                </h3>
                                                <!-- Progress Removed -->
                                            </div>
                                            <!-- END col-6 -->
                                            <!-- BEGIN col-6 -->
                                            <div class="col-5">
                                                <div class=" text-gray-500">Qtd. Títulos</div>
                                                <h3 class="fw-bold"><span><?php echo ($totaltitulovencer); ?></span>
                                                </h3>

                                            </div>
                                            <!-- END col-6 -->
                                        </div>
                                        <!-- END row -->
                                    </div>
                                    <!-- END col-7 -->


                                </div>
                                <!-- END card-body -->
                            </div>
                            <!-- END card -->


                            <!-- END col-6 -->

                            <!-- BEGIN col-6 -->
                            <div class="col-xl-3">
                                <!-- BEGIN card -->
                                <div class="card border-0 mb-3 overflow-hidden card-financeiro-custom">
                                    <!-- BEGIN card-body -->
                                    <div class="card-body"
                                        style="background: no-repeat bottom right; background-image: url(../dashboard/images/svg/financeiro2.png); background-size: auto 35%;">

                                        <!-- BEGIN row -->

                                        <!-- BEGIN col-7 -->
                                        <div class="col-xl-7 col-lg-8">

                                            <!-- BEGIN title -->
                                            <div class="mb-3 text-red-500">
                                                <b>RECEBER ATRASADO</b>
                                                <span class="ms-2">
                                                    <i class="fa fa-info-circle" data-bs-toggle="popover"
                                                        data-bs-trigger="hover" data-bs-title="RECEBER ATRASADO"
                                                        data-bs-placement="top"
                                                        data-bs-content="Total a receber em atraso."></i>
                                                </span>

                                            </div>


                                            <!-- END title -->
                                            <!-- BEGIN total-sales -->
                                            <div class="d-flex mb-1">
                                                <h2 class="text-red mb-0">
                                                    R$:<span><?php echo number_format($valor_receber_vencidos, 2, ',', '.'); ?></span>
                                                </h2>

                                                <div class="ms-auto mt-n1 mb-n1">
                                                    <div id="total-sales-sparkline"></div>
                                                </div>
                                            </div>
                                            <!-- END total-sales -->
                                            <!-- BEGIN percentage -->


                                            <div class="mb-3 text-red-500">
                                                <i class="fa fa-caret-down"></i> <span>
                                                    <?php echo ($perc_finreceb_atra); ?>%</span>
                                                Sobre a Total
                                            </div>

                                        </div>
                                        <!-- END percentage -->
                                        <hr class="bg-white bg-opacity-50" />
                                        <!-- BEGIN row -->
                                        <div class="row text-truncate">
                                            <!-- BEGIN col-6 -->
                                            <div class="col-5">
                                                <div class=" text-red-500">Qtd. Clientes</div>
                                                <h3 class="fw-bold text-red">
                                                    <span><?php echo ($totalcliatra); ?></span>
                                                </h3>

                                            </div>
                                            <!-- END col-6 -->
                                            <!-- BEGIN col-6 -->
                                            <div class="col-5">
                                                <div class=" text-red-500">Qtd. Títulos</div>
                                                <h3 class="fw-bold text-red">
                                                    <span><?php echo ($totaltituloatra); ?></span>
                                                </h3>

                                            </div>
                                            <!-- END col-6 -->

                                        </div>

                                        <!-- END row -->
                                    </div>
                                    <!-- END col-7 -->

                                </div>
                                <!-- END card-body -->
                            </div>

                            <!-- BEGIN col-6 -->
                            <div class="col-xl-3">
                                <!-- BEGIN card -->
                                <div class="card border-0 mb-3 overflow-hidden card-financeiro-custom">
                                    <!-- BEGIN card-body -->
                                    <div class="card-body"
                                        style="background: no-repeat bottom right; background-image: url(../dashboard/images/svg/financeiro1.png); background-size: auto 35%;">

                                        <!-- BEGIN row -->

                                        <!-- BEGIN col-7 -->
                                        <div class="col-xl-7 col-lg-8">
                                            <!-- BEGIN title -->
                                            <div class="mb-3 text-blue-500">
                                                <b>PAGAR A VENCER</b>
                                                <span class="ms-2">
                                                    <i class="fa fa-info-circle" data-bs-toggle="popover"
                                                        data-bs-trigger="hover" data-bs-title="TOTAL A PAGAR"
                                                        data-bs-placement="top"
                                                        data-bs-content="Total a pagar em aberto a vencer."></i>
                                                </span>
                                            </div>

                                            <!-- END title -->
                                            <!-- BEGIN total-sales -->
                                            <div class="d-flex mb-1">
                                                <h2 class="mb-0">
                                                    R$:<span><?php echo number_format($valor_pagar_avencer, 2, ',', '.'); ?></span>
                                                </h2>

                                                <div class="ms-auto mt-n1 mb-n1">
                                                    <div id="total-sales-sparkline"></div>
                                                </div>
                                            </div>
                                            <!-- END total-sales -->
                                            <!-- BEGIN percentage -->


                                            <div class="mb-3 text-blue-500">
                                                <i class="fa fa-caret-up"></i> <span>
                                                    <?php echo ($perc_finpagar_vencer); ?>%</span>
                                                Sobre a Total
                                            </div>

                                        </div>
                                        <!-- END percentage -->
                                        <hr class="bg-white bg-opacity-50" />
                                        <!-- BEGIN row -->
                                        <div class="row text-truncate">
                                            <!-- BEGIN col-6 -->
                                            <div class="col-5">
                                                <div class=" text-gray-500">Qtd. Fornecedores</div>
                                                <h3 class="fw-bold"><span><?php echo ($totalforvencer); ?></span>
                                                </h3>

                                            </div>
                                            <!-- END col-6 -->
                                            <!-- BEGIN col-6 -->
                                            <div class="col-5">
                                                <div class=" text-gray-500">Qtd. Títulos</div>
                                                <h3 class="fw-bold"><span><?php echo ($totaltitulovencer); ?></span>
                                                </h3>

                                            </div>
                                            <!-- END col-6 -->
                                        </div>
                                        <!-- END row -->
                                    </div>
                                    <!-- END col-7 -->


                                </div>
                                <!-- END card-body -->
                            </div>
                            <!-- END card -->




                            <!-- BEGIN col-6 -->
                            <div class="col-xl-3">
                                <!-- BEGIN card -->
                                <div class="card border-0 mb-3 overflow-hidden card-financeiro-custom">
                                    <!-- BEGIN card-body -->
                                    <div class="card-body"
                                        style="background: no-repeat bottom right; background-image: url(../dashboard/images/svg/financeiro2.png); background-size: auto 35%;">

                                        <!-- BEGIN row -->

                                        <!-- BEGIN col-7 -->
                                        <div class="col-xl-7 col-lg-8">

                                            <!-- BEGIN title -->
                                            <div class="mb-3 text-red-500">
                                                <b>PAGAR ATRASADO</b>
                                                <span class="ms-2">
                                                    <i class="fa fa-info-circle" data-bs-toggle="popover"
                                                        data-bs-trigger="hover" data-bs-title="PAGAR ATRASADO"
                                                        data-bs-placement="top"
                                                        data-bs-content="Total a pagar em atraso."></i>
                                                </span>

                                            </div>


                                            <!-- END title -->
                                            <!-- BEGIN total-sales -->
                                            <div class="d-flex mb-1">
                                                <h2 class="text-red mb-0">
                                                    R$:<span><?php echo number_format($valor_pagar_vencidos, 2, ',', '.'); ?></span>
                                                </h2>

                                                <div class="ms-auto mt-n1 mb-n1">
                                                    <div id="total-sales-sparkline"></div>
                                                </div>
                                            </div>
                                            <!-- END total-sales -->
                                            <!-- BEGIN percentage -->


                                            <div class="mb-3 text-red-500">
                                                <i class="fa fa-caret-down"></i> <span>
                                                    <?php echo ($perc_finpagar_venc); ?>%</span>
                                                Sobre a Total
                                            </div>

                                        </div>
                                        <!-- END percentage -->
                                        <hr class="bg-white bg-opacity-50" />
                                        <!-- BEGIN row -->
                                        <div class="row text-truncate">
                                            <!-- BEGIN col-6 -->
                                            <div class="col-5">
                                                <div class=" text-red-500">Qtd. Fornecedores</div>
                                                <h3 class="fw-bold text-red">
                                                    <span><?php echo ($totalforvencidos); ?></span>
                                                </h3>

                                            </div>
                                            <!-- END col-6 -->
                                            <!-- BEGIN col-6 -->
                                            <div class="col-5">
                                                <div class=" text-red-500">Qtd. Títulos</div>
                                                <h3 class="fw-bold text-red">
                                                    <span><?php echo ($totaltitulovencidos); ?></span>
                                                </h3>

                                            </div>
                                            <!-- END col-6 -->

                                        </div>

                                        <!-- END row -->
                                    </div>
                                    <!-- END col-7 -->

                                </div>
                                <!-- END card-body -->
                            </div>



                        </div>
                        <!-- END row -->

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
                                        <strong>Distribuição por Tipo de Recebimento</strong>
                                        <i class="fa fa-info-circle" data-bs-toggle="popover" data-bs-trigger="hover"
                                            data-bs-title="Distribuição por Tipo de Recebimento" data-bs-placement="top"
                                            data-bs-content="Total a receber no periodo selecionado."></i>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-chart-container">
                                            <canvas id="chartTipoRecebimento"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Distribuição por Tipo de Pagamento</strong>
                                        <i class="fa fa-info-circle" data-bs-toggle="popover" data-bs-trigger="hover"
                                            data-bs-title="Distribuição por Tipo de Pagamento" data-bs-placement="top"
                                            data-bs-content="Total a pagar no periodo selecionado."></i>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-chart-container">
                                            <canvas id="chartTipoPagamento"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>





                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Pagar X Pago por Filial</strong>
                                        <i class="fa fa-info-circle" data-bs-toggle="popover" data-bs-trigger="hover"
                                            data-bs-title="Pagar X Pago por Filial" data-bs-placement="top"
                                            data-bs-content="Total no periodo selecionado."></i>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-chart-container">
                                            <canvas id="pagar_pagoChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Receber X Recebido por Filial</strong>
                                        <i class="fa fa-info-circle" data-bs-toggle="popover" data-bs-trigger="hover"
                                            data-bs-title="Receber X Recebido por Filial" data-bs-placement="top"
                                            data-bs-content="Total no periodo selecionado."></i>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-chart-container">
                                            <canvas id="receber_recebidoChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="col-md-12 mt-4">
                            <div class="card">
                                <div class="card-header">
                                    <strong>Evolução Diária - Saídas</strong>
                                    <i class="fa fa-info-circle" data-bs-toggle="popover" data-bs-trigger="hover"
                                        data-bs-title="Evolução Diária - Saídas" data-bs-placement="top"
                                        data-bs-content="Total de saídas no periodo selecionado."></i>
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
                                    <strong>Evolução Receber - Pagar</strong>
                                    <i class="fa fa-info-circle" data-bs-toggle="popover" data-bs-trigger="hover"
                                        data-bs-title="Evolução Receber - Pagar" data-bs-placement="top"
                                        data-bs-content="Total de receber e pagar no periodo selecionado."></i>
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
                                    <strong>Valor a Receber X Pagar Anual</strong>
                                    <i class="fa fa-info-circle" data-bs-toggle="popover" data-bs-trigger="hover"
                                        data-bs-title="Valor a Receber X Pagar Anual" data-bs-placement="top"
                                        data-bs-content="Total de receber e pagar no ano atual."></i>
                                </div>
                                <div class="card-body">
                                    <div class="card-graficoICMS-container">
                                        <canvas id="graficoICMS"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4 mb-4">
                            <div class="col-md-12">
                                <div class="card border-0 shadow-sm card-atrasados-custom">
                                    <div
                                        class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center pt-3">
                                        <h3 class="card-title fw-bold text-uppercase fs-12px">
                                            <span class="badge bg-primary px-2 py-1 me-2">CLIENTES ATRASADOS</span>
                                            <i class="fa fa-info-circle text-muted" data-bs-toggle="tooltip"
                                                title="Top 10 clientes com valores vencidos"></i>
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <h4 class="text-center fw-bold mb-5">10 Maiores Clientes atrasados</h4>
                                        <div style="height: 350px; position: relative;">
                                            <canvas id="chartAtrasados"></canvas>
                                        </div>

                                        <div class="row mt-5 px-3">
                                            <?php foreach ($clientes_atrasados as $idx => $clie): ?>
                                            <div class="col-md-4 mb-4">
                                                <div class="d-flex align-items-start border-bottom pb-3 h-100"
                                                    style="border-color: rgba(128,128,128,0.1) !important;">
                                                    <div class="me-3 fs-18px fw-bold mt-1 text-muted"
                                                        style="min-width: 30px;"><?= ($idx + 1) ?>º</div>
                                                    <div class="flex-grow-1">
                                                        <div class="fw-bold fs-13px text-uppercase text-truncate"
                                                            style="max-width: 50%;"
                                                            title="<?= htmlspecialchars($clie['cliente']) ?>">
                                                            <?= htmlspecialchars($clie['cliente'] ?? 'CONSUMIDOR') ?>
                                                        </div>
                                                        <div class="fs-13px mt-1">R$: <span
                                                                class="fw-bold"><?= number_format($clie['t_atrasado'], 2, ',', '.') ?></span>
                                                            - <?= round($clie['media_dias_atraso']) ?> Dias
                                                            <small>(Média)</small>
                                                        </div>
                                                        <div class="fs-11px mt-1 text-muted">
                                                            <i class="fa fa-caret-up text-primary me-1"></i>
                                                            <?= $clie['qtd_t_atrasado'] ?> Títulos -
                                                            <?= number_format($clie['percentual'], 2, ',', '.') ?> % do
                                                            Total
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4 mb-4">
                            <!-- GRÁFICO TOP 10 DESPESAS -->
                            <div class="col-xl-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title text-uppercase fw-bold">Top 10 Despesas</h5>
                                        <div class="text-info small fw-bold">
                                            Representa <?= number_format($total_percentual_top10, 2, ',', '.') ?>% do
                                            total
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div style="height: 350px; position: relative;">
                                            <canvas id="grafico_despesas"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- GRÁFICO TOP 10 RECEITAS -->
                            <div class="col-xl-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title text-uppercase fw-bold">Top 10 Receitas</h5>
                                        <div class="text-info small fw-bold">
                                            Representa <?= number_format($total_percentual_top10, 2, ',', '.') ?>% do
                                            total
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div style="height: 350px; position: relative;">
                                            <canvas id="grafico_topreceitas"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Scripts dos Gráficos -->
                        <script>
                        // Verifica se o tema definido no body é dark
                        const isDarkTheme = document.body.getAttribute('data-bs-theme') === 'dark';

                        // Se Dark: Texto Branco (#f5f5f5). Se Light: Texto Escuro (#212529).
                        const chartTextColor = isDarkTheme ? '#f5f5f5' : '#212529';
                        // Cor do título um pouco mais suave
                        const chartTitleColor = isDarkTheme ? '#e0e0e0' : '#495057';
                        // Cor das linhas de grade (grid)
                        const chartGridColor = isDarkTheme ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
                        // Cor de fundo do tooltip
                        const chartTooltipBg = isDarkTheme ? 'rgba(50, 50, 50, 0.9)' : 'rgba(255, 255, 255, 0.9)';
                        // Cor do texto do tooltip
                        const chartTooltipText = isDarkTheme ? '#ffffff' : '#000000';



                        // Dados para gráfico de Clientes Atrasados
                        <?php 
                                $labels_atrasados = [];
                                $valores_atrasados = [];
                                foreach ($clientes_atrasados as $c) {
                                    $labels_atrasados[] = $c['cliente'];
                                    $valores_atrasados[] = (float)$c['t_atrasado'];
                                }
                            ?>
                        const labelsAtrasados = <?php echo json_encode($labels_atrasados); ?>;
                        const dataAtrasados = <?php echo json_encode($valores_atrasados); ?>;

                        const ctxAtrasados = document.getElementById('chartAtrasados');
                        if (ctxAtrasados) {
                            new Chart(ctxAtrasados.getContext('2d'), {
                                type: 'bar',
                                data: {
                                    labels: labelsAtrasados,
                                    datasets: [{
                                        label: 'Valor Atrasado',
                                        data: dataAtrasados,
                                        backgroundColor: [
                                            'rgba(54, 162, 235, 0.8)',
                                            'rgba(153, 102, 255, 0.8)',
                                            'rgba(75, 192, 192, 0.8)',
                                            'rgba(255, 159, 64, 0.8)',
                                            'rgba(255, 99, 132, 0.8)',
                                            'rgba(54, 162, 235, 0.8)',
                                            'rgba(153, 102, 255, 0.8)',
                                            'rgba(75, 192, 192, 0.8)',
                                            'rgba(255, 159, 64, 0.8)',
                                            'rgba(255, 99, 132, 0.8)'
                                        ],
                                        borderRadius: 8,
                                        borderWidth: 0,
                                        barThickness: 50
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: false
                                        },
                                        tooltip: {
                                            backgroundColor: chartTooltipBg,
                                            titleColor: chartTooltipText,
                                            bodyColor: chartTooltipText,
                                            callbacks: {
                                                label: function(context) {
                                                    return 'R$ ' + context.raw.toLocaleString('pt-BR', {
                                                        minimumFractionDigits: 2
                                                    });
                                                }
                                            }
                                        },
                                        datalabels: {
                                            color: chartTextColor,
                                            anchor: 'end',
                                            align: 'top',
                                            offset: 5,
                                            font: {
                                                weight: 'bold',
                                                size: 11
                                            },
                                            formatter: (value) => value.toLocaleString('pt-BR', {
                                                minimumFractionDigits: 2
                                            })
                                        }
                                    },
                                    scales: {
                                        x: {
                                            ticks: {
                                                color: chartTextColor,
                                                font: {
                                                    size: 10
                                                },
                                                // Abrevia nomes longos
                                                callback: function(val, index) {
                                                    const label = this.getLabelForValue(val);
                                                    return label.length > 20 ? label.substr(0, 18) + '...' :
                                                        label;
                                                }
                                            },
                                            grid: {
                                                display: false
                                            }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                color: chartTextColor,
                                                callback: function(value) {
                                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                                }
                                            },
                                            grid: {
                                                color: chartGridColor
                                            }
                                        }
                                    }
                                },
                                plugins: [ChartDataLabels]
                            });
                        }


                        // Dados para gráficos gerais



                        // Gráfico de Tipos de Recebimento (Donut)
                        const ctxTipoReceb = document.getElementById('chartTipoRecebimento');
                        if (ctxTipoReceb) { // Verifica se o elemento existe antes de criar
                            new Chart(ctxTipoReceb.getContext('2d'), {
                                type: 'doughnut',
                                data: {
                                    labels: <?php echo json_encode($nometiporeceb); ?>,
                                    datasets: [{
                                        data: <?php echo json_encode($totalvalorreceb); ?>,
                                        backgroundColor: [
                                            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74c3c',
                                            '#5a5c69', '#f8f9fc', '#4e0273', '#02735e', '#730202'
                                        ],
                                        hoverOffset: 4,
                                        borderWidth: 1,
                                        borderColor: isDarkTheme ? '#2a3038' : '#ffffff'
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    cutout: '50%',
                                    plugins: {
                                        title: {
                                            display: true,
                                            text: 'Tipos de Recebimento',
                                            color: chartTitleColor,
                                            padding: {
                                                bottom: 20
                                            }
                                        },
                                        legend: {
                                            display: true,
                                            position: 'bottom',
                                            labels: {
                                                color: chartTextColor,
                                                boxWidth: 12,
                                                padding: 12,
                                                font: { size: 10}
                                            }
                                        },
                                        tooltip: {
                                            backgroundColor: chartTooltipBg,
                                            titleColor: chartTooltipText,
                                            bodyColor: chartTooltipText,
                                            callbacks: {
                                                label: function(context) {
                                                    let label = context.label || '';
                                                    let value = context.raw || 0;
                                                    let total = context.dataset.data.reduce((a, b) => a + b,
                                                        0);
                                                    let percentage = total > 0 ? ((value / total) * 100)
                                                        .toFixed(1) : 0;
                                                    return label + ': R$ ' + value.toLocaleString('pt-BR', {
                                                        minimumFractionDigits: 2
                                                    }) + ' (' + percentage + '%)';
                                                }
                                            }
                                        },
                                        datalabels: {
                                            color: chartTextColor,
                                            font: {
                                                weight: 'bold',
                                                size: 11
                                            },
                                            formatter: (value, ctx) => {
                                                if (value === 0) return '';
                                                let sum = 0;
                                                let dataArr = ctx.chart.data.datasets[0].data;
                                                dataArr.map(data => {
                                                    sum += data;
                                                });
                                                let percentage = sum > 0 ? (value * 100 / sum).toFixed(1) +
                                                    "%" : "0%";
                                                return percentage;
                                            },
                                            anchor: 'end',
                                            align: 'end',
                                            offset: 5,
                                            display: 'auto'
                                        }
                                    },
                                    layout: {
                                        padding: {
                                            top: 30,
                                            bottom: 20
                                        }
                                    }
                                },
                                plugins: [ChartDataLabels]
                            });
                        }

                        // Gráfico de Tipos de Pagamento (Donut)
                        const ctxTipoPagamen = document.getElementById('chartTipoPagamento');
                        if (ctxTipoPagamen) { // Verifica se o elemento existe antes de criar
                            new Chart(ctxTipoPagamen.getContext('2d'), {
                                type: 'doughnut',
                                data: {
                                    labels: <?php echo json_encode($nometipopagamen); ?>,
                                    datasets: [{
                                        data: <?php echo json_encode($totalvalorpagamen); ?>,
                                        backgroundColor: [
                                            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74c3c',
                                            '#5a5c69', '#f8f9fc', '#4e0273', '#02735e', '#730202'
                                        ],
                                        hoverOffset: 4,
                                        borderWidth: 1,
                                        borderColor: isDarkTheme ? '#2a3038' : '#ffffff'
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    cutout: '50%',
                                    plugins: {
                                        title: {
                                            display: true,
                                            text: 'Tipos de Pagamento',
                                            color: chartTitleColor,
                                            padding: {
                                                bottom: 30
                                            }
                                        },
                                        legend: {
                                            display: true,
                                            position: 'bottom',
                                            labels: {
                                                color: chartTextColor,
                                                boxWidth: 12,
                                                padding: 12,
                                                font: { size: 10}
                                            }
                                        },
                                        tooltip: {
                                            backgroundColor: chartTooltipBg,
                                            titleColor: chartTooltipText,
                                            bodyColor: chartTooltipText,
                                            callbacks: {
                                                label: function(context) {
                                                    let label = context.label || '';
                                                    let value = context.raw || 0;
                                                    let total = context.dataset.data.reduce((a, b) => a + b,
                                                        0);
                                                    let percentage = total > 0 ? ((value / total) * 100)
                                                        .toFixed(1) : 0;
                                                    return label + ': R$ ' + value.toLocaleString('pt-BR', {
                                                        minimumFractionDigits: 2
                                                    }) + ' (' + percentage + '%)';
                                                }
                                            }
                                        },
                                        datalabels: {
                                            color: chartTextColor,
                                            font: {
                                                weight: 'bold',
                                                size: 11
                                            },
                                            formatter: (value, ctx) => {
                                                if (value === 0) return '';
                                                let sum = 0;
                                                let dataArr = ctx.chart.data.datasets[0].data;
                                                dataArr.map(data => {
                                                    sum += data;
                                                });
                                                let percentage = sum > 0 ? (value * 100 / sum).toFixed(1) +
                                                    "%" : "0%";
                                                return percentage;
                                            },
                                            anchor: 'end',
                                            align: 'end',
                                            offset: 5,
                                            display: 'auto'
                                        }
                                    },
                                    layout: {
                                        padding: {
                                            top: 30,
                                            bottom: 20
                                        }
                                    }
                                },
                                plugins: [ChartDataLabels]
                            });
                        }

                        // Gráfico 3: Pagar vs Pago por Filial
                        const ctx3 = document.getElementById('pagar_pagoChart').getContext('2d');

                        new Chart(ctx3, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode($filial_labels_pagar); ?>,
                                color: chartTextColor,
                                datasets: [{
                                        label: 'Vlr a Pagar',
                                        data: <?php echo json_encode($v_apagers); ?>,
                                        backgroundColor: 'rgba(12, 35, 238, 0.7)',
                                    },
                                    {
                                        label: 'Vlr Pago',
                                        data: <?php echo json_encode($v_pagos); ?>,
                                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                                    },
                                    {
                                        label: 'Diferença',
                                        data: <?php echo json_encode($diferencasp); ?>,
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
                                            color: chartTextColor
                                        },
                                        position: 'top',
                                    },
                                    title: {
                                        display: true,
                                        color: chartTitleColor,
                                        text: 'Pagar vs Pago por Filial'
                                    }
                                },
                                scales: {
                                    x: {
                                        ticks: {
                                            color: chartTextColor,
                                        },
                                        grid: {
                                            color: chartGridColor
                                        }
                                    },
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            color: chartTextColor,
                                            callback: function(value) {
                                                return 'R$ ' + value.toLocaleString('pt-BR');
                                            }
                                        }
                                    }
                                }
                            }
                        });

                        // Gráfico 4: Margem por Filial
                        const ctx5 = document.getElementById('receber_recebidoChart').getContext('2d');

                        new Chart(ctx5, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode($filial_labels_icms); ?>,
                                color: chartTextColor,
                                datasets: [{
                                        label: 'Vlr a Receber',
                                        data: <?php echo json_encode($v_arecebers); ?>,
                                        backgroundColor: 'rgba(12, 35, 238, 0.7)',
                                    },
                                    {
                                        label: 'Vlr Recebido',
                                        data: <?php echo json_encode($v_recebidos); ?>,
                                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                                    },
                                    {
                                        label: 'Diferença',
                                        data: <?php echo json_encode($diferencas); ?>,
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
                                            color: chartTextColor
                                        },
                                        position: 'top',
                                    },
                                    title: {
                                        display: true,
                                        color: chartTitleColor,
                                        text: 'Receber por Filial'
                                    }
                                },
                                scales: {
                                    x: {
                                        ticks: {
                                            color: chartTextColor,
                                        },
                                        grid: {
                                            color: chartGridColor
                                        }
                                    },
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            color: chartTextColor,
                                            callback: function(value) {
                                                return 'R$ ' + value.toLocaleString('pt-BR');
                                            }
                                        }
                                    }
                                }
                            }
                        });




                        // Gráfico Diário - Saídas
                        const dailyLabels = <?php echo json_encode($labels); ?>;
                        const dailyVALORRECEBER = <?php echo json_encode($valor_a_receber); ?>;
                        const dailyVALORRECEBIDO = <?php echo json_encode($valor_recebido); ?>;

                        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
                        new Chart(dailyCtx, {
                            type: 'line',
                            data: {
                                labels: dailyLabels,
                                datasets: [

                                    {
                                        label: 'V. Receber',
                                        data: dailyVALORRECEBER,
                                        borderColor: '#03680bff',
                                        backgroundColor: 'rgba(12, 116, 34, 0.1)',
                                        tension: 0.3,
                                        fill: true,
                                        borderWidth: 2
                                    },
                                    {
                                        label: 'V. Recebido',
                                        data: dailyVALORRECEBIDO,
                                        borderColor: '#2b77e9ff',
                                        backgroundColor: 'rgba(43, 119, 233, 0.1)',
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
                                            color: chartTextColor,
                                            font: {
                                                size: 13
                                            }
                                        }
                                    },
                                    title: {
                                        display: true,
                                        text: 'Evolução Diária de Recebimentos',
                                        color: chartTitleColor,
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
                                        titleColor: chartTooltipText,
                                        bodyColor: chartTooltipText,
                                        backgroundColor: chartTooltipBg,
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
                                            color: chartTextColor,
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
                                            color: chartTextColor,
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
                        const dailyICMSe = <?php echo json_encode($val_icms_entradas); ?>;
                        const dailyPISCOFINSe = <?php echo json_encode($val_totaldanf_entradas); ?>;
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
                                        label: 'PAGAR',
                                        data: dailyICMSe,
                                        borderColor: '#e74c3c',
                                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                                        tension: 0.3,
                                        fill: true,
                                        borderWidth: 2
                                    },
                                    {
                                        label: 'RECEBER',
                                        data: dailyPISCOFINSe,
                                        borderColor: '#2ecc71',
                                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
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
                                            color: chartTextColor,
                                            font: {
                                                size: 13
                                            }
                                        }
                                    },
                                    title: {
                                        display: true,
                                        text: 'Receber x Pagar',
                                        color: chartTitleColor,
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
                                        titleColor: chartTooltipText,
                                        bodyColor: chartTooltipText,
                                        backgroundColor: chartTooltipBg,
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
                                            color: chartTextColor,
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
                                            color: chartTextColor,
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
                                        label: 'Diferença (%)',
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
                                            color: chartTextColor
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
                                        text: 'VALOR A RECEBER X PAGAR ANUAL',
                                        color: chartTitleColor
                                    }
                                }
                            }
                        });






                        // --- GRÁFICO TOP 10 DESPESAS ---

                        const ctx_despesas = document.getElementById('grafico_despesas');
                        if (ctx_despesas) {
                            new Chart(ctx_despesas, {
                                type: 'bar',
                                data: {
                                    labels: <?= json_encode($labels_despesas) ?>,
                                    datasets: [{
                                        label: 'Despesas',
                                        data: <?= json_encode($percentuais_despesas) ?>,
                                        valores: <?= json_encode($valores_despesas) ?>,
                                        backgroundColor: '#4fc3f7',
                                        borderRadius: 6,
                                        barThickness: 10,
                                        maxBarThickness: 12
                                    }]
                                },
                                options: {
                                    indexAxis: 'y',
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    layout: {
                                        padding: {
                                            right: 60
                                        }
                                    },
                                    plugins: {
                                        legend: {
                                            display: false
                                        },
                                        tooltip: {
                                            backgroundColor: chartTooltipBg,
                                            titleColor: chartTooltipText,
                                            bodyColor: chartTooltipText,
                                            callbacks: {
                                                label: function(ctx) {
                                                    const val = ctx.dataset.valores[ctx.dataIndex];
                                                    const perc = ctx.raw;
                                                    return 'R$ ' + val.toLocaleString('pt-BR', {
                                                        minimumFractionDigits: 2
                                                    }) + ' (' + perc.toFixed(2) + '%)';
                                                }
                                            }
                                        },
                                        datalabels: {
                                            color: chartTextColor,
                                            anchor: 'end',
                                            align: 'right',
                                            offset: 5,
                                            font: {
                                                weight: 'bold',
                                                size: 9
                                            },
                                            formatter: (value, context) => {
                                                const val = context.dataset.valores[context.dataIndex];
                                                return 'R$ ' + val.toLocaleString('pt-BR', {
                                                    minimumFractionDigits: 2
                                                });
                                            }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            ticks: {
                                                callback: value => value + '%',
                                                color: chartTextColor,
                                                font: {
                                                    size: 10
                                                }
                                            },
                                            grid: {
                                                color: chartGridColor
                                            }
                                        },
                                        y: {
                                            ticks: {
                                                color: chartTextColor,
                                                font: {
                                                    size: 10
                                                }
                                            },
                                            grid: {
                                                display: false
                                            }
                                        }
                                    }
                                },
                                plugins: [ChartDataLabels]
                            });
                        }




                        // --- GRÁFICO TOP 11 receitas ---

                        const ctx_topreceitas = document.getElementById('grafico_topreceitas');
                        if (ctx_topreceitas) {
                            new Chart(ctx_topreceitas, {
                                type: 'bar',
                                data: {
                                    labels: <?= json_encode($labels_topreceitas) ?>,
                                    datasets: [{
                                        label: 'Receitas',
                                        data: <?= json_encode($percentuais_topreceitas) ?>,
                                        valores: <?= json_encode($valores_topreceitas) ?>,
                                        backgroundColor: '#4fc3f7',
                                        borderRadius: 6,
                                        barThickness: 10,
                                        maxBarThickness: 12
                                    }]
                                },
                                options: {
                                    indexAxis: 'y',
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    layout: {
                                        padding: {
                                            right: 60
                                        }
                                    },
                                    plugins: {
                                        legend: {
                                            display: false
                                        },
                                        tooltip: {
                                            backgroundColor: chartTooltipBg,
                                            titleColor: chartTooltipText,
                                            bodyColor: chartTooltipText,
                                            callbacks: {
                                                label: function(ctx) {
                                                    const val = ctx.dataset.valores[ctx.dataIndex];
                                                    const perc = ctx.raw;
                                                    return 'R$ ' + val.toLocaleString('pt-BR', {
                                                        minimumFractionDigits: 2
                                                    }) + ' (' + perc.toFixed(2) + '%)';
                                                }
                                            }
                                        },
                                        datalabels: {
                                            color: chartTextColor,
                                            anchor: 'end',
                                            align: 'right',
                                            offset: 5,
                                            font: {
                                                weight: 'bold',
                                                size: 9
                                            },
                                            formatter: (value, context) => {
                                                const val = context.dataset.valores[context.dataIndex];
                                                return 'R$ ' + val.toLocaleString('pt-BR', {
                                                    minimumFractionDigits: 2
                                                });
                                            }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            ticks: {
                                                callback: value => value + '%',
                                                color: chartTextColor,
                                                font: {
                                                    size: 10
                                                }
                                            },
                                            grid: {
                                                color: chartGridColor
                                            }
                                        },
                                        y: {
                                            ticks: {
                                                color: chartTextColor,
                                                font: {
                                                    size: 10
                                                }
                                            },
                                            grid: {
                                                display: false
                                            }
                                        }
                                    }
                                },
                                plugins: [ChartDataLabels]
                            });
                        }
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
    function setperiodo(tipo) {
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

    // Inicialização de Tooltips e Popovers
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl)
        })
    });
    </script>
</body>

</html>