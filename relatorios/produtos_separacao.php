<?php
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

function convert_date_br_to_sql($date_input) {
    if (empty($date_input)) return '';
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date_input, $matches)) {
        return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
    }
    return $date_input;
}

// --- FILTROS DINÂMICOS ---
$filial = $_GET['filial'] ?? '';
$data_inicio_raw = $_GET['data_inicio'] ?? '';
$data_fim_raw = $_GET['data_fim'] ?? '';

$grupos_selecionados = $_GET['grupo'] ?? [];
$grupos_selecionados = array_values(array_filter(array_map('intval', (array) $grupos_selecionados)));

$data_inicio = convert_date_br_to_sql($data_inicio_raw);
$data_fim = convert_date_br_to_sql($data_fim_raw);

$filtros_dinamicos = [];
$params = [];

if ($filial !== '') {
    $filtros_dinamicos[] = "o.FILIAL = ?";
    $params[] = $filial;
}
if ($data_inicio) {
    $filtros_dinamicos[] = "o.DATA_PEDIDO >= ?";
    $params[] = $data_inicio;
}
if ($data_fim) {
    $filtros_dinamicos[] = "o.DATA_PEDIDO <= ?";
    $params[] = $data_fim;
}
if (!empty($grupos_selecionados)) {
    $placeholders = implode(',', array_fill(0, count($grupos_selecionados), '?'));
    $filtros_dinamicos[] = "pr.GRUPO IN ($placeholders)";
    foreach ($grupos_selecionados as $g) {
        $params[] = $g;
    }
}

$where = "WHERE o.TIPO = 1
    AND o.SITUACAO IN ('AI','FP')
    AND o.SIT_SEPAR IN ('N','P')
    AND (p.QUANTIDADE - p.QTDE_SEPARADA) > 0";

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

// Obter lista de grupos de produtos
$result_grupos = mysqli_query($dash, "SELECT codigo, nome FROM {$banco_publico}.cad_pgru WHERE ativo = 'S' ORDER BY nome ASC");
$grupos = [];
while ($row = mysqli_fetch_assoc($result_grupos)) {
    $grupos[] = [
        'codigo' => $row['codigo'],
        'nome' => $row['nome']
    ];
}

// --- RESUMO DOS FILTROS PARA IMPRESSÃO ---
$nomes_filiais = [];
foreach ($filiais as $f) {
    $nomes_filiais[$f['codigo']] = $f['nome'];
}
$nomes_grupos = [];
foreach ($grupos as $g) {
    $nomes_grupos[(int) $g['codigo']] = $g['nome'];
}

$filtro_filial_txt = ($filial !== '' && isset($nomes_filiais[$filial]))
    ? $filial . ' - ' . $nomes_filiais[$filial]
    : 'Todas';

$filtro_periodo_txt = ($data_inicio_raw || $data_fim_raw)
    ? (($data_inicio_raw ?: 'início') . ' a ' . ($data_fim_raw ?: 'fim'))
    : 'Todos';

$grupos_nomes_txt = [];
foreach ($grupos_selecionados as $gid) {
    $grupos_nomes_txt[] = $nomes_grupos[$gid] ?? $gid;
}
$filtro_grupos_txt = !empty($grupos_nomes_txt) ? implode(', ', $grupos_nomes_txt) : 'Todos';

// --- CONSULTA PRINCIPAL ---
$sqlProdutos = "
    SELECT o.CLIENTE AS cliente_codigo,
           c.nome AS cliente_nome,
           o.CODIGO AS pedido,
           p.PRODUTO AS produto_codigo,
           (p.QUANTIDADE - p.QTDE_SEPARADA) AS quantidade,
           pr.DESCRICAO AS produto_nome,
           o.DATA_PEDIDO
    FROM {$banco_vendas}.cad_orca o
    INNER JOIN {$banco_vendas}.pro_orca p ON p.ORCAMENTO = o.CODIGO
    LEFT JOIN {$banco_publico}.cad_clie c ON c.CODIGO = o.CLIENTE
    LEFT JOIN {$banco_publico}.cad_prod pr ON pr.CODIGO = p.PRODUTO
    {$where}
    ORDER BY o.DATA_PEDIDO DESC, o.CODIGO ASC, pr.DESCRICAO ASC
";

$dadosProdutos = mysqli_fetch_all(executar_consulta_preparada($dash, $sqlProdutos, $params), MYSQLI_ASSOC);
?>

