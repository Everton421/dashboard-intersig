<?php
/**
 * SISTEMA DE FLUXO DE CAIXA COM SALDO INICIAL DINÂMICO E EXPORTAÇÃO
 */
require_once '../config/session.php'; 

date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION["user_name"])) {
    header("Location: ../index.php");
    exit();
}

$nome_usuario_logado = htmlspecialchars($_SESSION["user_name"]);
$id_usuario_logado = (int) $_SESSION["user_id"];

// 1. CONFIGURAÇÃO DE CONEXÃO
$host = '192.168.8.3';
$db   = 'mesquita_financeiro';
$user = 'intersig';
$pass = 'ewqiop2017';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}

// 2. FILTROS DE DATA
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d', strtotime('+30 days'));
$exportar = $_GET['exportar'] ?? false;
$descontar_intercompany = isset($_GET['descontar_intercompany']);

// 3. BUSCAR SALDO INICIAL (VALOR ACUMULADO ANTES DA DATA DE INÍCIO)
// Substitua o SELECT abaixo pelo seu select real caso ele seja diferente
$sql_saldo_inicial = "SELECT 
    ( (SELECT IFNULL(SUM(valor),0) FROM ct_receb WHERE vencimento < :data_ini AND (data_pgto IS NOT NULL AND data_pgto <> '0000-00-00')) 
    - (SELECT IFNULL(SUM(valor),0) FROM ct_pagar WHERE vencimento < :data_ini AND (data_pgto IS NOT NULL AND data_pgto <> '0000-00-00')) 
    ) as SALDO_INI";

// Caso você queira apenas forçar o valor que você passou (1209967.47):
//$saldo_acumulado = 1209967.47; 

/* 
// DESCOMENTE ESTE BLOCO SE QUISER QUE O SALDO VENHA DO BANCO AUTOMATICAMENTE
$stmt_ini = $pdo->prepare($sql_saldo_inicial);
$stmt_ini->execute(['data_ini' => $data_inicio]);
$res_ini = $stmt_ini->fetch();
$saldo_acumulado = (float)$res_ini['SALDO_INI']; 
*/

// BUSCAR SALDO CONCILIADO
$sql_saldo_conciliado = "SELECT sum(saldo_conciliado) as SALDO_CONCILIADO FROM ct_mvto WHERE tipo_conta <> 'T' AND ATIVO = 'S' AND tipo_conta = 'B'";
$stmt_conc = $pdo->query($sql_saldo_conciliado);
$res_conc = $stmt_conc->fetch();
$valor_saldo_conciliado = (float)$res_conc['SALDO_CONCILIADO'];

// Define o primeiro valor do saldo acumulado como sendo o saldo conciliado
$saldo_acumulado = $valor_saldo_conciliado;

// 4. PROCESSAMENTO DOS DADOS DO PERÍODO
$dados = [];

function buscarDados($pdo, $sql, $coluna, $inicio, $fim, &$dados) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['inicio' => $inicio, 'fim' => $fim]);
    while ($row = $stmt->fetch()) {
        $dt = $row['DATA'];
        if (!isset($dados[$dt])) {
            $dados[$dt] = ['receber' => 0, 'cheques_rec' => 0, 'cartoes' => 0, 'pagar' => 0, 'cheques_emit' => 0];
        }
        $dados[$dt][$coluna] = (float)$row['VALOR'];
    }
}

// Selects principais  cliente NOT IN (9605, 18376, 113232, 152874,195645)
$filtro_cliente = $descontar_intercompany ? " AND CR.cliente NOT IN (9605, 18376, 113232, 152874, 195645) " : "";

