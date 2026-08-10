<?php

require_once '../config/config.php';
require_once '../config/session.php'; 

date_default_timezone_set('America/Sao_Paulo');


if (!isset($_SESSION["user_name"])) {

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

} else {

};



$config_id = 1; 

// Busca os parâmetros da configuração no banco
$result = mysqli_query($dash, "SELECT * FROM configuracoes_empresa WHERE id = " . intval($config_id));
if (!$result || mysqli_num_rows($result) == 0) {
    die("Erro: Configuracao com ID $config_id nao encontrada.");
}
$config = mysqli_fetch_assoc($result);

$transacoes = validar_lista_ids($config['transacoes_venda']);
$series = validar_lista_ids($config['series_venda']);
$transacoescompra = validar_lista_ids($config['transacoes_compra']);
$seriescompra = validar_lista_ids($config['series_compra']);
$transacoes_devolucao = validar_lista_ids($config['transacoes_devolucao']);
$series_devolucao = validar_lista_ids($config['series_devolucao']);
$transacoes_devolucaocompra = validar_lista_ids($config['transacoes_devolucaocompra']);
$series_devolucaocompra = validar_lista_ids($config['series_devolucaocompra']);




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



// quantidade de fornecedores
		$querytipopessoa = "SELECT
		SUM(CASE WHEN fis_jur = 'F' THEN 1 ELSE 0 END) as total_fisico,
		SUM(CASE WHEN fis_jur = 'J' THEN 1 ELSE 0 END) as total_juridico,
		COUNT(fis_jur) as total_fornecedor
		
		FROM {$banco_publico}.cad_forn
		WHERE ativo = 'S'";
		
		$resultadotipopessoa = mysqli_query($dash, $querytipopessoa);
		if ($resultadotipopessoa && mysqli_num_rows($resultadotipopessoa) > 0) {
			$row = mysqli_fetch_assoc($resultadotipopessoa);
			$total_fisico = $row['total_fisico'] ?? 0;
			$total_juridico = $row['total_juridico'] ?? 0;
			$total_fornecedor = $row['total_fornecedor'] ?? 0;
		} else {
			$total_fisico = 0;
			$total_juridico = 0;
			$total_fornecedor = 0;
		}
     
                // select devolucao
                $valor_dev = 0;
                $qtd_dev = 0;
                $sqldevolucao = "SELECT  
                    COUNT(DISTINCT n.codigo) AS qtd_dev,
                    COALESCE(SUM(n.total_nf), 0) AS valor_dev
                FROM {$banco_vendas}.cad_nf n
                LEFT JOIN {$banco_vendas}.transacoes t ON (t.codigo = n.transacao AND t.filial = n.filial)
                WHERE n.cancelada = 'N'
                  AND n.data_saida_entrada BETWEEN '{$data_inicio}' AND '{$data_fim}'
                  AND t.ent_sai = 'S' AND t.tipo = 'D'
                  " . ($filial ? " AND n.filial = '$filial_esc'" : "");

                $resultadadevolucao = mysqli_query($dash, $sqldevolucao);

                if ($resultadadevolucao && mysqli_num_rows($resultadadevolucao) > 0) {
                    $row = mysqli_fetch_assoc($resultadadevolucao);
                    $valor_dev = $row['valor_dev'];
                    $qtd_dev = $row['qtd_dev'];
                }

                //sql meta
                $sql12 = "SELECT meta AS totalmeta FROM metas_compras";
	
		$result = $dash->query($sql12);	
		while ($row = mysqli_fetch_assoc($result)) {
			$meta_compras = $row['totalmeta'];
		};
		

                //SQL COMPRAS
                $valor_compra = 0;
                $sqlcompras = "SELECT 
                    COUNT(DISTINCT nf.codigo) AS qtd_compra,
                    COALESCE(SUM(nf.total_nf), 0) AS valor_compra
                FROM {$banco_vendas}.cad_nf nf
                LEFT JOIN {$banco_vendas}.series se ON (se.codigo = nf.serie AND se.filial = nf.filial)
                LEFT JOIN {$banco_vendas}.transacoes t ON (t.codigo = nf.transacao AND t.filial = nf.filial)
                WHERE nf.cancelada = 'N'
                  AND nf.data_saida_entrada BETWEEN '{$data_inicio}' AND '{$data_fim}'
                  AND t.ent_sai = 'E'";

                if (!empty($transacoescompra)) {
                    $sqlcompras .= " AND nf.transacao IN ($transacoescompra)";
                }
                if (!empty($seriescompra)) {
                    $sqlcompras .= " AND nf.serie IN ($seriescompra)";
                }
                $sqlcompras .= ($filial ? " AND nf.filial = '$filial_esc'" : "");

                $resultadocompras = mysqli_query($dash, $sqlcompras);

                if ($resultadocompras && mysqli_num_rows($resultadocompras) > 0) {
                    $row = mysqli_fetch_assoc($resultadocompras);
                    $valor_compra = $row['valor_compra'];
                    $qtd_compra = $row['qtd_compra'];
                }

                if($valor_compra > 0){
                    $tickt_medio = $valor_compra / $qtd_compra;
                }


                //CALCULA PERCENTUAL META
                if($meta_compras > 0 or $valor_compra > 0){
                    $perc_meta_compras = ($valor_compra / $meta_compras) * 100;
                }
                    
                $tendenciaFechamento = 0;
		
			// Converte as datas para o formato DateTime
			$data_inicio1 = new DateTime($data_inicio);
			$data_fim1 = new DateTime($data_fim);
		
			// Inicializa o contador de dias úteis
			$dias_uteis = 0;
		
			// Itera pelas datas
			// Limita a data final para hoje caso o filtro ultrapasse a data atual
			$hoje = new DateTime();
			$hoje->setTime(0, 0, 0);
			$data_final_calculo = ($data_fim1 > $hoje) ? $hoje : $data_fim1;

			// Itera pelas datas para contar dias úteis decorridos
			while ($data_inicio1 <= $data_final_calculo) {
				// Verifica se o dia não é sábado (6) ou domingo (0)
				if ($data_inicio1->format('N') < 6) { // 'N' retorna o dia da semana de 1 (segunda) a 7 (domingo)
					$dias_uteis++;
				}
				// Avança para o próximo dia
				$data_inicio1->modify('+1 day');
			}
		
			// Total de dias no mês (desconsiderando fins de semana)
			$dias_totais = 0;
			$data_inicio_mes = new DateTime($data_inicio);
			$data_fim_mes = new DateTime($data_fim);
			$data_fim_mes->modify('last day of this month'); // Último dia do mês
		
			while ($data_inicio_mes <= $data_fim_mes) {
				if ($data_inicio_mes->format('N') < 6) {
					$dias_totais++;
				}
				$data_inicio_mes->modify('+1 day');
			}
		
			// Cálculo da tendência de fechamento
			if ($dias_uteis > 0) {
				$tendenciaFechamento = ($valor_compra / $dias_uteis) * $dias_totais;
			} else {
				$tendenciaFechamento = 0;
			}

             
            	//----------------------orcamentos em aberto ------------------
		$totalorcamentos = 0;
		$qtdorcamentos = 0;
		$qtdcliorcamentos = 0;
		
			$queryorcamentos = " SELECT sum(orc.total_geral) as totalorcamentos, count(orc.codigo) as qtdorcamentos,
		count(DISTINCT(orc.fornecedor)) as qtdcliorcamentos
		from {$banco_vendas}.cad_comp orc
		LEFT JOIN {$banco_publico}.cad_forn cli ON (cli.codigo = orc.fornecedor)
		WHERE orc.situacao in ('EA')
		AND orc.data_cadastro >= '$data_inicio' 
		AND orc.data_cadastro <= '$data_fim' ";
		
		
			$resulorcamentos = mysqli_query($dash, $queryorcamentos);
			if (mysqli_num_rows($resulorcamentos) > 0) {
				$row = mysqli_fetch_assoc($resulorcamentos);
				$totalorcamentos = $row['totalorcamentos'];
				$qtdorcamentos = $row['qtdorcamentos'];
				$qtdcliorcamentos = $row['qtdcliorcamentos'];
			}
							

            	//----------------------pedidos em aberto ------------------
		$totalpedidosaberto = 0;
		$qtdpedidosaberto = 0;
		$qtdclipedidosaberto = 0;
		
			$querytpedidos = " SELECT sum(orc.total_geral) as totalpedidosaberto, count(orc.codigo) as qtdpedidosaberto,
		count(DISTINCT(orc.fornecedor)) as qtdclipedidosaberto
		from {$banco_vendas}.cad_comp orc
		LEFT JOIN {$banco_publico}.cad_forn cli ON (cli.codigo = orc.fornecedor)
		where orc.situacao in ('FP', 'AI')
		AND orc.data_cadastro >= '$data_inicio' 
		and orc.data_cadastro <= '$data_fim' ";
		
			$resultpedidos = mysqli_query($dash, $querytpedidos);
			if (mysqli_num_rows($resultpedidos) > 0) {
				$row = mysqli_fetch_assoc($resultpedidos);
	
				$totalpedidosaberto = $row['totalpedidosaberto'];
				$qtdpedidosaberto = $row['qtdpedidosaberto'];
				$qtdclipedidosaberto = $row['qtdclipedidosaberto'];
			}

