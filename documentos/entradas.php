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


date_default_timezone_set('America/Sao_Paulo');

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
$numero_nota = $_GET['numero_nota'] ?? '';
$operacao = $_GET['operacao'] ?? '';
$cidade = $_GET['cidade'] ?? '';
$nome = $_GET['nome'] ?? '';
$serie = $_GET['serie'] ?? '';
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
    $filtros_dinamicos[] = "n.filial = ?";
    $params[] = $filial;
}
if ($chave_mvto) {
    $filtros_dinamicos[] = "n.codigo = ?";
    $params[] = $chave_mvto;
}
if ($data_inicio) {
    $filtros_dinamicos[] = "n.data_saida_entrada >= ?";
    $params[] = $data_inicio;
}
if ($data_fim) {
    $filtros_dinamicos[] = "n.data_saida_entrada <= ?"; 
    $params[] = $data_fim;
}
if ($numero_nota) {
    $filtros_dinamicos[] = "n.numero_nf = ?";
    $params[] = $numero_nota;
}
if ($operacao) {
    $filtros_dinamicos[] = "t.codigo = ?";
    $params[] = $operacao;
}
if ($cidade) {
    $filtros_dinamicos[] = "(c.cidade LIKE ? OR f.cidade LIKE ?)";
    $params[] = "%$cidade%";
    $params[] = "%$cidade%";
}
if ($nome) {
    $filtros_dinamicos[] = "(c.nome LIKE ? OR f.razao_social LIKE ?)";
    $params[] = "%$nome%";
    $params[] = "%$nome%";
}
if ($serie) {
    $filtros_dinamicos[] = "n.serie = ?";
    $params[] = $serie;
}

$where = "WHERE " . $condicoes_fixas;
if (!empty($filtros_dinamicos)) {
    $where .= " AND " . implode(' AND ', $filtros_dinamicos);
}

// Obter lista de filiais
$result_filial = mysqli_query($dash, "SELECT filial, nome_fantasia FROM {$banco_vendas}.empresas WHERE ativa = 'S' ORDER BY filial ASC");
$filiais = [];
while ($row = mysqli_fetch_assoc($result_filial)) {
    $filiais[] = [
        'codigo' => $row['filial'],
        'nome' => $row['nome_fantasia']
    ];
}

