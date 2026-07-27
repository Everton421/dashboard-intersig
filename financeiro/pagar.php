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

  
      $filtroFilial = 0;
      $filtroPago = 0;
?>

<!doctype html>
<html lang="pt-br">
<!--begin::Head-->

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Contas a Pagar</title>
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

    <link href="../assets/plugins/datatables.net-bs5/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-colreorder-bs5/css/colReorder.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-keytable-bs5/css/keyTable.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-rowreorder-bs5/css/rowReorder.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-select-bs5/css/select.bootstrap5.min.css" rel="stylesheet" />
    <script src="../assets/plugins/jquery/dist/jquery.min.js"></script>

    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">


    <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=JetBrains+Mono:wght@400;700&display=swap');

/* Container para agrupar os campos de valor */
.value-range-group {
    display: flex;
    align-items: center;
    gap: 8px; /* Espaço entre os inputs */
}

.value-range-group .form-control-premium {
    flex: 1; /* Faz os dois inputs terem o mesmo tamanho */
    text-align: center; /* Centraliza o valor para parecer um range */
    min-width: 80px;
    font-family: var(--font-mono); /* Estilo de fonte de dados */
}

.range-separator {
    color: var(--text-dim);
    font-size: 0.8rem;
    font-weight: bold;
    text-transform: uppercase;
}


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

    .container-premium {
        max-width: 1550px;
        margin: 0 auto;
    }

    header.premium-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 20px;
    }

    .logo-container img {
        filter: brightness(0) invert(1);
        opacity: 0.9;
    }

    [data-bs-theme="light"] .logo-container img {
        filter: none;
        opacity: 1;
    }

    .header-title h1 {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        text-transform: uppercase;
        margin: 0;
        color: var(--accent-green);
    }

    .filter-panel {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        padding: 16px 20px;
        margin-bottom: 25px;
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
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

    .form-control-premium {
        background: var(--bg-hover) !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-main) !important;
        padding: 10px 12px !important;
        width: 100%;
        font-size: 0.9rem !important;
        border-radius: 2px !important;
        height: auto !important;
    }

    .btn-update {
        background: var(--accent-green) !important;
        color: #000 !important;
        border: none !important;
        padding: 10px 24px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        font-size: 0.85rem !important;
        cursor: pointer;
        transition: all 0.2s ease;
        height: 44px !important;
    }

    .btn-update:hover {
        background: #00d672 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 255, 136, 0.2);
    }

    .kpi-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .kpi-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        padding: 18px 20px;
        border-right: 3px solid var(--accent-blue);
        transition: all 0.3s ease;
    }

    .kpi-card:hover {
        border-right-width: 8px;
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
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-main);
    }

    .table-container-premium {
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        padding: 0;
    }

    #myTable {
        width: 100% !important;
        margin: 0 !important;
        border-collapse: collapse !important;
        font-size: 11px !important;
    }

    #myTable thead th {
        background: var(--bg-hover) !important;
        color: var(--text-dim) !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        padding: 10px 12px !important;
        border-bottom: 1px solid var(--border-color) !important;
    }

    #myTable tbody td {
        background: transparent !important;
        color: var(--text-main) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        padding: 8px 12px !important;
    }

    #myTable tr:hover td {
        background: var(--bg-hover) !important;
    }

    .mono {
        font-family: var(--font-mono);
    }

    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate,
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        color: var(--text-dim) !important;
        padding: 15px 20px !important;
    }

    .dataTables_wrapper .dataTables_filter input {
        background: #1a1a1c !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-main) !important;
        padding: 6px 12px !important;
        border-radius: 2px !important;
    }

    .dt-buttons .btn {
        background: transparent !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-dim) !important;
        margin-right: 5px !important;
        text-transform: uppercase !important;
        font-size: 0.75rem !important;
        font-weight: 600 !important;
    }

    .dt-buttons .btn:hover {
        border-color: var(--accent-blue) !important;
        color: var(--accent-blue) !important;
    }

    .page-link {
        background: var(--bg-card) !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-dim) !important;
        padding: 5px 12px !important;
    }

    [data-bs-theme="dark"] .dataTables_wrapper .dataTables_filter input {
        background: #1a1a1c !important;
    }

    [data-bs-theme="light"] .dataTables_wrapper .dataTables_filter input {
        background: #ffffff !important;
    }

    .page-item.active .page-link {
        background: var(--accent-blue) !important;
        border-color: var(--accent-blue) !important;
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
                        <h3 class="mb-0">Contas a Pagar </h3>



                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
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

                <?php 
            $dataVencimento = isset($_GET['data_vencimento']) ? $_GET['data_vencimento'] : date('Y-m-d');
            $dataVencimentoate = isset($_GET['data_vencimentoate']) ? $_GET['data_vencimentoate'] : date('Y-m-t');
            $tipoRecebimento = isset($_GET['tipo_pagamento']) ? $_GET['tipo_pagamento'] : 'todos';
            ?>



                <div class="row">
                    <nav class="filter-panel">
                        <form id="filter-form">
                            <div class="filter-grid">
                                <div class="filter-group">
                                    <label>Filtrar Por</label>
                                    <select name="tipo_data" id="tipo_data" class="form-control-premium">
                                        <option value="lancamento" selected>Data de Lançamento</option>
                                        <option value="vencimento">Data de Vencimento</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Data Inicial</label>
                                    <input class="form-control-premium" type="date" name="data_vencimento"
                                        id="data_vencimento" value="<?php echo $dataVencimento; ?>">
                                </div>
                                <div class="filter-group">
                                    <label>Data Final</label>
                                    <input class="form-control-premium" type="date" name="data_vencimentoate"
                                        id="data_vencimentoate" value="<?php echo $dataVencimentoate; ?>">
                                </div>
                                <div class="filter-group">
                                    <label>Pagamento</label>
                                    <select name="tipo_pagamento" id="tipo_pagamento" class="form-control-premium">
                                        <option value="todos"
                                            <?php echo ($tipoRecebimento == 'todos') ? 'selected' : ''; ?>>Todos
                                        </option>
                                        <?php
                                $tiposRecebimentoQuery = mysqli_query($dash, "SELECT codigo, nome FROM ".$banco_financeiro.".tip_rece WHERE ativo = 'S' ORDER BY nome");
                                while ($tipo = mysqli_fetch_assoc($tiposRecebimentoQuery)) {
                                    echo "<option value='{$tipo['codigo']}' ".($tipoRecebimento == $tipo['codigo'] ? 'selected' : '').">{$tipo['nome']}</option>";
                                }
                                ?>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Situação</label>
                                    <select name="filtro_pago" id="filtro_pago" class="form-control-premium">
                                        <option value="todos" <?php echo ($filtroPago == 'todos') ? 'selected' : ''; ?>>
                                            Todos</option>
                                        <option value="sim" <?php echo ($filtroPago == 'sim') ? 'selected' : ''; ?>>
                                            Pagos</option>
                                        <option value="nao" <?php echo ($filtroPago == 'nao') ? 'selected' : ''; ?>>
                                            Pendentes</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Empresa</label>
                                    <select name="filtro_filial" id="filtro_filial" class="form-control-premium">
                                        <option value="todos"
                                            <?php echo ($filtroFilial == 'todos') ? 'selected' : ''; ?>>Todas</option>
                                        <?php
                                $empresasQuery = mysqli_query($dash, "SELECT filial, CONCAT(filial, ' - ', razao_social) as razao_social FROM ".$banco_vendas.".empresas WHERE ativa = 'S' ORDER BY filial ASC");
                                while ($empresa = mysqli_fetch_assoc($empresasQuery)) {
                                    echo "<option value='{$empresa['filial']}' " . (($filtroFilial == $empresa['filial']) ? 'selected' : '') . ">{$empresa['razao_social']}</option>";
                                }
                                ?>
                                    </select>
                                </div>
                                   
                                <div class="filter-group">
                                    <label>Origem</label>
                                    <select name="filtro_origem" id="filtro_origem" class="form-control-premium">
                                        <option value="todos">Todos</option>
                                        <option value="faturamento">Faturamento</option>
                                        <option value="manual">Manual</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label>Cód:</label>
                                    <input class="form-control-premium" type="text" name="codigo_pagamento"
                                        id="codigo_pagamento" value="">
                                </div>
                               <div class="filter-group">
                                    <label>N° Doc:</label>
                                    <input class="form-control-premium" type="text" name="num_documento"
                                        id="num_documento" value="">
                                </div>

                                <div class="filter-group">
                                    <label>Fornecedor:</label>
                                    <input class="form-control-premium" type="text" name="fornecedor"
                                        id="fornecedor" value="">
                                </div>
                                <div class="filter-group wide">
                                    <label>Faixa de Valor (R$)</label>
                                    <div class="value-range-group">
                                        <input 
                                            class="form-control-premium" 
                                            type="text" 
                                            name="valor_inicial" 
                                            id="valor_inicial" 
                                            placeholder="Mínimo"
                                            value=""
                                        >
                                        
                                        <span class="range-separator">até</span>
                                        
                                        <input 
                                            class="form-control-premium" 
                                            type="text" 
                                            name="valor_final" 
                                            id="valor_final" 
                                            placeholder="Máximo"
                                            value=""
                                        >
                                    </div>
                                </div>

                               
                                <button type="button" id="btn-refresh" class="btn-update">Pesquisar</button>
                            </div>
                        </form>
                    </nav>

                    <div class="kpi-row">
                        <div class="kpi-card">
                            <div class="kpi-label">Volume do Período</div>
                            <div class="kpi-value" id="kpi-total-periodo">R$ 0,00</div>
                        </div>
                        <div class="kpi-card positive">
                            <div class="kpi-label">Total Pago</div>
                            <div class="kpi-value" id="kpi-total-recebido">R$ 0,00</div>
                        </div>
                        <div class="kpi-card negative">
                            <div class="kpi-label">Pendente (Aberto)</div>
                            <div class="kpi-value" id="kpi-total-pendente">R$ 0,00</div>
                        </div>
                        <div class="kpi-card negative" style="border-right-color: #ff9900;">
                            <div class="kpi-label">Vencidos</div>
                            <div class="kpi-value" id="kpi-total-vencidos" style="color: #ff9900;">R$ 0,00</div>
                        </div>
                    </div>

                    <div class="col-xl-12">
                        <div class="panel panel-inverse">



                            <div class="table-responsive table-container-premium">
                                <table id="myTable" class="table table-striped align-middle text-nowrap" width="100%">
                                    <thead>
                                        <tr>
                                            <th>Codigo</th>
                                            <th>Empresa</th>
                                            <th>Documento</th>
                                            <th>Fornecedor</th>
                                            <th>Valor</th>
                                            <th>Valor Pago</th>
                                            <th>Tipo Pgto</th>
                                            <th>Cadastro</th>
                                            <th>Vencimento</th>
                                            <th>Pagamento</th>
                                            <th>C. Custo</th>
                                            <th>Atraso</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>



            <script>
            $(document).ready(function() {
                var table = $("#myTable").DataTable({
                    "responsive": true,
                    "processing": true,

                    "lengthMenu": [
                        [20, 30, 50, 100],
                        [20, 30, 50, 100]
                    ],
                    "language": {
                        "sEmptyTable": "Nenhum registro encontrado",
                        "sInfo": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
                        "sInfoEmpty": "Mostrando 0 até 0 de 0 registros",
                        "sInfoFiltered": "(Filtrados de _MAX_ registros)",
                        "sInfoPostFix": "",
                        "sInfoThousands": ".",
                        "sLengthMenu": "Mostrar _MENU_ resultados por página",
                        "sLoadingRecords": "Carregando...",
                        "sProcessing": "Processando...",
                        "sZeroRecords": "Nenhum registro encontrado",
                        "sSearch": "Buscar:",
                        "oPaginate": {
                            "sNext": "Próximo",
                            "sPrevious": "Anterior",
                            "sFirst": "Primeiro",
                            "sLast": "Último"
                        },
                        "oAria": {
                            "sSortAscending": ": Ordenar colunas de forma ascendente",
                            "sSortDescending": ": Ordenar colunas de forma descendente"
                        }
                    },
                    ajax: {
                        url: "dadospagar.php",
                        type: "POST",
                        cache: false,
                        // passar os filtros aqui
                        data: function(d) {
                            d.tipo_data = $('#tipo_data').val();
                            d.data_vencimento = $('#data_vencimento').val();
                            d.data_vencimentoate = $('#data_vencimentoate').val();
                            d.tipo_pagamento = $('#tipo_pagamento').val();
                            d.filtro_pago = $('#filtro_pago').val();
                            d.filtro_filial = $('#filtro_filial').val();
                            d.filtro_origem = $('#filtro_origem').val();
                                 d.codigo_pagamento = $('#codigo_pagamento').val();
                                 d.num_documento= $('#num_documento').val();
                                 d.fornecedor= $('#fornecedor').val();
                                 d.valor_inicial= $('#valor_inicial').val();
                                 d.valor_final= $('#valor_final').val();
                        },
                        dataSrc: "",
                        error: function(xhr, error, thrown) {
                            console.error('Erro na carga dos dados:', error);
                            alert('Erro ao carregar dados. Verifique a conexão ou os filtros.');
                        }
                    },
                    columns: [{
                            data: "codigo"
                        },
                        {
                            data: "empresa"
                        },

                        {
                            data: "documento",
                            render: function(data, type, row) {
                                if (type === 'display') {
                                    // Criar o link para a página titulosp.php com o código como parâmetro
                                    return '<a href="titulos.php?codigo=' + row.codigo +
                                        '" target="_blank">' + data + '</a>';
                                }
                                return data;
                            }
                        },


                        {
                            data: "cliente",
                            render: function(data, type, row) {
                                if (type === 'display') {
                                    // Criar o link para a página titulosp.php com o código como parâmetro
                                    return '<a href="../fornecedores/forn.php?codigo_forn=' +
                                        row.fornecedor +
                                        '" target="_blank">' + data + '</a>';
                                }
                                return data;
                            }
                        },





                        {
                            data: "valor",
                            render: function(data, type, row) {
                                // Se o tipo de renderização for 'display', formate o valor
                                if (type === 'display') {
                                    // Formatar o valor para o padrão 1.125,29
                                    return parseFloat(data).toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                                // Se não, apenas retorne o valor original
                                return data;
                            }
                        },

                        {
                            data: "valor_pago",
                            render: function(data, type, row) {
                                if (type === 'display') {
                                    return parseFloat(data).toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                                return data;
                            }
                        },
                        {
                            data: "nome_recebimento"
                        },
                        {
                            data: "data_lancamento",
                            render: function(data, type, row) {
                                if (type === 'display' && data && data !== '0000-00-00') {
                                    var parts = data.split('-');
                                    return parts[2] + '/' + parts[1] + '/' + parts[0];
                                }
                                return data;
                            }
                        },
                        {
                            data: "vencimento",
                            render: function(data, type, row) {
                                if (type === 'display' && data && data !== '0000-00-00') {
                                    var parts = data.split('-');
                                    return parts[2] + '/' + parts[1] + '/' + parts[0];
                                }
                                return data;
                            }
                        },
                        {
                            data: "pagamento",
                            render: function(data, type, row) {
                                if (type === 'display' && data && data !== '0000-00-00') {
                                    var parts = data.split('-');
                                    return parts[2] + '/' + parts[1] + '/' + parts[0];
                                }
                                return data;
                            }
                        },
                        {
                            data: "centrocusto"
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                // calular dias atrasados, Verificar se o valor pago é maior que zero
                                if (parseFloat(row.valor_pago) > 0) {
                                    // Se o valor pago for maior que zero, retorna vazio
                                    return '';
                                } else {
                                    // Calcular dias de atraso
                                    var today = new Date();
                                    var dueDate = new Date(row.vencimento);
                                    var timeDiff = dueDate.getTime() - today.getTime();
                                    var diffDays = Math.ceil(timeDiff / (1000 * 3600 * 24));
                                    // Retorna o número de dias de atraso
                                    return (diffDays > 0) ? 0 : Math.abs(diffDays);
                                }
                            }
                        }


                    ],

                    //    initComplete: function() {
                    //        var cidades = [];
                    //        this.api().column(6).data().each(function(d) {
                    //            if (d && !cidades.includes(d)) {
                    //                cidades.push(d);
                    //              }
                    //         });
                    //          cidades.sort();
                    //          var dropdown = $('#city-filter .dropdown-menu');
                    //          cidades.forEach(function(cidade) {
                    //              dropdown.append(
                    //                  `<li><a class="dropdown-item" href="#" data-city="${cidade}">${cidade}</a></li>`
                    //              );
                    //           });
                    //       },
                    dom: 'Blfrtip',
                     buttons: [
                        'copy',
                        'csv',
                        {
                            extend: 'excelHtml5',
                            title: 'Relatorio_Contas_a_Pagar',
                            exportOptions: {
                                orthogonal: 'export'
                            }
                        },
                        'pdf',
                        'print'
                    ],



                    footerCallback: function(row, data, start, end, display) {
                        var api = this.api();
                        var numFormat = $.fn.dataTable.render.number('.', ',', 2, 'R$ ').display;

                        var totalPeriodo = 0;
                        var totalRecebido = 0;
                        var totalPendente = 0;
                        var totalVencidos = 0;

                        var today = new Date();
                        today.setHours(0, 0, 0, 0);

                        api.rows({
                            search: 'applied'
                        }).data().each(function(row) {
                            var valor = parseFloat(row.valor) || 0;
                            var pago = parseFloat(row.valor_pago) || 0;
                            var vencimentoStr = row.vencimento;
                            var parts = vencimentoStr.split('-');
                            var vencimento = new Date(parts[0], parts[1] - 1, parts[2]);

                            totalPeriodo += valor;
                            totalRecebido += pago;

                            if (pago < valor) {
                                totalPendente += (valor - pago);
                                if (vencimento < today) {
                                    totalVencidos += (valor - pago);
                                }
                            }
                        });

                        $('#kpi-total-periodo').html(numFormat(totalPeriodo));
                        $('#kpi-total-recebido').html(numFormat(totalRecebido));
                        $('#kpi-total-pendente').html(numFormat(totalPendente));
                        $('#kpi-total-vencidos').html(numFormat(totalVencidos));
                        $('#total').html(numFormat(totalPeriodo));
                    }

                });


                // Evento para filtrar por cidade
                // $('#city-filter').on('click', '.dropdown-item', function(e) {
                //      e.preventDefault();
                //   var selectedCity = $(this).data('city');
                //  table.column(6).search(selectedCity || '').draw();
                //  });

                // Impedir que o formulário recarregue a página e forçar a atualização da tabela
                $('#filter-form').on('submit', function(e) {
                    e.preventDefault();
                    table.ajax.reload(null, true);
                });

                $('#btn-refresh').on('click', function() {
                    table.ajax.reload(null, true); // true para resetar a paginação ao filtrar
                });
            });
            </script>
        </div>
        </div>
        </div>




    </main>
    <!--end::App Main-->
    <!--begin::Footer-->
    <?php 
        require_once '../menu/footer.php';
        ?>

    </div>
    <!--end::Footer-->
    </div>

    <script src="../assets/plugins/datatables.net/js/dataTables.min.js"></script>
    <script src="../assets/plugins/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script src="../assets/plugins/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
    <script src="../assets/plugins/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js"></script>
    <script src="../assets/plugins/datatables.net-colreorder/js/dataTables.colReorder.min.js"></script>
    <script src="../assets/plugins/datatables.net-colreorder-bs5/js/colReorder.bootstrap5.min.js"></script>
    <script src="../assets/plugins/datatables.net-keytable/js/dataTables.keyTable.min.js"></script>
    <script src="../assets/plugins/datatables.net-keytable-bs5/js/keyTable.bootstrap5.min.js"></script>
    <script src="../assets/plugins/datatables.net-rowreorder/js/dataTables.rowReorder.min.js"></script>
    <script src="../assets/plugins/datatables.net-rowreorder-bs5/js/rowReorder.bootstrap5.min.js"></script>
    <script src="../assets/plugins/datatables.net-select/js/dataTables.select.min.js"></script>
    <script src="../assets/plugins/datatables.net-select-bs5/js/select.bootstrap5.min.js"></script>
    <script src="../assets/plugins/datatables.net-buttons/js/dataTables.buttons.min.js"></script>
    <script src="../assets/plugins/datatables.net-buttons-bs5/js/buttons.bootstrap5.min.js"></script>
    <script src="../assets/plugins/datatables.net-buttons/js/buttons.colVis.min.js"></script>
    <script src="../assets/plugins/datatables.net-buttons/js/buttons.html5.min.js"></script>
    <script src="../assets/plugins/datatables.net-buttons/js/buttons.print.min.js"></script>
    <script src="../assets/plugins/pdfmake/build/pdfmake.min.js"></script>
    <script src="../assets/plugins/pdfmake/build/vfs_fonts.js"></script>
    <script src="../assets/plugins/jszip/dist/jszip.min.js"></script>
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