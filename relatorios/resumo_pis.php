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




// --- FUNÇÃO AUXILIAR PARA PREPARED STATEMENTS COM MYSQLI ---
function executar_consulta_preparada($conexao, $sql, $params = []) {
    $stmt = mysqli_prepare($conexao, $sql);
    if (!$stmt) {
        die("Erro na preparação da consulta: " . mysqli_error($conexao) . " SQL: " . $sql);
    }
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (!mysqli_stmt_execute($stmt)) {
        die("Erro na execução da consulta: " . mysqli_stmt_error($stmt));
    }
    return mysqli_stmt_get_result($stmt);
}

// Função auxiliar para converter datas
function convert_date_br_to_sql($date_input) {
    if (empty($date_input)) return '';
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date_input, $matches)) {
        return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
    }
    return $date_input;
}

// --- AJUSTES PRINCIPAIS: JOIN E CONDIÇÕES FIXAS ---
$join_series = " LEFT JOIN {$banco_vendas}.series s ON (s.codigo = n.serie AND s.filial = n.filial) ";
$condicoes_fixas = " s.consid_sped = 'S' AND n.cancelada = 'N' ";

// --- FILTROS DINÂMICOS ---
$filial = $_GET['filial'] ?? '';
$chave_mvto = $_GET['nf'] ?? '';
$data_inicio_raw = $_GET['data_inicio'] ?? '';
$data_fim_raw = $_GET['data_fim'] ?? '';
$uf = isset($_GET['uf']) ? $_GET['uf'] : '';

if (empty($data_inicio_raw) && empty($data_fim_raw) && empty($chave_mvto)) {
    $hoje = new DateTime();
    $data_inicio_sql_padrao = $hoje->format('Y-m-01');
    $data_fim_sql_padrao = $hoje->format('Y-m-t');
    $data_inicio_raw = (new DateTime($data_inicio_sql_padrao))->format('d/m/Y');
    $data_fim_raw = (new DateTime($data_fim_sql_padrao))->format('d/m/Y');
}

$data_inicio = convert_date_br_to_sql($data_inicio_raw);
$data_fim = convert_date_br_to_sql($data_fim_raw);

// --- CONSTRUÇÃO DA CLÁUSULA WHERE E PARÂMETROS ---
$filtros_dinamicos = []; 
$params = []; 

if ($filial) {
    $filtros_dinamicos[] = "n.FILIAL = ?";
    $params[] = $filial;
}
if ($chave_mvto) {
    $filtros_dinamicos[] = "n.CODIGO = ?";
    $params[] = $chave_mvto;
}
if ($data_inicio) {
    $filtros_dinamicos[] = "n.DATA_SAIDA_ENTRADA >= ?";
    $params[] = $data_inicio;
}
if ($data_fim) {
    $filtros_dinamicos[] = "n.DATA_SAIDA_ENTRADA <= ?";
    $params[] = $data_fim;
}

$where = "WHERE " . $condicoes_fixas;
if (!empty($filtros_dinamicos)) {
    $where .= " AND " . implode(' AND ', $filtros_dinamicos);
}

// Obter lista de filiais

$result_filial = mysqli_query($dash, "SELECT filial, nome_fantasia FROM {$banco_vendas}.empresas WHERE ativa = 'S' ORDER BY
    filial ASC"); $filiais=[]; while ($row=mysqli_fetch_assoc($result_filial)) { $filiais[]=[ 'codigo'=> $row['filial'],
    'nome' => $row['nome_fantasia']
    ];
    }
// ---

 

$sql_apuracao_ipi = "SELECT 
    p.cfop,
    p.cst_pis,
    SUM(t.base_pis_cofins) as total_base,
    SUM(t.valor_pis) as total_pis
FROM {$banco_vendas}.cad_nf n
INNER JOIN {$banco_vendas}.mvto_produtos p ON p.CHAVE_MVTO = n.CODIGO
LEFT JOIN {$banco_vendas}.mvto_tributos t ON t.CHAVE_MVTO = n.CODIGO AND t.item = p.item
{$join_series} 
 LEFT JOIN {$banco_publico}.cad_clie c ON (n.codigo_cli_for = c.codigo AND n.cli_for = 'C')
    LEFT JOIN {$banco_publico}.cad_forn f ON (n.codigo_cli_for = f.codigo AND n.cli_for = 'F')
{$where}
   and t.valor_pis > 0
    and cst_pis is not null
    and cst_pis <> ' '";