// Obter lista de operações (transações) para o select dinâmico
$result_operacoes = mysqli_query($dash, "SELECT DISTINCT t.codigo, t.nome 

                                        FROM {$banco_vendas}.transacoes t 
                                        WHERE t.ent_sai = 'E' 
                                          AND t.ativa = 'S'  
                                        ORDER BY t.nome ASC");
$operacoes = [];
while ($row = mysqli_fetch_assoc($result_operacoes)) {
    $operacoes[] = [
        'codigo' => $row['codigo'],
        'nome' => $row['nome']
    ];
}


$result_series = mysqli_query($dash, "SELECT DISTINCT t.codigo, t.nome 
                                        FROM {$banco_vendas}.series t 
                                        WHERE t.consid_sped = 'S' 
                                        AND t.ativa = 'S'
                                        ORDER BY t.nome ASC");
$series = [];
while ($row = mysqli_fetch_assoc($result_series)) {
    $series[] = [
        'codigo' => $row['codigo'],
        'nome' => $row['nome']
    ];
}

// Consulta principal para resumo por CFOP
// ----------------------------------------------------------------------------------------------
if (!$chave_mvto) {
    $sqlResumoCFOP = "SELECT n.codigo as codigo, t.ent_sai, n.filial, n.numero_nf, CONCAT(s.codigo,'-',s.nome) as serie, CONCAT(t.codigo,'-',t.nome) as operacao, n.codigo_cli_for, 
n.cli_for, n.desc_prod as descontos, t.tipo,s.nome as serie1, n.base_icms as baseicms, n.valor_icms as valoricms, n.valor_ipi as ipi_cfop, n.pis as pis_cfop, n.cofins as cofins_cfop, n.valor_icms_sub as icmsst_cfop,
    n.valor_icms_uf_dest as icms_uf_dest_cfop, n.valor_fcp_uf_dest as fcp_uf_dest_cfop,
CASE n.cli_for
    WHEN 'C' THEN LEFT(CONCAT(c.codigo, ' - ', c.nome), 30)
    WHEN 'F' THEN LEFT(CONCAT(f.codigo, ' - ', f.razao_social), 30)
END AS nome, 
    CASE n.cli_for
    WHEN 'C' THEN c.cidade
    WHEN 'F' THEN f.cidade
END AS cidade, 
CASE n.cli_for
    WHEN 'C' THEN c.estado
    WHEN 'F' THEN f.estado
END AS uf,
    n.data_emissao as data_emissao, n.total_produtos, n.total_nf, n.situacao_nfe as situacao
FROM ".$banco_vendas.".cad_nf n 
JOIN ".$banco_vendas.".transacoes t ON (t.codigo = n.transacao AND t.filial = n.filial) 
LEFT JOIN ".$banco_publico.".cad_clie c ON (n.codigo_cli_for = c.codigo AND n.cli_for = 'C')
LEFT JOIN ".$banco_publico.".cad_forn f ON (n.codigo_cli_for = f.codigo AND n.cli_for = 'F')
LEFT JOIN ".$banco_vendas.".series s ON (s.codigo = n.serie AND s.filial = n.filial)

    {$where}
    
AND t.ent_sai = 'E'
";

if (!empty($uf)) {$sqlResumoCFOP .= " AND (
        (n.cli_for = 'C' AND c.estado = '$uf')
        OR (n.cli_for = 'F' AND f.estado = '$uf')
    )";}
  
$sqlResumoCFOP .= "
    ORDER BY n.data_saida_entrada ASC";

    $dadosResumoCFOP = mysqli_fetch_all(executar_consulta_preparada($dash, $sqlResumoCFOP, $params), MYSQLI_ASSOC);
}

echo "<!--"; var_dump($sqlResumoCFOP); echo "-->";
?>

<!doctype html>
<html lang="pt-br">
<!--begin::Head-->

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Entradas</title>
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

    td {
        padding: 8px;
        border-bottom: 1px solid #ddd;
        font-size: 10px;
    }

    th {
        background-color: #f4f4f4;
        padding: 10px;
        text-align: left;
        font-size: 12px;
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

    .select-filial,
    .select-operacao {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #444;
        border-radius: 8px;
        background-color: #1e1e1e;
        color: #fff;
        font-size: 14px;
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

    .select-filial:hover,
    .select-operacao:hover {
        border-color: #00bcd4;
    }

    .select-filial:focus,
    .select-operacao:focus {
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
                        <h3 class="mb-0">Entradas</h3>
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

                                                <!-- Filtro por Número da Nota -->
                                                <div class="col-md-2">
                                                    <label class="form-label">Nº Nota</label>
                                                    <input type="text" name="numero_nota"
                                                        class="form-control form-control-sm"
                                                        value="<?= htmlspecialchars($numero_nota) ?>"
                                                        placeholder="Número da nota">
                                                </div>


                                                <div class="col-md-2">
                                                    <label class="form-label">Operação</label>
                                                    <select name="operacao" class="select-operacao form-control-sm">
                                                        <option value="">Todas</option>
                                                        <?php foreach ($operacoes as $op): ?>
                                                        <option value="<?= $op['codigo'] ?>"
                                                            <?= ($op['codigo'] == $operacao ? 'selected' : '') ?>>
                                                            <?= $op['codigo'] ?> - <?= htmlspecialchars($op['nome']) ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <!-- Filtro por Cidade -->
                                                <div class="col-md-2">
                                                    <label class="form-label">Cidade</label>
                                                    <input type="text" name="cidade"
                                                        class="form-control form-control-sm"
                                                        value="<?= htmlspecialchars($cidade) ?>"
                                                        placeholder="Nome da cidade">
                                                </div>
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

                                                <!-- Filtro por Nome -->
                                                <div class="col-md-2">
                                                    <label class="form-label">Nome</label>
                                                    <input type="text" name="nome" class="form-control form-control-sm"
                                                        value="<?= htmlspecialchars($nome) ?>"
                                                        placeholder="Nome cliente/fornecedor">
                                                </div>

                                                <!-- Filtro por Série -->
                                                <div class="col-md-2">
                                                    <label class="form-label">Série</label>
                                                    <select name="serie" class="select-operacao form-control-sm">
                                                        <option value="">Todas</option>
                                                        <?php foreach ($series as $se): ?>
                                                        <option value="<?= $se['codigo'] ?>"
                                                            <?= ($se['codigo'] == $serie ? 'selected' : '') ?>>
                                                            <?= $se['codigo'] ?> - <?= htmlspecialchars($se['nome']) ?>
                                                        </option>
                                                        <?php endforeach; ?>
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

                    <?php if (!empty($dadosResumoCFOP)): ?>
                    <!-- Resumo por CFOP -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <strong></strong>
                                    <button class="btn btn-success btn-sm"
                                        onclick="exportToExcel('tabelaCfopDetalhada', 'Resumo_CFOP_Detalhado.xlsx')">
                                        <i class="bi bi-file-earmark-excel"></i> Exportar para Excel
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="tabelaCfopDetalhada"
                                            class="table table-striped table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Ver</th>
                                                    <th>Filial</th>
                                                    <th>Nf</th>
                                                    <th>Data</th>
                                                    <th>Serie</th>
                                                    <th>Operação</th>
                                                    <th>Nome</th>
                                                    <th>Cidade</th>
                                                    <th>UF</th>
                                                    <th>Produtos</th>
                                                    <th>Descontos</th>
                                                    <th>Total Nf</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $total_venda_geral_cfop = 0;
                                                $total_desconto_geral_cfop = 0;
                                                $total_baseicms_geral_cfop = 0;

                                                foreach ($dadosResumoCFOP as $c): 
                                                    $total_venda_geral_cfop += $c['total_nf'];
                                                    $total_desconto_geral_cfop += $c['descontos'];
                                                    $total_baseicms_geral_cfop += $c['total_produtos'];
                                                ?>
                                                <tr>
                                                    <td>
                                                        <button class="btn btn-primary btn-sm"
                                                            onclick="verNotasCFOP('<?= htmlspecialchars($c['codigo']) ?>')"
                                                            title="Ver Detalhes da Nota">
                                                            <i class="bi bi-eye"></i> Ver
                                                        </button>
                                                    </td>
                                                    <td><?= htmlspecialchars($c['filial']) ?></td>
                                                    <td><?= number_format($c['numero_nf'], 0, ",", ".") ?></td>
                                                    <td><?= htmlspecialchars($c['data_emissao']) ?></td>
                                                    <td><?= htmlspecialchars($c['serie']) ?></td>
                                                    <td><?= htmlspecialchars($c['operacao']) ?></td>
                                                    <td><?= htmlspecialchars($c['nome']) ?></td>
                                                    <td><?= htmlspecialchars($c['cidade']) ?></td>
                                                    <td><?= htmlspecialchars($c['uf']) ?></td>
                                                    <td>R$ <?= number_format($c['total_produtos'], 2, ",", ".") ?></td>
                                                    <td>R$ <?= number_format($c['descontos'], 2, ",", ".") ?></td>
                                                    <td>R$ <?= number_format($c['total_nf'], 2, ",", ".") ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th colspan="9">TOTAIS</th>
                                                    <th>R$ <?= number_format($total_baseicms_geral_cfop, 2, ",", ".") ?>
                                                    </th>
                                                    <th>R$ <?= number_format($total_desconto_geral_cfop, 2, ",", ".") ?>
                                                    </th>
                                                    <th>R$ <?= number_format($total_venda_geral_cfop, 2, ",", ".") ?>
                                                    </th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!$chave_mvto): ?>
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
                    const anoAtual = hoje.getFullYear();
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
                        case 'primeiro_trimestre':
                            dataInicio = formatDate(new Date(anoAtual, 0, 1));
                            dataFim = formatDate(new Date(anoAtual, 2, 31));
                            break;
                        case 'segundo_trimestre':
                            dataInicio = formatDate(new Date(anoAtual, 3, 1));
                            dataFim = formatDate(new Date(anoAtual, 5, 30));
                            break;
                        case 'terceiro_trimestre':
                            dataInicio = formatDate(new Date(anoAtual, 6, 1));
                            dataFim = formatDate(new Date(anoAtual, 8, 30));
                            break;
                        case 'quarto_trimestre':
                            dataInicio = formatDate(new Date(anoAtual, 9, 1));
                            dataFim = formatDate(new Date(anoAtual, 11, 31));
                            break;
                        case 'primeiro_semestre':
                            dataInicio = formatDate(new Date(anoAtual, 0, 1));
                            dataFim = formatDate(new Date(anoAtual, 5, 30));
                            break;
                        case 'segundo_semestre':
                            dataInicio = formatDate(new Date(anoAtual, 6, 1));
                            dataFim = formatDate(new Date(anoAtual, 11, 31));
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

                function exportToExcel(tableId, filename) {
                    const table = document.getElementById(tableId);
                    const wb = XLSX.utils.table_to_book(table, {
                        sheet: "Dados"
                    });
                    XLSX.writeFile(wb, filename);
                }
                </script>

            </div>
        </div>
    </main>


    <?php 
        require_once '../menu/footer.php';
        ?>

    </div>

    <!-- Scripts -->
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
</body>

</html>