//---------------------- Financeiro Pagar (Vencidos e A Vencer) ------------------
$valor_pagar_avencer = 0; $totalforvencer = 0; $totaltitulovencer = 0;
$querypagvencer = "SELECT COALESCE(SUM(valor),0) AS valor_pagar_avencer, count(distinct(fornecedor)) as totalforvencer, count(distinct(codigo)) as totaltitulovencer 
    FROM {$banco_financeiro}.ct_pagar WHERE valor_pago = 0 AND vencimento >= CURRENT_DATE AND valor > 0.01 {$filtro_pagar} AND provisao = 'N' AND inadimplencia = '0000-00-00' ";
$resultpagvencer = mysqli_query($dash, $querypagvencer);
if ($resultpagvencer && mysqli_num_rows($resultpagvencer) > 0) {
    $row = mysqli_fetch_assoc($resultpagvencer);
    $valor_pagar_avencer = $row['valor_pagar_avencer']; $totalforvencer = $row['totalforvencer']; $totaltitulovencer = $row['totaltitulovencer'];
}

$valor_pagar_vencidos = 0; $totalforvencidos = 0; $totaltitulovencidos = 0;
$querypagven = "SELECT COALESCE(SUM(valor),0) AS valor_pagar_vencidos, count(distinct(fornecedor)) as totalforvencidos, count(distinct(codigo)) as totaltitulovencidos 
    FROM {$banco_financeiro}.ct_pagar WHERE valor_pago = 0 AND vencimento < CURRENT_DATE AND valor > 0.01 {$filtro_pagar} AND provisao = 'N' AND inadimplencia = '0000-00-00' ";
$resultpagven = mysqli_query($dash, $querypagven);
if ($resultpagven && mysqli_num_rows($resultpagven) > 0) {
    $row = mysqli_fetch_assoc($resultpagven);
    $valor_pagar_vencidos = $row['valor_pagar_vencidos']; $totalforvencidos = $row['totalforvencidos']; $totaltitulovencidos = $row['totaltitulovencidos'];
}
$sql_compras_acumulado = " SELECT
    data_emissao,
    total_nf,
    round((@cumulative_sum := @cumulative_sum + total_nf),2) AS total_acumulado
FROM (
    SELECT
    DATE(nf.data_saida_entrada) AS data_emissao,
    SUM(nf.total_nf) AS total_nf
  FROM {$banco_vendas}.cad_nf nf
  LEFT JOIN {$banco_vendas}.series se ON (se.codigo = nf.serie)
  LEFT JOIN {$banco_vendas}.transacoes t ON (t.codigo = nf.transacao AND t.filial = nf.filial )
    WHERE
        YEAR(nf.data_saida_entrada) = YEAR(NOW()) AND
    MONTH(nf.data_saida_entrada) = MONTH(NOW()) 
      AND t.ent_sai = 'e' 
  AND nf.CANCELADA = 'N' ";
  
if (!empty($transacoescompra)) { $sql_compras_acumulado .= " AND nf.transacao IN ($transacoescompra)"; }
if (!empty($seriescompra)) { $sql_compras_acumulado .= " AND nf.serie IN ($seriescompra)"; }

$sql_compras_acumulado .= "
   GROUP BY DATE(nf.data_saida_entrada)
) AS daily_sales
CROSS JOIN (SELECT @cumulative_sum := 0) AS init
ORDER BY data_emissao
";

$result_diario = executar_consulta($dash, $sql_compras_acumulado, []);


$labels = array();
$valor_a_receber = array();
$valor_recebido = array();


if ($result_diario) {
    while ($row = mysqli_fetch_assoc($result_diario)) {
        $labels[] = $row['data_emissao'];
        $valor_a_receber[] = round($row['total_nf'], 2);
        $valor_recebido[] = round($row['total_acumulado'], 2);
        
    }
}

// --- GRÁFICO COMPARATIVO ANUAL (ÚLTIMOS 5 ANOS) ---
$ano_atual = date('Y');
$ano_inicio = $ano_atual - 4;

$sql_comparativo_anual = "SELECT 
    YEAR(nf.data_saida_entrada) as ano,
    MONTH(nf.data_saida_entrada) as mes,
    SUM(nf.total_nf) as total_nf
FROM {$banco_vendas}.cad_nf nf
LEFT JOIN {$banco_vendas}.series se ON (se.codigo = nf.serie)
LEFT JOIN {$banco_vendas}.transacoes t ON (t.codigo = nf.transacao AND t.filial = nf.filial)
WHERE YEAR(nf.data_saida_entrada) >= $ano_inicio
  AND nf.cancelada = 'N'
  AND t.ent_sai = 'E'";

if (!empty($transacoescompra)) { $sql_comparativo_anual .= " AND nf.transacao IN ($transacoescompra)"; }
if (!empty($seriescompra)) { $sql_comparativo_anual .= " AND nf.serie IN ($seriescompra)"; }
$sql_comparativo_anual .= ($filial ? " AND nf.filial = '$filial_esc'" : "");

$sql_comparativo_anual .= " GROUP BY YEAR(nf.data_saida_entrada), MONTH(nf.data_saida_entrada) ORDER BY ano ASC, mes ASC";

$result_comparativo = executar_consulta($dash, $sql_comparativo_anual, []);

$dados_comparativos = [];
// Inicializa array para os últimos 5 anos com 0 em todos os meses
for ($y = $ano_inicio; $y <= $ano_atual; $y++) {
    $dados_comparativos[$y] = array_fill(1, 12, 0);
}

if ($result_comparativo) {
    while ($row = mysqli_fetch_assoc($result_comparativo)) {
        $ano = $row['ano'];
        $mes = $row['mes'];
        $val = $row['total_nf'];
        if (isset($dados_comparativos[$ano])) {
            $dados_comparativos[$ano][$mes] = round($val, 2);
        }
    }
}

