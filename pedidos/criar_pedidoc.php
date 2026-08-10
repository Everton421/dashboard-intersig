<?php
require_once '../config/config.php';
require_once '../config/session.php'; 

if (!isset($_SESSION["user_name"])) {
    header("Location: ../index.php");
    exit();
}

$nomeEmpresa = $empresa_nome ?? 'Empresa';
date_default_timezone_set('America/Sao_Paulo');

// Processamento do Formulário
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_pedido'])) {
    $filial = $_POST['filial'];
    $fornecedor = $_POST['fornecedor_id'];
    $comprador = $_POST['comprador'];
    $data_cadastro = date('Y-m-d');
    $observacoes = $_POST['observacoes'];
    $observacoes2 = $_POST['observacoes2'];
    $contato = $_POST['contato'];

    // Inicia Transação
    $dash->begin_transaction();

    try {
        // 1. Inserir Header (cad_comp)
        // Precisamos do próximo código. Se for auto-increment ok, senão buscamos o MAX + 1
        $resMax = $dash->query("SELECT MAX(codigo) as max_cod FROM {$banco_vendas}.cad_comp");
        $rowMax = $resMax->fetch_assoc();
        $novo_codigo = ($rowMax['max_cod'] ?? 0) + 1;

        $total_produtos = 0;
        $desc_total = 0;
        
        
        // Processar Itens primeiro para calcular totais
        $itens = [];
        if (isset($_POST['item_id'])) {
            foreach ($_POST['item_id'] as $i => $id) {
                $qtd = (float)$_POST['item_qtd'][$i];
                $sigla = $_POST['item_sigla'][$i];
                $preco = (float)$_POST['item_preco'][$i];
                $desc = (float)$_POST['item_desc'][$i];
                $total_liq = ($qtd * $preco) - $desc;

                $itens[] = [
                    'ordem' => $novo_codigo,
                    'sequencia' => count($itens) + 1,
                    'produto' => $id,
                    'quantidade' => $qtd,
                    'unidade' => $sigla,
                    'unitario' => $preco,
                    'desconto' => $desc,
                    'total_liq' => $total_liq
                ];

                $total_produtos += ($qtd * $preco);
                $desc_total += $desc;
            }
        }

        $total_geral = $total_produtos - $desc_total;

        $qtde_pgto = (int)($_POST['qtde_pgto'] ?? 0);
        $intervalo_pgto = (int)($_POST['intervalo_pgto'] ?? 0);
        $primeiro_pgto = ($_POST['intervalo_pgto'][0] ?? 0);

        $stmt = $dash->prepare("INSERT INTO {$banco_vendas}.cad_comp (codigo, filial, fornecedor, contato, comprador, data_cadastro, total_produtos, desc_prod, total_geral, situacao, observacoes, observacoes2, tipo, data_receb, qtde_pgto, primeiro_pgto, intervalo_pgto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'EA', ?, ?, 1, '0000-00-00', ?, ?, ?)");
      $stmt->bind_param(
    "iiisisdddssisi",
    $novo_codigo,
    $filial,
    $fornecedor,
    $contato,
    $comprador,
    $data_cadastro,
    $total_produtos,
    $desc_total,
    $total_geral,
    $observacoes,
    $observacoes2,
    $qtde_pgto,
    $primeiro_pgto,
    $intervalo_pgto
);
//mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $stmt->execute();

        // 2. Inserir Itens (pro_comp)
        $stmtItem = $dash->prepare("INSERT INTO {$banco_vendas}.pro_comp (ordem, sequencia, produto, quantidade, unidade, unitario, desconto,
        grade,  fator_val, fator_qtde, qtde_mov, qtde_faturada, aprovado, cst_ipi) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, 1, 0, 0, 'A', '00')");
        foreach ($itens as $item) {
            $stmtItem->bind_param("iisdsdd", $item['ordem'], $item['sequencia'], $item['produto'], $item['quantidade'], $item['unidade'], $item['unitario'], $item['desconto']);
            $stmtItem->execute();
        }   

        // 3. Inserir Parcelas (par_comp)
        if (isset($_POST['parc_valor'])) {
            $stmtParc = $dash->prepare("INSERT INTO {$banco_vendas}.par_comp (ordem, parcela, valor, vencimento, tipo_receb) VALUES (?, ?, ?, ?, 1)");
            foreach ($_POST['parc_valor'] as $p => $valor) {
                $num_parc = $p + 1;
                $venc = $_POST['parc_venc'][$p];
                $stmtParc->bind_param("iids", $novo_codigo, $num_parc, $valor, $venc);
                $stmtParc->execute();
            }
        }

        $dash->commit();
        header("Location: pedidoc.php?codigo_pedido=" . $novo_codigo . "&status=criado");
        exit;

    } catch (Exception $e) {
        $dash->rollback();
        $mensagem = "Erro ao salvar pedido: " . $e->getMessage();
    }
}

