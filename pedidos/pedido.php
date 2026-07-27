<?php
require_once '../config/config.php';
require_once '../config/session.php'; 
$nomeEmpresa = $empresa_nome ?? 'Empresa';

date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION["user_name"])) {
    header("Location: ../index.php");
    exit();
}

function exibirErro($mensagem, $titulo = "Ops! Algo deu errado") {
    if (headers_sent()) {
        echo "<div style='padding: 20px; color: red; text-align: center; font-family: sans-serif;'>";
        echo "<h1>" . htmlspecialchars($titulo) . "</h1>";
        echo "<p>" . htmlspecialchars($mensagem) . "</p>";
        echo "</div>";
        exit;
    }
    ?>
    <!doctype html>
    <html lang="pt-br">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars($titulo) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .error-card { max-width: 500px; width: 90%; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-radius: 12px; }
            .icon-box { width: 80px; height: 80px; background-color: #fee2e2; color: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        </style>
    </head>
    <body>
        <div class="card error-card">
            <div class="card-body text-center p-5">
                <div class="icon-box"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg></div>
                <h3 class="card-title mb-3"><?= htmlspecialchars($titulo) ?></h3>
                <p class="card-text text-muted mb-4"><?= htmlspecialchars($mensagem) ?></p>
                <div class="d-grid gap-2 col-8 mx-auto">
                    <a href="javascript:history.back()" class="btn btn-primary">Voltar</a>
                    <a href="../index.php" class="btn btn-outline-secondary">Ir para Início</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Processar aprovação/reprovação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if (!isset($_GET['codigo_pedido'])) { exibirErro("Código do pedido não informado."); }
    $cod_pedido = $_GET['codigo_pedido'];
    $acao = $_POST['acao'];
    if (!in_array($acao, ['aprovar', 'reprovar'])) { exibirErro("Ação inválida."); }
    
    $sql = "SELECT codigo FROM {$banco_vendas}.cad_orca WHERE codigo = ?";
    $stmt = $dash->prepare($sql);
    $stmt->bind_param("i", $cod_pedido);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows == 0) { exibirErro("Pedido não encontrado.", "Pedido não encontrado"); }
    $codigo_pedido = $result->fetch_assoc()['codigo'];
    $novo_status = $acao === 'aprovar' ? 'AI' : 'RE';
    
    $sql_update = "UPDATE {$banco_vendas}.cad_orca SET situacao = ? WHERE codigo = ?";
    $stmt_update = $dash->prepare($sql_update);
    $stmt_update->bind_param("si", $novo_status, $codigo_pedido);
    
    if ($stmt_update->execute()) {
        header("Location: ?codigo_pedido=" . urlencode($cod_pedido) . "&status=sucesso");
        exit();
    } else {
        exibirErro("Erro ao atualizar status do pedido.");
    }
}

if (!isset($_GET['codigo_pedido'])) { exibirErro("Código do pedido não informado."); }
$cod_pedido = $_GET['codigo_pedido'];

$sql = "SELECT codigo FROM {$banco_vendas}.cad_orca WHERE codigo = ?";
$stmt = $dash->prepare($sql);
$stmt->bind_param("i", $cod_pedido);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) { exibirErro("O pedido solicitado não foi encontrado.", "Pedido não encontrado"); }
$codigo_pedido = $result->fetch_assoc()['codigo'];

