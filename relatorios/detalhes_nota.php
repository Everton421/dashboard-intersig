<?php 
// --- NO CADASTRO DA SERIE ELE PEGA OS VALORES SOMENTE O QUE ESTIVER MARCADA PARA ENVIAR SPED ---
        require_once '../config/config.php';
        require_once '../config/session.php'; 

date_default_timezone_set('America/Sao_Paulo');



// --- FUNÇÃO PARA EXECUTAR CONSULTAS PREPARADAS ---
function executar_consulta_preparada($dash, $sql, $parametros = [])
{
    $stmt = mysqli_prepare($dash, $sql);
    if (!$stmt) {
        die("Erro ao preparar consulta: " . mysqli_error($dash));
    }

    if (!empty($parametros)) {
        $tipos = str_repeat('s', count($parametros));
        mysqli_stmt_bind_param($stmt, $tipos, ...$parametros);
    }

    if (!mysqli_stmt_execute($stmt)) {
        die("Erro ao executar consulta: " . mysqli_stmt_error($stmt));
    }

    return mysqli_stmt_get_result($stmt);
}

// --- CAPTURA DO PARÂMETRO ---
$chave_mvto = $_GET['nf'] ?? '';
$custo_nf = 0;
$nf = null; // Inicia a variável como nula

if (!empty($chave_mvto)) {
    $sql_nf_unica = "SELECT * FROM {$banco_vendas}.cad_nf WHERE codigo = ?";
    $result_nf = executar_consulta_preparada($dash, $sql_nf_unica, [$chave_mvto]);
    $nf = mysqli_fetch_assoc($result_nf);

    if ($nf) { // Garante que a nota fiscal foi encontrada antes de prosseguir
        $result_prod = executar_consulta_preparada($dash, "SELECT * FROM {$banco_vendas}.mvto_produtos WHERE CHAVE_MVTO = ?", [$chave_mvto]);
        $produtos = mysqli_fetch_all($result_prod, MYSQLI_ASSOC);

          

        $result_trib = executar_consulta_preparada($dash, "SELECT * 
FROM {$banco_vendas}.mvto_produtos m
LEFT JOIN {$banco_vendas}.mvto_tributos t on (t.CHAVE_MVTO = m.CHAVE_MVTO and t.ITEM = m.ITEM) WHERE m.CHAVE_MVTO = ?", [$chave_mvto]);
        $tributos = mysqli_fetch_all($result_trib, MYSQLI_ASSOC);

       
        $total_icms          = array_sum(array_column($tributos, 'VALOR_ICMS'));
        $total_ipi           = array_sum(array_column($tributos, 'VALOR_IPI'));
        $total_pis           = array_sum(array_column($tributos, 'VALOR_PIS'));
        $total_cofins        = array_sum(array_column($tributos, 'VALOR_COFINS'));
        $total_basest            = array_sum(array_column($tributos, 'BASE_SUBST'));
        $total_st            = array_sum(array_column($tributos, 'VALOR_SUBST'));
        $total_fcp           = array_sum(array_column($tributos, 'VALOR_FCP_UF_DEST'));
        $total_icms_uf_dest  = array_sum(array_column($tributos, 'VALOR_ICMS_UF_DEST'));
        $total_iss           = array_sum(array_column($tributos, 'OUTRAS_DESP')); 
        $total_frete           = array_sum(array_column($tributos, 'FRETE')); 
        $total_seguro         = array_sum(array_column($tributos, 'SEGURO')); 
        
        foreach ($produtos as $item) {
            
            $quantidade = (float)($item['QUANTIDADE'] ?? 0);
            $custo_medio = (float)($item['CUSTO_MEDIO'] ?? 0);
            $custo_nf += $quantidade * $custo_medio;
            
        }

        $venda_total_nf = (float)($nf['TOTAL_NF'] ?? 0);
        $venda_total_produtosnf = (float)($nf['TOTAL_PRODUTOS'] ?? 0);
        $total_frete_seguro = $total_frete + $total_seguro;
        $total_impostos_nf = $total_icms + $total_ipi + $total_pis + $total_cofins + $total_st + $total_fcp + $total_icms_uf_dest + $total_iss;
        $lucro_bruto_nf = $venda_total_nf - $custo_nf - $total_impostos_nf;
    }
}
?>


<!doctype html>
<html lang="pt-br">
<!--begin::Head-->

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Detalhes da Nf</title>
    <!--begin::Accessibility Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <meta name="supported-color-schemes" content="light dark" />
    <link rel="preload" href="../css/adminlte.css" as="style" />
    <!--end::Accessibility Features-->

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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
                        <h3 class="mb-0">NF
                            #<?= htmlspecialchars($nf['NUMERO_NF']) ?></h3>
                        <p class="mb-0 opacity-75">Emitida em
                            <?= date('d/m/Y H:i', strtotime($nf['DATA_SAIDA_ENTRADA'] . ' ' . $nf['HORA_SAIDA_ENTRADA'])) ?>
                        </p>
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




                <?php if ($nf): ?>


                <!-- Gráficos da NF -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <strong>Impostos da Nota Fiscal</strong>
                            </div>
                            <div class="card-body">
                                <div class="card-chart-container">
                                    <canvas id="graficoImpostos"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <strong>Composição Financeira da NF</strong>
                            </div>
                            <div class="card-body">
                                <div class="card-chart-container">
                                    <canvas id="graficoProdutos"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumo da NF -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <strong>Resumo da Nota Fiscal</strong>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-4">Filial:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($nf['FILIAL']) ?></dd>

                                    <dt class="col-sm-4">Série:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($nf['SERIE']) ?></dd>

                                    <dt class="col-sm-4">Número:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($nf['NUMERO_NF']) ?></dd>



                                    <dt class="col-sm-4">Desconto:</dt>
                                    <dd class="col-sm-8">R$
                                        <?= number_format($nf['DESC_PROD'], 2, ',', '.') ?></dd>

                                    <dt class="col-sm-4">Outras Desp.:</dt>
                                    <dd class="col-sm-8">R$ <?= number_format($total_iss, 2, ',', '.') ?>
                                    </dd>

                                    <dt class="col-sm-4">Frete/Seguro.:</dt>
                                    <dd class="col-sm-8">R$ <?= number_format($total_frete_seguro, 2, ',', '.') ?>
                                    </dd>

                                    <dt class="col-sm-4">Total Produtos:</dt>
                                    <dd class="col-sm-8">R$
                                        <?= number_format($nf['TOTAL_PRODUTOS'], 2, ',', '.') ?></dd>

                                    <dt class="col-sm-4">Total Nota:</dt>
                                    <dd class="col-sm-8"><span class="highlight">R$
                                            <?= number_format($nf['TOTAL_NF'], 2, ',', '.') ?></span></dd>
                                </dl>
                            </div>
                        </div>
                    </div>



                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <strong>Totais de Impostos</strong>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-6">ICMS:</dt>
                                    <dd class="col-sm-6">R$ <?= number_format($total_icms, 2, ',', '.') ?>
                                    </dd>

                                    <dt class="col-sm-6">IPI:</dt>
                                    <dd class="col-sm-6">R$ <?= number_format($total_ipi, 2, ',', '.') ?>
                                    </dd>

                                    <dt class="col-sm-6">PIS:</dt>
                                    <dd class="col-sm-6">R$ <?= number_format($total_pis, 2, ',', '.') ?>
                                    </dd>

                                    <dt class="col-sm-6">COFINS:</dt>
                                    <dd class="col-sm-6">R$ <?= number_format($total_cofins, 2, ',', '.') ?>
                                    </dd>

                                    <!-- CORREÇÃO 4: Exibição correta dos valores na tabela -->
                                    <dt class="col-sm-6">ICMS-ST:</dt>
                                    <dd class="col-sm-6">R$ <?= number_format($total_st, 2, ',', '.') ?>
                                    </dd>

                                    <dt class="col-sm-6">DIFAL:</dt>
                                    <dd class="col-sm-6">R$ <?= number_format($total_icms_uf_dest, 2, ',', '.') ?>
                                    </dd>

                                    <dt class="col-sm-6">FCP:</dt>
                                    <dd class="col-sm-6">R$ <?= number_format($total_fcp, 2, ',', '.') ?>
                                    </dd>




                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Produtos da NF -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <strong>Itens da Nota Fiscal (<?= count($produtos) ?> produtos)</strong>
                                <span class="badge bg-primary">CFOP:
                                    <?= htmlspecialchars($produtos[0]['CFOP'] ?? '-') ?></span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Produto</th>
                                                <th>Cfop</th>
                                                <th>Unidade</th>
                                                <th>Quantidade</th>
                                                <th>Valor Unit.</th>
                                                <th>Desconto</th>
                                                <th>Total</th>
                                                <th>Cst Icms</th>
                                                <th>Alíquota Icms</th>
                                                <th>Cst Ipi</th>
                                                <th>Alíquota Ipi</th>
                                                <th>Cst Cofins</th>
                                                <th>Alíquota Cofins</th>
                                                <th>Cst Pis</th>
                                                <th>Alíquota Pis</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($produtos as $item):
                                    ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['ITEM']) ?></td>
                                                <td><?= htmlspecialchars($item['PRODUTO']) ?></td>
                                                <td><?= htmlspecialchars($item['CFOP']) ?></td>
                                                <td><?= htmlspecialchars($item['UNIDADE']) ?></td>
                                                <td><?= number_format($item['QUANTIDADE'], 2, ',', '.') ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($item['VALOR_UNITARIO'], 2, ',', '.') ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($item['VALOR_DESCONTO'], 2, ',', '.') ?>
                                                </td>
                                                <td><strong>R$
                                                        <?= number_format($item['TOTAL'], 2, ',', '.') ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($item['CST']) ?></td>
                                                <td><?= htmlspecialchars($item['ALIQ_ICMS']) ?></td>
                                                <td><?= htmlspecialchars($item['CST_IPI']) ?></td>
                                                <td><?= htmlspecialchars($item['ALIQ_VAL_IPI']) ?></td>
                                                <td><?= htmlspecialchars($item['CST_COFINS']) ?></td>
                                                <td><?= htmlspecialchars($item['ALIQ_COFINS']) ?></td>
                                                <td><?= htmlspecialchars($item['CST_PIS']) ?></td>
                                                <td><?= htmlspecialchars($item['ALIQ_PIS']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detalhes dos Tributos -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <strong>Detalhamento dos Tributos por Item</strong>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Produto</th>
                                                <th>Base ICMS</th>
                                                <th>Valor ICMS</th>
                                                <th>Base ST</th>
                                                <th>Valor ST</th>
                                                <th>Base IPI</th>
                                                <th>Valor IPI</th>
                                                <th>Base PIS/COFINS</th>
                                                <th>Valor PIS</th>
                                                <th>Valor COFINS</th>
                                                <th>Valor OUTRAS</th>
                                                <th>Valor FRETE</th>
                                                <th>Valor SEGURO</th>
                                                <th>Difal</th>
                                                <th>FCP</th>

                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tributos as $trib): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($trib['ITEM']) ?></td>
                                                <td><?= htmlspecialchars($trib['PRODUTO']) ?></td>
                                                <td>R$ <?= number_format($trib['BASE_ICMS'], 2, ',', '.') ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($trib['VALOR_ICMS'], 2, ',', '.') ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($trib['BASE_SUBST'], 2, ',', '.') ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($trib['VALOR_SUBST'], 2, ',', '.') ?>
                                                </td>
                                                <td>R$ <?= number_format($trib['BASE_IPI'], 2, ',', '.') ?>
                                                </td>
                                                <td>R$ <?= number_format($trib['VALOR_IPI'], 2, ',', '.') ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($trib['BASE_PIS_COFINS'], 2, ',', '.') ?>
                                                </td>
                                                <td>R$ <?= number_format($trib['VALOR_PIS'], 2, ',', '.') ?>
                                                </td>

                                                <td>R$
                                                    <?= number_format($trib['VALOR_COFINS'], 2, ',', '.') ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($trib['VALOR_ICMS_UF_DEST'], 2, ',', '.') ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($trib['VALOR_FCP_UF_DEST'], 2, ',', '.') ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($trib['OUTRAS_DESP'], 2, ',', '.') ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($trib['FRETE'], 2, ',', '.') ?>
                                                </td>
                                                <td>R$
                                                    <?= number_format($trib['SEGURO'], 2, ',', '.') ?>
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
                <?php endif; ?>





                <script>
                <?php if ($nf): ?>
                // --- CORREÇÃO 5: Atualização dos dados e labels do gráfico de impostos ---
                const ctx1 = document.getElementById('graficoImpostos').getContext('2d');
                new Chart(ctx1, {
                    type: 'bar',
                    data: {
                        labels: ['ICMS', 'IPI', 'PIS', 'COFINS', 'ICMS-ST', 'DIFAL', 'FCP', 'OUTRAS DESP.'],
                        datasets: [{
                            label: 'Valor (R$)',
                            data: [
                                <?= $total_icms ?>,
                                <?= $total_ipi ?>,
                                <?= $total_pis ?>,
                                <?= $total_cofins ?>,
                                <?= $total_st ?>, // Usando a variável correta
                                <?= $total_icms_uf_dest ?>,
                                <?= $total_fcp ?>,
                                <?= $total_iss ?>
                            ],
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.6)',
                                'rgba(255, 99, 132, 0.6)',
                                'rgba(255, 206, 86, 0.6)',
                                'rgba(75, 192, 192, 0.6)',
                                'rgba(199, 199, 199, 0.6)', // Cor para ICMS-ST
                                'rgba(255, 159, 64, 0.6)', // Cor para DIFAL
                                'rgba(153, 102, 255, 0.6)', // Cor para FCP
                                'rgba(200, 100, 100, 0.6)' // Cor para ISSQN
                            ],
                            borderColor: [
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 99, 132, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(199, 199, 199, 1)',
                                'rgba(255, 159, 64, 1)',
                                'rgba(153, 102, 255, 1)',
                                'rgba(200, 100, 100, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                // Gráfico de composição financeira (doughnut) já funciona corretamente com as variáveis corrigidas no PHP.
                const ctx2 = document.getElementById('graficoProdutos').getContext('2d');
                new Chart(ctx2, {
                    type: 'doughnut',
                    data: {
                        labels: ['Mercadorias', 'Impostos Totais', 'Frete/Seguro'],
                        datasets: [{
                            label: 'Valor (R$)',
                            data: [<?= $venda_total_produtosnf ?? 0 ?>, <?= $total_impostos_nf ?? 0 ?>,
                                <?= $total_frete_seguro ?? 0 ?>
                            ],
                            backgroundColor: ['rgba(27, 98, 196, 0.8)',
                                'rgba(212, 33, 33, 0.8)',
                                'rgba(75, 192, 192, 0.8)'
                            ],
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Composição da NF'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = <?= $venda_total_nf ?? 0 ?>;
                                        const value = context.parsed;
                                        const percentage = total > 0 ? ((value / total) * 100)
                                            .toFixed(2) : 0;
                                        return context.label + ': R$ ' + value.toFixed(2).replace(
                                                '.', ',') + ' (' +
                                            percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
                <?php endif; ?>
                </script>


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