if (!empty($uf)) {$sql_apuracao_ipi .= " AND (
        (n.cli_for = 'C' AND c.estado = '$uf')
        OR (n.cli_for = 'F' AND f.estado = '$uf')
    )";}
  
$sql_apuracao_ipi .= "    
GROUP BY p.cst_pis,p.cfop
ORDER BY  p.cfop, p.cst_pis"
;
$dados_apuracao_ipi = mysqli_fetch_all(
    executar_consulta_preparada($dash, $sql_apuracao_ipi, $params),
    MYSQLI_ASSOC
);



$sql_apuracao_ipi_cfop = "SELECT 
    p.cfop,
   SUM(t.base_pis_cofins) as total_base,
    SUM(t.valor_pis) as total_pis
FROM {$banco_vendas}.cad_nf n
INNER JOIN {$banco_vendas}.mvto_produtos p ON p.CHAVE_MVTO = n.CODIGO
LEFT JOIN {$banco_vendas}.mvto_tributos t ON t.CHAVE_MVTO = n.CODIGO AND t.item = p.item
{$join_series}
 LEFT JOIN {$banco_publico}.cad_clie c ON (n.codigo_cli_for = c.codigo AND n.cli_for = 'C')
    LEFT JOIN {$banco_publico}.cad_forn f ON (n.codigo_cli_for = f.codigo AND n.cli_for = 'F')
 {$where}
   and t.valor_pis > 0
    and cst_pis is not null
    and cst_pis <> ' '";

if (!empty($uf)) {$sql_apuracao_ipi_cfop .= " AND (
        (n.cli_for = 'C' AND c.estado = '$uf')
        OR (n.cli_for = 'F' AND f.estado = '$uf')
    )";}
  
$sql_apuracao_ipi_cfop .= "    
GROUP BY p.cfop
ORDER BY  p.cfop"
;
$dados_apuracao_ipi_cfop = mysqli_fetch_all(
    executar_consulta_preparada($dash, $sql_apuracao_ipi_cfop, $params),
    MYSQLI_ASSOC
);

    

?>