$sql_rec = "SELECT IF(CR.num_bloqueto <> '', DATE_ADD(CR.vencimento, INTERVAL tr.dias_credito DAY), CR.vencimento) AS DATA, SUM(CR.valor) AS VALOR 
            FROM ct_receb AS CR LEFT OUTER JOIN tip_rece tr ON tr.codigo = CR.tipo_recebimento
            WHERE CR.CODIGO <> 0 AND CR.VENCIMENTO BETWEEN :inicio AND :fim
           AND (ISNULL(CR.data_pgto) OR CR.data_pgto = '0000-00-00' OR CR.data_pgto = '1899-12-30') 
  AND (ISNULL(CR.DATA_DESCONTO) OR CR.DATA_DESCONTO = '0000-00-00' OR CR.DATA_DESCONTO = '1899-12-30') 
  AND (ISNULL(CR.CARTORIO) OR CR.CARTORIO = '0000-00-00' OR CR.CARTORIO = '1899-12-30') 
  AND (ISNULL(CR.CAUCAO) OR CR.CAUCAO = '0000-00-00' OR CR.CAUCAO = '1899-12-30') 
  AND (ISNULL(CR.inadimplencia) OR CR.inadimplencia = '0000-00-00' OR CR.inadimplencia = '1899-12-30') 
  AND (ISNULL(CR.agrup_destino) OR CR.agrup_destino = 0) 
  AND CR.FILIAL IN (2, 6, 7, 8, 9, 10, 12, 14, 15, 16, 17, 0) {$filtro_cliente} GROUP BY DATA";
buscarDados($pdo, $sql_rec, 'receber', $data_inicio, $data_fim, $dados);


//  fornecedor NOT IN (10500,13611,392)
$filtro_fornecedor = $descontar_intercompany ? " AND CP.fornecedor NOT IN (10500, 13611, 392) " : "";

$sql_pag = "SELECT CP.vencimento as DATA, sum(CP.valor) as VALOR from ct_pagar as CP
            WHERE CP.CODIGO <> 0 and CP.VENCIMENTO BETWEEN :inicio AND :fim
             and (isnull(CP.data_pgto) or CP.data_pgto = '0000-00-00' or CP.data_pgto = '1899-12-30') 
 and (ISNULL(CP.inadimplencia) or CP.inadimplencia = '0000-00-00' or CP.inadimplencia ='1899-12-30') 
 And (ISNULL(CP.agrup_destino) or CP.agrup_destino = 0) 
 And CP.FILIAL in(02, 06, 07, 08, 09, 10, 12, 14, 15, 16, 17, 0) {$filtro_fornecedor} group by DATA";
buscarDados($pdo, $sql_pag, 'pagar', $data_inicio, $data_fim, $dados);

$sql_ch_rec = "SELECT vencimento as DATA, sum(valor) as VALOR from cheques 
               WHERE CODIGO <> 0 And VENCIMENTO BETWEEN :inicio AND :fim and SITUACAO = 'C' group by DATA";
buscarDados($pdo, $sql_ch_rec, 'cheques_rec', $data_inicio, $data_fim, $dados);

$sql_cart = "SELECT DATA_CREDITO as DATA, sum(VALOR_LIQ) as VALOR from cartao_mvtos 
             WHERE CODIGO <> 0 And DATA_CREDITO BETWEEN :inicio AND :fim and BAIXADO = 'N' group by DATA";
buscarDados($pdo, $sql_cart, 'cartoes', $data_inicio, $data_fim, $dados);

$sql_ch_emi = "SELECT vencimento AS DATA, SUM(valor) AS VALOR FROM che_emit 
               WHERE codigo <> 0 AND vencimento BETWEEN :inicio AND :fim group by DATA";
buscarDados($pdo, $sql_ch_emi, 'cheques_emit', $data_inicio, $data_fim, $dados);

ksort($dados);

// LOGICA DOS TOTALIZADORES GERAIS
$soma_total_credito = 0;
$soma_total_debito = 0;
foreach ($dados as $v) {
    $soma_total_credito += ($v['receber'] + $v['cheques_rec'] + $v['cartoes']);
    $soma_total_debito += ($v['pagar'] + $v['cheques_emit']);
}
$diferenca_credito_debito = $soma_total_credito - $soma_total_debito;

// LOGICA DE EXPORTAÇÃO EXCEL
if ($exportar == 'excel') {
    $filename = "Fluxo_Caixa_" . date('d-m-Y') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
}
?>
<?php if ($exportar == 'excel'): ?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Fluxo de Caixa</title>
</head>