// Prepara datasets para Chart.js
$datasets_comparativo = [];
$colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74c3c']; // Cores para os 5 anos
$i = 0;
foreach ($dados_comparativos as $ano => $valores) {
    $datasets_comparativo[] = [
        'label' => (string)$ano,
        'data' => array_values($valores), // reindexa para 0-11
        'backgroundColor' => $colors[$i % count($colors)],
        'borderRadius' => 4
    ];
    $i++;
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


$sqlestoque = " select  LEFT(CONCAT(e.produto, ' - ', p.descricao), 30)  as descprod, round(sum(e.estoque)) as estoque,
				ROUND((SUM(e.estoque) * 100.0) / SUM(SUM(e.estoque)) OVER (), 2) AS perc_estoque
				from {$banco_estoque}.prod_setor e
				left join {$banco_publico}.cad_prod p on (p.codigo = e.produto)
				GROUP BY e.produto
				ORDER BY e.estoque desc
				limit 5";

$resultestoque = mysqli_query($dash, $sqlestoque);

$labelsestoque = [];
$percentuaisestoque = [];
$estoques = [];

$totalPerc = 0;
$totalEstoqueOutros = 0;
$totalPercOutros = 0;

$limit = 5;
$i = 0;

while ($row = mysqli_fetch_assoc($resultestoque)) {
    if ($i < $limit) {
        $labelsestoque[] = $row['descprod'];
        $percentuaisestoque[] = (float) $row['perc_estoque'];
        $estoques[] = (float) $row['estoque'];
        $totalPerc += (float) $row['perc_estoque'];
    } else {
        $totalPercOutros += (float) $row['perc_estoque'];
        $totalEstoqueOutros += (float) $row['estoque'];
    }
    $i++;
}

if ($totalPercOutros > 0) {
    $labelsestoque[] = 'Outros';
    $percentuaisestoque[] = round($totalPercOutros, 2);
    $estoques[] = $totalEstoqueOutros;
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

// --- MAIORES FORNECEDORES ---
$sql_atrasados = "SELECT
				f.razao_social as cliente,
				   ROUND(SUM(
      CASE 
        WHEN t.ent_sai = 'E' AND t.dashboard = 'S' AND t.tipo = 'C' THEN (nf.total_nf )
        WHEN t.ent_sai = 'S' AND t.dashboard = 'S' AND t.tipo = 'D' THEN -(nf.total_nf)
        ELSE 0
      END), 2) AS total_vendas
				
FROM  {$banco_vendas}.cad_nf nf
   
     
    LEFT JOIN 
        {$banco_vendas}.transacoes t ON t.codigo = nf.transacao AND t.filial = nf.filial
    LEFT JOIN 
        {$banco_publico}.cad_forn f on (f.codigo = nf.codigo_cli_for and nf.cli_for = 'F')
		WHERE nf.cancelada = 'N'
        AND nf.data_saida_entrada >= '$data_inicio' AND nf.data_saida_entrada <= '$data_fim'
		AND nf.cli_for = 'F'
         GROUP BY  nf.codigo_cli_for
			ORDER BY total_vendas desc
			limit 15";

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

//------------------------TOP 10 PRODUTOS VALOR------------------------------------
$sql_top_produtos_vlr = "SELECT 
     ROW_NUMBER() OVER (ORDER BY total_valor DESC) AS ordem,
    LEFT(CONCAT(pro.codigo, ' - ', pro.descricao), 40) AS descricao,
     SUM(po.total) AS total_valor,
    ROUND((SUM(po.total) / total_valor_all_items * 100), 2) AS percentual,
    'V' AS tipo
FROM {$banco_vendas}.mvto_produtos po
LEFT JOIN {$banco_vendas}.cad_nf nf ON nf.codigo = po.chave_mvto
LEFT JOIN {$banco_publico}.cad_prod pro ON pro.codigo = po.produto
JOIN (
      SELECT SUM(po.total) AS total_valor_all_items
    FROM {$banco_vendas}.mvto_produtos po
    LEFT JOIN {$banco_vendas}.cad_nf nf ON nf.codigo = po.chave_mvto
    WHERE nf.data_saida_entrada >= '$data_inicio' AND nf.data_saida_entrada <= '$data_fim'
      AND nf.cancelada = 'N'";

if (!empty($transacoescompra)) {
    $sql_top_produtos_vlr .= " AND nf.transacao IN ($transacoescompra)";
}
if (!empty($seriescompra)) {
    $sql_top_produtos_vlr .= " AND nf.serie IN ($seriescompra)";
}

$sql_top_produtos_vlr .= "
) AS total_all_items ON 1=1
WHERE nf.data_saida_entrada >= '$data_inicio' AND nf.data_saida_entrada <= '$data_fim'
  AND nf.cancelada = 'N'";

if (!empty($transacoescompra)) {
    $sql_top_produtos_vlr .= " AND nf.transacao IN ($transacoescompra)";
}
if (!empty($seriescompra)) {
    $sql_top_produtos_vlr .= " AND nf.serie IN ($seriescompra)";
}

$sql_top_produtos_vlr .= "
GROUP BY po.produto, pro.descricao, total_valor_all_items
ORDER BY total_valor DESC
LIMIT 10
";

$result_top_produtos_vlr = mysqli_query($dash, $sql_top_produtos_vlr);

$labels_top_produtos_vlr = [];
$percentuais_top_produtos_vlr = [];
$valores_top_produtos_vlr = [];

if ($result_top_produtos_vlr) {
    while ($row = mysqli_fetch_assoc($result_top_produtos_vlr)) {
        $labels_top_produtos_vlr[] = $row['descricao'];
        $percentuais_top_produtos_vlr[] = (float)$row['percentual'];
        $valores_top_produtos_vlr[] = (float)$row['total_valor'];
    }
}
$total_perc_top10_produtos_vlr = array_sum($percentuais_top_produtos_vlr);


//------------------------TOP 10 PRODUTOS QTD------------------------------------
$sql_top_produtos_qtd = "SELECT 
    ROW_NUMBER() OVER (ORDER BY total_quantidade DESC) AS ordem,
    LEFT(CONCAT(pro.codigo, ' - ', pro.descricao), 40) AS descricao,
    SUM(po.quantidade) AS total_quantidade,
    ROUND((SUM(po.quantidade) / total_quantidade_all_items * 100), 2) AS percentual,
    'P' AS tipo
FROM {$banco_vendas}.mvto_produtos po
LEFT JOIN {$banco_vendas}.cad_nf nf ON nf.codigo = po.chave_mvto
LEFT JOIN {$banco_publico}.cad_prod pro ON pro.codigo = po.produto
JOIN (
    SELECT SUM(po.quantidade) AS total_quantidade_all_items
    FROM {$banco_vendas}.mvto_produtos po
    LEFT JOIN {$banco_vendas}.cad_nf nf ON nf.codigo = po.chave_mvto
    WHERE nf.data_saida_entrada >= '$data_inicio' AND nf.data_saida_entrada <= '$data_fim'
      AND nf.cancelada = 'N'";

if (!empty($transacoescompra)) {
    $sql_top_produtos_qtd .= " AND nf.transacao IN ($transacoescompra)";
}
if (!empty($seriescompra)) {
    $sql_top_produtos_qtd .= " AND nf.serie IN ($seriescompra)";
}

$sql_top_produtos_qtd .= "
) AS total_all_items ON 1=1
WHERE nf.data_saida_entrada >= '$data_inicio' AND nf.data_saida_entrada <= '$data_fim'
  AND nf.cancelada = 'N'";

if (!empty($transacoescompra)) {
    $sql_top_produtos_qtd .= " AND nf.transacao IN ($transacoescompra)";
}
if (!empty($seriescompra)) {
    $sql_top_produtos_qtd .= " AND nf.serie IN ($seriescompra)";
}

$sql_top_produtos_qtd .= "
GROUP BY po.produto, pro.descricao, total_quantidade_all_items
ORDER BY total_quantidade DESC
LIMIT 10
";

$result_top_produtos_qtd = mysqli_query($dash, $sql_top_produtos_qtd);

$labels_top_produtos_qtd = [];
$percentuais_top_produtos_qtd = [];
$valores_top_produtos_qtd = [];

if ($result_top_produtos_qtd) {
    while ($row = mysqli_fetch_assoc($result_top_produtos_qtd)) {
        $labels_top_produtos_qtd[] = $row['descricao'];
        $percentuais_top_produtos_qtd[] = (float)$row['percentual'];
        $valores_top_produtos_qtd[] = (float)$row['total_quantidade'];
    }
}
$total_perc_top10_produtos_qtd = array_sum($percentuais_top_produtos_qtd);


//------------------------TOP 10 GRUPOS VALOR------------------------------------
$sql_top_grupos_vlr = "SELECT 
    ROW_NUMBER() OVER (ORDER BY total_valor DESC) AS ordem,
    gru.nome AS descricaogru,
    gru.codigo AS codgrupo,
    SUM(po.total) AS total_valor,
    ROUND((SUM(po.total) / total_valor_all_items * 100), 2) AS percentual,
    'V' AS tipo
FROM {$banco_vendas}.mvto_produtos po
LEFT JOIN {$banco_vendas}.cad_nf nf ON nf.codigo = po.chave_mvto
LEFT JOIN {$banco_publico}.cad_prod pro ON pro.codigo = po.produto
LEFT JOIN {$banco_publico}.cad_pgru gru ON pro.grupo = gru.codigo
JOIN (
    SELECT SUM(po.total) AS total_valor_all_items
    FROM {$banco_vendas}.mvto_produtos po
    LEFT JOIN {$banco_vendas}.cad_nf nf ON nf.codigo = po.chave_mvto
    LEFT JOIN {$banco_publico}.cad_prod pro ON pro.codigo = po.produto
    LEFT JOIN {$banco_publico}.cad_pgru gru ON pro.grupo = gru.codigo
    WHERE nf.data_saida_entrada >= '$data_inicio' AND nf.data_saida_entrada <= '$data_fim'
      AND nf.cancelada = 'N'";

if (!empty($transacoescompra)) {
    $sql_top_grupos_vlr .= " AND nf.transacao IN ($transacoescompra)";
}
if (!empty($seriescompra)) {
    $sql_top_grupos_vlr .= " AND nf.serie IN ($seriescompra)";
}

$sql_top_grupos_vlr .= "
) AS total_all_items ON 1=1
WHERE nf.data_saida_entrada >= '$data_inicio' AND nf.data_saida_entrada <= '$data_fim'
  AND nf.cancelada = 'N'";

if (!empty($transacoescompra)) {
    $sql_top_grupos_vlr .= " AND nf.transacao IN ($transacoescompra)";
}
if (!empty($seriescompra)) {
    $sql_top_grupos_vlr .= " AND nf.serie IN ($seriescompra)";
}

$sql_top_grupos_vlr .= "
GROUP BY gru.nome, gru.codigo, total_valor_all_items
ORDER BY total_valor DESC
LIMIT 10
";

$result_top_grupos_vlr = mysqli_query($dash, $sql_top_grupos_vlr);

$labels_top_grupos_vlr = [];
$percentuais_top_grupos_vlr = [];
$valores_top_grupos_vlr = [];

if ($result_top_grupos_vlr) {
    while ($row = mysqli_fetch_assoc($result_top_grupos_vlr)) {
        $labels_top_grupos_vlr[] = $row['descricaogru'];
        $percentuais_top_grupos_vlr[] = (float)$row['percentual'];
        $valores_top_grupos_vlr[] = (float)$row['total_valor'];
    }
}
$total_perc_top10_grupos_vlr = array_sum($percentuais_top_grupos_vlr);


//------------------------TOP 10 GRUPOS QUANTIDADE------------------------------------
$sql_top_grupos_qtd = "SELECT 
   ROW_NUMBER() OVER (ORDER BY total_quantidade DESC) AS ordem,
    gru.nome AS descricaogru,
    gru.codigo AS codgrupo,
    SUM(po.quantidade) AS total_quantidade,
    ROUND((SUM(po.quantidade) / total_quantidade_all_items * 100), 2) AS percentual,
    'P' AS tipo
FROM {$banco_vendas}.mvto_produtos po
LEFT JOIN {$banco_vendas}.cad_nf nf ON nf.codigo = po.chave_mvto
LEFT JOIN {$banco_publico}.cad_prod pro ON pro.codigo = po.produto
LEFT JOIN {$banco_publico}.cad_pgru gru ON pro.grupo = gru.codigo
JOIN (
      SELECT SUM(po.quantidade) AS total_quantidade_all_items
    FROM {$banco_vendas}.mvto_produtos po
    LEFT JOIN {$banco_vendas}.cad_nf nf ON nf.codigo = po.chave_mvto
    LEFT JOIN {$banco_publico}.cad_prod pro ON pro.codigo = po.produto
    LEFT JOIN {$banco_publico}.cad_pgru gru ON pro.grupo = gru.codigo
    WHERE nf.data_saida_entrada >= '$data_inicio' AND nf.data_saida_entrada <= '$data_fim'
      AND nf.cancelada = 'N'";

if (!empty($transacoescompra)) {
    $sql_top_grupos_qtd .= " AND nf.transacao IN ($transacoescompra)";
}
if (!empty($seriescompra)) {
    $sql_top_grupos_qtd .= " AND nf.serie IN ($seriescompra)";
}

$sql_top_grupos_qtd .= "
) AS total_all_items ON 1=1
WHERE nf.data_saida_entrada >= '$data_inicio' AND nf.data_saida_entrada <= '$data_fim'
  AND nf.cancelada = 'N'";

if (!empty($transacoescompra)) {
    $sql_top_grupos_qtd .= " AND nf.transacao IN ($transacoescompra)";
}
if (!empty($seriescompra)) {
    $sql_top_grupos_qtd .= " AND nf.serie IN ($seriescompra)";
}

$sql_top_grupos_qtd .= "
GROUP BY gru.nome, gru.codigo, total_quantidade_all_items
ORDER BY total_quantidade DESC
LIMIT 10
";

$result_top_grupos_qtd = mysqli_query($dash, $sql_top_grupos_qtd);

$labels_top_grupos_qtd = [];
$percentuais_top_grupos_qtd = [];
$valores_top_grupos_qtd = [];

if ($result_top_grupos_qtd) {
    while ($row = mysqli_fetch_assoc($result_top_grupos_qtd)) {
        $labels_top_grupos_qtd[] = $row['descricaogru'];
        $percentuais_top_grupos_qtd[] = (float)$row['percentual'];
        $valores_top_grupos_qtd[] = (float)$row['total_quantidade'];
    }
}
$total_perc_top10_grupos_qtd = array_sum($percentuais_top_grupos_qtd);


//----------------------------VALOR ESTOQUE--------------------------------
$sqlValorEstoque = " SELECT 
        ROUND(SUM(p.custo_medio * ps.estoque), 2) AS valor_estoque
  FROM {$banco_estoque}.prod_setor ps
       LEFT JOIN {$banco_publico}.cad_prod pro ON (pro.codigo = ps.produto)
         LEFT JOIN {$banco_publico}.prod_custos p ON p.produto = ps.produto
      LEFT JOIN {$banco_estoque}.setores se ON se.codigo = ps.setor
      WHERE pro.ativo = 'S'
      and p.filial = 0
";

$result = mysqli_query($dash, $sqlValorEstoque);
$row = mysqli_fetch_assoc($result);

$valorEstoque = (float) $row['valor_estoque'];

//----------------------------quantide de produtos--------------------------------
$sql_qtd_produtosativos = "SELECT COUNT(codigo) AS total1
 FROM  {$banco_publico}.cad_prod
  WHERE ativo = 'S' ";

  $result_produtos_ativos = mysqli_query($dash, $sql_qtd_produtosativos);
  $row = mysqli_fetch_assoc($result_produtos_ativos);

$qtd_produtos_ativos = $row['total1'];

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


   <!-- ================== mapa-css ================== -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">


     <!-- Styles -->
    <link rel="stylesheet" href="../css/adminlte.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">

    <!-- Scripts Headers -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
    /* Custom Midnight Theme Styles */
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&family=JetBrains+Mono:wght@400;700&display=swap');
    :root {
        --bg-deep: #0F172A;
        --bg-surface: #1E293B;
        --bg-elevated: #334155;
        --accent-primary: #38BDF8;
        --accent-success: #10B981;
        --accent-warning: #F59E0B;
        --accent-error: #EF4444;
        --text-main: #fcf8f8;
        --text-muted: #94A3B8;
        --border-main: rgba(255, 255, 255, 0.05);
        --sidebar-width: 320px;
        --transition-premium: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    [data-bs-theme="light"] {
        --bg-deep: #f4f6f9;
        --bg-surface: #ffffff;
        --bg-elevated: #e9ecef;
        --accent-primary: #0284c7; /* Darker blue for light mode */
        --text-main: #1f2937;
        --text-muted: #64748b;
        --border-main: rgba(0, 0, 0, 0.1);
    }

    body {
        background-color: var(--bg-deep);
        color: var(--text-main);
        font-family: 'Plus Jakarta Sans', sans-serif;
        margin: 0;
        overflow-x: hidden;
    }
    .dashboard-wrapper { display: flex; min-height: 100vh; }
    .command-sidebar {
        width: var(--sidebar-width);
        background: linear-gradient(180deg, #1E293B 0%, #0F172A 100%);
        border-right: 1px solid var(--border-main);
        padding: 40px 24px;
        height: 100vh;
        position: fixed;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }
    .operational-main {
        margin-left: var(--sidebar-width);
        flex: 1;
        padding: 40px 60px;
        max-width: calc(100vw - var(--sidebar-width));
    }
    .strategic-identity h1 { font-size: 10px; font-weight: 800; letter-spacing: 3px; text-transform: uppercase; color: var(--accent-primary); margin-bottom: 12px; }
    .strategic-kpi { margin-bottom: 48px; position: relative; }
    .kpi-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); display: block; margin-bottom: 8px; }
    .huge-value { font-size: 36px; font-weight: 800; color: var(--text-main); font-family: 'JetBrains Mono', monospace; line-height: 1; display: flex; align-items: baseline; }
    .huge-value .currency { font-size: 16px; margin-right: 4px; color: var(--accent-primary); }
    .trend-indicator { font-size: 12px; font-weight: 600; margin-top: 8px; display: flex; align-items: center; gap: 6px; }

    .trend-up { color: var(--accent-success); }
    .grid-fragments { 
        display: grid; 
        grid-template-columns: repeat(12, 1fr); 
        gap: 20px; }
    .card-fragment { 
        grid-column: span 12; 
        background: var(--bg-surface);
        border: 1px solid var(--border-main); 
        border-radius: 12px; 
        padding: 20px; 
        min-height: 140px; 
        display: flex; 
        flex-direction: column; 
        justify-content: center;
        color: var(--text-main); }

    @media (min-width: 992px) { 
        .col-span-md-4 { grid-column: span 4; } 
        .col-span-md-3 { grid-column: span 3; }
    }
    .frag-title { 
        font-size: 11px; 
        font-weight: 700; 
        text-transform: uppercase; 
        letter-spacing: 1px; 
        color: var(--text-muted); 
        margin-bottom: 10px;
    }
    .frag-value { 
        font-size: 22px;
        font-weight: 700; 
        font-family: 'JetBrains Mono', monospace; 
    }
    .filter-fragment { 
        background: var(--bg-elevated);
        border-radius: 24px;
        padding: 30px; 
        margin-bottom: 40px; }


    .btn-command { 
        background: var(--accent-primary);
        color: #0F172A;
        font-weight: 700;
        text-transform: uppercase;
        border-radius: 6px;
        padding: 10px 20px;
        border: none; }
    /* Animations */
    @keyframes revealUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    .reveal { animation: revealUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards; opacity: 0; }
    .delay-1 { animation-delay: 0.1s; } .delay-2 { animation-delay: 0.2s; } .delay-3 { animation-delay: 0.3s; }
    
    /* Charts & Cards Custom overrides */
    .card { background-color: var(--bg-surface); border: 1px solid var(--border-main); color: var(--text-main); }
    .card-header { border-bottom: 1px solid var(--border-main); background-color: rgba(255,255,255,0.02); }
    
    [data-bs-theme="light"] .card-header { background-color: rgba(0,0,0,0.02); }
    </style>



</head>
<!--end::Head-->
<!--begin::Body-->

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary" data-bs-theme="dark">

<div class="app-wrapper">

    <!-- 1. NAVBAR (Nav Dash Code) -->
    <?php require_once '../menu/nav_dash.php'; ?>

    <!-- 2. SIDEBAR (Sidebar Code) -->
    <aside class="app-sidebar bg-dark shadow" data-bs-theme="dark">
        <!-- Brand -->
        <div class="sidebar-brand">
            <a href="../dashboard/dashboard.php" class="brand-link">
                <img src="../assets/img/logosite.png" alt="Logo" class="brand-image opacity-75 shadow" />
                <span class="brand-text fw-light">Intersig</span>
            </a>
        </div>
        <!-- Sidebar Wrapper & Menu -->
        <div class="sidebar-wrapper">
             <?php require_once '../menu/menu_dash.php'; ?>
        </div>
    </aside>

    <!-- 3. MAIN CONTENT -->
    <main class="app-main">
        <!-- Header do Conteúdo -->
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6"><h3 class="mb-0">Dashboard de Compras</h3></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item active">Compras</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conteúdo Principal -->
        <div class="app-content">
            <div class="container-fluid">
                
                <!-- FILTROS -->
                <section class="filter-fragment">
                    <form method="GET" id="form-filtros" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label text-muted small">FILIAL</label>
                            <select name="filial" class="form-select bg-dark text-white border-secondary">
                                <option value="">Todas</option>
                                <?php foreach ($filiais as $f): ?>
                                <option value="<?= $f['codigo'] ?>" <?= ($f['codigo'] == $filial ? 'selected' : '') ?>>
                                    <?= $f['codigo'] ?> - <?= htmlspecialchars($f['nome']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">INÍCIO</label>
                            <input type="text" name="data_inicio" id="data_inicio" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data_inicio_raw) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">TÉRMINO</label>
                            <input type="text" name="data_fim" id="data_fim" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data_fim_raw) ?>">
                        </div>
                          <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn-command flex-grow-1">Filtrar</button>
                        <?php if ($filial || $data_inicio_raw || $data_fim_raw): ?>
                        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-secondary rounded-3 px-3"><i class="fa fa-undo"></i></a>
                        <?php endif; ?>
                    </div>
                        <div class="col-12">
                            <small class="text-muted me-2">Períodos Sugeridos:</small>
                            <button type="button" class="btn btn-xs btn-outline-info rounded-pill" onclick="setperiodo('hoje')">Hoje</button>
                            <button type="button" class="btn btn-xs btn-outline-info rounded-pill" onclick="setperiodo('semana')">Semana</button>
                            <button type="button" class="btn btn-xs btn-outline-info rounded-pill" onclick="setperiodo('mes')">Mês</button>
                            <button type="button" class="btn btn-xs btn-outline-info rounded-pill" onclick="setperiodo('ano')">Ano</button>
                        </div>
                    </form>
                </section>
            <!-- KPI Cards -->
            <div class="grid-fragments mb-5 reveal delay-1">
                  <div class="card-fragment col-span-md-3 delay-1" style="border-left: 4px solid var(--accent-primary);">
                    <span class="frag-title">Valor Compras</span>
                    <div class="frag-value kpi-anime" data-value="<?= $valor_compra ?>" data-prefix="R$ " data-decimals="2" style="color: var(--accent-primary);">
                        R$ <?php echo number_format($valor_compra, 2, ',', '.'); ?>
                    </div>
                    <div class="text-muted small mt-2 ">
                        <span style="color: var(--accent-success);">Meta: <?php echo number_format($meta_compras, 2, ',', '.'); ?></span>
                        <span style="color: var(--accent-success);">Atingido: <?php echo number_format($perc_meta_compras, 1); ?>%</span>
                    </div>
                </div>

                  <div class="card-fragment col-span-md-3 delay-2" style="border-left: 4px solid var(--accent-warning);">
                    <span class="frag-title">Valor em Estoque(Custo)</span>
                    <div class="frag-value kpi-anime" data-value="<?= $valorEstoque ?>" data-prefix="R$ " data-decimals="2" style="color: var(--accent-warning);">
                        R$ <?php echo number_format($valorEstoque, 2, ',', '.'); ?>
                    </div>
                    <div class="text-muted small mt-2 ">
                      <span style="color: var(--accent-warning);">SKUs Ativos: <?php echo $qtd_produtos_ativos; ?></span>
                    </div>
                </div>
                 <div class="card-fragment col-span-md-3 delay-6" style="border-left: 4px solid var(--accent-success);">
                    <span class="frag-title">Fornecedores Ativos</span>
                    <div class="frag-value kpi-anime" data-value="<?= $total_fornecedor ?? 0 ?>" data-prefix="" data-decimals="0" style="color: var(--accent-success);">
                        <?php echo number_format($total_fornecedor ?? 0, 0, ',', '.'); ?>
                    </div>
                    <div class="text-muted small mt-2">Total de fornecedores</div>
                </div>
                <div class="card-fragment col-span-md-3 delay-6" style="border-left: 4px solid var(--accent-primary);">
                    <span class="frag-title">Orçamentos Pendentes</span>
                    <div class="frag-value kpi-anime" data-value="<?= $totalorcamentos ?>" data-prefix="R$ " data-decimals="2" style="color: var(--accent-primary);">
                        R$ <?php echo number_format($totalorcamentos, 2, ',', '.'); ?>
                    </div>
                    <div class="text-muted small mt-2">
                         <span style="color: var(--accent-success);">Fornecedores em cotação: <?php echo $qtdcliorcamentos; ?></span>
                    </div>



                </div>
                <div class="card-fragment col-span-md-3 delay-4" style="border-left: 4px solid var(--accent-warning);">
                    <span class="frag-title">Pedidos em Aberto</span>
                    <div class="frag-value kpi-anime" data-value="<?= $totalpedidosaberto ?>" data-prefix="R$ " data-decimals="2">
                        R$ <?php echo number_format($totalpedidosaberto, 2, ',', '.'); ?>
                    </div>
                    <div class="text-muted small mt-2">
                        <i class="fa fa-box-open me-1"></i> <?php echo $qtdpedidosaberto; ?> aguardando faturamento
                    </div>
                </div>
              

                 <div class="card-fragment col-span-md-3 delay-5" style="border-left: 4px solid var(--accent-error);">
                    <span class="frag-title" style="color: var(--accent-error);">Devoluções (NF)</span>
                    <div class="frag-value kpi-anime" data-value="<?= $valor_dev ?? 0 ?>" data-prefix="R$ " data-decimals="2" style="color: var(--accent-error);">
                        R$ <?php echo number_format($valor_dev ?? 0, 2, ',', '.'); ?>
                    </div>
                    <div class="text-muted small mt-2">
                     <span style="color: var(--accent-error);">Devoluções (NF): <?php echo $qtd_dev; ?></span>
                    </div>
                </div>
               
                <div class="card-fragment col-span-md-3 delay-6" style="border-left: 4px solid var(--accent-success);">
                    <span class="frag-title">Ticket Médio</span>
                    <div class="frag-value kpi-anime" data-value="<?= $tickt_medio ?? 0 ?>" data-prefix="R$ " data-decimals="2" style="color: var(--accent-success);">
                        R$ <?php echo number_format($tickt_medio ?? 0, 2, ',', '.'); ?>
                    </div>
                    <div class="text-muted small mt-2">Eficiência operacional de compra</div>
                </div>
                <div class="card-fragment col-span-md-3 delay-7" style="border-left: 4px solid var(--accent-primary);">
                    <span class="frag-title">Projeção de Fechamento</span>
                    <div class="frag-value kpi-anime" data-value="<?= $tendenciaFechamento ?>" data-prefix="R$ " data-decimals="2">
                        R$ <?php echo number_format($tendenciaFechamento, 2, ',', '.'); ?>
                    </div>
                    <div class="text-muted small mt-2">Tendência estatística mensal</div>
                </div>
            </div>

            <!-- ANALYTIC VISUALIZER (Charts Area) -->
            <h3 class="kpi-label mb-4 reveal delay-2">Análise de Performance e Distribuição</h3>
            <div class="row g-4 mb-5 reveal delay-2">

                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header"><strong>Produtos com maior estoque</strong></div>
                        <div class="card-body">
                            <div style="height: 300px;"><canvas id="chartMaioresestoque"></canvas></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header"><strong>Distribuição por Tipo de Pagamento</strong></div>
                        <div class="card-body">
                            <div style="height: 300px;"><canvas id="chartTipoPagamento"></canvas></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header"><strong>Pagar X Pago por Filial</strong></div>
                        <div class="card-body">
                            <div style="height: 300px;"><canvas id="pagar_pagoChart"></canvas></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header"><strong>Receber X Recebido por Filial</strong></div>
                        <div class="card-body">
                            <div style="height: 300px;"><canvas id="receber_recebidoChart"></canvas></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header"><strong>Evolução Diária - Compras</strong></div>
                        <div class="card-body">
                            <div style="height: 350px;"><canvas id="dailyChart"></canvas></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header"><strong>Comparativo Mensal (Últimos 5 Anos)</strong></div>
                        <div class="card-body">
                            <div style="height: 350px;"><canvas id="chartComparativoAnual"></canvas></div>
                        </div>
                    </div>
                </div>

               

                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header"><strong>Evolução Receber - Pagar</strong></div>
                        <div class="card-body">
                            <div style="height: 350px;"><canvas id="dailyCharte"></canvas></div>
                        </div>
                    </div>
                </div>

                <!-- TOP PRODUCTS & GROUPS -->
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header"><h5 class="card-title text-uppercase fw-bold">Top 10 Produtos(Vlr)</h5></div>
                        <div class="card-body"><div style="height: 300px;"><canvas id="grafico_top_produtos_vlr"></canvas></div></div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header"><h5 class="card-title text-uppercase fw-bold">Top 10 Produtos(Qtd)</h5></div>
                        <div class="card-body"><div style="height: 300px;"><canvas id="grafico_top_produtos_qtd"></canvas></div></div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header"><h5 class="card-title text-uppercase fw-bold">Top 10 Grupos(Vlr)</h5></div>
                        <div class="card-body"><div style="height: 300px;"><canvas id="grafico_top_grupos_vlr"></canvas></div></div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header"><h5 class="card-title text-uppercase fw-bold">Top 10 Grupos(Qtd)</h5></div>
                        <div class="card-body"><div style="height: 300px;"><canvas id="grafico_top_grupos_qtd"></canvas></div></div>
                    </div>
                </div>

                 <!-- MAIORES FORNECEDORES CHART -->
                 <div class="col-md-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header"><h3 class="card-title fw-bold text-uppercase">Maiores Fornecedores</h3></div>
                        <div class="card-body">
                             <div style="height: 350px;"><canvas id="chartAtrasados"></canvas></div>
                        </div>
                    </div>
                </div>

                <!-- MAPA -->
                 <div class="col-md-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header"><h3 class="card-title fw-bold text-uppercase">Fornecedores por Região</h3></div>
                        <div class="card-body p-0">
                            <div id="map" style="height: 600px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                

            </div> <!-- End Row Charts -->
        </div> <!-- End Content Body -->
    </main>
</div>

<?php require_once '../menu/footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    function animateValue(el, start, end, duration) {
        let startTime = null;
        let prefix = el.getAttribute('data-prefix') || '';
        let decimals = parseInt(el.getAttribute('data-decimals')) || 0;

        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            // Progress from 0 to 1
            const progress = Math.min((timestamp - startTime) / duration, 1);
            
            // Ease-out cubic function for smooth deceleration
            const easeOut = 1 - Math.pow(1 - progress, 3);
            
            const value = (easeOut * (end - start) + start);
            
            el.innerText = prefix + value.toLocaleString('pt-BR', { 
                minimumFractionDigits: decimals, 
                maximumFractionDigits: decimals 
            });
            
            if (progress < 1) window.requestAnimationFrame(step);
        }
        window.requestAnimationFrame(step);
    }

    const kpiElements = document.querySelectorAll('.kpi-anime');
    kpiElements.forEach(el => {
        // Parse float from PHP output which might vary, usually safe if it's a raw number
        const endValue = parseFloat(el.getAttribute('data-value'));
        if (!isNaN(endValue)) {
            animateValue(el, 0, endValue, 1500);
        }
    });
});
</script>
    <!--end::App Wrapper-->
    <!--begin::Script-->
    <!--begin::Third Party Plugin(OverlayScrollbars)-->
         <script>
                    // Crie o gráfico de barras
                    Highcharts.chart('cidades', {
                        chart: {
                            type: 'column',
                            backgroundColor: '#2d353c', // Cor de fundo do gráfico
                        },
                        title: {
                            text: 'Top 20 Cidades',
                            color: '#ffffff' // Cor do título
                        },
                        xAxis: {
                            categories: <?php echo $dados_json ?>.categories,
                            title: {
                                text: 'Cidades'
                            },
                            labels: {
                                style: {
                                    color: '#adb5bd' // Cor dos rótulos do eixo Y
                                }
                            },
                            gridLineColor: '#444', // Cor da grade do eixo Y
                            title: {
                                style: {
                                    color: '#ffffff' // Cor do título do eixo Y
                                }
                            }
                        },
                        yAxis: {
                            min: 0,
                            title: {
                                text: 'Total de Vendas'
                            },
                            labels: {
                                style: {
                                    color: '#adb5bd' // Cor dos rótulos do eixo Y
                                }
                            },
                            gridLineColor: '#444', // Cor da grade do eixo Y
                            title: {
                                style: {
                                    color: '#ffffff' // Cor do título do eixo Y
                                }
                            }
                        },
                        plotOptions: {
                            column: {
                                colorByPoint: true,
                                dataLabels: {
                                    //color: '#adb5bd', // Cor dos rótulos de dados das colunas
                                    enabled: true, // Exibe os valores das colunas
                                    formatter: function() {
                                        return Highcharts.numberFormat(this.y, 2, ',',
                                            '.'); // Formato 1.000,99
                                    }
                                }
                            }
                        },
                        series: [{

                            name: ' ',
                            data: <?php echo $dados_json ?>.data,
                            dataLabels: {
                                enabled: true,
                                formatter: function() {
                                    return Highcharts.numberFormat(this.y, 2, ',',
                                        '.'); // Formato 1.000,99
                                }
                            }
                        }]
                    });
                    </script>



                    <?php
				// Função para remover acentos
				function removerAcentos($texto)
				{
					$acentos = array(
						'á',
						'à',
						'ã',
						'â',
						'ä',
						'é',
						'è',
						'ê',
						'ë',
						'í',
						'ì',
						'î',
						'ï',
						'ó',
						'ò',
						'õ',
						'ô',
						'ö',
						'ú',
						'ù',
						'û',
						'ü',
						'ç',
						'Á',
						'À',
						'Ã',
						'Â',
						'Ä',
						'É',
						'È',
						'Ê',
						'Ë',
						'Í',
						'Ì',
						'Î',
						'Ï',
						'Ó',
						'Ò',
						'Õ',
						'Ô',
						'Ö',
						'Ú',
						'Ù',
						'Û',
						'Ü',
						'Ç'
					);

					$semAcentos = array(
						'a',
						'a',
						'a',
						'a',
						'a',
						'e',
						'e',
						'e',
						'e',
						'i',
						'i',
						'i',
						'i',
						'o',
						'o',
						'o',
						'o',
						'o',
						'u',
						'u',
						'u',
						'u',
						'c',
						'A',
						'A',
						'A',
						'A',
						'A',
						'E',
						'E',
						'E',
						'E',
						'I',
						'I',
						'I',
						'I',
						'O',
						'O',
						'O',
						'O',
						'O',
						'U',
						'U',
						'U',
						'U',
						'C'
					);

					return str_replace($acentos, $semAcentos, $texto);
				}


					// Consulta SQL corrigida, adicionando um valor para LIMIT
					$sqltopcid = "
					SELECT f.cidade as cidade, d.latitude, d.longitude, COUNT(f.codigo) as totalforn
					from " . $banco_publico . ".cad_forn f                                         
                     LEFT JOIN cidades_geo d  ON d.nome_municipio = f.cidade AND d.uf = f.estado
					 where f.ativo = 'S'
					 GROUP BY f.cidade, d.latitude, d.longitude";

				


					$resulttopcid = $dash->query($sqltopcid);

					$cidades = array();

					while ($row = mysqli_fetch_assoc($resulttopcid)) {
						$nomeCidade = removerAcentos($row['cidade']); // Remove acentos no nome da cidade

						$cidades[] = array(
							'nome' => substr($nomeCidade, 0, 20),
							'totalforn' => intval($row['totalforn']),
							
							'coords' => array(
								floatval($row['latitude']),
								floatval($row['longitude'])
							)
						);
					}
					
				?>
                    <script>
                    // Inicializa o mapa centralizado no Brasil
                    const map = L.map('map').setView([-23.410, -50.933], 6); // Latitude, Longitude e Zoom

                    // Adiciona o mapa base (camadas visuais)
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(map);

                    // Dados das cidades gerados pelo PHP
                    const cidades = <?php echo json_encode($cidades); ?>;

                    // Adiciona os marcadores ao mapa
                    cidades.forEach(cidade => {
                        L.marker(cidade.coords)
                            .addTo(map)
                            .bindPopup(
                                `<strong>${cidade.nome}</strong><br>
								Fornecedores: ${cidade.totalforn}`

                            );
                    });
                    </script>

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
                                    $valores_atrasados[] = (float)$c['total_vendas'];
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
               const ctx = document.getElementById('chartMaioresestoque');

