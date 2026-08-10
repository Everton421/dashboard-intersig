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




?>

<!doctype html>
<html lang="pt-br">
<!--begin::Head-->

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Clientes</title>
    <!--begin::Accessibility Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <meta name="supported-color-schemes" content="light dark" />
    <link rel="preload" href="../css/adminlte.css" as="style" />
    <!--end::Accessibility Features-->
    <!--begin::Fonts-->

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

    <!-- ================== BEGIN page-css ================== -->
    <link href="../assets/plugins/datatables.net-bs5/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-colreorder-bs5/css/colReorder.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-keytable-bs5/css/keyTable.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-rowreorder-bs5/css/rowReorder.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-select-bs5/css/select.bootstrap5.min.css" rel="stylesheet" />
    <script src="../assets/plugins/jquery/dist/jquery.min.js"></script>




    <link href="../assets/plugins/datatables.net-fixedcolumns-bs5/css/fixedColumns.bootstrap5.min.css"
        rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">

    <style>
    /* Estilo para adicionar o contorno permanente nos botões de filtro */
    .btn-filtro-contorno {
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        /* Cor da borda para tema escuro */
    }



    /* Garante que a borda não mude ao passar o mouse ou focar */
    .btn-filtro-contorno:hover,
    .btn-filtro-contorno:focus {
        border: 1px solid rgba(255, 255, 255, 0.25) !important;
    }

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

    /* Estilo para rolagem no dropdown */
    .dropdown-scroll {
        max-height: 300px;
        /* Altura máxima para o dropdown */
        overflow-y: auto;
        /* Habilita a rolagem vertical */
        overflow-x: hidden;
        /* Impede a rolagem horizontal */
    }


    /* Define o tamanho da fonte para a tabela */
    #tableclientes {
        font-size: 10px;
        /* Define o tamanho da fonte para 12 pixels */
    }

    /* Define o tamanho da fonte para as células de dados (td) */
    #tableclientes td {
        font-size: 10px;
        /* Define o tamanho da fonte para 12 pixels */
    }

    /* Define o tamanho da fonte para os cabeçalhos de coluna (th) */
    #tableclientes th {
        font-size: 10px;
        /* Define o tamanho da fonte para 12 pixels */
    }



    /* Efeito de hover para as linhas da tabela */
    #tableclientes tbody tr:hover {
        background-color: rgba(255, 255, 255, 0.08) !important;
        /* Cor sutil para tema escuro */
        cursor: pointer;
        /* Muda o cursor para uma "mãozinha" para indicar que é clicável */
    }


    .badge {
        font-size: 0.75em;
        font-weight: 600;
        padding: 0.35em 0.65em;
    }

    /* Cores específicas para os badges */
    .badge.border-success {
        border-color: #198754 !important;
        color: #198754 !important;
    }

    .badge.border-danger {
        border-color: #dc3545 !important;
        color: #dc3545 !important;
    }

    .badge.border-warning {
        border-color: #ffc107 !important;
        color: #ffc107 !important;
    }

    .badge.border-info {
        border-color: #0dcaf0 !important;
        color: #0dcaf0 !important;
    }

    .badge.border-dark {
        border-color: #6c757d !important;
        color: #6c757d !important;
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
                        <h3 class="mb-0">Clientes</h3>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="../dashboard/dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Clientes</li>

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




                <div class="row">


                    <!-- Filtro Status -->
                    <div id="contribuinte-filter-container" class="col-lg-2 d-lg-block d-none">
                        <label>Contribuinte:</label>
                        <a href="#" class="btn btn-white d-flex align-items-center rounded-3 btn-filtro-contorno"
                            data-bs-toggle="dropdown">
                            <iconify-icon class="fs-20px my-n1 me-2 ms-n1 text-theme" icon="solar:tag-linear">
                            </iconify-icon>
                            <span class="filter-label">Contribuinte</span>
                            <i class="fa ms-auto fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu" id="contribuinte-filter">
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-muted"></i> Todos
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="SIM">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-warning"></i> SIM
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="NÃO">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-success"></i> NÃO
                                </a>
                            </li>

                        </ul>
                    </div>

                    <!-- Filtro Status -->
                    <div id="consumidor-filter-container" class="col-lg-2 d-lg-block d-none">
                        <label>Consumidor Final:</label>
                        <a href="#" class="btn btn-white d-flex align-items-center rounded-3 btn-filtro-contorno"
                            data-bs-toggle="dropdown">
                            <iconify-icon class="fs-20px my-n1 me-2 ms-n1 text-theme" icon="solar:tag-linear">
                            </iconify-icon>
                            <span class="filter-label">Consumidor</span>
                            <i class="fa ms-auto fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu" id="consumidor-filter">
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-muted"></i> Todos
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="SIM">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-warning"></i> SIM
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="NÃO">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-success"></i> NÃO
                                </a>
                            </li>

                        </ul>
                    </div>

                    <!-- Filtro REGIME -->
                    <div id="regime-filter-container" class="col-lg-2 d-lg-block d-none">
                        <label>Regime:</label>
                        <a href="#" class="btn btn-white d-flex align-items-center rounded-3 btn-filtro-contorno"
                            data-bs-toggle="dropdown">
                            <iconify-icon class="fs-20px my-n1 me-2 ms-n1 text-theme" icon="solar:tag-linear">
                            </iconify-icon>
                            <span class="filter-label">Regime</span>
                            <i class="fa ms-auto fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu" id="regime-filter">
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-muted"></i> Todos
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="SIMPLES">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-warning"></i> SIMPLES
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="NORMAL">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-success"></i> NORMAL
                                </a>
                            </li>

                        </ul>
                    </div>

                    <!-- Filtro Status -->
                    <div id="tipo-filter-container" class="col-lg-2 d-lg-block d-none">
                        <label>Tipo:</label>
                        <a href="#" class="btn btn-white d-flex align-items-center rounded-3 btn-filtro-contorno"
                            data-bs-toggle="dropdown">
                            <iconify-icon class="fs-20px my-n1 me-2 ms-n1 text-theme" icon="solar:tag-linear">
                            </iconify-icon>
                            <span class="filter-label">Tipo</span>
                            <i class="fa ms-auto fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu" id="tipo-filter">
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-muted"></i> Todos
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="F">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-warning"></i> FÍSICA
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="J">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-success"></i> JURÍDICA
                                </a>
                            </li>
                        </ul>
                    </div>


                    <div class="col-lg-2 d-lg-block d-none">
                        <label>&nbsp;</label> <!-- Label vazia para alinhar com os outros -->
                        <a href="#" id="clear-filters-btn" class="btn btn-danger d-flex align-items-center rounded-3">
                            <iconify-icon class="fs-20px my-n1 me-2 ms-n1" icon="solar:broom-bold-duotone">
                            </iconify-icon>
                            Limpar Filtros
                        </a>
                    </div>

                    <div class="col-lg-2 d-lg-block d-none">
                        <label>&nbsp;</label> <!-- Label vazia para alinhar com os outros -->
                        <a href="clientes_view.php" class="btn btn-secondary d-flex align-items-center rounded-3">

                            <iconify-icon class="fs-20px my-n1 me-2 ms-n1" icon="solar:refresh-bold-duotone">
                            </iconify-icon>
                            Reload Data
                        </a>

                    </div>

                </div>
                <h1></h1>


                <!-- BEGIN row -->
                <div class="row">

                    <!-- END col-2 -->
                    <!-- BEGIN col-10 -->
                    <div class="col-xl-12">
                        <div class="panel panel-inverse">

                            <!-- BEGIN panel-body -->
                            <div class="panel-body">
                                <table id="tableclientes" width="100%"
                                    class="table table-striped table-bordered align-middle text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Razão Social</th>
                                            <th>Fantasia</th>
                                            <th>CNPJ/CPF</th>
                                            <th>Inscrição/Rg</th>
                                            <th>Contribuinte</th>
                                            <th>Cons. Final</th>
                                            <th>Regime</th>
                                            <th>Tipo</th>
                                            <th>Cidade</th>

                                            <th>Ver</th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Dados serão carregados dinamicamente -->
                                    </tbody>
                                </table>
                            </div>
                            <script>
                            $(document).ready(function() {
                                const table = $('#tableclientes').DataTable({
                                    responsive: true,
                                    processing: true,
                                    lengthMenu: [
                                        [15, 20, 50, 100],
                                        [15, 20, 50, 100]
                                    ],
                                    order: [
                                        [0, "desc"]
                                    ],
                                    language: {
                                        sEmptyTable: "Nenhum registro encontrado",
                                        sInfo: "Mostrando de _START_ até _END_ de _TOTAL_ registros",
                                        sInfoEmpty: "Mostrando 0 até 0 de 0 registros",
                                        sInfoFiltered: "(Filtrados de _MAX_ registros)",
                                        sLengthMenu: "Mostrar _MENU_ resultados por página",
                                        sLoadingRecords: "Carregando...",
                                        sProcessing: "Processando...",
                                        sZeroRecords: "Nenhum registro encontrado",
                                        sSearch: "Buscar:",
                                        oPaginate: {
                                            sNext: "Próximo",
                                            sPrevious: "Anterior",
                                            sFirst: "Primeiro",
                                            sLast: "Último"
                                        }
                                    },
                                    ajax: {
                                        url: "dados_clientes_view.php",
                                        type: "GET",
                                        dataSrc: function(json) {
                                            return json === "Nenhum resultado encontrado." ? [] :
                                                json;
                                        }
                                    },
                                    columns: [{
                                            data: "codigo"
                                        },
                                        {
                                            data: "nome"
                                        },
                                        {
                                            data: "apelido"
                                        },
                                        {
                                            data: "cpf"
                                        },
                                        {
                                            data: "rg"
                                        },
                                        {
                                            data: "contrib",
                                            render: function(data) {
                                                switch (data) {
                                                    case 'SIM':
                                                        return '<span class="badge border border-success text-success">SIM</span>';
                                                    case 'NÃO':
                                                        return '<span class="badge border border-danger text-danger">NÃO</span>';
                                                    default:
                                                        return '<span class="badge border border-dark text-dark">' +
                                                            (data || '') + '</span>';
                                                }
                                            }
                                        },
                                        {
                                            data: "consumidor_final",
                                            render: function(data) {
                                                switch (data) {
                                                    case 'SIM':
                                                        return '<span class="badge border border-success text-success">SIM</span>';
                                                    case 'NÃO':
                                                        return '<span class="badge border border-danger text-danger">NÃO</span>';
                                                    default:
                                                        return '<span class="badge border border-dark text-dark">' +
                                                            (data || '') + '</span>';
                                                }
                                            }
                                        },
                                        {
                                            data: "regime_tribut",
                                            render: function(data) {
                                                switch (data) {
                                                    case 'SIMPLES':
                                                        return '<span class="badge border border-warning text-warning">SIMPLES</span>';
                                                    case 'NORMAL':
                                                        return '<span class="badge border border-info text-info">NORMAL</span>';
                                                    default:
                                                        return '<span class="badge border border-dark text-dark">' +
                                                            (data || '') + '</span>';
                                                }
                                            }
                                        },
                                        {
                                            data: "fis_jur",
                                            render: function(data) {
                                                switch (data) {
                                                    case 'F':
                                                        return '<span class="badge border border-warning text-warning">Física</span>';
                                                    case 'J':
                                                        return '<span class="badge border border-success text-success">Jurídica</span>';
                                                    default:
                                                        return '<span class="badge border border-dark text-dark">Desconhecido</span>';
                                                }
                                            }
                                        },
                                        {
                                            data: "cidade"
                                        },
                                        {
                                            data: null,
                                            render: function(data) {
                                                return `
            <div class="d-flex">
                <a href="cli.php?codigo_cli=${data.codigo}" target="_blank" class="btn btn-primary btn-xs me-1">
                    <i class="bi bi-eye"></i>
                </a>
            </div>`;
                                            }
                                        }
                                    ],
                                    dom: 'Blfrtip',
                                    buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                                    responsive: true,

                                });

                                // --- CÓDIGO DE FILTROS CORRIGIDO E UNIFICADO ---

                                // Filtro de TIPO
                                $('#contribuinte-filter-container').on('click', '.dropdown-item',
                                    function(e) {
                                        e.preventDefault();
                                        var status = $(this).data('value');
                                        var label = $('#contribuinte-filter-container .filter-label');
                                        var selectedText = $(this).clone().find('i').remove().end()
                                            .text()
                                            .trim(); // Pega texto sem o ícone

                                        if (status !== "") {
                                            table.column(5).search(status).draw();
                                            label.text(selectedText);
                                        } else {
                                            table.column(5).search('').draw();
                                            label.text('CONTRIBUINTE');
                                        }
                                    });

                                // Filtro de CONSUMDOR
                                $('#consumidor-filter-container').on('click', '.dropdown-item',
                                    function(e) {
                                        e.preventDefault();
                                        var status = $(this).data('value');
                                        var label = $('#consumidor-filter-container .filter-label');
                                        var selectedText = $(this).clone().find('i').remove().end()
                                            .text()
                                            .trim(); // Pega texto sem o ícone

                                        if (status !== "") {
                                            table.column(6).search(status).draw();
                                            label.text(selectedText);
                                        } else {
                                            table.column(6).search('').draw();
                                            label.text('CONSUMIDOR');
                                        }
                                    });

                                // Filtro de regime
                                $('#regime-filter-container').on('click', '.dropdown-item', function(
                                    e) {
                                    e.preventDefault();
                                    var status = $(this).data('value');
                                    var label = $('#regime-filter-container .filter-label');
                                    var selectedText = $(this).clone().find('i').remove().end()
                                        .text()
                                        .trim(); // Pega texto sem o ícone

                                    if (status !== "") {
                                        table.column(7).search(status).draw();
                                        label.text(selectedText);
                                    } else {
                                        table.column(7).search('').draw();
                                        label.text('REGIME');
                                    }
                                });


                                // Filtro de ORIGEM
                                $('#tipo-filter-container').on('click', '.dropdown-item', function(e) {
                                    e.preventDefault();
                                    var origem = $(this).data('value');
                                    var label = $('#tipo-filter-container .filter-label');
                                    var selectedText = $(this).clone().find('i').remove().end()
                                        .text()
                                        .trim();

                                    if (origem !== "") {
                                        table.column(8).search(origem).draw();
                                        // Mostra o texto do item, que é mais amigável
                                        label.text(selectedText);
                                    } else {
                                        table.column(8).search('').draw();
                                        label.text('TIPO');
                                    }
                                });



                                // Botão de LIMPAR TODOS OS FILTROS
                                $('#clear-filters-btn').on('click', function(e) {
                                    e.preventDefault();
                                    table.search('');
                                    table.columns().search('');
                                    table.draw();

                                    // Restaura os textos originais de todos os botões de filtro
                                    $('#contribuinte-filter-container .filter-label').text(
                                        'Contribuinte');
                                    $('#consumidor-filter-container .filter-label').text(
                                        'Consumidor');
                                    $('#regime-filter-container .filter-label').text('Regime');
                                    $('#tipo-filter-container .filter-label').text('Tipo');

                                });
                            });
                            </script>

                            <!-- END panel-body -->
                            <!-- BEGIN hljs-wrapper -->

                            <!-- END hljs-wrapper -->
                        </div>
                        <!-- END panel -->
                    </div>
                    <!-- END col-10 -->
                </div>
                <!-- END row -->
            </div>



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

    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>

    <!-- ================== END page-js ================== -->

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

    <!-- DEPENDÊNCIAS PARA OS BOTÕES (CARREGAR PRIMEIRO) -->
    <script src="../assets/plugins/jszip/dist/jszip.min.js"></script>
    <script src="../assets/plugins/pdfmake/build/pdfmake.min.js"></script>
    <script src="../assets/plugins/pdfmake/build/vfs_fonts.js"></script>

    <!-- SCRIPTS DOS BOTÕES (CARREGAR DEPOIS DAS DEPENDÊNCIAS) -->
    <script src="../assets/plugins/datatables.net-buttons/js/dataTables.buttons.min.js"></script>
    <script src="../assets/plugins/datatables.net-buttons-bs5/js/buttons.bootstrap5.min.js"></script>
    <script src="../assets/plugins/datatables.net-buttons/js/buttons.colVis.min.js"></script>
    <script src="../assets/plugins/datatables.net-buttons/js/buttons.html5.min.js"></script>
    <script src="../assets/plugins/datatables.net-buttons/js/buttons.print.min.js"></script>