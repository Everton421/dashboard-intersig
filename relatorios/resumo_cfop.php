<?php
// --- NO CADASTRO DA SERIE ELE PEGA OS VALORES SOMENTE O QUE ESTIVER MARCADA PARA ENVIAR SPED ---
require_once '../config/config.php';
require_once '../config/session.php'; 
$nomeEmpresa = $empresa_nome ?? 'Empresa';

date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION["user_name"])) {
    // Redireciona para o login se não estiver logado
    header("Location: ../index.php");
    exit();
}



                                                
$nome_usuario_logado = htmlspecialchars($_SESSION["user_name"]);
$id_usuario_logado = (int) $_SESSION["user_id"];
$vendedor = 0;

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
$condicoes_fixas = " s.consid_sped = 'S' AND n.cancelada = 'N'";


// --- FILTROS DINÂMICOS ---
$filial = $_GET['filial'] ?? '';
$chave_mvto = $_GET['nf'] ?? '';
$data_inicio_raw = $_GET['data_inicio'] ?? '';
$data_fim_raw = $_GET['data_fim'] ?? '';
$uf = isset($_GET['uf']) ? $_GET['uf'] : '';
$uf = isset($_GET['uf']) ? $_GET['uf'] : '';
$operacao = $_GET['operacao'] ?? '';
$vendedor_selecionado = $_GET['vendedor'] ?? '';


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
if ($vendedor_selecionado) {
$filtros_dinamicos[] = "n.VEND1 = ?";
$params[] = $vendedor_selecionado;
}
if ($operacao) {
    $filtros_dinamicos[] = "tr.ENT_SAI = ?";
    $params[] = $operacao;
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
$filtros_dinamicos[] = "n.DATA_SAIDA_ENTRADA <= ?"; $params[]=$data_fim; } $where="WHERE " . $condicoes_fixas; if
    (!empty($filtros_dinamicos)) { $where .=" AND " . implode(' AND ', $filtros_dinamicos);
}




// Obter lista de filiais

$result_filial = mysqli_query($dash, "SELECT filial, nome_fantasia FROM {$banco_vendas}.empresas WHERE ativa = 'S' ORDER BY
    filial ASC"); $filiais=[]; while ($row=mysqli_fetch_assoc($result_filial)) { $filiais[]=[ 'codigo'=> $row['filial'],
    'nome' => $row['nome_fantasia']
    ];
    }

