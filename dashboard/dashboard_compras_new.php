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



$config_id = 1; // ID fixo, não precisa mais pegar de argumentos ou URL.

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



// quantide de fornecedores
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
			while ($data_inicio1 <= $data_fim1) {
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



// --- GRÁFICO DIÁRIO  ---
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
    <title>Strategic Dashboard | Compras</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <link rel="stylesheet" href="../css/dashboard_premium.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">
</head>


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
.kpi-card {
    background: linear-gradient(135deg, #1f2933, #111827);
    border-radius: 14px;
    padding: 26px;
    box-shadow: 0 8px 20px rgba(0,0,0,.35);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.kpi-card::after {
    content: '';
    position: absolute;
    top: -40%;
    right: -40%;
    width: 200px;
    height: 200px;
    background: rgba(34,197,94,0.08);
    border-radius: 50%;
}

.kpi-title {
    display: block;
    font-size: 14px;
    color: #9ca3af;
    letter-spacing: .5px;
}

.kpi-value {
    display: block;
    margin-top: 12px;
    font-size: 34px;
    font-weight: 700;
    color: #22c55e;
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

