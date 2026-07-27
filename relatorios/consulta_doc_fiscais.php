<?php
// --- NO CADASTRO DA SERIE ELE PEGA OS VALORES SOMENTE O QUE ESTIVER MARCADA PARA ENVIAR SPED ---
set_time_limit(220); // 2 minutos máximo
ini_set('memory_limit', '2048M'); // Aumenta memória se necessário
require_once '../config/config.php';
require_once '../config/session.php'; 
$nomeEmpresa = $empresa_nome ?? 'Empresa';
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
    $stmt->bind_param("isss", $id_usuario_logado, $nome_usuario_logado, $page_url, $user_agent);
    $stmt->execute();
}

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

// --- FILTROS DINÂMICOS ---
$filial = $_GET['filial'] ?? '';
$numero_nota = $_GET['nf'] ?? '';
$data_inicio_raw = $_GET['data_inicio'] ?? '';
$data_fim_raw = $_GET['data_fim'] ?? '';
$uf = $_GET['uf'] ?? '';
$operacao = $_GET['operacao'] ?? '';

// Define o período padrão para o mês atual se nenhum filtro de data ou nota for informado
if (empty($data_inicio_raw) && empty($data_fim_raw) && empty($numero_nota)) {
    $hoje = new DateTime();
  $data_inicio_sql_padrao = $hoje->format('Y-m-d');
$data_fim_sql_padrao = $hoje->format('Y-m-d');

    $data_inicio_raw = (new DateTime($data_inicio_sql_padrao))->format('d/m/Y');
    $data_fim_raw = (new DateTime($data_fim_sql_padrao))->format('d/m/Y');
}

$data_inicio = convert_date_br_to_sql($data_inicio_raw);
$data_fim = convert_date_br_to_sql($data_fim_raw);

// --- CONSTRUÇÃO DA CLÁUSULA WHERE E PARÂMETROS ---
$filtros_dinamicos = [];
$params = [];
$dadosResumoCFOP = []; // Inicializa a variável de dados

if ($filial) {
    $filtros_dinamicos[] = "CN.FILIAL = ?";
    $params[] = $filial;
}
if ($numero_nota) { // ALTERADO
    $filtros_dinamicos[] = "CN.NUMERO_NF = ?"; 
    $params[] = $numero_nota;
}

if ($data_inicio) {
    $filtros_dinamicos[] = "CN.DATA_SAIDA_ENTRADA >= ?";
    $params[] = $data_inicio;
}
if ($data_fim) {
    $filtros_dinamicos[] = "CN.DATA_SAIDA_ENTRADA <= ?";
    $params[] = $data_fim;
}
if ($operacao) {
    $filtros_dinamicos[] = "T.ENT_SAI = ?";
    $params[] = $operacao;
}
if ($uf) {
    // Adiciona o filtro de UF diretamente na cláusula WHERE da subconsulta
    $filtros_dinamicos[] = "(CASE WHEN CN.CLI_FOR = 'C' THEN C.ESTADO ELSE F.ESTADO END) = ?";
    $params[] = $uf;
}

// Obter lista de filiais
$result_filial = mysqli_query($dash, "SELECT filial, nome_fantasia FROM {$banco_vendas}.empresas WHERE ativa = 'S' ORDER BY filial ASC");
$filiais = [];
while ($row = mysqli_fetch_assoc($result_filial)) {
    $filiais[] = ['codigo' => $row['filial'], 'nome' => $row['nome_fantasia']];
}