// lista de vendedores
    $result_vendedores = mysqli_query($dash, "SELECT codigo, nome FROM {$banco_publico}.cad_vend WHERE ativo = 'S' AND tipo = 'V' ORDER BY
    nome ASC"); $vendedores=[]; while ($row=mysqli_fetch_assoc($result_vendedores)) { $vendedores[]=[ 'codigo'=> $row['codigo'],
    'nome' => $row['nome']
    ];
    }

    
    // Consulta principal para resumo por CFOP
    // ----------------------------------------------------------------------------------------------
    if (!$chave_mvto) {
    $sqlResumoCFOP = "SELECT p.CFOP, SUM(p.TOTAL) AS total_cfop, SUM(p.VALOR_DESCONTO) AS desconto_cfop,
    SUM(t.VALOR_ICMS) AS icms_cfop,
    SUM(t.VALOR_SUBST) AS icmsst_cfop, SUM(t.VALOR_IPI) AS ipi_cfop, SUM(t.VALOR_PIS) AS pis_cfop, SUM(t.VALOR_COFINS)
    AS cofins_cfop,
    SUM(t.BASE_ICMS) AS baseicms_cfop , SUM(t.valor_icms_uf_dest) AS icms_uf_dest_cfop, SUM(t.valor_fcp_uf_dest) AS
    fcp_uf_dest_cfop
    FROM {$banco_vendas}.mvto_produtos p
    INNER JOIN {$banco_vendas}.cad_nf n ON n.CODIGO = p.CHAVE_MVTO {$join_series}
    LEFT JOIN {$banco_vendas}.mvto_tributos t ON t.CHAVE_MVTO = p.CHAVE_MVTO AND t.ITEM = p.ITEM
    left join {$banco_vendas}.transacoes tr 
on tr.codigo = n.transacao and tr.filial = n.filial 
 LEFT JOIN {$banco_publico}.cad_clie c ON (n.codigo_cli_for = c.codigo AND n.cli_for = 'C')
    LEFT JOIN {$banco_publico}.cad_forn f ON (n.codigo_cli_for = f.codigo AND n.cli_for = 'F')
   
     {$where}
     ";

if (!empty($uf)) {$sqlResumoCFOP .= " AND (
        (n.cli_for = 'C' AND c.estado = '$uf')
        OR (n.cli_for = 'F' AND f.estado = '$uf')
    )";}
  
$sqlResumoCFOP .= "
    GROUP BY p.CFOP
    ORDER BY p.CFOP ASC";

    $dadosResumoCFOP = mysqli_fetch_all(executar_consulta_preparada($dash, $sqlResumoCFOP, $params),
    MYSQLI_ASSOC);
    };


      // ---  Consulta para o resumo agrupado por CST e CFOP ---
    $sqlResumoCstCfop = "
        SELECT
            p.cst as CST_ICMS,
            p.CFOP,
            MAX(p.ALIQ_ICMS) AS aliquota_icms,
            SUM(p.TOTAL) AS total_operacao,
            SUM(t.BASE_ICMS) AS base_icms,
            SUM(t.VALOR_ICMS) AS total_icms,
            SUM(t.BASE_SUBST) AS base_icms_st,
            SUM(t.VALOR_SUBST) AS total_icms_st
        FROM {$banco_vendas}.mvto_produtos p
        INNER JOIN {$banco_vendas}.cad_nf n ON n.CODIGO = p.CHAVE_MVTO {$join_series}
        LEFT JOIN {$banco_vendas}.mvto_tributos t ON t.CHAVE_MVTO = p.CHAVE_MVTO AND t.ITEM = p.ITEM
        left join {$banco_vendas}.transacoes tr 
on tr.codigo = n.transacao and tr.filial = n.filial 
 LEFT JOIN {$banco_publico}.cad_clie c ON (n.codigo_cli_for = c.codigo AND n.cli_for = 'C')
    LEFT JOIN {$banco_publico}.cad_forn f ON (n.codigo_cli_for = f.codigo AND n.cli_for = 'F')
        {$where} ";

if (!empty($uf)) {$sqlResumoCstCfop .= " AND (
        (n.cli_for = 'C' AND c.estado = '$uf')
        OR (n.cli_for = 'F' AND f.estado = '$uf')
    )";}
  
$sqlResumoCstCfop .= "
    
        GROUP BY p.cst, p.CFOP
        ORDER BY p.cst, p.CFOP ASC
    ";
    $dadosResumoCstCfop = mysqli_fetch_all(executar_consulta_preparada($dash, $sqlResumoCstCfop, $params), MYSQLI_ASSOC);
    
    ?>

