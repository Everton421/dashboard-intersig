<?php
// --- NO CADASTRO DA SERIE ELE PEGA OS VALORES SOMENTE O QUE ESTIVER MARCADA PARA ENVIAR SPED ---
require_once '../config/config.php';
require_once '../config/session.php'; 

date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION["user_name"])) {
    header("Location: ../index.php");
    exit();
}

$nome_usuario_logado = htmlspecialchars($_SESSION["user_name"]);
$id_usuario_logado = (int) $_SESSION["user_id"];

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$page_url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
$user_agent = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

if (isset($dash) && $stmt = $dash->prepare(
    "INSERT INTO logs (user_id, user_name, pagina_url, acesso, user_agent) VALUES (?, ?, ?, NOW(), ?)"
)) {
    $stmt->bind_param("isss",
        $id_usuario_logado,
        $nome_usuario_logado,
        $page_url,
        $user_agent
    );
    $stmt->execute();
}
?>

<!doctype html>
<html lang="pt-br">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Transações</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <meta name="supported-color-schemes" content="light dark" />
    <link rel="preload" href="../css/adminlte.css" as="style" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="../css/adminlte.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css"
        integrity="sha256-4MX+61mt9NVvvuPjUWdUdyfZfxSB1/Rf9WtqRHgG5S0=" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css"
        integrity="sha256-+uGLJmmTKOqBr+2E6KDYs/NRsHxSkONXFHUL0fy2O/4=" crossorigin="anonymous" />
    <link href="../assets/plugins/datatables.net-bs5/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-colreorder-bs5/css/colReorder.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-keytable-bs5/css/keyTable.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-rowreorder-bs5/css/rowReorder.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-select-bs5/css/select.bootstrap5.min.css" rel="stylesheet" />
    <link href="../assets/plugins/datatables.net-fixedcolumns-bs5/css/fixedColumns.bootstrap5.min.css"
        rel="stylesheet" />
    <script src="../assets/plugins/jquery/dist/jquery.min.js"></script>
    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">

    <style>
    .btn-filtro-contorno {
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
    }

    .btn-filtro-contorno:hover,
    .btn-filtro-contorno:focus {
        border: 1px solid rgba(255, 255, 255, 0.25) !important;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #ddd;
        font-size: 0.85em;
    }

    .dropdown-scroll {
        max-height: 300px;
        overflow-y: auto;
        overflow-x: hidden;
    }

    #tableclientes {
        font-size: 12px;
    }

    #tableclientes td,
    #tableclientes th {
        font-size: 12px;
    }

    #tableclientes tbody tr:hover {
        background-color: rgba(255, 255, 255, 0.08) !important;
        cursor: pointer;
    }

    .badge {
        font-size: 0.9em;
        font-weight: 600;
        padding: 0.35em 0.65em;
    }

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

    .badge.border-roxo {
        border-color: #3d18a1ff !important;
        color: #3d18a1ff !important;
    }

    .dropdown-menu {
        min-width: 300px;
    }

    .colunas-container {
        max-height: 400px;
        overflow-y: auto;
    }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open" data-bs-theme="dark">
    <div class="app-wrapper">
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
    </aside>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h3 class="mb-0">Transações</h3>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="../dashboard/dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Transações</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                <!-- ========== LINHA DE FILTROS ========== -->
                <div class="row g-2 mb-3">

                    <!-- Colunas do Relatório -->
                    <div id="colunas-filter-container" class="col-lg-2 col-md-6 col-sm-12">
                        <label>Colunas do Relatório:</label>
                        <a href="#" class="btn btn-info d-flex align-items-center rounded-3 btn-filtro-contorno"
                            data-bs-toggle="dropdown">
                            <iconify-icon class="fs-5 me-1" icon="solar:columns-3-bold-duotone"></iconify-icon>
                            <span class="filter-label">Colunas</span>
                            <i class="fa fa-chevron-down ms-auto"></i>
                        </a>
                        <div class="dropdown-menu dropdown-scroll p-3" style="min-width: 320px;">
                            <h6 class="mb-3">Selecione as colunas para exibir:</h6>
                            <div class="colunas-container">

                                <!-- Grupo: Informações Básicas -->
                                <div class="coluna-group">
                                    <div class="coluna-group-title">Informações Básicas</div>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input coluna-checkbox" type="checkbox"
                                                    value="filial" id="coluna-filial" checked>
                                                <label class="form-check-label" for="coluna-filial">Filial</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input coluna-checkbox" type="checkbox"
                                                    value="codigo" id="coluna-codigo" checked>
                                                <label class="form-check-label" for="coluna-codigo">Código</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input coluna-checkbox" type="checkbox"
                                                    value="nome" id="coluna-nome" checked>
                                                <label class="form-check-label" for="coluna-nome">Nome</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input coluna-checkbox" type="checkbox"
                                                    value="tipo" id="coluna-tipo" checked>
                                                <label class="form-check-label" for="coluna-tipo">Tipo</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input coluna-checkbox" type="checkbox"
                                                    value="setor" id="coluna-setor" checked>
                                                <label class="form-check-label" for="coluna-setor">Setor</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input coluna-checkbox" type="checkbox"
                                                    value="gera_financeiro" id="coluna-gera_financeiro" checked>
                                                <label class="form-check-label" for="coluna-gera_financeiro">Gera
                                                    Financeiro</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input coluna-checkbox" type="checkbox"
                                                    value="comissao" id="coluna-comissao" checked>
                                                <label class="form-check-label" for="coluna-comissao">Comissão</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input coluna-checkbox" type="checkbox"
                                                    value="produtos" id="coluna-produtos" checked>
                                                <label class="form-check-label" for="coluna-produtos">Produtos</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input coluna-checkbox" type="checkbox"
                                                    value="servicos" id="coluna-servicos" checked>
                                                <label class="form-check-label" for="coluna-servicos">Servicos</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input coluna-checkbox" type="checkbox"
                                                    value="ent_sai" id="coluna-ent_sai" checked>
                                                <label class="form-check-label"
                                                    for="coluna-ent_sai">Entrada/Saída</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input coluna-checkbox" type="checkbox"
                                                    value="ativa" id="coluna-ativa" checked>
                                                <label class="form-check-label" for="coluna-ativa">Ativa</label>
                                            </div>

                                        </div>

                                    </div>
                                </div>



                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                    id="selecionar-todas-colunas">
                                    <iconify-icon icon="solar:checklist-minimalistic-bold-duotone"></iconify-icon> Todas
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="limpar-colunas">
                                    <iconify-icon icon="solar:eraser-bold-duotone"></iconify-icon> Limpar
                                </button>
                                <button type="button" class="btn btn-sm btn-primary" id="aplicar-colunas">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon> Aplicar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Demais filtros mantidos iguais -->
                    <!-- consid_sped -->
                    <div id="tipo-filter-container" class="col-lg-2 col-md-4 col-sm-6">
                        <label>Tipo:</label>
                        <a href="#" class="btn btn-white d-flex align-items-center rounded-3 btn-filtro-contorno"
                            data-bs-toggle="dropdown">
                            <iconify-icon class="fs-5 me-1" icon="solar:tag-linear"></iconify-icon>
                            <span class="filter-label">Tipo</span>
                            <i class="fa fa-chevron-down ms-auto"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value=""><i
                                        class="fa fa-circle fs-7px me-2 text-muted"></i> Todos</a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="A"><i
                                        class="fa fa-circle fs-7px me-2 text-danger"></i> AJUSTE</a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="L"><i
                                        class="fa fa-circle fs-7px me-2 text-success"></i> COMPLEMENTO</a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="C"><i
                                        class="fa fa-circle fs-7px me-2 text-danger"></i> COMPRA/AQUISIÇÃO</a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="H"><i
                                        class="fa fa-circle fs-7px me-2 text-success"></i> CONHECIMENTO DE
                                    TRANSPORTE</a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="D"><i
                                        class="fa fa-circle fs-7px me-2 text-danger"></i> DEVOLUÇÃO</a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="B"><i
                                        class="fa fa-circle fs-7px me-2 text-success"></i> NOTA DÉBITO</a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="R"><i
                                        class="fa fa-circle fs-7px me-2 text-danger"></i> NOTA CRÉDITO</a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="O"><i
                                        class="fa fa-circle fs-7px me-2 text-success"></i> OUTRAS ENTRADAS/SAÍDAS</a>
                            </li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="P"><i
                                        class="fa fa-circle fs-7px me-2 text-success"></i> PERMUTA</a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="T"><i
                                        class="fa fa-circle fs-7px me-2 text-success"></i> TRANSFERÊNCIA</a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="V"><i
                                        class="fa fa-circle fs-7px me-2 text-danger"></i> VENDA/PRESTAÇÃO</a></li>



                        </ul>
                    </div>


                    <!-- ativa -->
                    <div id="ativa-filter-container" class="col-lg-1 col-md-4 col-sm-6">
                        <label>Ativa:</label>
                        <a href="#" class="btn btn-white d-flex align-items-center rounded-3 btn-filtro-contorno"
                            data-bs-toggle="dropdown">
                            <iconify-icon class="fs-5 me-1" icon="solar:check-circle-linear"></iconify-icon>
                            <span class="filter-label">Ativa</span>
                            <i class="fa fa-chevron-down ms-auto"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value=""><i
                                        class="fa fa-circle fs-7px me-2 text-muted"></i> Todos</a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="S"><i
                                        class="fa fa-circle fs-7px me-2 text-success"></i> SIM</a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="N"><i
                                        class="fa fa-circle fs-7px me-2 text-danger"></i> NÃO</a></li>
                        </ul>
                    </div>
                    <!-- ent_sai -->
                    <div id="ent_sai-filter-container" class="col-lg-1 col-md-4 col-sm-6">
                        <label>Tipo Mov.:</label>
                        <a href="#" class="btn btn-white d-flex align-items-center rounded-3 btn-filtro-contorno"
                            data-bs-toggle="dropdown">
                            <iconify-icon class="fs-5 me-1" icon="solar:tag-linear"></iconify-icon>
                            <span class="filter-label">Tipo Mov.</span>
                            <i class="fa fa-chevron-down ms-auto"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value=""><i
                                        class="fa fa-circle fs-7px me-2 text-muted"></i> Todos</a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="E"><i
                                        class="fa fa-circle fs-7px me-2 text-success"></i> ENTRADA</a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" data-value="S"><i
                                        class="fa fa-circle fs-7px me-2 text-danger"></i> SAIDA</a></li>
                        </ul>
                    </div>




                    <!-- Filial -->
                    <?php 
                    $filtroFilial = isset($_GET['filtroFilial']) ? $_GET['filtroFilial'] : '';
                    ?>

                    <div id="filial-filter-container" class="col-lg-2 col-md-4 col-sm-6">
                        <label>Filial:</label>
                        <select id="filtroFilial" name="filtroFilial" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <?php
                            $empresasQuery = mysqli_query($dash, "SELECT filial, CONCAT(filial, ' - ', razao_social) as razao_social FROM ".$banco_vendas.".empresas WHERE ativa = 'S' ORDER BY filial ASC");
                            while ($empresa = mysqli_fetch_assoc($empresasQuery)) {
                                $selected = ($filtroFilial == $empresa['filial']) ? 'selected' : '';
                                echo "<option value='{$empresa['filial']}' {$selected}>{$empresa['razao_social']}</option>";
                            }
                            ?>
                        </select>
                    </div>






                    <!-- nome -->
                    <div id="nome-filter-container" class="col-lg-2 col-md-6 col-sm-12">
                        <label>Nome:</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="filtro-nome-input" class="form-control form-control-sm"
                                placeholder="Digite o nome...">
                            <button class="btn btn-outline-secondary btn-sm" type="button" id="btn-filtrar-nome">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>


                    <!-- Botões -->
                    <div class="col-lg-2 col-md-12 d-flex align-items-end">
                        <a href="#" id="clear-filters-btn" class="btn btn-danger btn-sm me-1 d-flex align-items-center">
                            <iconify-icon class="fs-5 me-1" icon="solar:broom-bold-duotone"></iconify-icon> Limpar
                        </a>
                        <a href="transacoes.php" class="btn btn-secondary btn-sm d-flex align-items-center">
                            <iconify-icon class="fs-5 me-1" icon="solar:refresh-bold-duotone"></iconify-icon> Recarregar
                        </a>
                    </div>
                </div>

                <!-- Tabela -->
                <div class="row">
                    <div class="col-xl-12">
                        <div class="panel panel-inverse">
                            <div class="panel-body">
                                <table id="tableclientes"
                                    class="table table-striped table-bordered align-middle text-nowrap" width="100%">
                                    <thead>
                                        <tr>
                                            <th>Filial</th>
                                            <th>Código</th>
                                            <th>Nome</th>
                                            <th>Tipo</th>
                                            <th>Setor</th>
                                            <th>Gerar Financeiro</th>
                                            <th>Comissão</th>
                                            <th>Produtos</th>
                                            <th>Serviços</th>
                                            <th>Entrada/Saída</th>
                                            <th>Ativa</th>
                                            <th>Ver</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once '../menu/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous">
    </script>
    <script src="../js/adminlte.js"></script>
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
    <script src="../assets/plugins/datatables.net/js/dataTables.min.js"></script>
    <script src="../assets/plugins/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script src="../assets/plugins/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
    <script src="../assets/plugins/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js"></script>
    <script src="../assets/plugins/datatables.net-buttons/js/dataTables.buttons.min.js"></script>
    <script src="../assets/plugins/datatables.net-buttons-bs5/js/buttons.bootstrap5.min.js"></script>
    <script src="../assets/plugins/jszip/dist/jszip.min.js"></script>
    <script src="../assets/plugins/pdfmake/build/pdfmake.min.js"></script>
    <script src="../assets/plugins/pdfmake/build/vfs_fonts.js"></script>
    <script src="../assets/plugins/datatables.net-buttons/js/buttons.html5.min.js"></script>
    <script src="../assets/plugins/datatables.net-buttons/js/buttons.print.min.js"></script>
    <script src="../assets/plugins/datatables.net-buttons/js/buttons.colVis.min.js"></script>

    <script>
    $(document).ready(function() {
        // Array com as colunas padrão selecionadas
        let colunasSelecionadas = ['filial', 'codigo', 'nome', 'tipo', 'gera_financeiro', 'comissao', 'setor',
            'produtos', 'servicos', 'ent_sai', 'ativa'
        ];



        // Inicializar DataTable (server-side)
        const table = $('#tableclientes').DataTable({
            processing: true,
            serverSide: true,
            lengthMenu: [
                [15, 25, 50, 100, -1],
                [15, 25, 50, 100, "Todos"]
            ],
            order: [
                [0, "desc"]
            ],
            language: {
                sEmptyTable: "Nenhum registro encontrado",
                sInfo: "Mostrando de _START_ até _END_ de _TOTAL_ registros",
                sInfoEmpty: "Mostrando 0 até 0 de 0 registros",
                sInfoFiltered: "(Filtrados de _MAX_ registros)",
                sLengthMenu: "Mostrar _MENU_ por página",
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
                url: "dados_transacoes.php",
                type: "POST",
                data: function(d) {
                    d.filtros = {
                        tipo: $('#tipo-filter-container').data('selected-value') || '',
                        filial: $('#filtroFilial').val() || '',
                        ativa: $('#ativa-filter-container').data('selected-value') || '',
                        ent_sai: $('#ent_sai-filter-container').data('selected-value') || '',
                        nome: $('#filtro-nome-input').val() || ''
                    };
                    console.log('Enviando filtros:', d.filtros);
                }
            },
            columns: [{
                    data: "filial",
                    render: (data) => {
                        return data || '-';
                    },
                    visible: true
                },
                {
                    data: "codigo",
                    visible: true
                },
                {
                    data: "nome",
                    visible: true
                },

                {
                    data: "tipo",
                    render: (data) => {
                        switch (data) {
                            case 'COMPLEMENTO':
                                return '<span class="badge border border-warning text-warning">COMPLEMENTO</span>';
                            case 'COMPRA/AQUISIÇÃO':
                                return '<span class="badge border border-success text-success">COMPRA/AQUISIÇÃO</span>';
                            case 'CONHECIMENTO DE TRANSPORTE':
                                return '<span class="badge border border-warning text-warning">CONHECIMENTO DE TRANSPORTE</span>';

                            case 'DEVOLUÇÃO':
                                return '<span class="badge border border-danger text-danger">DEVOLUÇÃO</span>';
                            case 'OUTRAS ENTRADAS/SAÍDAS':
                                return '<span class="badge border border-dark text-dark">OUTRAS ENTRADAS/SAÍDAS</span>';
                            case 'TRANSFERÊNCIA':
                                return '<span class="badge border border-light text-light">TRANSFERÊNCIA</span>';
                            case 'VENDA/PRESTAÇÃO':
                                return '<span class="badge border border-primary text-primary">VENDA/PRESTAÇÃO</span>';
                            case 'PERMUTA':
                                return '<span class="badge border border-light text-light">PERMUTA</span>';
                            case 'AJUSTE':
                                return '<span class="badge border border-light text-light">AJUSTE</span>';

                            case 'NOTA DÉBITO':
                                return '<span class="badge border border-info text-info">NOTA DÉBITO</span>';
                            case 'NOTA CRÉDITO':
                                return '<span class="badge border border-info text-info">NOTA CRÉDITO</span>';

                                return '<span class="badge border border-success text-success">65 - NOTA FISCAL ELETRONICA - NFC-E</span>';
                            default:
                                return '<span class="badge border border-dark text-dark">' + (
                                    data || '') + '</span>';
                        }
                    },
                    visible: true
                },

                {
                    data: "setor",
                    visible: true
                },
                {
                    data: "gera_financeiro",
                    visible: true
                },
                {
                    data: "comissao",
                    render: (data) => {
                        return data || '-';
                    },
                    visible: true
                },


                {
                    data: "produtos",
                    render: (data) => {
                        switch (data) {
                            case 'SIM':
                                return '<span class="badge border border-success text-success">SIM</span>';
                            case 'NÃO':
                                return '<span class="badge border border-danger text-danger">NÃO</span>';
                            default:
                                return '<span class="badge border border-dark text-dark">' + (
                                    data || '') + '</span>';
                        }
                    },
                    visible: true
                },
                {
                    data: "servicos",
                    render: (data) => {
                        switch (data) {
                            case 'SIM':
                                return '<span class="badge border border-success text-success">SIM</span>';
                            case 'NÃO':
                                return '<span class="badge border border-danger text-danger">NÃO</span>';
                            default:
                                return '<span class="badge border border-dark text-dark">' + (
                                    data || '') + '</span>';
                        }
                    },
                    visible: true
                },
                {
                    data: "ent_sai",
                    render: (data) => {
                        switch (data) {
                            case 'ENTRADA':
                                return '<span class="badge border border-success text-success">ENTRADA</span>';
                            case 'SAIDA':
                                return '<span class="badge border border-primary text-primary">SAIDA</span>';
                            default:
                                return '<span class="badge border border-dark text-dark">' + (
                                    data || '') + '</span>';
                        }
                    },
                    visible: true
                },
                {
                    data: "ativa",
                    render: (data) => {
                        switch (data) {
                            case 'SIM':
                                return '<span class="badge border border-success text-success">SIM</span>';
                            case 'NÃO':
                                return '<span class="badge border border-danger text-danger">NÃO</span>';
                            default:
                                return '<span class="badge border border-dark text-dark">' + (
                                    data || '') + '</span>';
                        }
                    },
                    visible: true
                },
                {
                    data: null,
                    render: (data) =>
                        `<a href="cad_transacao.php?codigo_tra=${data.codigo}&filial=${data.filial}" class="btn btn-primary btn-xs"><i class="bi bi-eye"></i></a>`,
                    orderable: false,
                    searchable: false,
                    visible: true
                }
            ],
            dom: 'Blfrtip',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
        });

        // Função para configurar dropdowns de forma correta
        function setupDropdown(containerId, defaultLabel) {
            const $container = $(containerId);
            const $filterLabel = $container.find('.filter-label');
            const $dropdownToggle = $container.find('[data-bs-toggle="dropdown"]');

            // Armazenar label padrão
            $filterLabel.data('default', defaultLabel);

            $container.on('click', '.dropdown-item', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $this = $(this);
                const value = $this.data('value');
                const text = $this.text().replace(/^[●\s]+|[●\s]+$/g, '').trim();

                // Remover classe active de todos os itens
                $container.find('.dropdown-item').removeClass('active');

                // Adicionar classe active apenas no item clicado
                $this.addClass('active');

                // Atualizar label
                if (value === '') {
                    $filterLabel.text(defaultLabel);
                } else {
                    $filterLabel.text(text);
                }

                // Armazenar valor selecionado no container
                $container.data('selected-value', value);

                console.log(`Filtro ${containerId}:`, value);

                // Fechar o dropdown
                const dropdownInstance = bootstrap.Dropdown.getInstance($dropdownToggle[0]);
                if (dropdownInstance) {
                    dropdownInstance.hide();
                }

                // Recarregar a tabela
                table.ajax.reload();
            });
        }

        // Configurar todos os dropdowns com seus labels padrão
        setupDropdown('#tipo-filter-container', 'Tipo');
        setupDropdown('#ativa-filter-container', 'Ativa');
        setupDropdown('#ent_sai-filter-container', 'Tipo Mov');
        setupDropdown('#filtroFilial', 'Filial');


        // Filtro de filial (select)
        $('#filtroFilial').on('change', function() {
            table.ajax.reload();
        });
        $('#ent_sai-filter-container').on('change', function() {
            table.ajax.reload();
        });

        // Filtro por nome (Enter ou botão)
        $('#btn-filtrar-nome').on('click', function() {
            table.ajax.reload();
        });

        $('#filtro-nome-input').on('keypress', function(e) {
            if (e.key === 'Enter') {
                table.ajax.reload();
            }
        });

        // Filtro por data
        $('#btn-filtrar-data').on('click', function() {
            table.ajax.reload();
        });

        // Limpar filtros
        $('#clear-filters-btn').on('click', function(e) {
            e.preventDefault();

            // Limpar todos os dropdowns
            $('[id$="-filter-container"]').each(function() {
                const $container = $(this);
                const $filterLabel = $container.find('.filter-label');
                const $dropdownToggle = $container.find('[data-bs-toggle="dropdown"]');
                const defaultLabel = $filterLabel.data('default') || $filterLabel.text();

                // Remover active de todos os itens
                $container.find('.dropdown-item').removeClass('active');

                // Ativar item "Todos" (primeiro item)
                $container.find('.dropdown-item').first().addClass('active');

                // Restaurar label padrão
                $filterLabel.text(defaultLabel);

                // Limpar valor selecionado
                $container.data('selected-value', '');

                // Fechar dropdown se estiver aberto
                const dropdownInstance = bootstrap.Dropdown.getInstance($dropdownToggle[0]);
                if (dropdownInstance) {
                    dropdownInstance.hide();
                }
            });

            // Limpar select de filial
            $('#filtroFilial').val('');

            // Limpar select de ent_sai
            $('#ent_sai-filter-container').val('');

            // Limpar inputs
            $('#filtro-nome-input').val('');

            // Recarregar tabela
            table.ajax.reload();

            console.log('Todos os filtros limpos');
        });

        // Função para atualizar a visibilidade das colunas
        function atualizarColunas() {
            const colunasMap = {
                'filial': 0,
                'codigo': 1,
                'nome': 2,
                'tipo': 3,
                'setor': 4,
                'gera_financeiro': 5,
                'comissao': 6,
                'produtos': 7,
                'servicos': 8,
                'ent_sai': 9,
                'ativa': 10
            };

            // Esconder todas as colunas primeiro
            table.columns().visible(false);

            // Mostrar apenas as colunas selecionadas
            colunasSelecionadas.forEach(coluna => {
                if (colunasMap[coluna] !== undefined) {
                    table.column(colunasMap[coluna]).visible(true);
                }
            });

            // Sempre mostrar a coluna "Ver" (índice 21)
            table.column(11).visible(true);

            console.log('Colunas atualizadas:', colunasSelecionadas);
        }

        // Controle de colunas do relatório - COM FECHAMENTO DO DROPDOWN
        $('#selecionar-todas-colunas').on('click', function() {
            $('.coluna-checkbox').prop('checked', true);
        });

        $('#limpar-colunas').on('click', function() {
            $('.coluna-checkbox').prop('checked', false);
        });

        $('#aplicar-colunas').on('click', function() {
            // Obter colunas selecionadas
            colunasSelecionadas = [];
            $('.coluna-checkbox:checked').each(function() {
                colunasSelecionadas.push($(this).val());
            });

            // Se nenhuma coluna foi selecionada, manter pelo menos o código
            if (colunasSelecionadas.length === 0) {
                colunasSelecionadas = ['codigo'];
                $('#coluna-codigo').prop('checked', true);
            }

            console.log('Colunas selecionadas:', colunasSelecionadas);

            // Atualizar a visibilidade das colunas
            atualizarColunas();

            // Fechar o dropdown das colunas
            const $colunasDropdownToggle = $(
                '#colunas-filter-container [data-bs-toggle="dropdown"]');
            const colunasDropdownInstance = bootstrap.Dropdown.getInstance($colunasDropdownToggle[
                0]);
            if (colunasDropdownInstance) {
                colunasDropdownInstance.hide();
            }
        });

        // Fechar dropdown das colunas ao clicar fora
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#colunas-filter-container').length) {
                const $colunasDropdownToggle = $(
                    '#colunas-filter-container [data-bs-toggle="dropdown"]');
                const colunasDropdownInstance = bootstrap.Dropdown.getInstance(
                    $colunasDropdownToggle[
                        0]);
                if (colunasDropdownInstance) {
                    colunasDropdownInstance.hide();
                }
            }
        });

        // Inicializar checkboxes
        function atualizarCheckboxes() {
            $('.coluna-checkbox').each(function() {
                const valor = $(this).val();
                $(this).prop('checked', colunasSelecionadas.includes(valor));
            });
        }

        // Inicializar checkboxes
        atualizarCheckboxes();

        // Atualizar colunas na inicialização
        setTimeout(atualizarColunas, 1000);

        // Inicializar dropdowns ativos
        setTimeout(function() {
            $('[id$="-filter-container"]').each(function() {
                const $container = $(this);
                const $firstItem = $container.find('.dropdown-item').first();
                if ($firstItem.length) {
                    $firstItem.addClass('active');
                }
            });
        }, 500);
    });
    </script>
</body>

</html>