function fetchPedidos($dash, $banco_vendas, $banco_publico, $codigo_pedido) {
    $sql = "SELECT p.orcamento as codigo, p.produto, pro.descricao, p.desconto, p.quantidade, p.unitario as preco, p.total_liq as total, p.ipi as prod_ipi, p.subst as prod_sub 
            FROM ".$banco_vendas.".pro_orca p 
            LEFT JOIN ".$banco_publico.".cad_prod pro ON (pro.codigo = p.produto)
            WHERE p.orcamento = ?";
    $stmt = $dash->prepare($sql);
    $stmt->bind_param("i", $codigo_pedido);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchServicos($dash, $banco_vendas, $banco_publico, $codigo_pedido) {
    $sql = "SELECT p.orcamento as codigo, p.servico as produto, pro.aplicacao as descricao, p.desconto, p.quantidade, p.unitario as preco, p.unit_orig as total
            FROM ".$banco_vendas.".ser_orca p 
            LEFT JOIN ".$banco_publico.".cad_serv pro ON (pro.codigo = p.servico)
            WHERE p.orcamento = ?";
    $stmt = $dash->prepare($sql);
    $stmt->bind_param("i", $codigo_pedido);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchPedidoHeader($dash, $banco_vendas, $banco_publico, $codigo_pedido) {
    $query = "SELECT pe.total_geral, c.nome, c.cpf as cnpj, c.telefone_com, c.celular, c.cidade, c.cep, c.endereco, c.bairro, c.numero, pe.data_cadastro, pe.codigo, pe.total_produtos, pe.tipo, pe.desc_prod as descontos, pe.total_ipi, pe.valor_icms_sub, pe.situacao, pe.observacoes, pe.observacoes2
              FROM ".$banco_vendas.".cad_orca pe
              LEFT JOIN ".$banco_publico.".cad_clie c ON (c.codigo = pe.cliente)
              WHERE pe.codigo = ?";
    $stmt = $dash->prepare($query);
    $stmt->bind_param("i", $codigo_pedido);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function fetchPedidoParcelas($dash, $banco_vendas, $codigo_pedido) {
    $query = "SELECT parcela, valor, vencimento
              FROM ".$banco_vendas.".par_orca
              WHERE orcamento = ?";
    $stmt = $dash->prepare($query);
    $stmt->bind_param("i", $codigo_pedido);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchCustoInfo($dash, $banco_vendas, $codigo_pedido) {
    $query = "SELECT SUM(pro.custo_medio * pro.quantidade) as tcusto_medio, SUM(pro.ult_custo * pro.quantidade) as ult_custo, SUM(pro.total_liq) as prod_totalliqu, SUM(pro.desconto) as pro_desc
              FROM ".$banco_vendas.".pro_orca pro WHERE pro.orcamento = ?";
    $stmt = $dash->prepare($query);
    $stmt->bind_param("i", $codigo_pedido);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function fetchServicoTotal($dash, $banco_vendas, $codigo_pedido) {
    $query = "SELECT total_servicos, desc_serv FROM ".$banco_vendas.".cad_orca WHERE codigo = ?";
    $stmt = $dash->prepare($query);
    $stmt->bind_param("i", $codigo_pedido);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function fetchFilialInfo($dash, $banco_vendas) {
    $query = "SELECT em.razao_social as filial_razao, em.endereco as filial_endereco, em.numero as filial_numero, em.bairro as filial_bairro, em.telefone as filial_telefone, em.cidade as filial_cidade, em.cep as filial_cep, em.nome_fantasia as filial_fantasia, em.site as filial_site, em.email as filial_email
              FROM ".$banco_vendas.".empresas em LIMIT 1";
    $result = $dash->query($query);
    return $result->fetch_assoc();
}

function formatCurrency($value) { return number_format((float)$value, 2, ',', '.'); }
function calculatePercentage($part, $whole) { return ($whole > 0) ? ($part / $whole) * 100 : 0; }
function formatDate($dateString) {
    if (empty($dateString)) return '';
    $timestamp = strtotime($dateString);
    if ($timestamp === false) return '';
    $meses = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
    return date('j', $timestamp) . ' de ' . $meses[date('n', $timestamp)] . ', ' . date('Y', $timestamp);
}
function getStatusDescription($situacao) {
    $statusMap = ['EA' => 'Em Orçamento', 'FI' => 'Faturado', 'AI' => 'Aprovado', 'FP' => 'Faturado Parcial', 'RE' => 'Reprovado', 'BM' => 'Faturado', 'FT' => 'Faturado'];
    return $statusMap[$situacao] ?? $situacao;
}
function getStatusColor($situacao) {
    $colorMap = ['EA' => 'warning', 'FI' => 'success', 'AI' => 'info', 'FP' => 'primary', 'RE' => 'danger', 'BM' => 'success', 'FT' => 'success'];
    return $colorMap[$situacao] ?? 'secondary';
}
function getStatusIcon($situacao) {
    $iconMap = ['EA' => 'fa-file-invoice-dollar', 'FI' => 'fa-check-circle', 'AI' => 'fa-thumbs-up', 'FP' => 'fa-hourglass-half', 'RE' => 'fa-times-circle', 'BM' => 'fa-check-circle', 'FT' => 'fa-check-circle'];
    return $iconMap[$situacao] ?? 'fa-clock';
}
function getTipoDescricao($tipo) {
    $tipoMap = [1 => 'Pedido', 2 => 'Pedido', 3 => 'Ordem de Serviço', 4 => 'Contratos', 5 => 'Devolução'];
    return $tipoMap[$tipo] ?? 'Pedido';
}

$pedidos = fetchPedidos($dash, $banco_vendas, $banco_publico, $codigo_pedido);
$servicos = fetchServicos($dash, $banco_vendas, $banco_publico, $codigo_pedido);
$headerData = fetchPedidoHeader($dash, $banco_vendas, $banco_publico, $codigo_pedido);
$headerParcelas = fetchPedidoParcelas($dash, $banco_vendas, $codigo_pedido);
$custoInfo = fetchCustoInfo($dash, $banco_vendas, $codigo_pedido);
$servicoTotal = fetchServicoTotal($dash, $banco_vendas, $codigo_pedido);
$filialInfo = fetchFilialInfo($dash, $banco_vendas);

$defaultValues = ['nome' => '', 'cnpj' => '', 'celular' => '', 'telefone_com' => '', 'cidade' => '', 'cep' => '', 'endereco' => '', 'numero' => '', 'total_geral' => 0, 'total_produtos' => 0, 'descontos' => 0, 'total_ipi' => 0, 'valor_icms_sub' => 0, 'situacao' => 'EA'];
$headerData = $headerData ? array_merge($defaultValues, $headerData) : $defaultValues;

$situacao = $headerData['situacao'];
$status_descricao = getStatusDescription($situacao);
$status_color = getStatusColor($situacao);
$status_icon = getStatusIcon($situacao);
$tipo_pedido = $headerData['tipo'] ?? 0;
$tipo_descricao = getTipoDescricao($tipo_pedido);
$pronome = in_array($tipo_pedido, [3, 5]) ? 'sua' : 'seu';
$mostrar_botoes_aprovacao = ($situacao === 'EA');

$percentual_descontos = calculatePercentage($headerData['descontos'], $headerData['total_produtos']);
$total_geral_formatted = formatCurrency($headerData['total_geral']);
$valor_icms_sub_formatted = formatCurrency($headerData['valor_icms_sub']);
$total_produtos_formatted = formatCurrency($headerData['total_produtos']);
$total_descontos_formatted = formatCurrency($headerData['descontos']);

$ser_totalliqu_val = $servicoTotal['total_servicos'] ?? 0;
$ser_desc_val = $servicoTotal['desc_serv'] ?? 0;
$sertotalsemdescontos1 = $ser_totalliqu_val - $ser_desc_val;
$sertotalsemdescontos = formatCurrency($sertotalsemdescontos1);


$headerParcelas = $headerParcelas ?: [];

$filialDefaults = ['filial_razao' => 'N/A', 'filial_endereco' => 'N/A', 'filial_numero' => '', 'filial_bairro' => '', 'filial_telefone' => 'N/A', 'filial_cidade' => 'N/A', 'filial_cep' => '', 'filial_fantasia' => 'Company Name', 'filial_site' => '#', 'filial_email' => 'N/A'];
$filialInfo = $filialInfo ? array_merge($filialDefaults, $filialInfo) : $filialDefaults;
?>

<!doctype html>
<html lang="pt-br">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title><?= htmlspecialchars($filialInfo['filial_fantasia']) ?> | Pedido <?= $codigo_pedido ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.1/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../css/adminlte.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">

    <style>
    /* Estilos Gerais */
    h1 { text-align: center; color: #2c3e50; margin-bottom: 25px; }
    label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
    input[type=text] { width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 16px; }
    input[type=text]:focus { border-color: #007bff; outline: none; box-shadow: 0 0 0 2px rgba(0, 123, 255, .25); }

    .order-header { border-radius: 15px; box-shadow: 0 4px 15px rgba(138, 134, 134, 0.57); }
    .order-card { border-radius: 12px; box-shadow: 0 2px 10px rgba(138, 134, 134, 0.57); border: none; margin-bottom: 20px; transition: transform 0.2s; }
    .order-card:hover { transform: translateY(-2px); }
    .status-badge { padding: 8px 16px; border-radius: 20px; font-weight: 600; font-size: 0.85rem; border: 2px solid transparent; }
    
    .bg-warning { background-color: #977202ff !important; }
    .bg-success { background-color: #28a745 !important; }
    .bg-info { background-color: #17a2b8 !important; }
    .bg-primary { background-color: #007bff !important; }
    .bg-danger { background-color: #dc3545 !important; }

    .product-image { width: 60px; height: 60px; background: var(--bg-light); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--secondary-color); }
    .summary-card { background: linear-gradient(135deg, #6a6c74ff 0%, #2b282eff 100%); color: white; border-radius: 12px; }

    .timeline { position: relative; padding-left: 30px; }
    .timeline::before { content: ''; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px;  }
    .timeline-item { position: relative; margin-bottom: 20px; }
    .timeline-item::before { content: ''; position: absolute; left: -25px; top: 5px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 0 2px var(--border-color); }
    .timeline-item .card.bg-light { border-left: 4px solid var(--success-color) !important; }

    /* --- ESTILOS DE IMPRESSÃO --- */
    
    /* 1. Oculta a área matricial na tela normal */
    .matricial-print-area {
        display: none;
    }

    /* 2. Configurações APENAS para o momento da impressão */
    @media print {
        @page {
            margin: 0;
            size: auto;
        }

        /* Oculta tudo que não for a área matricial */
        body > *:not(.matricial-print-area) {
            display: none !important;
        }

        /* Reseta corpo da página */
        body {
            background-color: #fff !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Exibe a área matricial */
        .matricial-print-area {
            display: block !important;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            margin: 0;
            padding: 5mm;
            background-color: white;
            color: black;
            font-family: 'Courier New', Courier, monospace !important;
            font-size: 11px;
            z-index: 9999;
        }

        /* Layout Matricial */
        .matricial-header, .matricial-row, .matricial-footer {
            width: 100%;
        }

        .matricial-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }

        .matricial-divider {
            border-bottom: 1px dashed #000;
            margin: 10px 0;
            width: 100%;
            display: block;
        }

        .matricial-table {
            width: 100%;
            border-collapse: collapse;
        }

        .matricial-table thead {
            border-bottom: 1px dashed #000;
        }

        .matricial-table th {
            text-align: left;
            font-weight: normal;
        }

        .matricial-table td, .matricial-table th {
            padding: 2px 0;
        }

        /* Utilitários Impressão */
        .w-50 { width: 50%; }
        .w-25 { width: 25%; }
        .w-100 { width: 100%; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
    }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open" data-bs-theme="dark">

    <!--begin::App Wrapper-->
    <div class="app-wrapper">
        <?php require_once '../menu/nav_dash.php'; ?>
        <?php require_once '../menu/sidebar.php'; ?>
        <?php require_once '../menu/menu_dash.php'; ?>
        </aside>

        <!--begin::App Main-->
        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6">
                            <h3 class="page-header hidden-print">Pedido <small>#<?= $codigo_pedido ?></small></h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                <li class="breadcrumb-item"><a href="#">Home</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    <div class="container py-4">
                        <?php if (isset($_GET['status']) && $_GET['status'] === 'sucesso'): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Sucesso!</strong> Status do pedido atualizado.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <!-- Header Visual -->
                        <div class="order-header p-4 mb-4">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <p class="mb-2"><i class="fas fa-store me-2 "></i><strong><?= htmlspecialchars($filialInfo['filial_fantasia']) ?></strong></p>
                                    <p class="mb-2"><i class="fas fa-map-marker-alt me-2 "></i>
                                        <?= htmlspecialchars($filialInfo['filial_endereco']) ?>, <?= htmlspecialchars($filialInfo['filial_numero']) ?>,
                                        <?= htmlspecialchars($filialInfo['filial_bairro']) ?>, <?= htmlspecialchars($filialInfo['filial_cidade']) ?> - <?= htmlspecialchars($filialInfo['filial_cep']) ?>
                                    </p>
                                    <h2 class="h3 mb-2"><i class="fas fa-receipt me-2"></i><?= htmlspecialchars($tipo_descricao) ?></h2>
                                    <p class="mb-0 opacity-75">Acompanhe os detalhes do <?= $pronome ?> <?= strtolower(htmlspecialchars($tipo_descricao)) ?></p>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <p class="mb-2"><i class="fas fa-phone me-2 "></i><?= htmlspecialchars($filialInfo['filial_telefone']) ?></p>
                                    <p class="mb-2"><i class="fas fa-envelope me-2 "></i><?= htmlspecialchars($filialInfo['filial_email']) ?></p>
                                    <div class="h2 mb-1">#<?= $codigo_pedido ?></div>
                                    <div class="status-badge bg-<?= $status_color ?> d-inline-block">
                                        <i class="fas <?= $status_icon ?> me-1"></i> <?= $status_descricao ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Conteúdo Principal -->
                            <div class="col-lg-8">
                                <!-- Informações do Cliente -->
                                <div class="order-card p-4 mb-4">
                                    <h5 class="mb-3"><i class="fas fa-user me-2"></i>Informações do Cliente</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong><?= htmlspecialchars($headerData['nome']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($headerData['cnpj']) ?></small>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted">
                                                <i class="fas fa-phone me-1"></i><?= htmlspecialchars($headerData['telefone_com']) ?> / <?= htmlspecialchars($headerData['celular']) ?><br>
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?= htmlspecialchars($headerData['endereco']) ?>, <?= htmlspecialchars($headerData['numero']) ?> - <?= htmlspecialchars($headerData['bairro']) ?> - <?= htmlspecialchars($headerData['cidade']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Produtos -->
                                <?php if (!empty($pedidos)): ?>
                                <div class="order-card p-4 mb-4">
                                    <h5 class="mb-3"><i class="fas fa-boxes me-2 text-primary"></i>Produtos</h5>
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th>Produto</th>
                                                    <th class="text-center">Qtd</th>
                                                    <th class="text-end">Preço</th>
                                                    <th class="text-end">Desconto</th>
                                                    <th class="text-end">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pedidos as $pedido): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="product-image me-3"><i class="fas fa-box fa-lg"></i></div>
                                                            <div>
                                                                <h6 class="mb-0"><?= htmlspecialchars($pedido['descricao']) ?></h6>
                                                                <small class="text-muted">Cód: <?= htmlspecialchars($pedido['produto']) ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center align-middle"><?= number_format($pedido['quantidade'], 2, ',', '.') ?></td>
                                                    <td class="text-end align-middle">R$ <?= number_format($pedido['preco'], 2, ',', '.') ?></td>
                                                    <td class="text-end align-middle">R$ <?= number_format($pedido['desconto'], 2, ',', '.') ?></td>
                                                    <td class="text-end align-middle fw-bold">R$ <?= number_format($pedido['total'], 2, ',', '.') ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Serviços -->
                                <?php if (!empty($servicos)): ?>
                                <div class="order-card p-4 mb-4">
                                    <h5 class="mb-3"><i class="fas fa-tools me-2 text-primary"></i>Serviços</h5>
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th>Serviço</th>
                                                    <th class="text-center">Qtd</th>
                                                    <th class="text-end">Preço</th>
                                                    <th class="text-end">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($servicos as $servico): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="product-image me-3"><i class="fas fa-wrench fa-lg"></i></div>
                                                            <div>
                                                                <h6 class="mb-0"><?= htmlspecialchars($servico['descricao']) ?></h6>
                                                                <small class="text-muted">Cód: <?= htmlspecialchars($servico['produto']) ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center align-middle"><?= number_format($servico['quantidade'], 2, ',', '.') ?></td>
                                                    <td class="text-end align-middle">R$ <?= number_format($servico['preco'], 2, ',', '.') ?></td>
                                                    <td class="text-end align-middle fw-bold">R$ <?= number_format($servico['total'], 2, ',', '.') ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Observações -->
                                <?php if (!empty($headerData['observacoes']) || !empty($headerData['observacoes2'])): ?>
                                <div class="order-card p-4 mb-4">
                                    <h5 class="mb-3"><i class="fas fa-comment-alt me-2"></i>Observações</h5>
                                    <?php if (!empty($headerData['observacoes'])): ?>
                                    <p class="text-muted mb-2"><?= nl2br(htmlspecialchars($headerData['observacoes'])) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($headerData['observacoes2'])): ?>
                                    <p class="text-muted mb-0"><?= nl2br(htmlspecialchars($headerData['observacoes2'])) ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <!-- Timeline -->
                                <div class="order-card p-4 mb-4">
                                    <h5 class="mb-3"><i class="fas fa-history me-2"></i>Histórico do Pedido</h5>
                                    <div class="timeline">
                                        <div class="timeline-item">
                                            <div class="p-3 rounded border border-opacity-10 border-white">
                                                <div class="d-flex justify-content-between">
                                                    <strong>Pedido Realizado</strong>
                                                    <small class="text-muted"><?= formatDate($headerData['data_cadastro']) ?></small>
                                                </div>
                                                <p class="mb-0 text-muted small">O pedido foi criado e aguarda aprovação.</p>
                                            </div>
                                        </div>
                                        <?php if ($situacao == 'AI' || $situacao == 'FI' || $situacao == 'FT'): ?>
                                        <div class="timeline-item">
                                            <div class="p-3 rounded border border-opacity-10 border-white">
                                                <div class="d-flex justify-content-between">
                                                    <strong>Pedido Aprovado</strong>
                                                    <small class="text-muted"><i class="fas fa-check text-success"></i></small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($situacao == 'RE'): ?>
                                        <div class="timeline-item">
                                            <div class="p-3 rounded border border-danger border-opacity-25" style="background-color: rgba(220, 53, 69, 0.1);">
                                                <div class="d-flex justify-content-between">
                                                    <strong class="text-danger">Pedido Reprovado</strong>
                                                    <small class="text-muted"><i class="fas fa-times text-danger"></i></small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Sidebar com Totais -->
                            <div class="col-lg-4">
                                <div class="summary-card p-4 mb-4 sticky-top" style="top: 100px; z-index: 1;">
                                    <h5 class="mb-4">Resumo do Pedido</h5>
                                    <div class="d-flex justify-content-between mb-2"><span>Total Produtos</span><strong>R$ <?= $total_produtos_formatted ?></strong></div>
                                    <?php if ($sertotalsemdescontos1 > 0): ?>
                                    <div class="d-flex justify-content-between mb-2"><span>Total Serviços</span><strong>R$ <?= $sertotalsemdescontos ?></strong></div>
                                    <?php endif; ?>
                                    <?php if ($headerData['descontos'] > 0): ?>
                                    <div class="d-flex justify-content-between mb-2 text-warning"><span>Descontos</span><strong>- R$ <?= $total_descontos_formatted ?></strong></div>
                                    <?php endif; ?>
                                    <?php if ($headerData['total_ipi'] > 0): ?>
                                    <div class="d-flex justify-content-between mb-2"><span>IPI</span><strong>R$ <?= formatCurrency($headerData['total_ipi']) ?></strong></div>
                                    <?php endif; ?>
                                    <?php if ($headerData['valor_icms_sub'] > 0): ?>
                                    <div class="d-flex justify-content-between mb-2"><span>ICMS ST</span><strong>R$ <?= $valor_icms_sub_formatted ?></strong></div>
                                    <?php endif; ?>
                                    
                                    <hr class="my-3 bg-white opacity-25">
                                    <div class="d-flex justify-content-between mb-4"><span class="h5 mb-0">Total Geral</span><span class="h4 mb-0">R$ <?= $total_geral_formatted ?></span></div>

                                    <?php if ($mostrar_botoes_aprovacao): ?>
                                    <div class="d-grid gap-2 no-print">
                                        <form method="POST" class="d-grid gap-2">
                                            <button type="submit" name="acao" value="aprovar" class="btn btn-success btn-lg"><i class="fas fa-check me-2"></i>Aprovar Pedido</button>
                                            <button type="submit" name="acao" value="reprovar" class="btn btn-outline-light btn-sm mt-2" onclick="return confirm('Tem certeza que deseja reprovar este pedido?');"><i class="fas fa-times me-2"></i>Reprovar</button>
                                        </form>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-white bg-white bg-opacity-10 border-0 text-center mb-0 no-print">
                                        <i class="fas <?= $status_icon ?> mb-2 fa-2x"></i><br>
                                        Este pedido está <strong><?= strtolower($status_descricao) ?></strong>
                                    </div>
                                    <?php endif; ?>

                                    <div class="mt-4 text-center no-print">
                                        <button onclick="window.print()" class="btn btn-link text-white text-decoration-none">
                                            <i class="fas fa-print me-2"></i>Imprimir Pedido
                                        </button>
                                    </div>
                                </div>



                                <div class="summary-card p-4 mb-4 sticky-top" style="top: 100px; z-index: 1;">
                                    <h5 class="mb-4">Parcelas do Pedido</h5>
                                    <?php if (!empty($headerParcelas)): ?>
                                        <?php foreach ($headerParcelas as $idx => $parcela): ?>
                                            <div class="mb-3 <?= $idx < count($headerParcelas) - 1 ? 'border-bottom border-white border-opacity-10 pb-2' : '' ?>">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-warning">Parcela <?= $parcela['parcela'] ?></span>
                                                    <strong>R$ <?= formatCurrency($parcela['valor']) ?></strong>
                                                </div>
                                                <div class="d-flex justify-content-between small opacity-75">
                                                    <span>Vencimento</span>
                                                    <span><?= formatDate($parcela['vencimento']) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">Nenhuma parcela encontrada.</p>
                                    <?php endif; ?>
                                </div>
                            </div>



                            
                        </div>
                    </div>
                </div>
            </div>
        </main>
        
        <?php require_once '../menu/footer.php'; ?>
    </div>
    <!-- FIM DO APP-WRAPPER -->

    <!-- Área de Impressão Matricial (Oculta na tela, visível na impressão e fora do wrapper) -->
    <div class="matricial-print-area">
        <div class="matricial-header">
            <div class="matricial-row">
                <span class="w-50"><?= strtoupper($filialInfo['filial_fantasia']) ?></span>
                <span class="text-end w-50">DATA: <?= date('d/m/Y', strtotime($headerData['data_cadastro'])) ?></span>
            </div>
            <div class="matricial-row">
                <span>PEDIDO: <?= $codigo_pedido ?></span>
                <span class="text-end">TIPO: <?= strtoupper($tipo_descricao) ?></span>
            </div>
        </div>

        <div class="matricial-row">
            <span class="matricial-label">CLIENTE:</span>
            <span><?= strtoupper($headerData['nome']) ?></span>
        </div>
        <div class="matricial-row">
            <span class="matricial-label">CNPJ/CPF:</span>
            <span><?= $headerData['cnpj'] ?></span>
        </div>
        <div class="matricial-row">
            <span class="matricial-label">END:</span>
            <span><?= strtoupper($headerData['endereco']) ?>, <?= $headerData['numero'] ?></span>
        </div>
        <div class="matricial-row">
            <span class="matricial-label">CIDADE:</span>
            <span><?= strtoupper($headerData['cidade']) ?></span>
        </div>

        <div class="matricial-divider"></div>

        <table class="matricial-table">
            <thead>
                <tr>
                    <th>PRODUTO/SERVICO</th>
                    <th class="text-end">QTD</th>
                    <th class="text-end">UNIT</th>
                    <th class="text-end">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $pedido): ?>
                <tr>
                    <td><?= strtoupper(substr($pedido['descricao'], 0, 30)) ?></td>
                    <td class="text-end"><?= number_format($pedido['quantidade'], 2, ',', '.') ?></td>
                    <td class="text-end"><?= number_format($pedido['preco'], 2, ',', '.') ?></td>
                    <td class="text-end"><?= number_format($pedido['total'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>

                <?php foreach ($servicos as $servico): ?>
                <tr>
                    <td>[S] <?= strtoupper(substr($servico['descricao'], 0, 30)) ?></td>
                    <td class="text-end"><?= number_format($servico['quantidade'], 2, ',', '.') ?></td>
                    <td class="text-end"><?= number_format($servico['preco'], 2, ',', '.') ?></td>
                    <td class="text-end"><?= number_format($servico['total'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="matricial-divider"></div>

        <div class="matricial-totals">
            <div class="matricial-row">
                <span class="w-50"></span>
                <span class="w-25 text-end">TOTAL PROD:</span>
                <span class="w-25 text-end"><?= $total_produtos_formatted ?></span>
            </div>
            <?php if ($sertotalsemdescontos1 > 0): ?>
            <div class="matricial-row">
                <span class="w-50"></span>
                <span class="w-25 text-end">TOTAL SERV:</span>
                <span class="w-25 text-end"><?= $sertotalsemdescontos ?></span>
            </div>
            <?php endif; ?>
            <?php if ($headerData['descontos'] > 0): ?>
            <div class="matricial-row">
                <span class="w-50"></span>
                <span class="w-25 text-end">DESCONTOS:</span>
                <span class="w-25 text-end">-<?= $total_descontos_formatted ?></span>
            </div>
            <?php endif; ?>
            <div class="matricial-row">
                <span class="w-50"></span>
                <span class="w-25 text-end">TOTAL GERAL:</span>
                <span class="w-25 text-end"><?= $total_geral_formatted ?></span>
            </div>
        </div>

        <div class="matricial-footer">
            <div class="matricial-row">
                <span class="w-100 text-center">ASSINATURA DO CLIENTE</span>
            </div>
            <br><br>
            <div class="matricial-divider"></div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
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
</body>
</html>