<body>
    <table border="1">
        <thead>
            <tr>
                <th bgcolor="#dcdcdc">Vencimento</th>
                <th bgcolor="#dcdcdc">Receber</th>
                <th bgcolor="#dcdcdc">Cheques</th>
                <th bgcolor="#dcdcdc">Cartões rec.</th>
                <th bgcolor="#dcdcdc">Total Crédito</th>
                <th bgcolor="#dcdcdc">Pagar</th>
                <th bgcolor="#dcdcdc">Cheques Emit.</th>
                <th bgcolor="#dcdcdc">Total Débito</th>
                <th bgcolor="#dcdcdc">Saldo Dia</th>
                <th bgcolor="#dcdcdc">Saldo Acum.</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $saldo_acum_exp = $valor_saldo_conciliado;
            foreach ($dados as $data => $v): 
                $tot_credito = $v['receber'] + $v['cheques_rec'] + $v['cartoes'];
                $tot_debito = $v['pagar'] + $v['cheques_emit'];
                $saldo_dia = $tot_credito - $tot_debito;
                $saldo_acum_exp += $saldo_dia;
            ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($data)) ?></td>
                <td><?= number_format($v['receber'], 2, ',', '.') ?></td>
                <td><?= number_format($v['cheques_rec'], 2, ',', '.') ?></td>
                <td><?= number_format($v['cartoes'], 2, ',', '.') ?></td>
                <td><?= number_format($tot_credito, 2, ',', '.') ?></td>
                <td><?= number_format($v['pagar'], 2, ',', '.') ?></td>
                <td><?= number_format($v['cheques_emit'], 2, ',', '.') ?></td>
                <td><?= number_format($tot_debito, 2, ',', '.') ?></td>
                <td><?= number_format($saldo_dia, 2, ',', '.') ?></td>
                <td><?= number_format($saldo_acum_exp, 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>

</html>
<?php exit; endif; ?>

<!doctype html>
<html lang="pt-br">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Fluxo de Caixa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <meta name="supported-color-schemes" content="light dark" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.1/index.css" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../css/adminlte.css" />
    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=JetBrains+Mono:wght@400;700&display=swap');

    :root {
        --accent-green: #00ff88;
        --accent-red: #ff3e3e;
        --accent-blue: #0ea5e9;
        --font-main: 'Inter', sans-serif;
        --font-mono: 'JetBrains Mono', monospace;
    }

    [data-bs-theme="dark"],
    .dark-mode {
        --bg-main: #1a1d20;
        --bg-card: #212529;
        --bg-hover: #2c3035;
        --text-main: #e2e8f0;
        --text-dim: #94a3b8;
        --border-color: #343a40;
    }

    [data-bs-theme="light"] {
        --bg-main: #f4f6f9;
        --bg-card: #ffffff;
        --bg-hover: #f8f9fa;
        --text-main: #212529;
        --text-dim: #6c757d;
        --border-color: #dee2e6;
    }

    body {
        background-color: var(--bg-main) !important;
        color: var(--text-main) !important;
        font-family: var(--font-main) !important;
        margin: 0;
        padding: 0 !important;
    }

    .app-content {
        background-color: transparent !important;
        padding: 40px !important;
        margin-left: 0 !important;
        min-height: 100vh;
    }

    .filter-panel {
        background: var(--bg-card);
        padding: 24px;
        border: 1px solid var(--border-color);
        margin-bottom: 30px;
        position: relative;
    }

    .filter-panel::before {
        content: 'FILTROS';
        position: absolute;
        top: -10px;
        left: 20px;
        background: var(--bg-main);
        padding: 0 10px;
        font-family: var(--font-mono);
        font-size: 10px;
        color: var(--accent-green);
        letter-spacing: 0.2em;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        align-items: end;
    }

    .filter-group label {
        display: block;
        font-size: 0.75rem;
        color: var(--text-dim);
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .form-control-custom {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        color: var(--text-main);
        padding: 10px 12px;
        width: 100%;
        font-size: 0.9rem;
        border-radius: 4px;
        transition: all 0.15s ease;
    }

    .form-control-custom:focus {
        border-color: var(--accent-green);
        outline: none;
        background: var(--bg-hover);
        box-shadow: 0 0 8px rgba(0, 255, 136, 0.08);
    }

    .btn-update {
        background: var(--accent-blue);
        color: #fff;
        border: none;
        padding: 10px 24px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
        height: 42px;
        border-radius: 4px;
        display: inline-flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
    }

    .btn-update:hover {
        background: #0288d1;
        transform: translateY(-1px);
    }

    .btn-excel {
        background: #1f7244;
        color: #fff;
        border: none;
        padding: 10px 24px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
        height: 42px;
        border-radius: 4px;
        display: inline-flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
    }

    .btn-excel:hover {
        background: #144f2f;
        transform: translateY(-1px);
    }

    .btn-print {
        background: #7f8c8d;
        color: #fff;
        border: none;
        padding: 10px 24px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
        height: 42px;
        border-radius: 4px;
        display: inline-flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-print:hover {
        background: #606b6c;
        transform: translateY(-1px);
        color: #fff;
    }

    .kpi-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .kpi-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        padding: 24px;
        border-right: 3px solid var(--accent-blue);
        transition: transform 0.2s ease;
    }

    .kpi-card:hover {
        transform: translateY(-2px);
        background: var(--bg-hover);
    }

    .kpi-card.positive {
        border-right-color: var(--accent-green);
    }

    .kpi-card.negative {
        border-right-color: var(--accent-red);
    }

    .kpi-label {
        color: var(--text-dim);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 12px;
    }

    .kpi-value {
        font-family: var(--font-mono);
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-main);
    }

    .kpi-value.green {
        color: var(--accent-green);
    }

    .kpi-value.red {
        color: var(--accent-red);
    }

    .table-container {
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        margin-bottom: 20px;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }

    th {
        background: var(--bg-card);
        color: var(--text-dim);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 16px 20px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    td {
        padding: 14px 20px;
        border-bottom: 1px solid rgba(42, 42, 44, 0.5);
        color: var(--text-main);
    }

    tr:hover td {
        background: var(--bg-hover);
    }

    .mono {
        font-family: var(--font-mono);
        font-weight: 500;
    }

    .text-positive {
        color: var(--accent-green) !important;
    }

    .text-negative {
        color: var(--accent-red) !important;
    }

    @media print {

        .filter-panel,
        header,
        aside,
        footer,
        .app-content-header {
            display: none !important;
        }

        body {
            background: white !important;
            color: black !important;
            padding: 0 !important;
        }

        .app-content {
            padding: 0 !important;
        }

        .kpi-card {
            background: #f9f9f9 !important;
            color: black !important;
            border: 1px solid #ddd;
        }

        .kpi-value {
            color: black !important;
        }

        th,
        td {
            color: black !important;
            border-bottom: 1px solid #ddd !important;
        }

        .table-container {
            border: none;
            overflow: visible;
        }
    }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open" data-bs-theme="light">
    <div class="app-wrapper">
        <?php require_once '../menu/nav_dash.php'; ?>
        <?php require_once '../menu/sidebar.php'; ?>
        <?php require_once '../menu/menu_dash.php'; ?>

        </aside>

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6">
                            <h3 class="mb-0">Fluxo de Caixa</h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Fluxo de Caixa</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">

                    <nav class="filter-panel">
                        <form method="GET">
                            <div class="filter-grid">
                                <div class="filter-group">
                                    <label>Data Início</label>
                                    <input type="date" name="data_inicio" value="<?=$data_inicio?>"
                                        class="form-control-custom">
                                </div>
                                <div class="filter-group">
                                    <label>Data Fim</label>
                                    <input type="date" name="data_fim" value="<?=$data_fim?>"
                                        class="form-control-custom">
                                </div>
                                <div class="filter-group" style="display:flex; align-items:center; gap: 8px;">
                                    <input type="checkbox" name="descontar_intercompany" id="desc_inter"
                                        <?= $descontar_intercompany ? 'checked' : '' ?>
                                        style="width:16px; height:16px; cursor:pointer;" value="1">
                                    <label for="desc_inter"
                                        style="margin:0; cursor:pointer; font-size:12px; font-weight:bold;">Descontar
                                        Clientes/Fornecedores</label>
                                </div>
                                <button type="submit" class="btn-update">
                                    <i class="fa-solid fa-magnifying-glass"></i> Filtrar
                                </button>
                                <button type="submit" name="exportar" value="excel" class="btn-excel">
                                    <i class="fa-solid fa-file-excel"></i> Exportar
                                </button>
                                <a href="javascript:window.print()" class="btn-print">
                                    <i class="fa-solid fa-print"></i> Imprimir
                                </a>
                            </div>
                        </form>
                    </nav>

                    <div class="kpi-row">
                        <div class="kpi-card positive">
                            <div class="kpi-label">Total Crédito</div>
                            <div class="kpi-value green">R$ <?= number_format($soma_total_credito, 2, ',', '.') ?></div>
                        </div>
                        <div class="kpi-card negative">
                            <div class="kpi-label">Total Débito</div>
                            <div class="kpi-value red">R$ <?= number_format($soma_total_debito, 2, ',', '.') ?></div>
                        </div>
                        <div class="kpi-card <?= $diferenca_credito_debito < 0 ? 'negative' : 'positive' ?>">
                            <div class="kpi-label">Diferença</div>
                            <div class="kpi-value <?= $diferenca_credito_debito < 0 ? 'red' : 'green' ?>">R$
                                <?= number_format($diferenca_credito_debito, 2, ',', '.') ?></div>
                        </div>
                        <div class="kpi-card positive">
                            <div class="kpi-label">Saldo Conciliado</div>
                            <div class="kpi-value green">R$ <?= number_format($valor_saldo_conciliado, 2, ',', '.') ?>
                            </div>
                        </div>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Vencimento</th>
                                    <th style="text-align: right;">Receber</th>
                                    <th style="text-align: right;">Cheques</th>
                                    <th style="text-align: right;">Cartões rec.</th>
                                    <th style="text-align: right; color: var(--accent-green);">Total Crédito</th>
                                    <th style="text-align: right;">Pagar</th>
                                    <th style="text-align: right;">Cheques Emit.</th>
                                    <th style="text-align: right; color: var(--accent-red);">Total Débito</th>
                                    <th style="text-align: right;">Saldo Dia</th>
                                    <th style="text-align: right;">Saldo Acum.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $is_first = true;
                                foreach ($dados as $data => $v): 
                                    $tot_credito = $v['receber'] + $v['cheques_rec'] + $v['cartoes'];
                                    $tot_debito = $v['pagar'] + $v['cheques_emit'];
                                    $saldo_dia = $tot_credito - $tot_debito;
                                    
                                    // O saldo acumulado soma o saldo do dia atual ao valor que já existia
                                    $saldo_acumulado += $saldo_dia;
                                ?>
                                <tr>
                                    <td class="mono">
                                        <?= date('d/m/Y', strtotime($data)) ?>
                                    </td>
                                    <td class="mono" style="text-align: right;">
                                        <?= number_format($v['receber'], 2, ',', '.') ?></td>
                                    <td class="mono" style="text-align: right;">
                                        <?= number_format($v['cheques_rec'], 2, ',', '.') ?></td>
                                    <td class="mono" style="text-align: right;">
                                        <?= number_format($v['cartoes'], 2, ',', '.') ?></td>
                                    <td class="mono text-positive" style="text-align: right; font-weight: bold;">
                                        <?= number_format($tot_credito, 2, ',', '.') ?></td>

                                    <td class="mono" style="text-align: right;">
                                        <?= number_format($v['pagar'], 2, ',', '.') ?></td>
                                    <td class="mono" style="text-align: right;">
                                        <?= number_format($v['cheques_emit'], 2, ',', '.') ?></td>
                                    <td class="mono text-negative" style="text-align: right; font-weight: bold;">
                                        <?= number_format($tot_debito, 2, ',', '.') ?></td>

                                    <td class="mono <?= $saldo_dia < 0 ? 'text-negative' : 'text-positive' ?>"
                                        style="text-align: right; font-weight: bold;">
                                        <?= number_format($saldo_dia, 2, ',', '.') ?></td>
                                    <td class="mono <?= $saldo_acumulado < 0 ? 'text-negative' : 'text-positive' ?>"
                                        style="text-align: right; font-weight: bold;">
                                        <?= number_format($saldo_acumulado, 2, ',', '.') ?></td>
                                </tr>
                                <?php 
                                    $is_first = false; 
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </main>

        <?php require_once '../menu/footer.php'; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
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
        if (sidebarWrapper && typeof OverlayScrollbarsGlobal !== 'undefined') {
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
</body>

</html>