// --- Consulta principal ---
// A consulta só será executada se houver filtros (o padrão já define data_inicio e data_fim)
if (!empty($filtros_dinamicos)) {
    $sqlResumoCFOP = "
        SELECT 
            X.RAZAO_SOCIAL_FILIAL_EMIT_REL, X.OPERACAO, X.TIPO_EMISSAO, X.NUMERO_NF, X.CODIGO, X.MODELO, X.STATUS, X.CPF_CNPJ, X.RAZAO_SOCIAL, X.UF, X.DATA_EMISSAO, X.CFOP, X.DATA_SAIDA_ENTRADA,
            SUM(X.VALOR_CONTABIL) AS VALOR_CONTABIL, 
            (SUM(X.VALOR_CONTABIL)-SUM(X.VALOR_DESCONTO)) AS VALOR_PRODUTO, 
            SUM(X.VALOR_DESCONTO) AS VALOR_DESCONTO, 
            SUM(X.BASE_CALCULO) AS BASE_CALCULO, 
            X.ALIQ_ICMS, X.CST_ICMS, 
            SUM(X.VALOR_ICMS) AS VALOR_ICMS, 
            SUM(X.BASE_ST) AS BASE_ST, 
            SUM(X.VALOR_ST) AS VALOR_ST, 
            SUM(X.VALOR_ISENTAS) AS VALOR_ISENTAS, 
            SUM(X.VALOR_OUTRAS) AS VALOR_OUTRAS, 
            X.CST_IPI, 
            SUM(X.IPI) AS IPI 
        FROM (
            SELECT 
                EMPRESAS.NOME_REDUZ AS RAZAO_SOCIAL_FILIAL_EMIT_REL, CN.CODIGO, IF(T.ENT_SAI = 'E', 'ENTRADA', 'SAÍDA') AS OPERACAO,
                CASE WHEN S.ORIGEM = 'P' THEN 'Própria' ELSE 'Terceiros' END AS TIPO_EMISSAO, 
                NUMERO_NF,
                CASE WHEN S.MOD_SINTEGRA = '01' THEN 'NF 1' WHEN S.MOD_SINTEGRA = '04' THEN 'NF Produtor' WHEN S.MOD_SINTEGRA = '06' THEN 'NF Energia' WHEN S.MOD_SINTEGRA = '07' THEN 'NF Serviço transp.' WHEN S.MOD_SINTEGRA = '08' THEN 'CT Rodoviário' WHEN S.MOD_SINTEGRA = '09' THEN 'CT Aquaviário' WHEN S.MOD_SINTEGRA = '10' THEN 'CT Aéreo' WHEN S.MOD_SINTEGRA = '11' THEN 'CT Ferroviário' WHEN S.MOD_SINTEGRA = '1B' THEN 'NF Avulsa' WHEN S.MOD_SINTEGRA = '26' THEN 'CT Multimodal' WHEN S.MOD_SINTEGRA = '27' THEN 'NF Transp. Ferroviário' WHEN S.MOD_SINTEGRA = '28' THEN 'NF Gás' WHEN S.MOD_SINTEGRA = '29' THEN 'NF Água' WHEN S.MOD_SINTEGRA = '44' THEN 'NFS-e' WHEN S.MOD_SINTEGRA = '55' THEN 'NF-e' WHEN S.MOD_SINTEGRA = '57' THEN 'CT-e' WHEN S.MOD_SINTEGRA = '65' THEN 'NFC-e' WHEN S.MOD_SINTEGRA = '67' THEN 'CT-e OS' WHEN S.MOD_SINTEGRA = '8B' THEN 'CT Avulso' ELSE '' END AS MODELO, 
                CASE WHEN CN.CANCELADA = 'S' THEN 'CANCELADA' WHEN CN.SITUACAO_NFE IN ('N', 'P') AND S.MOD_SINTEGRA IN('44', '55', '65') AND S.ORIGEM = 'P' THEN 'PENDENTE' WHEN CN.SITUACAO_NFE IN ('R') AND S.MOD_SINTEGRA IN('44', '55', '65') AND S.ORIGEM = 'P' THEN 'REJEITADA' WHEN CN.SITUACAO_NFE IN ('A') AND S.MOD_SINTEGRA IN('44', '55', '65') AND S.ORIGEM = 'P' AND CN.CANCELADA = 'N' THEN 'AUTORIZADA' ELSE (CASE WHEN CN.CANCELADA = 'N' THEN 'NORMAL' ELSE '' END) END AS STATUS,
                CASE WHEN CN.CLI_FOR = 'C' THEN C.CPF ELSE F.CNPJ END AS CPF_CNPJ,
                CASE WHEN CN.CLI_FOR = 'C' THEN C.NOME ELSE F.RAZAO_SOCIAL END AS RAZAO_SOCIAL,
                CASE WHEN CN.CLI_FOR = 'C' THEN C.ESTADO ELSE F.ESTADO END AS UF,
                CN.DATA_EMISSAO, 
                CN.DATA_SAIDA_ENTRADA,
                CASE WHEN S.MOD_SINTEGRA IN ('08', '09', '10', '11', '26', '57', '67', '8B') THEN CN.CFOP ELSE MP.CFOP END AS CFOP, 
                CASE WHEN S.MOD_SINTEGRA IN ('08', '09', '10', '11', '26', '57', '67', '8B') THEN CN.TOTAL_NF ELSE (MP.TOTAL + MP.FRETE + MT.VALOR_IPI + MT.VALOR_SUBST + MP.OUTRAS_DESP) END AS VALOR_CONTABIL, 
                CASE WHEN S.MOD_SINTEGRA IN ('08', '09', '10', '11', '26', '57', '67', '8B') THEN 0 ELSE (MP.VALOR_DESCONTO) END AS VALOR_DESCONTO,
                CASE WHEN S.MOD_SINTEGRA IN ('08', '09', '10', '11', '26', '57', '67', '8B') THEN CN.BASE_ICMS ELSE MT.BASE_ICMS END AS BASE_CALCULO,
                CASE WHEN S.MOD_SINTEGRA IN ('08', '09', '10', '11', '26', '57', '67', '8B') THEN (CASE WHEN CN.BASE_ICMS <> 0 THEN (ROUND(CN.VALOR_ICMS / CN.BASE_ICMS) * 100) ELSE 0 END) ELSE MP.ALIQ_ICMS END AS ALIQ_ICMS,
                CASE WHEN S.MOD_SINTEGRA IN ('08', '09', '10', '11', '26', '57', '67', '8B') THEN CN.CST_ICMS ELSE MP.CST END AS CST_ICMS,
                CASE WHEN S.MOD_SINTEGRA IN ('08', '09', '10', '11', '26', '57', '67', '8B') THEN CN.VALOR_ICMS ELSE MT.VALOR_ICMS END AS VALOR_ICMS,
                CASE WHEN S.MOD_SINTEGRA IN ('08', '09', '10', '11', '26', '57', '67', '8B') THEN CN.BASE_ICMS_SUB ELSE MT.BASE_SUBST END AS BASE_ST, 
                CASE WHEN S.MOD_SINTEGRA IN ('08', '09', '10', '11', '26', '57', '67', '8B') THEN CN.VALOR_ICMS_SUB ELSE MT.VALOR_SUBST END AS VALOR_ST, 
                CASE WHEN S.MOD_SINTEGRA IN ('08', '09', '10', '11', '26', '57', '67', '8B') THEN 0 ELSE (CASE WHEN RIGHT(MP.CST, 2) IN ('51', '70') THEN (MP.TOTAL + MP.FRETE + MT.VALOR_IPI + MT.VALOR_SUBST + MP.OUTRAS_DESP - MT.BASE_ICMS) ELSE 0 END) END AS VALOR_ISENTAS, 
                CASE WHEN S.MOD_SINTEGRA IN ('08', '09', '10', '11', '26', '57', '67', '8B') THEN (CASE WHEN (CN.BASE_ICMS > 0) AND (CN.VALOR_ICMS / CN.BASE_ICMS > 0) THEN 0 ELSE CN.TOTAL_NF END) ELSE (CASE WHEN RIGHT(MP.CST_IPI,2) IN ('51', '70') THEN MT.BASE_ICMS ELSE (MP.TOTAL + MP.FRETE + MT.VALOR_IPI + MT.VALOR_SUBST + MP.OUTRAS_DESP) END) END AS VALOR_OUTRAS, 
                CASE WHEN S.MOD_SINTEGRA IN ('08', '09', '10', '11', '26', '57', '67', '8B') THEN '000' ELSE MP.CST_IPI END AS CST_IPI, 
                CASE WHEN S.MOD_SINTEGRA IN ('08', '09', '10', '11', '26', '57', '67', '8B') THEN 0 ELSE MT.VALOR_IPI END AS IPI 
            FROM {$banco_vendas}.cad_nf AS CN 
            LEFT JOIN {$banco_vendas}.empresas EMPRESAS ON CN.FILIAL = EMPRESAS.FILIAL 
            LEFT JOIN {$banco_vendas}.mvto_produtos AS MP ON (MP.CHAVE_MVTO = CN.CODIGO AND MP.TIPO = 'F')
            LEFT JOIN {$banco_vendas}.mvto_tributos AS MT ON CN.codigo = MT.chave_mvto AND MT.item = MP.item 
            LEFT JOIN {$banco_vendas}.transacoes AS T ON (T.CODIGO = CN.TRANSACAO AND T.FILIAL = CN.FILIAL) 
            LEFT JOIN {$banco_publico}.cad_clie AS C ON (C.CODIGO = CN.CODIGO_CLI_FOR AND CN.CLI_FOR = 'C') 
            LEFT JOIN {$banco_publico}.cad_forn AS F ON (F.CODIGO = CN.CODIGO_CLI_FOR AND CN.CLI_FOR = 'F')
            LEFT JOIN {$banco_vendas}.series AS S ON (S.CODIGO = CN.SERIE AND S.FILIAL = CN.FILIAL)
            WHERE S.MOD_SINTEGRA NOT IN ('01', '07', '44')
              AND " . implode(' AND ', $filtros_dinamicos) . "
              AND S.CONSID_SPED = 'S'
              AND (
                  (CN.CANCELADA = 'S') OR 
                  (
                      (CN.CANCELADA = 'N') AND 
                      NOT (CN.SITUACAO_NFE in ('N', 'P') AND S.MOD_SINTEGRA in('44','55','65') AND S.ORIGEM = 'P') AND 
                      NOT (CN.SITUACAO_NFE in ('R') AND S.MOD_SINTEGRA in('44','55','65') AND S.ORIGEM = 'P')
                  )
              )
        ) AS X 
        GROUP BY CODIGO, NUMERO_NF, CFOP, CST_ICMS, CST_IPI, ALIQ_ICMS, OPERACAO, TIPO_EMISSAO, MODELO, STATUS, CPF_CNPJ, RAZAO_SOCIAL, DATA_EMISSAO, UF, RAZAO_SOCIAL_FILIAL_EMIT_REL
        ORDER BY DATA_SAIDA_ENTRADA, NUMERO_NF
    ";

  //  $dadosResumoCFOP = mysqli_fetch_all(executar_consulta_preparada($dash, $sqlResumoCFOP, $params), MYSQLI_ASSOC);
    $resultadoConsulta = executar_consulta_preparada($dash, $sqlResumoCFOP, $params);
};
?>