// Buscar Filiais
$result_filial = $dash->query("SELECT filial, nome_fantasia FROM {$banco_vendas}.empresas WHERE ativa = 'S' ORDER BY filial ASC");
$filiais = $result_filial->fetch_all(MYSQLI_ASSOC);

// Buscar Compradores
$result_vend = $dash->query("SELECT codigo, nome FROM {$banco_publico}.cad_vend WHERE ativo = 'S' AND tipo <> 'V' ORDER BY nome ASC");
$compradores = $result_vend->fetch_all(MYSQLI_ASSOC);

?>

<!doctype html>
<html lang="pt-br">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Novo Pedido de Compra | <?= $nomeEmpresa ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../css/adminlte.css" />
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <style>
        .card { border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border: none; margin-bottom: 20px; }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #555; }
        .table thead th { background-color: #f8f9fa; border-top: none; font-size: 0.8rem; text-transform: uppercase; color: #777; }
        #lista-itens tr td { vertical-align: middle; }
        .remove-row { color: #dc3545; cursor: pointer; }
        .remove-row:hover { color: #a71d2a; }
        .ui-autocomplete { z-index: 2000 !important; }
        [data-bs-theme="dark"] .form-label { color: #ccc; }
        [data-bs-theme="dark"] .table thead th { background-color: #2b2b2b; color: #aaa; }

        @media (max-width: 768px) {
            .table-responsive table, .table-responsive thead, .table-responsive tbody, .table-responsive th, .table-responsive td, .table-responsive tr { 
                display: block; 
            }
            .table-responsive thead tr { 
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            .table-responsive tr { 
                border: 1px solid #444; 
                margin-bottom: 1rem;
                padding: 0.5rem;
                border-radius: 8px;
                background: #212529;
            }
            .table-responsive td { 
                border: none;
                position: relative;
                padding-left: 45% !important; 
                text-align: right !important;
                min-height: 45px;
                display: flex;
                align-items: center;
                justify-content: flex-end;
                border-bottom: 1px solid #333;
            }
            .table-responsive td:last-child {
                border-bottom: none;
            }
            .table-responsive td::before { 
                content: attr(data-label);
                position: absolute;
                left: 10px;
                width: 40%; 
                padding-right: 10px; 
                white-space: nowrap;
                text-align: left;
                font-weight: bold;
                font-size: 0.75rem;
                color: #aaa;
                text-transform: uppercase;
            }
            .table-responsive td input {
                width: 100% !important;
                max-width: 100% !important;
            }
            .table-responsive td[data-label="Produto"] {
                padding-left: 10px !important;
                display: block;
                text-align: left !important;
                height: auto;
            }
            .table-responsive td[data-label="Produto"]::before {
                position: static;
                display: block;
                width: 100%;
                margin-bottom: 5px;
            }
            .table-responsive td[data-label=""] { padding-left: 10px !important; justify-content: center; }
            .table-responsive td[data-label=""]::before { display: none; }

            .table-responsive tfoot tr {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                border-bottom: 1px solid #444;
                padding: 10px 0;
            }
            .table-responsive tfoot th {
                display: block;
                width: auto !important;
                border: none !important;
                padding: 0 !important;
            }
            .table-responsive tfoot th:first-child {
                font-weight: normal;
                color: #aaa;
            }
            .table-responsive tfoot th:last-child {
                display: none;
            }
            .table-responsive tfoot tr:last-child {
                border-bottom: none;
                font-size: 1.1rem;
            }
        }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open" data-bs-theme="dark">
    <div class="app-wrapper">
        <?php require_once '../menu/nav_dash.php'; ?>
        <?php require_once '../menu/sidebar.php'; ?>
        <?php require_once '../menu/menu_dash.php'; ?>
        </aside>

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6"><h3 class="mb-0">Novo Pedido de Compra</h3></div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                <li class="breadcrumb-item"><a href="resumo_pedidosc.php">Compras</a></li>
                                <li class="breadcrumb-item active">Novo Pedido</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    <?php if ($mensagem): ?>
                        <div class="alert alert-danger"><?= $mensagem ?></div>
                    <?php endif; ?>

                    <form method="POST" id="form-pedido">
                        <div class="row">
                            <!-- Cabeçalho do Pedido -->
                            <div class="col-lg-12">
                                <div class="card p-4">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">Filial</label>
                                            <select name="filial" class="form-select form-select-sm" required>
                                                <?php foreach ($filiais as $f): ?>
                                                    <option value="<?= $f['filial'] ?>"><?= $f['filial'] ?> - <?= htmlspecialchars($f['nome_fantasia']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">Fornecedor (Busca)</label>
                                            <input type="text" id="fornecedor_busca" class="form-control form-control-sm" placeholder="Digite o nome do fornecedor..." required>
                                            <input type="hidden" name="fornecedor_id" id="fornecedor_id">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Comprador</label>
                                            <select name="comprador" class="form-select form-select-sm" required>
                                                <option value="">Selecione...</option>
                                                <?php foreach ($compradores as $c): ?>
                                                    <option value="<?= $c['codigo'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Contato</label>
                                            <input type="text" name="contato" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Observações 1</label>
                                            <input type="text" name="observacoes" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">Observações 2</label>
                                            <input type="text" name="observacoes2" class="form-control form-control-sm">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Itens do Pedido -->
                            <div class="col-lg-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Itens do Pedido</h5>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addRowItem()"><i class="bi bi-plus-lg pe-1"></i> Adicionar Item</button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th style="width: 40%;">Produto</th>
                                                    <th class="text-center" style="width: 10%;">Qtd</th>
                                                    <th  class="text-center" style="width: 5%;">Unid</th>
                                                    <th class="text-end" style="width: 12%;">Vlr Unit.</th>
                                                    <th class="text-end" style="width: 5%;">Desconto</th>
                                                    <th class="text-end" style="width: 12%;">Total</th>
                                                    <th style="width: 40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="lista-itens">
                                                <!-- Linhas inseridas via JS -->
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th colspan="4" class="text-end">Total Produtos:</th>
                                                    <th class="text-end" id="total-produtos">R$ 0,00</th>
                                                    <th></th>
                                                </tr>
                                                <tr>
                                                    <th colspan="4" class="text-end">Total Descontos:</th>
                                                    <th class="text-end text-danger" id="total-descontos">R$ 0,00</th>
                                                    <th></th>
                                                </tr>
                                                <tr>
                                                    <th colspan="4" class="text-end fs-5">Total Geral:</th>
                                                    <th class="text-end fs-5 fw-bold text-primary" id="total-geral">R$ 0,00</th>
                                                    <th></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagamento -->
                            <div class="col-lg-12">
                                <div class="card p-4">
                                    <h5 class="mb-4">Condições de Pagamento</h5>
                                    <div class="row g-3 mb-4 align-items-end">
                                        <div class="col-md-2">
                                            <label class="form-label">Número de Parcelas</label>
                                            <input type="number" name="qtde_pgto" id="num_parcelas" class="form-control form-control-sm" value="1" min="1">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Intervalo (Dias)</label>
                                            <input type="number" name="intervalo_pgto" id="intervalo_dias" class="form-control form-control-sm" value="30" min="1">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="gerarParcelas()">Gerar Parcelas</button>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive" style="max-width: 600px;">
                                        <table class="table table-sm table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Parcela</th>
                                                    <th>Vencimento</th>
                                                    <th class="text-end">Valor</th>
                                                    <th style="width: 40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="lista-parcelas">
                                                <!-- Linhas via JS -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-12 text-end mb-5">
                               <button type="button" class="btn btn-outline-danger btn-lg px-5" onclick="cancelarPedido()">Cancelar</button>
                                <button type="submit" name="salvar_pedido" class="btn btn-primary btn-lg px-5">Salvar Pedido</button>

                                <script>
function cancelarPedido() {
    if (confirm("Deseja cancelar o pedido e apagar todos os dados?")) {
        localStorage.removeItem('draft_pedido_compra');
        window.location.href = "resumo_pedidosc.php";
    }
}



</script>

                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
        <?php require_once '../menu/footer.php'; ?>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/adminlte.js"></script>

    <script>
        function formatMoney(v) { return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }

        // Autocomplete Fornecedor
        $("#fornecedor_busca").autocomplete({
            source: "../cadastros/buscar_fornecedor.php",
            minLength: 2,
            select: function(event, ui) {
                $("#fornecedor_id").val(ui.item.codigo);
                $(this).val(ui.item.fornecedor);
                return false;
            }
        }).data("ui-autocomplete")._renderItem = function(ul, item) {
            return $("<li>").append("<div>" + item.codigo + " - " + item.fornecedor + "</div>").appendTo(ul);
        };

        function addRowItem(savedData = null) {
            const id = Date.now() + Math.floor(Math.random() * 1000);
            const html = `
                <tr id="row-${id}">
                    <td data-label="Produto">
                        <input type="text" class="form-control form-control-sm item-busca" placeholder="Busca produto..." data-row="${id}" value="${savedData ? savedData.busca : ''}">
                        <input type="hidden" name="item_id[]" id="id-${id}" value="${savedData ? savedData.id : ''}">
                    </td>
                    <td data-label="Qtd"><input type="number" name="item_qtd[]" class="form-control form-control-sm text-center calc-trigger" value="${savedData ? savedData.qtd : '1'}" min="0.01" step="0.01" data-row="${id}"></td>
                    <td data-label="Unid"><input type="text" name="item_sigla[]" class="form-control form-control-sm text-center" readonly id="sigla-${id}" value="${savedData ? savedData.sigla : ''}"></td>
                    <td data-label="Vlr Unit."><input type="number" name="item_preco[]" class="form-control form-control-sm text-end calc-trigger" value="${savedData ? savedData.preco : '0.00'}" step="0.000001" data-row="${id}" id="preco-${id}"></td>
                    <td data-label="Desconto"><input type="number" name="item_desc[]" class="form-control form-control-sm text-end calc-trigger" value="${savedData ? savedData.desc : '0.00'}" step="0.01" data-row="${id}"></td>
                    <td data-label="Total"><input type="text" class="form-control form-control-sm text-end total-row" value="R$ 0,00" readonly id="total-${id}"></td>
                    <td class="text-center" data-label=""><i class="bi bi-trash remove-row" onclick="removeRow('row-${id}')"></i></td>
                </tr>
            `;
            $("#lista-itens").append(html);
            initAutocompleteRow(id);
            if (savedData) updateCalculations();
            saveDraft();
        }

        function initAutocompleteRow(rowId) {
            $(`#row-${rowId} .item-busca`).autocomplete({
                source: "buscar_produto_ajax.php",
                minLength: 2,
                select: function(event, ui) {
                    $(`#id-${rowId}`).val(ui.item.id);
                    $(`#preco-${rowId}`).val(ui.item.preco.toFixed(2));
                    $(`#sigla-${rowId}`).val(ui.item.sigla);
                    $(this).val(ui.item.text);
                    updateCalculations();
                    saveDraft();
                    return false;
                }
            });
        }

        function removeRow(id) {
            $(`#${id}`).remove();
            updateCalculations();
            saveDraft();
        }

        $(document).on('input change', '#form-pedido input, #form-pedido select', function() {
            updateCalculations();
            saveDraft();
        });

        function updateCalculations() {
            let totalProd = 0;
            let totalDesc = 0;

            $("#lista-itens tr").each(function() {
                const id = $(this).find('.item-busca').data('row');
                const qtd = parseFloat($(this).find('input[name="item_qtd[]"]').val()) || 0;
                const sigla = $(this).find('input[name="item_sigla[]"]').val();
                const preco = parseFloat($(this).find('input[name="item_preco[]"]').val()) || 0;
                const desc = parseFloat($(this).find('input[name="item_desc[]"]').val()) || 0;
                
                const sub = (qtd * preco);
                const liq = sub - desc;
                
                $(`#total-${id}`).val(formatMoney(liq));
                totalProd += sub;
                totalDesc += desc;
            });

            $("#total-produtos").text(formatMoney(totalProd));
            $("#total-descontos").text(formatMoney(totalDesc));
            $("#total-geral").text(formatMoney(totalProd - totalDesc));
        }

        function gerarParcelas() {
            const n = parseInt($("#num_parcelas").val()) || 1;
            const intervalo = parseInt($("#intervalo_dias").val()) || 30;
            const totalGeralText = $("#total-geral").text().replace('R$', '').replace(/\./g, '').replace(',', '.').trim();
            const totalGeral = parseFloat(totalGeralText) || 0;
            
            $("#lista-parcelas").empty();
            if (totalGeral <= 0) return alert("Adicione itens ao pedido primeiro.");

            const valorParc = (totalGeral / n).toFixed(2);
            let resto = (totalGeral - (valorParc * n)).toFixed(2);
            
            for (let i = 0; i < n; i++) {
                const someDays = (i + 1) * intervalo;
                const d = new Date();
                d.setDate(d.getDate() + someDays);
                const dateStr = d.toISOString().split('T')[0];
                
                let vFinal = parseFloat(valorParc);
                if (i === 0) vFinal += parseFloat(resto); // Ajuste de centavos na primeira parcela

                const html = `
                    <tr id="parc-${i}">
                        <td class="align-middle" data-label="Parcela">Parcela ${i+1}</td>
                        <td data-label="Vencimento"><input type="date" name="parc_venc[]" class="form-control form-control-sm" value="${dateStr}"></td>
                        <td data-label="Valor"><input type="number" name="parc_valor[]" class="form-control form-control-sm text-end" value="${vFinal.toFixed(2)}" step="0.01"></td>
                        <td class="text-center" data-label=""><i class="bi bi-trash remove-row" onclick="removeParc('parc-${i}')"></i></td>
                    </tr>
                `;
                $("#lista-parcelas").append(html);
            }
            saveDraft();
        }

        function removeParc(id) { 
            $(`#${id}`).remove(); 
            saveDraft();
        }

        // --- SISTEMA DE BACKUP (LOCALSTORAGE) ---
        function saveDraft() {
            const formData = {
                filial: $('select[name="filial"]').val(),
                fornecedor_id: $('#fornecedor_id').val(),
                fornecedor_busca: $('#fornecedor_busca').val(),
                comprador: $('select[name="comprador"]').val(),
                contato: $('input[name="contato"]').val(),
                observacoes: $('input[name="observacoes"]').val(),
                observacoes2: $('input[name="observacoes2"]').val(),
                qtde_pgto: $('input[name="qtde_pgto"]').val(),
                intervalo_pgto: $('input[name="intervalo_pgto"]').val(),
                itens: [],
                parcelas: []
            };

            $("#lista-itens tr").each(function() {
                formData.itens.push({
                    id: $(this).find('input[name="item_id[]"]').val(),
                    busca: $(this).find('.item-busca').val(),
                    qtd: $(this).find('input[name="item_qtd[]"]').val(),
                    sigla: $(this).find('input[name="item_sigla[]"]').val(),
                    preco: $(this).find('input[name="item_preco[]"]').val(),
                    desc: $(this).find('input[name="item_desc[]"]').val()
                });
            });

            $("#lista-parcelas tr").each(function() {
                formData.parcelas.push({
                    venc: $(this).find('input[name="parc_venc[]"]').val(),
                    valor: $(this).find('input[name="parc_valor[]"]').val()
                });
            });

            localStorage.setItem('draft_pedido_compra', JSON.stringify(formData));
        }

        function restoreDraft() {
            const draft = localStorage.getItem('draft_pedido_compra');
            if (!draft) return addRowItem();

            const data = JSON.parse(draft);
            
            $('select[name="filial"]').val(data.filial);
            $('#fornecedor_id').val(data.fornecedor_id);
            $('#fornecedor_busca').val(data.fornecedor_busca);
            $('select[name="comprador"]').val(data.comprador);
            $('input[name="contato"]').val(data.contato);
            $('input[name="observacoes"]').val(data.observacoes);
            $('input[name="observacoes2"]').val(data.observacoes2);
            $('input[name="qtde_pgto"]').val(data.qtde_pgto);
            $('input[name="intervalo_pgto"]').val(data.intervalo_pgto);

            $("#lista-itens").empty();
            if (data.itens.length > 0) {
                data.itens.forEach(item => addRowItem(item));
            } else {
                addRowItem();
            }

            $("#lista-parcelas").empty();
            data.parcelas.forEach((p, i) => {
                const html = `
                    <tr id="parc-${i}">
                        <td class="align-middle" data-label="Parcela">Parcela ${i+1}</td>
                        <td data-label="Vencimento"><input type="date" name="parc_venc[]" class="form-control form-control-sm" value="${p.venc}"></td>
                        <td data-label="Valor"><input type="number" name="parc_valor[]" class="form-control form-control-sm text-end" value="${p.valor}" step="0.01"></td>
                        <td class="text-center" data-label=""><i class="bi bi-trash remove-row" onclick="removeParc('parc-${i}')"></i></td>
                    </tr>
                `;
                $("#lista-parcelas").append(html);
            });

            updateCalculations();
        }

        $("#form-pedido").on('submit', function() {
            localStorage.removeItem('draft_pedido_compra');
        });

        // Inicializa
        $(document).ready(function() {
            restoreDraft();
        });
    </script>
</body>
</html>
```
