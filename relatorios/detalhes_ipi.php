<?php
require_once '../config/config.php';
require_once '../config/session.php'; 

date_default_timezone_set('America/Sao_Paulo');


function convert_date_br_to_sql($date_input) {
    if (empty($date_input)) return '';
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date_input, $matches)) {
        return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
    }
    return $date_input;
}

$cfop = $_GET['cfop'] ?? '';
$filial = $_GET['filial'] ?? '';
$operacao = $_GET['operacao'] ?? '';
$data_inicio_raw = $_GET['data_inicio'] ?? '';
$data_fim_raw = $_GET['data_fim'] ?? '';


$data_inicio = convert_date_br_to_sql($data_inicio_raw);
$data_fim = convert_date_br_to_sql($data_fim_raw);


$params = [];
$where_conditions = [
    "s.consid_sped = 'S'",
    "n.cancelada = 'N'",
    "p.cfop = ?",
    "n.valor_ipi > 0"
   
];

$params[] = $cfop;

if ($filial) {
    $where_conditions[] = "n.filial = ?";
    $params[] = $filial;
}

if ($data_inicio) {
    $where_conditions[] = "n.data_saida_entrada >= ?";
    $params[] = $data_inicio;
}

if ($data_fim) {
    $where_conditions[] = "n.data_saida_entrada <= ?";
    $params[] = $data_fim;
}