<!doctype html>
<html lang="pt-br">
<!--begin::Head-->

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Documentos Fiscais <?php echo htmlspecialchars($nomeEmpresa); ?></title>
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

    td {
        font-size: 12px;
    }

    th {
        font-size: 14px;
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
                        <h5 class="mb-0">Consulta Documentos Fiscais</h5>
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

                <!-- Filters (mantive exatamente como seu original) -->
                <div class="row">
                    <div class="col-lg-12 connectedSortable">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card filter-card">
                                    <div class="card-header">
                                        <strong>Filtros</strong>
                                    </div>
                                    <div class="card-body">
                                        <form method="GET" id="form-filtros">
                                            <div class="row g-3 align-items-end">
                                                <!-- (seu formulário: filiais, datas, etc) -->
                                                <div class="col-md-2">
                                                    <label class="form-label">Filial</label>
                                                    <select name="filial" class="select-filial form-control-sm">
                                                        <option value="">Todas</option>
                                                        <?php foreach ($filiais as $f): ?>
                                                        <option value="<?= $f['codigo'] ?>"
                                                            <?= ($f['codigo'] == $filial ? 'selected' : '') ?>>
                                                            <?= $f['codigo'] ?> - <?= htmlspecialchars($f['nome']) ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="col-md-1">
                                                    <label for="nf" class="form-label">Nº da Nota</label>
                                                    <input type="text" name="nf" id="nf"
                                                        class="form-control form-control-sm"
                                                        value="<?= htmlspecialchars($numero_nota) ?>"
                                                        placeholder="Digite o nº da NF">
                                                </div>

                                                <div class="col-md-2">
                                                    <label class="form-label">Data Início</label>
                                                    <input type="text" name="data_inicio" id="data_inicio"
                                                        class="form-control form-control-sm"
                                                        value="<?= htmlspecialchars($data_inicio_raw) ?>"
                                                        placeholder="dd/mm/aaaa" maxlength="10">
                                                </div>

                                                <div class="col-md-2">
                                                    <label class="form-label">Data Fim</label>
                                                    <input type="text" name="data_fim" id="data_fim"
                                                        class="form-control form-control-sm"
                                                        value="<?= htmlspecialchars($data_fim_raw) ?>"
                                                        placeholder="dd/mm/aaaa" maxlength="10">
                                                </div>

                                                <div class="col-md-1">
                                                    <label class="form-label">UF</label>
                                                    <select name="uf" class="select-filial form-control-sm">
                                                        <option value="">Todas</option>
                                                        <!-- listagem de UFs (mantida) -->
                                                        <option value="AC" <?= ($uf == 'AC' ? 'selected' : '') ?>>AC
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

                                                <div class="col-md-2 d-flex gap-2">
                                                    <button type="submit"
                                                        class="btn btn-primary btn-sm-custom flex-grow-1">Filtrar</button>
                                                    <?php if ($data_inicio_raw || $data_fim_raw || $filial || $numero_nota || $operacao || $cidade || $nome || $serie): ?>
                                                    <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>"
                                                        class="btn btn-outline-secondary btn-sm-custom flex-grow-1">Limpar</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Botões de período rápido -->
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


                <!-- RESULTADO / TABELA -->
                <?php if (isset($resultadoConsulta) && $resultadoConsulta->num_rows > 0): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <strong>Detalhamento dos Documentos Fiscais - Agrupado por CST</strong>
                                <button class="btn btn-success btn-sm"
                                    onclick="exportToExcel('tabelaDetalhada', 'Documentos_Fiscais_Detalhado.xlsx')">
                                    <i class="bi bi-file-earmark-excel"></i> Exportar
                                </button>
                            </div>

                            <div class="card-body">

                                <!-- BARRA SUPERIOR SÓ PARA SCROLL HORIZONTAL (visível) -->
                                <div id="scrollTopWrapper" class="table-scroll-wrapper" aria-hidden="false"
                                    style="display:block;"></div>

                                <!-- Conteúdo com a tabela (bootstrap .table-responsive) -->
                                <div id="scrollBottomWrapper" class="table-responsive" style="overflow-x:auto;">
                                    <table id="tabelaDetalhada" class="table table-striped table-bordered table-sm"
                                        style="white-space:nowrap;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Filial</th>
                                                <th>Operação</th>
                                                <th>Tipo</th>
                                                <th>NF</th>
                                                <th>Modelo</th>
                                                <th>Status</th>
                                                <th>CNPJ/CPF</th>
                                                <th>Razão Social</th>
                                                <th>UF</th>
                                                <th>Data Emissão</th>
                                                <th>Data Saída/Ent.</th>
                                                <th>CFOP</th>
                                                <th>Vl. Contábil</th>
                                                <th>Vl. Produto</th>
                                                <th>Desconto</th>
                                                <th>Base ICMS</th>
                                                <th>% ICMS</th>
                                                <th>CST ICMS</th>
                                                <th>Vl. ICMS</th>
                                                <th>Base ST</th>
                                                <th>Vl. ST</th>
                                                <th>Vl. Isentas</th>
                                                <th>Vl. Outras</th>
                                                <th>CST IPI</th>
                                                <th>Vl. IPI</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                        // Totais
                                        $total_contabil = $total_produto = $total_desconto = 0;
                                        $total_base_icms = $total_icms = $total_base_st = $total_st = 0;
                                        $total_isentas = $total_outras = $total_ipi = 0;

                                       // foreach ($dadosResumoCFOP as $c):
                                        while ($c = mysqli_fetch_assoc($resultadoConsulta)):
                                            $total_contabil += $c['VALOR_CONTABIL'];
                                            $total_produto += $c['VALOR_PRODUTO'];
                                            $total_desconto += $c['VALOR_DESCONTO'];
                                            $total_base_icms += $c['BASE_CALCULO'];
                                            $total_icms += $c['VALOR_ICMS'];
                                            $total_base_st += $c['BASE_ST'];
                                            $total_st += $c['VALOR_ST'];
                                            $total_isentas += $c['VALOR_ISENTAS'];
                                            $total_outras += $c['VALOR_OUTRAS'];
                                            $total_ipi += $c['IPI'];
                                        ?>
                                            <tr>
                                                <td>
                                                    <button class="btn btn-primary btn-sm"
                                                        onclick="verNotasCFOP('<?= htmlspecialchars($c['CODIGO']) ?>')"
                                                        title="Ver Detalhes da Nota">
                                                        <i class="bi bi-eye"></i> Ver
                                                    </button>
                                                </td>
                                                <td><?= htmlspecialchars($c['RAZAO_SOCIAL_FILIAL_EMIT_REL']) ?></td>
                                                <td>
                                                    <?php
                                                $operacao = htmlspecialchars($c['OPERACAO'] ?? '');
                                                if ($operacao === 'ENTRADA') {
                                                    echo '<i class="bi bi-box-arrow-in-right text-warning" title="Entrada"></i> Entrada';
                                                } elseif ($operacao === 'SAÍDA') {
                                                    echo '<i class="bi bi-box-arrow-up-right text-primary" title="Saída"></i> Saída';
                                                } else {
                                                    echo $operacao;
                                                }
                                                ?>
                                                </td>
                                                <td><?= htmlspecialchars($c['TIPO_EMISSAO']) ?></td>
                                                <td><?= htmlspecialchars($c['NUMERO_NF']) ?></td>
                                                <td><?= htmlspecialchars($c['MODELO']) ?></td>
                                                <td>
                                                    <?php
                                                $status = htmlspecialchars($c['STATUS'] ?? '');
                                                $badge_class = 'badge text-dark';
                                                if ($status === 'AUTORIZADA' || $status === 'NORMAL') {
                                                    $badge_class = 'badge bg-success';
                                                } elseif ($status === 'CANCELADA') {
                                                    $badge_class = 'badge bg-danger';
                                                } elseif ($status === 'PENDENTE' || $status === 'REJEITADA') {
                                                    $badge_class = 'badge bg-warning';
                                                }
                                                echo "<span class='{$badge_class}'>{$status}</span>";
                                                ?>
                                                </td>
                                                <td><?= htmlspecialchars($c['CPF_CNPJ']) ?></td>
                                                <td><?= htmlspecialchars($c['RAZAO_SOCIAL']) ?></td>
                                                <td><?= htmlspecialchars($c['UF']) ?></td>
                                                <td data-date="<?= htmlspecialchars($c['DATA_EMISSAO']) ?>">
                                                    <?= (new DateTime($c['DATA_EMISSAO']))->format('d/m/Y') ?>
                                                </td>
                                                <td data-date="<?= htmlspecialchars($c['DATA_SAIDA_ENTRADA']) ?>">
                                                    <?= (new DateTime($c['DATA_SAIDA_ENTRADA']))->format('d/m/Y') ?>
                                                </td>
                                                <td><?= htmlspecialchars($c['CFOP']) ?></td>
                                                <td>R$ <?= number_format($c['VALOR_CONTABIL'], 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($c['VALOR_PRODUTO'], 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($c['VALOR_DESCONTO'], 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($c['BASE_CALCULO'], 2, ",", ".") ?></td>
                                                <td><?= number_format($c['ALIQ_ICMS'], 2, ".", ".") ?></td>
                                                <td><?= htmlspecialchars($c['CST_ICMS']) ?></td>
                                                <td>R$ <?= number_format($c['VALOR_ICMS'], 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($c['BASE_ST'], 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($c['VALOR_ST'], 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($c['VALOR_ISENTAS'], 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($c['VALOR_OUTRAS'], 2, ",", ".") ?></td>
                                                <td><?= htmlspecialchars($c['CST_IPI']) ?></td>
                                                <td>R$ <?= number_format($c['IPI'], 2, ",", ".") ?></td>
                                            </tr>

                                            <?php endwhile; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr style="font-weight: bold;">
                                                <td colspan="12" class="text-end">TOTAIS</td>
                                                <td>R$ <?= number_format($total_contabil, 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($total_produto, 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($total_desconto, 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($total_base_icms, 2, ",", ".") ?></td>
                                                <td></td>
                                                <td></td>
                                                <td>R$ <?= number_format($total_icms, 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($total_base_st, 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($total_st, 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($total_isentas, 2, ",", ".") ?></td>
                                                <td>R$ <?= number_format($total_outras, 2, ",", ".") ?></td>
                                                <td></td>
                                                <td>R$ <?= number_format($total_ipi, 2, ",", ".") ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <script>
                function verNotasCFOP(cfop) {
                    const filial = document.querySelector('select[name="filial"]').value;
                    const dataInicio = document.getElementById('data_inicio').value;
                    const dataFim = document.getElementById('data_fim').value;
                    const url =
                        `detalhes_nota.php?nf=${encodeURIComponent(cfop)}`;
                    window.open(url, '_blank');
                }
                </script>

                <!-- Estilos da barra e pequena sombra para indicar overflow -->
                <style>
                .table-scroll-wrapper {
                    overflow-x: auto;
                    overflow-y: hidden;
                    width: 100%;
                    height: 12px;
                    /* altura da barra visível */
                }

                .table-scroll-wrapper>div {
                    height: 2px;
                }

                /* Aparência do scroll (WebKit) */
                .table-scroll-wrapper::-webkit-scrollbar {
                    height: 10px;
                }

                .table-scroll-wrapper::-webkit-scrollbar-thumb {
                    background: rgba(255, 253, 253, 0.67);
                    border-radius: 6px;
                }

                /* Sombra suave nas laterais da tabela para indicar mais conteúdo */
                #scrollBottomWrapper {
                    position: relative;
                }

                #scrollBottomWrapper::before,
                #scrollBottomWrapper::after {
                    content: "";
                    position: absolute;
                    top: 0;
                    bottom: 0;
                    width: 18px;
                    pointer-events: none;
                    transition: opacity .2s;
                    opacity: 0;
                }

                #scrollBottomWrapper::before {
                    left: 0;
                    background: linear-gradient(to left, rgba(0, 0, 0, 0.05), rgba(0, 0, 0, 0));
                }

                #scrollBottomWrapper::after {
                    right: 0;
                    background: linear-gradient(to right, rgba(0, 0, 0, 0.05), rgba(0, 0, 0, 0));
                }

                /* quando houver overflow mostramos a sombra */
                #scrollBottomWrapper.has-scroll-left::before {
                    opacity: 1;
                }

                #scrollBottomWrapper.has-scroll-right::after {
                    opacity: 1;
                }
                </style>

                <!-- Script que cria e sincroniza a barra superior com várias proteções -->
                <script>
                (function() {
                    // espera o DOM
                    function initTopScrollbar() {
                        const topWrapper = document.getElementById('scrollTopWrapper');
                        const bottomWrapper = document.getElementById('scrollBottomWrapper');
                        const table = document.getElementById('tabelaDetalhada');

                        if (!topWrapper || !bottomWrapper || !table) return;

                        // cria inner element que define largura
                        let inner = topWrapper.querySelector('div');
                        if (!inner) {
                            inner = document.createElement('div');
                            topWrapper.appendChild(inner);
                        }

                        // função que atualiza a largura do inner com base no scrollWidth da tabela
                        function updateWidth() {
                            // usamos a largura real do conteúdo da tabela (scrollWidth do elemento que contém a tabela)
                            const contentWidth = table.scrollWidth || bottomWrapper.scrollWidth || table
                                .offsetWidth;
                            inner.style.width = contentWidth + 'px';
                            syncShadows();
                        }

                        // sincroniza posições
                        topWrapper.addEventListener('scroll', function() {
                            bottomWrapper.scrollLeft = topWrapper.scrollLeft;
                        });
                        bottomWrapper.addEventListener('scroll', function() {
                            topWrapper.scrollLeft = bottomWrapper.scrollLeft;
                            syncShadows();
                        });

                        // sombra indicadora
                        function syncShadows() {
                            const maxScroll = bottomWrapper.scrollWidth - bottomWrapper.clientWidth;
                            if (maxScroll <= 0) {
                                bottomWrapper.classList.remove('has-scroll-left', 'has-scroll-right');
                                return;
                            }
                            if (bottomWrapper.scrollLeft > 10) {
                                bottomWrapper.classList.add('has-scroll-left');
                            } else {
                                bottomWrapper.classList.remove('has-scroll-left');
                            }
                            if (bottomWrapper.scrollLeft < maxScroll - 10) {
                                bottomWrapper.classList.add('has-scroll-right');
                            } else {
                                bottomWrapper.classList.remove('has-scroll-right');
                            }
                        }

                        // Observa mudanças de tamanho no elemento da tabela (responde a DataTables, fonts carregando etc)
                        if (window.ResizeObserver) {
                            const ro = new ResizeObserver(() => {
                                // espera um frame pra garantir layout
                                window.requestAnimationFrame(updateWidth);
                            });
                            ro.observe(table);
                            ro.observe(bottomWrapper);
                        } else {
                            // fallback
                            window.addEventListener('resize', updateWidth);
                            setInterval(updateWidth, 500); // fallback para ambientes antigos
                        }

                        // atualiza 1 vez ao iniciar
                        updateWidth();

                        // se a tabela for atualizada via ajax/dataTables, chamar updateWidth() depois da atualização
                        // Ex.: se usar DataTables: table.on('draw.dt', updateWidth);
                        // você pode também disparar manualmente window.dispatchEvent(new Event('resize')) após redraw
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', initTopScrollbar);
                    } else {
                        initTopScrollbar();
                    }
                })();
                </script>

                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="alert alert-info" role="alert">
                            Nenhum resultado encontrado para os filtros aplicados.
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
            <!--end::Container-->
        </div>
        <!--end::App Content-->
    </main>










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




    $(function() {
        $("#nf").autocomplete({
            source: "autocomplete_nf.php", // Caminho para o seu script PHP
            // minLength: 2, // Começa a buscar após 2 caracteres digitados
            select: function(event, ui) {
                // Opcional: se quiser que o formulário seja enviado ao selecionar um item
                $("#form-filtros").submit();
            }
        });
    });




    function exportToExcel(tableId, filename) {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Exportando...';
        btn.disabled = true;
        const table = document.getElementById(tableId);
        const rows = table.querySelectorAll('tr');
        const data = [];

        // Itera sobre cada linha (tr) da tabela
        for (const row of rows) {
            const rowData = [];
            // Itera sobre cada célula (th ou td) da linha
            const cells = row.querySelectorAll('th, td');
            for (const cell of cells) {
                let cellValue = cell.innerText;

                // --- A LÓGICA PRINCIPAL PARA DATAS ---
                // Verifica se a célula tem nosso atributo 'data-date'
                if (cell.dataset.date) {
                    // Cria um objeto Date do JavaScript a partir do valor AAAA-MM-DD
                    // Adiciona o fuso horário para evitar problemas de "um dia a menos"
                    const dateParts = cell.dataset.date.split('-');
                    if (dateParts.length === 3) {
                        // new Date(ano, mês-1, dia)
                        cellValue = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
                    }
                }
                // Tenta converter valores numéricos (para colunas de R$)
                else if (cellValue.startsWith('R$')) {
                    // Remove "R$", espaço, troca ponto de milhar por nada e vírgula de decimal por ponto
                    const num = parseFloat(
                        cellValue.replace('R$', '').trim().replace(/\./g, '').replace(',', '.')
                    );
                    // Se for um número válido, usa o número, senão, mantém o texto
                    if (!isNaN(num)) {
                        cellValue = num;
                    }
                }

                rowData.push(cellValue);
            }
            data.push(rowData);
        }

        // Cria a planilha a partir do array de dados processado
        const ws = XLSX.utils.aoa_to_sheet(data);

        // Melhora a largura das colunas (opcional, mas recomendado)
        const cols = [];
        if (data.length > 0) {
            for (let i = 0; i < data[0].length; i++) {
                let maxWidth = 0;
                for (let j = 0; j < data.length; j++) {
                    const cellContent = data[j][i] ? String(data[j][i]) : '';
                    if (cellContent.length > maxWidth) {
                        maxWidth = cellContent.length;
                    }
                }
                cols.push({
                    wch: maxWidth + 2
                }); // Adiciona um pouco de espaço extra
            }
        }
        ws['!cols'] = cols;

        // Cria o workbook e adiciona a planilha
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Dados");

        // Gera o arquivo e inicia o download
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