if (ctx) {
    const labels = <?= json_encode($labelsestoque); ?>;
    const percentuais = <?= json_encode($percentuaisestoque); ?>;
    const estoques = <?= json_encode($estoques); ?>;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: percentuais,
                estoque: estoques, // dado extra
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc',
                    '#966b00ff', '#e74c3c', '#858796'
                ],
                borderWidth: 1,
                borderColor: isDarkTheme ? '#2a3038' : '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',

            onClick: (evt, elements, chart) => {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    const produto = chart.data.labels[index];

                    // 👉 aqui você pode abrir modal ou página
                    window.open(
                        `lista_produtos.php?produto=${encodeURIComponent(produto)}`,
                        '_blank'
                    );
                }
            },

            plugins: {
                title: {
                    display: true,
                    text: 'Maiores Estoque (%)',
                    color: chartTitleColor,
                    padding: { bottom: 15 }
                },

                /* LEGENDA 2 COLUNAS */
                legend: {
                    position: 'bottom',
                    labels: {
                        color: chartTextColor,
                        boxWidth: 12,
                        padding: 12,
                        font: { size: 11 }
                    }
                },

                /* TOOLTIP */
               tooltip: {
    callbacks: {
        label: function(ctx) {
            const perc = ctx.raw.toFixed(2).replace('.', ',');
            const estoque = ctx.dataset.estoque[ctx.dataIndex].toLocaleString('pt-BR');
            return `${ctx.label}: ${estoque} un (${perc}%)`;
        }
    }
},


                /* DATA LABELS */
              datalabels: {
    color: '#ffffff',
    font: {
        weight: 'bold',
        size: 11
    },
    formatter: function(value, ctx) {
        const estoque = ctx.dataset.estoque[ctx.dataIndex];

        // formatação pt-BR
        const perc = value.toFixed(2).replace('.', ',') + '%';
        const qtd  = estoque.toLocaleString('pt-BR');

        return `${perc}\n(${qtd})`;
    },
    textAlign: 'center',
    anchor: 'center',
    align: 'center',
    clamp: true
}

            }
        },
        plugins: [ChartDataLabels]
    });
}


                        // Gráfico de Tipos de Pagamento (Donut)
                        const ctxTipoPagamen = document.getElementById('chartTipoPagamento');
                        if (ctxTipoPagamen) {
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
                                                color: chartTextColor
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
                                        label: 'Diário',
                                        data: dailyVALORRECEBER,
                                        borderColor: '#03680bff',
                                        backgroundColor: 'rgba(12, 116, 34, 0.1)',
                                        tension: 0.3,
                                        fill: true,
                                        borderWidth: 2
                                    },
                                    {
                                        label: 'Acumulado',
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
                                        text: 'Evolução Diária de Compras',
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


                        // Gráfico Comparativo Anual
                        const ctxComparativo = document.getElementById('chartComparativoAnual');
                        if (ctxComparativo) {
                            const datasetsComparativo = <?php echo json_encode($datasets_comparativo); ?>;
                            
                            new Chart(ctxComparativo.getContext('2d'), {
                                type: 'bar',
                                data: {
                                    labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                                    datasets: datasetsComparativo
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'top',
                                            labels: { color: chartTextColor }
                                        },
                                        title: {
                                            display: true,
                                            text: 'Comparativo Mensal - Últimos 5 Anos',
                                            color: chartTitleColor
                                        },
                                        tooltip: {
                                            backgroundColor: chartTooltipBg,
                                            titleColor: chartTooltipText,
                                            bodyColor: chartTooltipText,
                                            callbacks: {
                                                label: function(context) {
                                                    return context.dataset.label + ': R$ ' + context.raw.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            ticks: { color: chartTextColor },
                                            grid: { display: false }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                color: chartTextColor,
                                                callback: function(value) { return 'R$ ' + value.toLocaleString('pt-BR'); }
                                            },
                                            grid: { color: chartGridColor }
                                        }
                                    }
                                }
                            });
                        }

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



                           // --- GRÁFICO TOP 10 PRODUTOS VALOR ---

                        const ctx_topprodutos_vlr = document.getElementById('grafico_top_produtos_vlr');
                        if (ctx_topprodutos_vlr) {
                            new Chart(ctx_topprodutos_vlr, {
                                type: 'bar',
                                data: {
                                    labels: <?= json_encode($labels_top_produtos_vlr) ?>,
                                    datasets: [{
                                        label: 'Produtos',
                                        data: <?= json_encode($percentuais_top_produtos_vlr) ?>,
                                        valores: <?= json_encode($valores_top_produtos_vlr) ?>,
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
                                            const perc = ctx.raw;
                                            const val  = ctx.dataset.valores[ctx.dataIndex];
                                            return perc.toFixed(2) + '% - ' +
                                            'R$ ' + val.toLocaleString('pt-BR', {
                                                minimumFractionDigits: 2
                                            });
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
                                          formatter: (value) => {
                                            return value.toFixed(2) + '%';
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
                                                crossAlign: 'far',
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



                        // --- GRÁFICO TOP 10 PRODUTOS QTD ---

                        const ctx_topreceitas = document.getElementById('grafico_top_produtos_qtd');
                        if (ctx_topreceitas) {
                            new Chart(ctx_topreceitas, {
                                type: 'bar',
                                data: {
                                    labels: <?= json_encode($labels_top_produtos_qtd) ?>,
                                    datasets: [{
                                        label: 'Produtos',
                                        data: <?= json_encode($percentuais_top_produtos_qtd) ?>,
                                        valores: <?= json_encode($valores_top_produtos_qtd) ?>,
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
                                            const perc = ctx.raw;
                                            const val  = ctx.dataset.valores[ctx.dataIndex];
                                            return perc.toFixed(2) + '% - ' +
                                            'R$ ' + val.toLocaleString('pt-BR', {
                                                minimumFractionDigits: 2
                                            });
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
                                          formatter: (value) => {
                                            return value.toFixed(2) + '%';
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
                                                crossAlign: 'far',
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



                               // --- GRÁFICO TOP 10 GRUPOS VALOR ---

                        const ctx_topgrupos_vlr = document.getElementById('grafico_top_grupos_vlr');
                        if (ctx_topgrupos_vlr) {
                            new Chart(ctx_topgrupos_vlr, {
                                type: 'bar',
                                data: {
                                    labels: <?= json_encode($labels_top_grupos_vlr) ?>,
                                    datasets: [{
                                        label: 'Grupos',
                                        data: <?= json_encode($percentuais_top_grupos_vlr) ?>,
                                        valores: <?= json_encode($valores_top_grupos_vlr) ?>,
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
                                            const perc = ctx.raw;
                                            const val  = ctx.dataset.valores[ctx.dataIndex];
                                            return perc.toFixed(2) + '% - ' +
                                            'R$ ' + val.toLocaleString('pt-BR', {
                                                minimumFractionDigits: 2
                                            });
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
                                          formatter: (value) => {
                                            return value.toFixed(2) + '%';
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
                                                crossAlign: 'far',
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



                              // --- GRÁFICO TOP 10 GRUPOS QTD ---

                        const ctx_topgrupos_qtd = document.getElementById('grafico_top_grupos_qtd');
                        if (ctx_topgrupos_qtd) {
                            new Chart(ctx_topgrupos_qtd, {
                                type: 'bar',
                                data: {
                                    labels: <?= json_encode($labels_top_grupos_qtd) ?>,
                                    datasets: [{
                                        label: 'Grupos',
                                        data: <?= json_encode($percentuais_top_grupos_qtd) ?>,
                                        valores: <?= json_encode($valores_top_grupos_qtd) ?>,
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
                                            const perc = ctx.raw;
                                            const val  = ctx.dataset.valores[ctx.dataIndex];
                                            return perc.toFixed(2) + '% - ' +
                                            'R$ ' + val.toLocaleString('pt-BR', {
                                                minimumFractionDigits: 2
                                            });
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
                                          formatter: (value) => {
                                            return value.toFixed(2) + '%';
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
                                                crossAlign: 'far',
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

    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"
        crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous">
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous">
    </script>

    <script src="../js/adminlte.js"></script>
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
    
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" crossorigin="anonymous"></script>
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
        let valor = input.value.replace(/\D/g, '');

        if (valor.length > 8) {
            valor = valor.substring(0, 8);
        }
        if (valor.length >= 5) {
            valor = valor.replace(/(\d{2})(\d{2})(\d{0,4})/, '$1/$2/$3');
        } else if (valor.length >= 3) {
            valor = valor.replace(/(\d{2})(\d{0,2})/, '$1/$2');
        }

        input.value = valor;
    }

    document.getElementById('data_inicio').addEventListener('input', function(e) {
        aplicarMascaraData(e.target);
    });

    document.getElementById('data_fim').addEventListener('input', function(e) {
        aplicarMascaraData(e.target);
    });

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

    <script>
    <script>
    // Midnight Command: Interaction & Animation Engine
    document.addEventListener('DOMContentLoaded', () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

        const animateKpi = (el) => {
            const rawText = el.innerText.replace(/[^\d]/g, '');
            const finalValue = parseFloat(rawText) / 100;
            if (isNaN(finalValue)) return;
            const duration = 1500;
            const startTime = performance.now();
            const step = (now) => {
                const progress = Math.min((now - startTime) / duration, 1);
                const easedProgress = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
                const current = easedProgress * finalValue;
                el.innerText = current.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (progress < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        };

        document.querySelectorAll('.kpi-animate').forEach(el => {
             setTimeout(() => animateKpi(el), 800);
        });
    });
    </script>
</body>
</html>