<!doctype html>
<html lang="pt-br">
<!--begin::Head-->

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Resumo CFOP <?php echo htmlspecialchars($nomeEmpresa); ?> </title>
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

        .select-vendedor {
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

    .select-vendedor:hover {
        border-color: #00bcd4;
    }

    .select-vendedor:focus {
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
                        <h3 class="mb-0">Resumo por CFOP</h3>
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
                                                
                                                 <!-- Coluna da vendedores -->
                                                <div class="col-md-2">
                                                    <label class="form-label">Vendedor</label>
                                                    <select name="vendedor" class="select-vendedor form-control-sm">
                                                        <option value="">Todos</option>
        <?php foreach ($vendedores as $vendedor): ?>
            <option value="<?= htmlspecialchars($vendedor['codigo']) ?>" 
                    <?= ($vendedor_selecionado == $vendedor['codigo']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($vendedor['nome']) ?>
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



                                                <div class="col-md-2">
                                                    <label class="form-label">Operação</label>
                                                    <select name="operacao" class="select-filial form-control-sm">
                                                        <option value="">Todas</option>
                                                        <option value="E" <?= ($operacao == 'E' ? 'selected' : '') ?>>
                                                            Entrada</option>
                                                        <option value="S" <?= ($operacao == 'S' ? 'selected' : '') ?>>
                                                            Saída</option>
                                                    </select>
                                                </div>

                                                <!-- Coluna dos Botões -->
                                                <div class="col-md-2 d-flex gap-2">
                                                    <button type="submit"
                                                        class="btn btn-primary btn-sm-custom flex-grow-1">Filtrar</button>
                                                    <?php if ($chave_mvto || $data_inicio_raw || $data_fim_raw || $filial || $numero_nota || $operacao || $cidade || $nome || $serie || $vendedor_selecionado): ?>
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


                <?php if (!empty($dadosResumoCFOP)): ?>
                <!-- Resumo por CFOP -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <strong>Detalhamento por CFOP</strong>
                                <!-- BOTÃO DE EXPORTAÇÃO PARA A TABELA DETALHADA -->
                                <button class="btn btn-success btn-sm"
                                    onclick="exportToExcel('tabelaCfopDetalhada', 'Resumo_CFOP_Detalhado.xlsx')">
                                    <i class="bi bi-file-earmark-excel"></i> Exportar para Excel
                                </button>
                                <button class="btn btn-primary btn-sm ms-2" onclick="verDetalhesGeral()">
                                    <i class="bi bi-eye"></i> Ver Todos
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tabelaCfopDetalhada" class="table table-striped table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>Ver</th>
                                                <th>CFOP</th>
                                                <th>Total Prod.</th>
                                                <th>Desconto</th>
                                                <th>Base ICMS</th>
                                                <th>ICMS</th>
                                                <th>SUB-ICMS</th>
                                                <th>IPI</th>
                                                <th>PIS</th>
                                                <th>COFINS</th>
                                                <th>DIFAL</th>
                                                <th>FCP</th>

                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                    // TOTAIS GERAIS (JÁ EXISTENTES)
                                    $total_venda_geral_cfop = 0;
                                    $total_desconto_geral_cfop = 0;
                                    $total_icms_geral_cfop = 0;
                                    $total_ipi_geral_cfop = 0;
                                    $total_pis_geral_cfop = 0;
                                    $total_cofins_geral_cfop = 0;
                                    $total_icmsst_geral_cfop = 0; 
                                    $total_baseicms_geral_cfop = 0;
                                    $total_valor_icms_uf_dest_cfop = 0;
                                    $total_valor_fcp_uf_dest_cfop = 0;
                                    

                                    // --- INÍCIO DA MODIFICAÇÃO 1: INICIALIZAR TOTAIS POR TIPO ---
                                    $totais_por_tipo = [
                                        'entradas' => ['label' => 'Entradas (CFOP 1, 2)', 'total' => 0, 'desconto' => 0, 'base_icms' => 0, 'icms' => 0, 'icms_st' => 0, 'ipi' => 0, 'pis' => 0, 'cofins' => 0, 'icms_uf_dest' => 0, 'fcp_uf_dest' => 0],
                                        'saidas'   => ['label' => 'Saídas (CFOP 5, 6)',   'total' => 0, 'desconto' => 0, 'base_icms' => 0, 'icms' => 0, 'icms_st' => 0, 'ipi' => 0, 'pis' => 0, 'cofins' => 0, 'icms_uf_dest' => 0, 'fcp_uf_dest' => 0],
                                        'imp'   => ['label' => 'Imp (CFOP 3)',  'total' => 0, 'desconto' => 0, 'base_icms' => 0, 'icms' => 0, 'icms_st' => 0, 'ipi' => 0, 'pis' => 0, 'cofins' => 0, 'icms_uf_dest' => 0, 'fcp_uf_dest' => 0],
                                          'exp'   => ['label' => 'Exp (CFOP 7)',  'total' => 0, 'desconto' => 0, 'base_icms' => 0, 'icms' => 0, 'icms_st' => 0, 'ipi' => 0, 'pis' => 0, 'cofins' => 0, 'icms_uf_dest' => 0, 'fcp_uf_dest' => 0],
                                    ];
                                    // --- FIM DA MODIFICAÇÃO 1 ---

                                    foreach ($dadosResumoCFOP as $c): 
                                        // Somatório para totais gerais (já existente)
                                        $total_venda_geral_cfop += $c['total_cfop'];
                                        $total_desconto_geral_cfop += $c['desconto_cfop'];
                                        $total_baseicms_geral_cfop += $c['baseicms_cfop'];
                                        $total_icms_geral_cfop += $c['icms_cfop'];
                                        $total_ipi_geral_cfop += $c['ipi_cfop'];
                                        $total_pis_geral_cfop += $c['pis_cfop'];
                                        $total_cofins_geral_cfop += $c['cofins_cfop'];
                                        $total_icmsst_geral_cfop += $c['icmsst_cfop'];
                                        $total_valor_icms_uf_dest_cfop += $c['icms_uf_dest_cfop'];
                                        $total_valor_fcp_uf_dest_cfop += $c['fcp_uf_dest_cfop'];

                                        // --- INÍCIO DA MODIFICAÇÃO 2: PROCESSAR TOTAIS POR TIPO ---
                                        $primeiro_digito = substr((string)$c['CFOP'], 0, 1);
                                        $tipo_mov = null;

                                        if ($primeiro_digito == '1' || $primeiro_digito == '2') {
                                            $tipo_mov = 'entradas';
                                        } elseif ($primeiro_digito == '5' || $primeiro_digito == '6') {
                                            $tipo_mov = 'saidas';
                                        } elseif ($primeiro_digito == '3' ) {
                                            $tipo_mov = 'imp';
                                        }
                                          elseif ($primeiro_digito == '7'){
                                            $tipo_mov = 'exp';
                                        }
                                        

                                        if ($tipo_mov) {
                                            $totais_por_tipo[$tipo_mov]['total']     += $c['total_cfop'];
                                            $totais_por_tipo[$tipo_mov]['desconto']  += $c['desconto_cfop'];
                                            $totais_por_tipo[$tipo_mov]['base_icms'] += $c['baseicms_cfop'];
                                            $totais_por_tipo[$tipo_mov]['icms']      += $c['icms_cfop'];
                                            $totais_por_tipo[$tipo_mov]['icms_st']   += $c['icmsst_cfop'];
                                            $totais_por_tipo[$tipo_mov]['ipi']       += $c['ipi_cfop'];
                                            $totais_por_tipo[$tipo_mov]['pis']       += $c['pis_cfop'];
                                            $totais_por_tipo[$tipo_mov]['cofins']    += $c['cofins_cfop'];
                                            $totais_por_tipo[$tipo_mov]['icms_uf_dest']    += $c['icms_uf_dest_cfop'];
                                            $totais_por_tipo[$tipo_mov]['fcp_uf_dest']    += $c['fcp_uf_dest_cfop'];
                                            
                                            
                                                   
                                            
                                        }
                                        // --- FIM DA MODIFICAÇÃO 2 ---
                                    ?>
                                            <tr>
                                                <td>
                                                    <button class="btn btn-primary btn-sm"
                                                        onclick="verNotasCFOP('<?= htmlspecialchars($c['CFOP']) ?>')"
                                                        title="Ver notas deste CFOP">
                                                        <i class="bi bi-eye"></i> Ver
                                                    </button>
                                                </td>
                                                <td><?= htmlspecialchars($c['CFOP']) ?></td>
                                                <td>R$ <?= number_format($c['total_cfop'], 2, ",", ".") ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($c['desconto_cfop'], 2, ",", ".") ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($c['baseicms_cfop'], 2, ",", ".") ?>
                                                </td>
                                                <td>R$ <?= number_format($c['icms_cfop'], 2, ",", ".") ?>
                                                </td>
                                                <td>R$ <?= number_format($c['icmsst_cfop'], 2, ",", ".") ?>
                                                </td>
                                                <td>R$ <?= number_format($c['ipi_cfop'], 2, ",", ".") ?>
                                                </td>
                                                <td>R$ <?= number_format($c['pis_cfop'], 2, ",", ".") ?>
                                                </td>
                                                <td>R$ <?= number_format($c['cofins_cfop'], 2, ",", ".") ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($c['icms_uf_dest_cfop'], 2, ",", ".") ?>
                                                </td>
                                                <td>R$ <?= number_format($c['fcp_uf_dest_cfop'], 2, ",", ".") ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th>##</th>
                                                <th>TOTAIS</th>
                                                <th>R$
                                                    <?= number_format($total_venda_geral_cfop, 2, ",", ".") ?>
                                                </th>
                                                <th>R$
                                                    <?= number_format($total_desconto_geral_cfop, 2, ",", ".") ?>
                                                </th>
                                                <th>R$
                                                    <?= number_format($total_baseicms_geral_cfop, 2, ",", ".") ?>
                                                </th>
                                                <th>R$
                                                    <?= number_format($total_icms_geral_cfop, 2, ",", ".") ?>
                                                </th>
                                                <th>R$
                                                    <?= number_format($total_icmsst_geral_cfop, 2, ",", ".") ?>
                                                </th>
                                                <th>R$
                                                    <?= number_format($total_ipi_geral_cfop, 2, ",", ".") ?>
                                                </th>
                                                <th>R$
                                                    <?= number_format($total_pis_geral_cfop, 2, ",", ".") ?>
                                                </th>
                                                <th>R$
                                                    <?= number_format($total_cofins_geral_cfop, 2, ",", ".") ?>
                                                </th>
                                                <th>R$
                                                    <?= number_format($total_valor_icms_uf_dest_cfop, 2, ",", ".") ?>
                                                </th>
                                                <th>R$
                                                    <?= number_format($total_valor_fcp_uf_dest_cfop, 2, ",", ".") ?>
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                // Função para redirecionar para a página de detalhes do CFOP
                function verNotasCFOP(cfop) {
                    // Obter os filtros atuais
                    const filial = document.querySelector('select[name="filial"]').value;
                    const dataInicio = document.getElementById('data_inicio').value;
                    const dataFim = document.getElementById('data_fim').value;
                    const operacao = document.querySelector('select[name="operacao"]').value;

                    // Construir a URL com os parâmetros
                    const url =
                        `detalhes_cfop.php?cfop=${encodeURIComponent(cfop)}&filial=${encodeURIComponent(filial)}&data_inicio=${encodeURIComponent(dataInicio)}&data_fim=${encodeURIComponent(dataFim)}&operacao=${encodeURIComponent(operacao)}`;

                    // Redirecionar para a nova página
                    window.location.href = url;
                }

                function verDetalhesGeral() {
                    const filial = document.querySelector('select[name="filial"]').value;
                    const dataInicio = document.getElementById('data_inicio').value;
                    const dataFim = document.getElementById('data_fim').value;
                    const operacao = document.querySelector('select[name="operacao"]').value;

                    const url =
                        `detalhes_cfopgeral.php?filial=${encodeURIComponent(filial)}&data_inicio=${encodeURIComponent(dataInicio)}&data_fim=${encodeURIComponent(dataFim)}&operacao=${encodeURIComponent(operacao)}`;
                    window.location.href = url;
                }
                </script>


                <?php if (!$chave_mvto): ?>

                <!-- --- INÍCIO DA NOVA SEÇÃO 2: Tabela de Resumo por CST e CFOP --- -->
                <?php if (!empty($dadosResumoCstCfop)): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <strong>RESUMO - TOTAIS (Agrupado por CST e CFOP)</strong>
                                <button class="btn btn-success btn-sm float-end"
                                    onclick="exportToExcel('tabelaCstCfop', 'Resumo_CST_CFOP.xlsx')">
                                    <i class="bi bi-file-earmark-excel"></i> Exportar para Excel
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tabelaCstCfop" class="table table-striped table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>CST/ICMS</th>
                                                <th>CFOP</th>
                                                <th>Alíquota ICMS</th>
                                                <th>Total Operação</th>
                                                <th>Base de Cálculo ICMS</th>
                                                <th>Total ICMS</th>
                                                <th>Base de Cálculo ICMS ST</th>
                                                <th>Total ICMS ST</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                                        // Inicializar variáveis para o rodapé de totais
                                                        $total_op_geral = 0;
                                                        $total_base_icms_geral = 0;
                                                        $total_icms_geral = 0;
                                                        $total_base_icms_st_geral = 0;
                                                        $total_icms_st_geral = 0;

                                                        foreach ($dadosResumoCstCfop as $row):
                                                            // Somar aos totais
                                                            $total_op_geral += $row['total_operacao'];
                                                            $total_base_icms_geral += $row['base_icms'];
                                                            $total_icms_geral += $row['total_icms'];
                                                            $total_base_icms_st_geral += $row['base_icms_st'];
                                                            $total_icms_st_geral += $row['total_icms_st'];
                                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['CST_ICMS']) ?></td>
                                                <td><?= htmlspecialchars($row['CFOP']) ?></td>
                                                <td><?= number_format($row['aliquota_icms'], 2, ",", ".") ?>
                                                </td>
                                                <td><?= number_format($row['total_operacao'], 2, ",", ".") ?>
                                                </td>
                                                <td><?= number_format($row['base_icms'], 2, ",", ".") ?></td>
                                                <td><?= number_format($row['total_icms'], 2, ",", ".") ?></td>
                                                <td><?= number_format($row['base_icms_st'], 2, ",", ".") ?></td>
                                                <td><?= number_format($row['total_icms_st'], 2, ",", ".") ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="3" class="text-end">TOTAIS</th>
                                                <th><?= number_format($total_op_geral, 2, ",", ".") ?></th>
                                                <th><?= number_format($total_base_icms_geral, 2, ",", ".") ?>
                                                </th>
                                                <th><?= number_format($total_icms_geral, 2, ",", ".") ?></th>
                                                <th><?= number_format($total_base_icms_st_geral, 2, ",", ".") ?>
                                                </th>
                                                <th><?= number_format($total_icms_st_geral, 2, ",", ".") ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <!-- --- FIM DA NOVA SEÇÃO 2 --- -->
                <!-- --- INÍCIO DA MODIFICAÇÃO 3: NOVA TABELA DE TOTALIZADORES --- -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <strong>Resumo por Tipo de Movimentação</strong>
                                <button class="btn btn-success btn-sm"
                                    onclick="exportToExcel('tabelaCfopResumo', 'Resumo_Movimentacao.xlsx')">
                                    <i class="bi bi-file-earmark-excel"></i> Exportar para Excel
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tabelaCfopResumo" class="table table-striped table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>Tipo de Movimentação</th>
                                                <th>Total Produtos</th>
                                                <th>Desconto</th>
                                                <th>Base ICMS</th>
                                                <th>ICMS</th>
                                                <th>SUB-ICMS</th>
                                                <th>IPI</th>
                                                <th>PIS</th>
                                                <th>COFINS</th>
                                                <th>DIFAL</th>
                                                <th>FCP</th>

                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($totais_por_tipo as $tipo => $valores): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($valores['label']) ?></strong>
                                                </td>
                                                <td>R$ <?= number_format($valores['total'], 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($valores['desconto'], 2, ",", ".") ?>
                                                </td>
                                                <td>R$ <?= number_format($valores['base_icms'], 2, ",", ".") ?>
                                                </td>
                                                <td>R$ <?= number_format($valores['icms'], 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($valores['icms_st'], 2, ",", ".") ?>
                                                </td>
                                                <td>R$ <?= number_format($valores['ipi'], 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($valores['pis'], 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($valores['cofins'], 2, ",", ".") ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($valores['icms_uf_dest'], 2, ",", ".") ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($valores['fcp_uf_dest'], 2, ",", ".") ?>
                                                </td>

                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- --- FIM DA MODIFICAÇÃO 3 --- -->
                <?php endif; ?>




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