<!doctype html>
<html lang="pt-br">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Produtos p/ Separação <?php echo htmlspecialchars($nomeEmpresa); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <link rel="preload" href="../css/adminlte.css" as="style" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.1/index.css" media="print" onload="this.media='all'" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../css/adminlte.css" />
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">

    <style>
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

    .select-filial {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #444;
        border-radius: 8px;
        background-color: #1e1e1e;
        color: #fff;
        font-size: 15px;
        outline: none;
        transition: all 0.2s ease;
    }

    .select-filial:hover,
    .select-filial:focus {
        border-color: #00bcd4;
        box-shadow: 0 0 0 2px #00bcd440;
    }

    #grupos-filter-container .dropdown-menu {
        min-width: 100%;
        max-height: 300px;
        overflow-y: auto;
    }

    .grupos-todos-btn {
        height: 36px;
        padding: 6px 12px;
        font-size: 13px;
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

    td, th {
        font-size: 12px;
    }

    .print-only {
        display: none;
    }

    @page {
        size: landscape;
        margin: 8mm;
    }

    /* REGRAS DE IMPRESSÃO */
    @media print {
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .app-header,
        .app-sidebar,
        aside,
        .app-content-header,
        .filter-card,
        .app-footer,
        .no-print {
            display: none !important;
        }

        .print-only {
            display: block !important;
            margin-bottom: 12px;
        }

        html,
        body,
        body.layout-fixed,
        body.layout-fixed .app-wrapper,
        body.layout-fixed .app-main,
        .app-wrapper,
        .app-main,
        .app-content,
        .container-fluid {
            position: static !important;
            height: auto !important;
            min-height: auto !important;
            max-height: none !important;
            overflow: visible !important;
            background-color: #ffffff !important;
            color: #000000 !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        .card,
        .card-body {
            background-color: #ffffff !important;
            color: #000000 !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .table-responsive {
            overflow: visible !important;
            display: block !important;
        }

        .table {
            width: 100% !important;
            border-collapse: collapse !important;
            background-color: #ffffff !important;
            color: #000000 !important;
        }

        .table > :not(caption) > * > * {
            background-color: #ffffff !important;
            color: #000000 !important;
            box-shadow: none !important;
        }

        .table th,
        .table td {
            color: #000000 !important;
            border: 1px solid #333333 !important;
            padding: 4px 6px !important;
            font-size: 11px !important;
        }

        .table thead th {
            background-color: #e9ecef !important;
            color: #000000 !important;
            font-weight: bold !important;
        }

        .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: #f2f2f2 !important;
            color: #000000 !important;
        }

        .table tfoot th {
            background-color: #e9ecef !important;
            color: #000000 !important;
        }

        tr {
            page-break-inside: avoid !important;
        }
    }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open" data-bs-theme="dark">

    <div class="app-wrapper">
        <?php require_once '../menu/nav_dash.php'; ?>
        <?php require_once '../menu/sidebar.php'; ?>
        <?php require_once '../menu/menu_dash.php'; ?>
        </div>
        <!--end::Sidebar Wrapper-->
    </aside>
    <!--end::Sidebar-->

    <main class="app-main">
        <div class="app-content-header no-print">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h3 class="mb-0">Produtos Pendentes de Separação</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">

                <div class="row no-print">
                    <div class="col-lg-12">
                        <div class="card filter-card">
                            <div class="card-header">
                                <strong>Filtros</strong>
                            </div>
                            <div class="card-body">
                                <form method="GET" id="form-filtros">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-3">
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

                                        <div class="col-md-3">
                                            <label class="form-label">Grupos de Produtos</label>
                                            <div class="dropdown" id="grupos-filter-container">
                                                <button type="button"
                                                    class="btn btn-outline-info w-100 d-flex align-items-center grupos-todos-btn"
                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bi bi-funnel me-1"></i>
                                                    <span id="grupos-selected-label">Todos os grupos</span>
                                                    <i class="bi bi-chevron-down ms-auto"></i>
                                                </button>
                                                <div class="dropdown-menu p-3 w-100">
                                                    <h6 class="mb-2">Selecione os grupos:</h6>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox" id="grupo-todos">
                                                        <label class="form-check-label fw-bold" for="grupo-todos">Todos</label>
                                                    </div>
                                                    <hr class="my-2">
                                                    <?php foreach ($grupos as $g): ?>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input grupo-checkbox"
                                                            type="checkbox" name="grupo[]"
                                                            value="<?= $g['codigo'] ?>"
                                                            id="grupo-<?= $g['codigo'] ?>"
                                                            <?= (in_array((int) $g['codigo'], $grupos_selecionados, true) ? 'checked' : '') ?>>
                                                        <label class="form-check-label" for="grupo-<?= $g['codigo'] ?>">
                                                            <?= htmlspecialchars($g['nome']) ?>
                                                        </label>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-2 d-flex gap-2">
                                            <button type="submit" class="btn btn-primary btn-sm-custom flex-grow-1">Filtrar</button>
                                            <?php if ($filial !== '' || $data_inicio_raw || $data_fim_raw || !empty($grupos_selecionados)): ?>
                                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-secondary btn-sm-custom flex-grow-1">Limpar</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($dadosProdutos)): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="print-only">
                                <h4 style="margin: 0 0 4px 0;">Produtos Pendentes de Separação</h4>
                                <div style="font-size: 13px; font-weight: bold; margin-bottom: 4px;">
                                    <?= htmlspecialchars($nomeEmpresa) ?>
                                </div>
                                <div style="font-size: 11px; margin-bottom: 10px;">
                                    <strong>Filial:</strong> <?= htmlspecialchars($filtro_filial_txt) ?> |
                                    <strong>Período:</strong> <?= htmlspecialchars($filtro_periodo_txt) ?> |
                                    <strong>Grupos:</strong> <?= htmlspecialchars($filtro_grupos_txt) ?>
                                </div>
                            </div>

                            <div class="card-header no-print">
                                <strong>Produtos para Produção</strong>
                                <button class="btn btn-secondary btn-sm float-end me-2 no-print" onclick="window.print()">
                                    <i class="bi bi-printer"></i> Imprimir
                                </button>
                                <button class="btn btn-success btn-sm float-end no-print"
                                    onclick="exportToExcel('tabelaSeparacao', 'Produtos_Separacao.xlsx')">
                                    <i class="bi bi-file-earmark-excel"></i> Exportar para Excel
                                </button>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tabelaSeparacao" class="table table-striped table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>Cliente</th>
                                                <th>Código do Pedido</th>
                                                <th>Código do Produto</th>
                                                <th>Quantidade</th>
                                                <th>Nome do Produto</th>
                                                <th>Data do Pedido</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $total_quantidade = 0;
                                            foreach ($dadosProdutos as $d):
                                                $total_quantidade += (float) $d['quantidade'];
                                            ?>
                                            <tr>
                                                <td>
                                                    <?= htmlspecialchars($d['cliente_codigo']) ?>
                                                    <?php if (!empty($d['cliente_nome'])): ?>
                                                    - <?= htmlspecialchars($d['cliente_nome']) ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($d['pedido']) ?></td>
                                                <td><?= htmlspecialchars($d['produto_codigo']) ?></td>
                                                <td><?= number_format((float) $d['quantidade'], 2, ",", ".") ?></td>
                                                <td><?= htmlspecialchars($d['produto_nome']) ?></td>
                                                <td><?= !empty($d['DATA_PEDIDO']) ? date('d/m/Y', strtotime($d['DATA_PEDIDO'])) : '' ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="3" class="text-end">
                                                    TOTAIS (<?= count($dadosProdutos) ?> itens)
                                                </th>
                                                <th><?= number_format($total_quantidade, 2, ",", ".") ?></th>
                                                <th colspan="2"></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-secondary" role="alert">
                    Nenhum produto pendente de separação foi encontrado para os filtros selecionados.
                </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <?php require_once '../menu/footer.php'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <script src="../js/adminlte.js"></script>

    <script>
    // Alterna para tema claro e remove travas do layout durante a impressão
    window.addEventListener('beforeprint', function () {
        document.body.classList.remove('layout-fixed');
        document.body.setAttribute('data-bs-theme', 'light');
    });

    window.addEventListener('afterprint', function () {
        document.body.classList.add('layout-fixed');
        document.body.setAttribute('data-bs-theme', 'dark');
    });

    function mascaraData(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length > 2) value = value.substring(0, 2) + '/' + value.substring(2);
        if (value.length > 5) value = value.substring(0, 5) + '/' + value.substring(5, 9);
        input.value = value;
    }

    document.addEventListener('DOMContentLoaded', function() {
        ['data_inicio', 'data_fim'].forEach(function(id) {
            const input = document.getElementById(id);
            if (input) input.addEventListener('input', function() {
                mascaraData(this);
            });
        });

        const container = document.getElementById('grupos-filter-container');
        const label = document.getElementById('grupos-selected-label');
        const todos = document.getElementById('grupo-todos');
        const checkboxes = Array.from(document.querySelectorAll('.grupo-checkbox'));

        if (!container || !label || !todos) return;

        container.querySelector('.dropdown-menu').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        function atualizarLabel() {
            const marcados = checkboxes.filter(c => c.checked).length;

            if (marcados === 0 || marcados === checkboxes.length) {
                label.textContent = 'Todos os grupos';
                todos.checked = marcados === checkboxes.length && checkboxes.length > 0;
            } else {
                label.textContent = marcados + ' grupo(s) selecionado(s)';
                todos.checked = false;
            }
        }

        todos.addEventListener('change', function() {
            checkboxes.forEach(c => {
                c.checked = todos.checked;
            });
            atualizarLabel();
        });

        checkboxes.forEach(c => {
            c.addEventListener('change', atualizarLabel);
        });

        atualizarLabel();
    });

    function exportToExcel(tableId, filename) {
        const table = document.getElementById(tableId);
        const wb = XLSX.utils.table_to_book(table, {
            sheet: "Dados"
        });
        XLSX.writeFile(wb, filename);
    }
    </script>
</body>

</html>