if ($operacao) {
    $where_conditions[] = "tr.ENT_SAI = ?";
    $params[] = $operacao;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

$sql_notas_cfop = "
    SELECT 
    n.codigo,
        n.numero_nf, 
       sum(p.total) as total_produtos, 
        sum(p.valor_desconto) as desc_prod, 
        sum(p.frete) as valor_frete, 
        sum(p.outras_desp) as valor_outras_despesas, 
        sum(t.base_subst) as base_icms_sub,
        sum(t.valor_subst) as valor_icms_sub, 
        sum(t.valor_ipi) as valor_ipi, 
        sum(t.base_icms) as base_icms,
        sum(t.valor_icms) as valor_icms,
        sum(t.valor_pis) as pis, 
        sum(t.valor_cofins) as cofins, 
        n.data_saida_entrada
    FROM {$banco_vendas}.mvto_produtos p
    INNER JOIN {$banco_vendas}.cad_nf n ON n.CODIGO = p.CHAVE_MVTO  
    LEFT JOIN {$banco_vendas}.mvto_tributos t on (t.chave_mvto = p.chave_mvto and t.item = p.item)
    LEFT JOIN {$banco_vendas}.series s ON (s.codigo = n.serie AND s.filial = n.filial) 
    LEFT JOIN {$banco_vendas}.transacoes tr ON (tr.codigo = n.transacao AND tr.filial = n.filial)
    {$where_clause}
    GROUP BY n.codigo
    ORDER BY n.numero_nf ASC
";

$stmt = mysqli_prepare($dash, $sql_notas_cfop);
if ($stmt) {
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result_notas = mysqli_stmt_get_result($stmt);
    $notas_cfop = mysqli_fetch_all($result_notas, MYSQLI_ASSOC);
} else {
    $notas_cfop = [];
    echo "Erro na consulta: " . mysqli_error($dash);
}
?>

<!doctype html>
<html lang="pt-br">
<!--begin::Head-->

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Detalhes IPI</title>
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
        padding: 12px;
        margin-bottom: 20px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 16px;
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
                        <h3 class="mb-0">Notas Fiscais - CFOP <?= htmlspecialchars($cfop) ?></h3>

                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>

                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
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




                <div class="app-content">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Filtros Aplicados:</strong>
                                        CFOP: <?= htmlspecialchars($cfop) ?> |
                                        Filial: <?= $filial ? htmlspecialchars($filial) : 'Todas' ?> |
                                        Operação: <?= $operacao ? ($operacao == 'E' ? 'Entrada' : 'Saída') : 'Todas' ?> |
                                        Período: <?= htmlspecialchars($data_inicio_raw) ?> a
                                        <?= htmlspecialchars($data_fim_raw) ?>

                                        <button class="btn btn-success btn-sm float-end"
                                            onclick="exportToExcel('tabelaNotasCFOP', 'Notas_CFOP_<?= htmlspecialchars($cfop) ?>.xlsx')">
                                            <i class="bi bi-file-earmark-excel"></i> Exportar para Excel
                                        </button>


                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($notas_cfop)): ?>
                                        <div class="table-responsive">
                                            <table id="tabelaNotasCFOP"
                                                class="table table-striped table-bordered table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Nº NF</th>
                                                        <th>Data</th>
                                                        <th>Base Icms</th>
                                                        <th>V. Icms</th>
                                                        <th>Base Icms ST</th>
                                                        <th>V. Icms ST</th>
                                                        <th>Total Produtos</th>
                                                        <th>Desconto</th>
                                                        <th>Frete</th>
                                                        <th>Outras Despesas</th>
                                                        <th>IPI</th>
                                                        <th>PIS</th>
                                                        <th>COFINS</th>
                                                        <th>Detalhes</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $totais = [
                                                        'total_produtos' => 0,
                                                        'desc_prod' => 0,
                                                        'valor_frete' => 0,
                                                        'valor_outras_despesas' => 0,
                                                        'valor_icms_sub' => 0,
                                                        'valor_ipi' => 0,
                                                        'valor_icms' => 0,
                                                        'pis' => 0,
                                                        'cofins' => 0,
                                                      //  'total_nf' => 0,
                                                        'base_icms' => 0,
                                                        'base_icms_sub' => 0
                                                    ];
                                                    
                                                    foreach ($notas_cfop as $nota): 
                                                        // Somar aos totais
                                                        foreach ($totais as $key => $value) {
                                                            $totais[$key] += $nota[$key] ?? 0;
                                                        }
                                                    ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($nota['numero_nf']) ?></td>
                                                        <td>
                                                            <?= date('d/m/Y', strtotime($nota['data_saida_entrada'])) ?>
                                                        </td>

                                                        <td>R$ <?= number_format($nota['base_icms'], 2, ",", ".") ?>
                                                        </td>


                                                        <td>R$ <?= number_format($nota['valor_icms'], 2, ",", ".") ?>
                                                        </td>
                                                        <td>R$
                                                            <?= number_format($nota['base_icms_sub'], 2, ",", ".") ?>
                                                        </td>
                                                        <td>R$
                                                            <?= number_format($nota['valor_icms_sub'], 2, ",", ".") ?>
                                                        </td>
                                                        <td>R$
                                                            <?= number_format($nota['total_produtos'], 2, ",", ".") ?>
                                                        </td>
                                                        <td>R$ <?= number_format($nota['desc_prod'], 2, ",", ".") ?>
                                                        </td>
                                                        <td>R$ <?= number_format($nota['valor_frete'], 2, ",", ".") ?>
                                                        </td>
                                                        <td>R$
                                                            <?= number_format($nota['valor_outras_despesas'], 2, ",", ".") ?>
                                                        </td>

                                                        <td>R$ <?= number_format($nota['valor_ipi'], 2, ",", ".") ?>
                                                        </td>
                                                        <td>R$ <?= number_format($nota['pis'], 2, ",", ".") ?></td>
                                                        <td>R$ <?= number_format($nota['cofins'], 2, ",", ".") ?></td>
                                                     <!-- <td>R$ <?= number_format($nota['total_nf'], 2, ",", ".") ?></td> -->

                                                        <td><a href="detalhes_nota.php?nf=<?= $nota['codigo'] ?>"
                                                                class="btn btn-sm btn-outline-primary">Ver
                                                                Detalhes</a></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th>TOTAIS</th>
                                                        <th></th>
                                                        <th>R$ <?= number_format($totais['base_icms'], 2, ",", ".") ?>
                                                        </th>

                                                        <th>R$ <?= number_format($totais['valor_icms'], 2, ",", ".") ?>
                                                        </th>


                                                        <th>R$
                                                            <?= number_format($totais['base_icms_sub'], 2, ",", ".") ?>
                                                        </th>
                                                        <th>R$
                                                            <?= number_format($totais['valor_icms_sub'], 2, ",", ".") ?>
                                                        </th>


                                                        <th>R$
                                                            <?= number_format($totais['total_produtos'], 2, ",", ".") ?>
                                                        </th>
                                                        <th>R$ <?= number_format($totais['desc_prod'], 2, ",", ".") ?>
                                                        </th>
                                                        <th>R$ <?= number_format($totais['valor_frete'], 2, ",", ".") ?>
                                                        </th>
                                                        <th>R$
                                                            <?= number_format($totais['valor_outras_despesas'], 2, ",", ".") ?>
                                                        </th>

                                                        <th>R$ <?= number_format($totais['valor_ipi'], 2, ",", ".") ?>
                                                        </th>

                                                        <th>R$ <?= number_format($totais['pis'], 2, ",", ".") ?></th>
                                                        <th>R$ <?= number_format($totais['cofins'], 2, ",", ".") ?></th>
                                                    <!--    <th>R$ <?= number_format($totais['total_nf'], 2, ",", ".") ?>
                                                        </th> -->
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                        <div class="alert alert-info">
                                            Nenhuma nota fiscal encontrada para o CFOP <?= htmlspecialchars($cfop) ?>
                                            com os filtros aplicados.
                                        </div>

                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <script>
                function exportToExcel(tableId, filename) {
                    const table = document.getElementById(tableId);
                    const wb = XLSX.utils.table_to_book(table, {
                        sheet: "Dados"
                    });
                    XLSX.writeFile(wb, filename);
                }
                </script>




    </main>
    <!--end::App Main-->
    <!--begin::Footer-->
    <footer class="app-footer bg-dark" data-bs-thme="dark">
        <!--begin::To the end-->

        <!--end::To the end-->
        <!--begin::Copyright-->
        <strong>
            Copyright &copy; <?= date('Y') ?> &nbsp;
            <a href="https://intersig.com.br" class="text-decoration-none">Intersig.com.br</a>.
        </strong>
        All rights reserved.
        <!--end::Copyright-->
    </footer>
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