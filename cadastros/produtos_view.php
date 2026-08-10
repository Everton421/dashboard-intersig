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
    <title>Produtos</title>
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
                        <h3 class="mb-0">Produtos</h3>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="../dashboard/dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Produtos</li>

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
                    <div id="tipo-filter-container" class="col-lg-2 d-lg-block d-none">
                        <label>Tipo:</label>
                        <a href="#" class="btn btn-white d-flex align-items-center rounded-3 btn-filtro-contorno"
                            data-bs-toggle="dropdown">
                            <iconify-icon class="fs-20px my-n1 me-2 ms-n1 text-theme" icon="solar:tag-linear">
                            </iconify-icon>
                            <span class="filter-label">Tipo</span>
                            <i class="fa ms-auto fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu" id="status-filter">
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-muted"></i> Todos
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="Consum">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-warning"></i> Consumo
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="Matéria-prima">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-success"></i> Matéria-prima
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#"
                                    data-value="Material de consumo">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-dark"></i> Material de consumo
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#"
                                    data-value="Material de montagem">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-info"></i> Material de montagem
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#"
                                    data-value="Material p/ constr./refor.">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-light"></i> Material p/ constr./refor.
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#"
                                    data-value="Material p/ escritório">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-primary"></i> Material p/ escritório
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="Patrimônio">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-success"></i> Patrimônio
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#"
                                    data-value="Produto acabado">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-dark"></i> Produto acabado
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#"
                                    data-value="Produto para revenda">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-warning"></i> Produto para revenda
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#"
                                    data-value="Produto para locação">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-light"></i> Produto para locação
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#"
                                    data-value="Tipo não especificado">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-dark"></i> Tipo não especificado
                                </a>
                            </li>
                        </ul>
                    </div>





                    <!-- Botão de Filtro de grupos -->
                    <div id="city-filter" class="col-lg-2 d-lg-block d-none">
                        <label for="filtro_filial">Grupos:</label>
                        <a href="#" class="btn btn-white d-flex align-items-center rounded-3 btn-filtro-contorno"
                            data-bs-toggle="dropdown">
                            <iconify-icon class="fs-20px my-n1 me-2 ms-n1 text-theme" icon="solar:tag-linear">
                            </iconify-icon>
                            <span class="filter-label">Grupos</span>
                            <i class="fa ms-auto fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-scroll">
                            <li><a class="dropdown-item" href="#" data-city="">Todos</a></li>
                            <!-- As opções de cidade serão inseridas dinamicamente aqui -->
                        </ul>
                    </div>


                    <!-- Botão de Filtro de ncm -->
                    <div id="ncm-filter" class="col-lg-2 d-lg-block d-none">
                        <label for="filtro_ncm">NCM:</label>
                        <a href="#" class="btn btn-white d-flex align-items-center rounded-3 btn-filtro-contorno"
                            data-bs-toggle="dropdown">
                            <iconify-icon class="fs-20px my-n1 me-2 ms-n1 text-theme" icon="solar:map-point-linear">
                            </iconify-icon>
                            <span class="filter-label">NCN</span>
                            <i class="fa ms-auto fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-scroll">
                            <li><a class="dropdown-item" href="#" data-ncm="">Todos</a></li>
                            <!-- As opções de cidade serão inseridas dinamicamente aqui -->
                        </ul>
                    </div>


                    <!-- Filtro Status -->
                    <div id="origem-filter-container" class="col-lg-2 d-lg-block d-none">
                        <label>Origem:</label>
                        <a href="#" class="btn btn-white d-flex align-items-center rounded-3 btn-filtro-contorno"
                            data-bs-toggle="dropdown">
                            <iconify-icon class="fs-20px my-n1 me-2 ms-n1 text-theme" icon="solar:tag-linear">
                            </iconify-icon>
                            <span class="filter-label">Origem</span>
                            <i class="fa ms-auto fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu" id="origem-filter">
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-muted"></i> Todos
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="0">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-warning"></i> Nacional
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="1">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-success"></i> Estrangeira,
                                    Importação
                                    direta,
                                    exceto a indicada no código 6
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="2">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-dark"></i> Estrangeira, Adquirida
                                    no
                                    mercado
                                    interno, exceto a indicada no código 7
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="3">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-info"></i> Nacional, Importação
                                    superior a 40%
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="4">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-light"></i> Nacional, processos
                                    produtivos
                                    básicos
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="5">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-primary"></i> Nacional, Importação
                                    inferior ou
                                    igual a 40%
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="6">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-success"></i> Estrangeira –
                                    Importação
                                    direta,
                                    sem similar nacional
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="7">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-dark"></i> Estrangeira – Adquirida
                                    no
                                    mercado
                                    interno, sem similar nacional
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="8">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-warning"></i> Nacional –
                                    Mercadoria ou
                                    bem com
                                    Conteúdo de Importação superior a 70%
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" data-value="">
                                    <i class="fa fa-circle fs-7px me-2 ms-n1 text-dark"></i> Tipo não especificado
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
                        <a href="produtos_view.php" class="btn btn-secondary d-flex align-items-center rounded-3">

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
                                            <th>Codigo</th>
                                            <th>Descrição</th>
                                            <th>Grupo</th>
                                            <th>Ean</th>
                                            <th>Ref</th>
                                            <th>Outro Cod.</th>
                                            <th>Unidade</th>
                                            <th>Ncm</th>
                                            <th>Cest</th>
                                            <th>Origem</th>
                                            <th>Tipo</th>
                                            <th>Visualizar</th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Dados serão carregados dinamicamente -->
                                    </tbody>
                                </table>



                            </div>



                            <script>
                            $(document).ready(function() {
                                // Inicialização da DataTable (seu código original aqui)
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
                                        url: "dados_produtos_view.php",
                                        type: "GET",
                                        dataSrc: function(json) {
                                            return json === "Nenhum resultado encontrado." ? [] :
                                                json;
                                        }
                                    },
                                    columns: [{
                                        data: "codigo"
                                    }, {
                                        data: "descricao"
                                    }, {
                                        data: "grupo"
                                    }, {
                                        data: "num_fabricante"
                                    }, {
                                        data: "num_original"
                                    }, {
                                        data: "outro_cod"
                                    }, {
                                        data: "unidade"
                                    }, {
                                        data: "class_fiscal"
                                    }, {
                                        data: "cest"
                                    }, {
                                        data: "origem"
                                    }, {
                                        data: "tipo"
                                    }, {
                                        data: null,
                                        render: function(data) {
                                            return `<div class="d-flex"><a href="produtos.php?codigo_pro=${data.codigo}" target="_blank" class="btn btn-primary btn-xs me-1"><i class="bi bi-eye"></i></a></div>`;
                                        }
                                    }],
                                    dom: 'Blfrtip',
                                    buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                                    responsive: true,
                                    initComplete: function() {
                                        var cidades = [];
                                        this.api().column(2).data().each(function(d) {
                                            if (d && !cidades.includes(d)) {
                                                cidades.push(d);
                                            }
                                        });
                                        cidades.sort();
                                        cidades.forEach(function(cidade) {
                                            $('#city-filter .dropdown-menu').append(
                                                `<li><a class="dropdown-item" href="#" data-city="${cidade}">${cidade}</a></li>`
                                            );
                                        });
                                        var ncms = [];
                                        this.api().column(7).data().each(function(d) {
                                            if (d && !ncms.includes(d)) {
                                                ncms.push(d);
                                            }
                                        });
                                        ncms.sort();
                                        ncms.forEach(function(ncm) {
                                            $('#ncm-filter .dropdown-menu').append(
                                                `<li><a class="dropdown-item" href="#" data-ncm="${ncm}">${ncm}</a></li>`
                                            );
                                        });
                                    },
                                });

                                // --- CÓDIGO DE FILTROS CORRIGIDO E UNIFICADO ---

                                // Filtro de TIPO
                                $('#tipo-filter-container').on('click', '.dropdown-item', function(e) {
                                    e.preventDefault();
                                    var status = $(this).data('value');
                                    var label = $('#tipo-filter-container .filter-label');
                                    var selectedText = $(this).clone().find('i').remove().end().text()
                                        .trim(); // Pega texto sem o ícone

                                    if (status !== "") {
                                        table.column(10).search(status).draw();
                                        label.text(selectedText);
                                    } else {
                                        table.column(10).search('').draw();
                                        label.text('Tipo');
                                    }
                                });

                                // Filtro de GRUPOS
                                $('#city-filter').on('click', '.dropdown-item', function(e) {
                                    e.preventDefault();
                                    var selectedGroup = $(this).data('city');
                                    var label = $('#city-filter .filter-label');

                                    if (selectedGroup) {
                                        table.column(2).search(selectedGroup).draw();
                                        label.text(selectedGroup);
                                    } else {
                                        table.column(2).search('').draw();
                                        label.text('Grupos');
                                    }
                                });

                                // Filtro de NCM
                                $('#ncm-filter').on('click', '.dropdown-item', function(e) {
                                    e.preventDefault();
                                    var selectedNcm = $(this).data('ncm');
                                    var label = $('#ncm-filter .filter-label');

                                    if (selectedNcm) {
                                        table.column(7).search(selectedNcm).draw();
                                        label.text(selectedNcm);
                                    } else {
                                        table.column(7).search('').draw();
                                        label.text('NCM');
                                    }
                                });

                                // Filtro de ORIGEM
                                $('#origem-filter-container').on('click', '.dropdown-item', function(e) {
                                    e.preventDefault();
                                    var origem = $(this).data('value');
                                    var label = $('#origem-filter-container .filter-label');
                                    var selectedText = $(this).clone().find('i').remove().end().text()
                                        .trim();

                                    if (origem !== "") {
                                        table.column(9).search(origem).draw();
                                        // Mostra o texto do item, que é mais amigável
                                        label.text(selectedText);
                                    } else {
                                        table.column(9).search('').draw();
                                        label.text('Origem');
                                    }
                                });

                                // Botão de LIMPAR TODOS OS FILTROS
                                $('#clear-filters-btn').on('click', function(e) {
                                    e.preventDefault();
                                    table.search('');
                                    table.columns().search('');
                                    table.draw();

                                    // Restaura os textos originais de todos os botões de filtro
                                    $('#tipo-filter-container .filter-label').text('Tipo');
                                    $('#city-filter .filter-label').text('Grupos');
                                    $('#ncm-filter .filter-label').text('NCM');
                                    $('#origem-filter-container .filter-label').text('Origem');
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