<!doctype html>
<html lang="pt-br">
<!--begin::Head-->

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Apuração Pis</title>
    <!--begin::Accessibility Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <meta name="supported-color-schemes" content="light dark" />
    <link rel="preload" href="../css/adminlte.css" as="style" />
    <!--end::Accessibility Features-->
    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.1/index.css"
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

    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">

    <style>
    h1 {
        text-align: center;
        color: #2c3e50;
        margin-bottom: 25px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #555;
    }

    input[type=text] {
        width: 100%;
        padding: 8px;
        margin-bottom: 0;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 12px;
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
        border: 1px solid #444;
        border-radius: 8px;
        background-color: #1e1e1e;
        color: #fff;
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

    .card-header {

        margin-bottom: 15px;
        font-size: 14px;
        border-bottom: 2px solid #3498db;
        padding-bottom: 8px;
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
    </div>

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
                        <h3 class="mb-0">Apuração PIS</h3>
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
                                                    <?php if ($chave_mvto || $data_inicio_raw || $data_fim_raw || $filial || $numero_nota || $operacao || $cidade || $nome || $serie): ?>
                                                    <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>"
                                                        class="btn btn-outline-secondary btn-sm-custom flex-grow-1">Limpar</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Botões de Filtro Rápido -->
                                            <div class="row mt-3">
                                                <div class="col-md-12">
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <span class="text-muted small align-self-center me-2">Período
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






                </div>


                <?php if (!$chave_mvto): ?>

                <?php
// ==========================================================
// CORREÇÃO: Inicialize as variáveis de totais ANTES do IF.
// Assim, elas sempre existirão.
// ==========================================================
$total_base_ipi = 0;
$total_valor_ipi = 0;
$totais_mov = [
    'entrada'    => ['base' => 0, 'valor' => 0],
    'saida'      => ['base' => 0, 'valor' => 0],
    'importacao' => ['base' => 0, 'valor' => 0],
    'exportacao' => ['base' => 0, 'valor' => 0],
];
?>



            </div>

            <!-- Apuração de IPI -->
            <?php if (!empty($dados_apuracao_ipi)): ?>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <strong>Apuração de Pis (Agrupado Por CFOP E CST)</strong>
                            <button class="btn btn-success btn-sm"
                                onclick="exportToExcel('tabelaIpiApuracao', 'ApuracaoIPI.xlsx')">
                                <i class="bi bi-file-earmark-excel"></i> Exportar para Excel
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaIpiApuracao"
                                    class="table table-striped table-bordered table-sm align-middle text-center">
                                    <thead>
                                        <tr>
                                            <th>CFOP</th>
                                            <th>CST Pis</th>
                                            <th>Base de Cálculo (R$)</th>
                                            <th>Total Pis (R$)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
              // Inicializa totais gerais e por tipo
              $total_base_ipi = 0;
              $total_valor_ipi = 0;

              $totais_mov = [
                  'entrada' => ['base' => 0, 'valor' => 0],
                  'saida' => ['base' => 0, 'valor' => 0],
                  'importacao' => ['base' => 0, 'valor' => 0],
                  'exportacao' => ['base' => 0, 'valor' => 0],
              ];

              foreach ($dados_apuracao_ipi as $row):
                  $cfop = trim($row['cfop']);
                  $prefixo = substr($cfop, 0, 1); // primeira casa do CFOP

                  $total_base_ipi  += $row['total_base'];
                  $total_valor_ipi += $row['total_pis'];

                  // Classifica o CFOP por tipo de movimento
                  if (in_array($prefixo, ['1','2'])) {
                      $tipo = 'entrada';
                  } elseif (in_array($prefixo, ['5','6'])) {
                      $tipo = 'saida';
                  } elseif ($prefixo == '3') {
                      $tipo = 'importacao';
                  } elseif ($prefixo == '7') {
                      $tipo = 'exportacao';
                  } else {
                      $tipo = null;
                  }

                  // Soma nos grupos correspondentes
                  if ($tipo) {
                      $totais_mov[$tipo]['base']  += $row['total_base'];
                      $totais_mov[$tipo]['valor'] += $row['total_pis'];
                  }
              ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['cfop']) ?></td>
                                            <td><?= htmlspecialchars($row['cst_pis']) ?></td>
                                            <td><?= number_format($row['total_base'], 2, ",", ".") ?></td>
                                            <td><?= number_format($row['total_pis'], 2, ",", ".") ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>


                                        <td colspan="2">TOTAL GERAL</td>
                                        <td>R$ <?= number_format($total_base_ipi, 2, ",", ".") ?>
                                        </td>
                                        <td>R$ <?= number_format($total_valor_ipi, 2, ",", ".") ?>
                                        </td>

                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>


            <!-- =================================================================== -->
            <!-- INÍCIO DO NOVO BLOCO -  IPI por CFOP -->
            <!-- =================================================================== -->


            <!-- Apuração de IPI -->
            <?php if (!empty($dados_apuracao_ipi_cfop)): ?>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <strong>Apuração de Pis (Agrupado Por CFOP)</strong>
                            <button class="btn btn-success btn-sm"
                                onclick="exportToExcel('tabelaIpiApuracaocfop', 'ApuracaoPIScfop.xlsx')">
                                <i class="bi bi-file-earmark-excel"></i> Exportar para Excel
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaIpiApuracaocfop"
                                    class="table table-striped table-bordered table-sm align-middle text-center">
                                    <thead>
                                        <tr>
                                            <th>CFOP</th>
                                            <th>Base de Cálculo (R$)</th>
                                            <th>Total Pis (R$)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
              // Inicializa totais gerais e por tipo
              $total_base_ipi = 0;
              $total_valor_ipi = 0;

              $totais_mov = [
                  'entrada' => ['base' => 0, 'valor' => 0],
                  'saida' => ['base' => 0, 'valor' => 0],
                  'importacao' => ['base' => 0, 'valor' => 0],
                  'exportacao' => ['base' => 0, 'valor' => 0],
              ];

              foreach ($dados_apuracao_ipi_cfop as $row):
                  $cfop = trim($row['cfop']);
                  $prefixo = substr($cfop, 0, 1); // primeira casa do CFOP

                  $total_base_ipi  += $row['total_base'];
                  $total_valor_ipi += $row['total_pis'];

                  // Classifica o CFOP por tipo de movimento
                  if (in_array($prefixo, ['1','2'])) {
                      $tipo = 'entrada';
                  } elseif (in_array($prefixo, ['5','6'])) {
                      $tipo = 'saida';
                  } elseif ($prefixo == '3') {
                      $tipo = 'importacao';
                  } elseif ($prefixo == '7') {
                      $tipo = 'exportacao';
                  } else {
                      $tipo = null;
                  }

                  // Soma nos grupos correspondentes
                  if ($tipo) {
                      $totais_mov[$tipo]['base']  += $row['total_base'];
                      $totais_mov[$tipo]['valor'] += $row['total_pis'];
                  }
              ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['cfop']) ?></td>
                                            <td><?= number_format($row['total_base'], 2, ",", ".") ?></td>
                                            <td><?= number_format($row['total_pis'], 2, ",", ".") ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>


                                        <td>TOTAL GERAL</td>
                                        <td>R$ <?= number_format($total_base_ipi, 2, ",", ".") ?>
                                        </td>
                                        <td>R$ <?= number_format($total_valor_ipi, 2, ",", ".") ?>
                                        </td>

                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>


            <!-- =================================================================== -->
            <!-- INÍCIO DO NOVO BLOCO - Resumo de IPI por Tipo de Movimentação -->
            <!-- =================================================================== -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <strong>Resumo do Pis por Tipo de Movimentação</strong>
                            <!-- Botão para exportar esta tabela de resumo -->
                            <button class="btn btn-success btn-sm"
                                onclick="exportToExcel('tabelaIpiResumo', 'Resumo_PIS_Movimentacao.xlsx')">
                                <i class="bi bi-file-earmark-excel"></i> Exportar para Excel
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaIpiResumo" class="table table-striped table-bordered table-sm">
                                    <thead>
                                        <tr>
                                            <th>Tipo de Movimentação</th>
                                            <th>Base de Cálculo Pis (R$)</th>
                                            <th>Total Pis (R$)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                            // Array para nomes mais amigáveis
                            $labels_mov = [
                                'entrada'    => 'Entradas (CFOP 1, 2)',
                                'saida'      => 'Saídas (CFOP 5, 6)',
                                'importacao' => 'Importações (CFOP 3)',
                                'exportacao' => 'Exportações (CFOP 7)',
                            ];

                            foreach ($totais_mov as $tipo => $valores):
                                // Só exibe a linha se houver valor de IPI para aquele tipo
                                if ($valores['valor'] >= 0): 
                            ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($labels_mov[$tipo]) ?></strong>
                                            </td>
                                            <td>R$ <?= number_format($valores['base'], 2, ",", ".") ?></td>
                                            <td>R$ <?= number_format($valores['valor'], 2, ",", ".") ?></td>
                                        </tr>
                                        <?php 
                                endif;
                            endforeach; 
                            ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- =================================================================== -->
            <!--  Comparativo de pis (Entrada vs Saída) -->
            <!-- =================================================================== -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <strong>Comparativo de PIS - Entrada vs Saída</strong>
                            <button class="btn btn-success btn-sm"
                                onclick="exportToExcel('tabelaComparativoIpi', 'Comparativo_PIS.xlsx')">
                                <i class="bi bi-file-earmark-excel"></i> Exportar para Excel
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaComparativoIpi" class="table table-striped table-bordered table-sm">
                                    <thead>
                                        <tr>
                                            <th>Descrição</th>
                                            <th>Base de Cálculo (R$)</th>
                                            <th>Valor do PIS (R$)</th>
                                            <th>Saldo (Pagar/Receber)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                            // Calcular totais para entrada e saída
                            $entrada_base = 0;
                            $entrada_valor = 0;
                            $saida_base = 0;
                            $saida_valor = 0;
                            
                            foreach ($dados_apuracao_ipi_cfop as $row) {
                                $cfop = trim($row['cfop']);
                                $prefixo = substr($cfop, 0, 1);
                                
                                if (in_array($prefixo, ['1','2','3'])) {
                                    // Entradas (CFOP 1,2,3) - IPI a recuperar
                                    $entrada_base += $row['total_base'];
                                    $entrada_valor += $row['total_pis'];
                                } elseif (in_array($prefixo, ['5','6','7'])) {
                                    // Saídas (CFOP 5,6,7) - IPI a pagar
                                    $saida_base += $row['total_base'];
                                    $saida_valor += $row['total_pis'];
                                }
                            }
                            
                            // Calcular saldo (Saída - Entrada)
                            $saldo_valor = $saida_valor - $entrada_valor;
                            $saldo_base = $saida_base - $entrada_base;
                            
                            // Determinar se deve pagar ou receber
                            $status_saldo = $saldo_valor >= 0 ? 'PAGAR' : 'RECEBER';
                            $cor_saldo = $saldo_valor >= 0 ? 'text-danger' : 'text-success';
                            ?>

                                        <tr>
                                            <td><strong>ENTRADA (CFOP 1,2,3) - PIS a Recuperar</strong></td>
                                            <td>R$ <?= number_format($entrada_base, 2, ",", ".") ?></td>
                                            <td>R$ <?= number_format($entrada_valor, 2, ",", ".") ?></td>
                                            <td>-</td>
                                        </tr>

                                        <tr>
                                            <td><strong>SAÍDA (CFOP 5,6,7) - PIS a Pagar</strong></td>
                                            <td>R$ <?= number_format($saida_base, 2, ",", ".") ?></td>
                                            <td>R$ <?= number_format($saida_valor, 2, ",", ".") ?></td>
                                            <td>-</td>
                                        </tr>

                                        <tr class="table-active">
                                            <td><strong>SALDO DO PIS</strong></td>
                                            <td>R$ <?= number_format($saldo_base, 2, ",", ".") ?></td>
                                            <td class="<?= $cor_saldo ?>">
                                                <strong>R$
                                                    <?= number_format(abs($saldo_valor), 2, ",", ".") ?></strong>
                                            </td>
                                            <td class="<?= $cor_saldo ?>">
                                                <strong><?= $status_saldo ?></strong>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Card com resumo simplificado -->
                            <div class="row mt-4">
                                <div class="col-md-4">
                                    <div class="card bg-dark text-white">
                                        <div class="card-body text-center">
                                            <h5 class="card-title">PIS a Recuperar</h5>
                                            <h3 class="text-primary">R$
                                                <?= number_format($entrada_valor, 2, ",", ".") ?></h3>
                                            <p class="text-muted">Entradas (CFOP 1,2,3)</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-dark text-white">
                                        <div class="card-body text-center">
                                            <h5 class="card-title">PIS a Pagar</h5>
                                            <h3 class="text-warning">R$
                                                <?= number_format($saida_valor, 2, ",", ".") ?></h3>
                                            <p class="text-muted">Saídas (CFOP 5,6,7)</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card <?= $saldo_valor >= 0 ? 'bg-danger' : 'bg-success' ?> text-white">
                                        <div class="card-body text-center">
                                            <h5 class="card-title">Saldo</h5>
                                            <h3>R$ <?= number_format(abs($saldo_valor), 2, ",", ".") ?></h3>
                                            <p class="mb-0">
                                                <strong><?= $status_saldo ?> DE PIS</strong>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>






            <?php endif; ?>




        </div>
        <script>
        // Funções para filtros rápidos
        function formatDate(date) {
            const dia = String(date.getDate()).padStart(2, '0');
            const mes = String(date.getMonth() + 1).padStart(2, '0');
            const ano = date.getFullYear();
            return `${dia}/${mes}/${ano}`;
        }

        function setPeriodo(tipo) {
            const hoje = new Date();
            const anoAtual = hoje
                .getFullYear(); // Definir anoAtual aqui para usar em todos os casos
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
                    dataInicio = formatDate(new Date(anoAtual, hoje.getMonth(), 1));
                    dataFim = formatDate(new Date(anoAtual, hoje.getMonth() + 1, 0));
                    break;

                case 'trimestre':
                    const trimestre = Math.floor(hoje.getMonth() / 3);
                    dataInicio = formatDate(new Date(anoAtual, trimestre * 3, 1));
                    dataFim = formatDate(new Date(anoAtual, trimestre * 3 + 3, 0));
                    break;

                case 'primeiro_trimestre':
                    dataInicio = formatDate(new Date(anoAtual, 0, 1)); // 1º de janeiro
                    dataFim = formatDate(new Date(anoAtual, 2, 31)); // 31 de março
                    break;

                case 'segundo_trimestre':
                    dataInicio = formatDate(new Date(anoAtual, 3, 1)); // 1º de abril
                    dataFim = formatDate(new Date(anoAtual, 5, 30)); // 30 de junho
                    break;

                case 'terceiro_trimestre':
                    dataInicio = formatDate(new Date(anoAtual, 6, 1)); // 1º de julho
                    dataFim = formatDate(new Date(anoAtual, 8, 30)); // 30 de setembro
                    break;

                case 'quarto_trimestre':
                    dataInicio = formatDate(new Date(anoAtual, 9, 1)); // 1º de outubro
                    dataFim = formatDate(new Date(anoAtual, 11, 31)); // 31 de dezembro
                    break;

                case 'primeiro_semestre':
                    dataInicio = formatDate(new Date(anoAtual, 0, 1)); // 1º de janeiro
                    dataFim = formatDate(new Date(anoAtual, 5, 30)); // 30 de junho
                    break;

                case 'segundo_semestre':
                    dataInicio = formatDate(new Date(anoAtual, 6, 1)); // 1º de julho
                    dataFim = formatDate(new Date(anoAtual, 11, 31)); // 31 de dezembro
                    break;

                case 'ano':
                    dataInicio = formatDate(new Date(anoAtual, 0, 1));
                    dataFim = formatDate(new Date(anoAtual, 11, 31));
                    break;

                default:
                    console.log('Tipo de período não reconhecido:', tipo);
                    return;
            }

            document.getElementById('data_inicio').value = dataInicio;
            document.getElementById('data_fim').value = dataFim;
            document.getElementById('form-filtros').submit();
        }



        function mascaraData(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length > 2) value = value.substring(0, 2) + '/' + value.substring(2);
            if (value.length > 5) value = value.substring(0, 5) + '/' + value.substring(5, 9);
            input.value = value;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const inputInicio = document.getElementById('data_inicio');
            const inputFim = document.getElementById('data_fim');
            if (inputInicio) inputInicio.addEventListener('input', function() {
                mascaraData(this);
            });
            if (inputFim) inputFim.addEventListener('input', function() {
                mascaraData(this);
            });
        });

        /**
                           * Função para exportar uma tabela HTML para um arquivo Excel(.xlsx) *
                               @param {
                                   string
                               }
                           tableId O ID da tag < table > a ser exportada.*@param {
                               string
                           }
                           filename O nome do arquivo a ser gerado.*/

        function exportToExcel(tableId, filename) {
            // Seleciona a tabela pelo ID fornecido
            const table = document.getElementById(tableId);

            // Converte o elemento da tabela HTML em um 'workbook' (pasta de trabalho) do SheetJS
            const wb = XLSX.utils.table_to_book(table, {
                sheet: "Dados"
            });

            // Gera o arquivo .xlsx e dispara o download no navegador do usuário
            XLSX.writeFile(wb, filename);
        }
        </script>

        <!-- BEGIN theme-panel -->
        </div>
        <!-- END card-body -->

        </div>
        <!-- END #content -->

        </div>
        <!-- END #app -->


        </div>

        </div>
        <!-- /.Start col -->
        <!-- Start col -->

        <!-- /.Start col -->
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