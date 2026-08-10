<?php 
require_once '../config/config.php';
require_once '../config/session.php'; 

date_default_timezone_set('America/Sao_Paulo');
mysqli_set_charset($dash, "utf8");


// 1. Recebe o codigo_tra e a filial via GET de forma segura
$codigo_tra = isset($_GET['codigo_tra']) ? intval($_GET['codigo_tra']) : 0;
// Filial geralmente é string (para manter o zero a esquerda, ex: "01"), mas validamos para não ser null
$filial = isset($_GET['filial']) ? $_GET['filial'] : '';

// Variáveis iniciais
$serie = null;

// Buscar dados da série
if ($codigo_tra > 0) {
    $sql = "SELECT 
        s.codigo, 
        s.filial,
        s.nome,
        s.natureza,
        CONCAT(s.filial, ' - ', e.razao_social) AS filial_nome,
        s.ent_sai, 
        s.referenciar,
        s.gera_financeiro,
        s.cli_for,
        s.produtos,
        s.servicos,
        s.gera_credito,
        s.comissao,
        s.tribut,
        s.setor,
        se.nome as setor_descricao,
        s.ativa,
           s.cfop01,
    concat (c1.cfop, ' - ', c1.descricao) AS cfop01_descricao,

    s.cfop02,
      concat (c2.cfop, ' - ', c2.descricao) AS cfop02_descricao,

    s.cfop03,
      concat (c3.cfop, ' - ', c3.descricao) AS cfop03_descricao,

    s.cfop04,
     concat (c4.cfop, ' - ', c4.descricao) AS cfop04_descricao,

    s.cfop05,
   concat (c5.cfop, ' - ', c5.descricao) AS cfop05_descricao,
   
   s.cfop06,
   concat (c6.cfop, ' - ', c6.descricao) AS cfop06_descricao,
        s.serie1,
        s.serie2,
        s.serie3,
        s.serie4,
        s.serie5,
        s.serie6,
          s.serie1,
    s1.nome AS serie1_nome,

    s.serie2,
    s2.nome AS serie2_nome,

    s.serie3,
    s3.nome AS serie3_nome,

    s.serie4,
    s4.nome AS serie4_nome,

    s.serie5,
    s5.nome AS serie5_nome,

    s.serie6,
    s6.nome AS serie6_nome,
        s.tab_precos,
         ta.descricao as tab_precos_descricao, 
        s.considerar_origem,
        CAST(s.dados_adicionais AS CHAR(5000) CHARACTER SET utf8) AS dados_adicionais,
       CASE tipo WHEN 'L' THEN 'COMPLEMENTO' 
    WHEN 'C' THEN 'COMPRA/AQUISIÇÃO' 
    WHEN 'H' THEN 'CONHECIMENTO DE TRANSPORTE' 
    WHEN 'D' THEN 'DEVOLUÇÃO' 
    WHEN 'O' THEN 'OUTRAS ENTRADAS/SAÍDAS' 
    WHEN 'T' THEN 'TRANSFERÊNCIA' 
    WHEN 'V' THEN 'VENDA/PRESTAÇÃO' 
    WHEN 'P' THEN 'PERMUTA' 
    WHEN 'A' THEN 'AJUSTE' 
    WHEN 'B' THEN 'NOTA DÉBITO' 
    WHEN 'R' THEN 'NOTA CRÉDITO' 
    ELSE 'NÃO ESPC.' END AS tipo
    FROM " . $banco_vendas . ".transacoes s
    LEFT JOIN " . $banco_vendas . ".empresas e ON (e.filial = s.filial)
    LEFT JOIN " . $banco_publico . ".regras_cfop c1 
       ON c1.codigo = s.cfop01

LEFT JOIN " . $banco_publico . ".regras_cfop c2 
       ON c2.codigo = s.cfop02

LEFT JOIN " . $banco_publico . ".regras_cfop c3 
       ON c3.codigo = s.cfop03

LEFT JOIN " . $banco_publico . ".regras_cfop c4 
       ON c4.codigo = s.cfop04

LEFT JOIN " . $banco_publico . ".regras_cfop c5 
       ON c5.codigo = s.cfop05
       
LEFT JOIN " . $banco_publico . ".regras_cfop c6 ON c6.codigo = s.cfop06

       LEFT JOIN " . $banco_vendas . ".series s1 ON s1.codigo = s.serie1 AND s1.filial = s.filial
LEFT JOIN " . $banco_vendas . ".series s2 ON s2.codigo = s.serie2 AND s2.filial = s.filial
LEFT JOIN " . $banco_vendas . ".series s3 ON s3.codigo = s.serie3 AND s3.filial = s.filial
LEFT JOIN " . $banco_vendas . ".series s4 ON s4.codigo = s.serie4 AND s4.filial = s.filial
LEFT JOIN " . $banco_vendas . ".series s5 ON s5.codigo = s.serie5 AND s5.filial = s.filial
LEFT JOIN " . $banco_vendas . ".series s6 ON s6.codigo = s.serie6 AND s6.filial = s.filial

LEFT JOIN " . $banco_estoque . ".setores se ON se.codigo = s.setor

LEFT JOIN " . $banco_publico . ".tab_precos ta ON ta.codigo = s.tab_precos
     WHERE s.codigo = ? AND s.filial = ?
    LIMIT 1";
    
    $stmt = $dash->prepare($sql);
    $stmt->bind_param("ii", $codigo_tra, $filial);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado && $resultado->num_rows > 0) {
        $serie = $resultado->fetch_assoc();
    }
}

// Buscar filiais ativas
$filiais = [];
$sql_filiais = "SELECT filial, razao_social FROM " . $banco_vendas . ".empresas WHERE ativa = 'S' ORDER BY filial";
$result_filiais = $dash->query($sql_filiais);
if ($result_filiais && $result_filiais->num_rows > 0) {
    while ($row = $result_filiais->fetch_assoc()) {
        $filiais[] = $row;
    }
}

// Buscar Setores
$setores = [];
$sql_setores = "SELECT codigo, nome FROM " . $banco_estoque . ".setores ORDER BY nome";
$result_setores = $dash->query($sql_setores);
if ($result_setores) {
    while ($row = $result_setores->fetch_assoc()) {
        $setores[] = $row;
    }
}

// Buscar Tabelas de Preço
$tab_precos = [];
$sql_tab_precos = "SELECT codigo, descricao FROM " . $banco_publico . ".tab_precos ORDER BY descricao";
$result_tab_precos = $dash->query($sql_tab_precos);
if ($result_tab_precos) {
    while ($row = $result_tab_precos->fetch_assoc()) {
        $tab_precos[] = $row;
    }
}

// Buscar CFOPs
$cfops = [];
$sql_cfops = "SELECT codigo, cfop, descricao FROM " . $banco_publico . ".regras_cfop ORDER BY cfop";
$result_cfops = $dash->query($sql_cfops);
if ($result_cfops) {
    while ($row = $result_cfops->fetch_assoc()) {
        $cfops[] = $row;
    }
}

// Buscar Séries (Filtrando pela filial atual)
$series_list = [];
if (!empty($filial)) {
    $sql_series = "SELECT codigo, filial, nome FROM " . $banco_vendas . ".series WHERE ativa = 'S' AND filial = ? ORDER BY nome";
    $stmt_series = $dash->prepare($sql_series);
    $stmt_series->bind_param("s", $filial); 
    $stmt_series->execute();
    $result_series = $stmt_series->get_result();
    if ($result_series) {
        while ($row = $result_series->fetch_assoc()) {
            $series_list[] = $row;
        }
    }
    $stmt_series->close();
}

// Função helper para escapar output
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    <link rel="preload" href="../css/adminlte.css" as="style" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="../css/adminlte.css" />
    <link rel="icon" type="image/x-icon" href="../img/intersig.ico">

    <style>
    .card-modern {
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .badge-custom {
        font-size: 0.875rem;
        padding: 0.5em 1em;
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
                        <h3 class="mb-0">Detalhes da Transação</h3>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="../dashboard/dashboard.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="series.php">Transações</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Detalhes</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                <?php if (!$serie): ?>
                <div class="alert alert-warning">
                    <h6 class="mb-1"><i class="bi bi-exclamation-triangle"></i> Transação não encontrada</h6>
                    <p class="mb-0">Nenhuma transação foi encontrada para o código
                        <strong><?= e($codigo_tra) ?></strong>.
                    </p>
                </div>
                <?php else: ?>

                <div class="row gy-3">
                    <!-- Dados Gerais -->
                    <div class="col-12">
                        <div class="card card-modern p-3">
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div>
                                    <h5 class="mb-1"><i class="bi bi-file-earmark-text"></i> Dados Gerais</h5>
                                    <small class="text-muted">Informações básicas da transação</small>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <?php if($serie['ativa'] == 'S'): ?>
                                    <span class="badge bg-success badge-custom">Ativa</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger badge-custom">Inativa</span>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-primary btn-sm" id="btnEditar">
                                        <i class="bi bi-pencil"></i> Editar
                                    </button>
                                    <button type="button" class="btn btn-success btn-sm d-none" id="btnSalvar">
                                        <i class="bi bi-check-lg"></i> Salvar
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm d-none" id="btnCancelar">
                                        <i class="bi bi-x-lg"></i> Cancelar
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Filial</label>
                                <select class="form-select" name="filial" disabled>
                                    <?php foreach ($filiais as $f): ?>
                                    <option value="<?= e($f['filial']) ?>"
                                        <?= $serie['filial'] == $f['filial'] ? 'selected' : '' ?>>
                                        <?= e($f['filial']) ?> - <?= e($f['razao_social']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Ativa</label>
                                <select class="form-select" name="ativa" disabled>
                                    <option value="S" <?= $serie['ativa'] == 'S' ? 'selected' : '' ?>>SIM</option>
                                    <option value="N" <?= $serie['ativa'] == 'N' ? 'selected' : '' ?>>NÃO</option>
                                </select>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-1">
                                    <label class="form-label">Código</label>
                                    <input class="form-control" value="<?= e($serie['codigo']) ?>" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Natureza Operação</label>
                                    <input class="form-control" name="natureza" value="<?= e($serie['natureza']) ?>"
                                        readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nome</label>
                                    <input class="form-control" name="nome" value="<?= e($serie['nome']) ?>" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Tipo</label>
                                    <select class="form-select" name="tipo" disabled>
                                        <option value="L"
                                            <?= $serie['tipo'] == 'COMPLEMENTO' || $serie['tipo'] == 'L' ? 'selected' : '' ?>>
                                            COMPLEMENTO</option>
                                        <option value="C"
                                            <?= $serie['tipo'] == 'COMPRA/AQUISIÇÃO' || $serie['tipo'] == 'C' ? 'selected' : '' ?>>
                                            COMPRA/AQUISIÇÃO</option>
                                        <option value="H"
                                            <?= $serie['tipo'] == 'CONHECIMENTO DE TRANSPORTE' || $serie['tipo'] == 'H' ? 'selected' : '' ?>>
                                            CONHECIMENTO DE TRANSPORTE</option>
                                        <option value="D"
                                            <?= $serie['tipo'] == 'DEVOLUÇÃO' || $serie['tipo'] == 'D' ? 'selected' : '' ?>>
                                            DEVOLUÇÃO</option>
                                        <option value="O"
                                            <?= $serie['tipo'] == 'OUTRAS ENTRADAS/SAÍDAS' || $serie['tipo'] == 'O' ? 'selected' : '' ?>>
                                            OUTRAS ENTRADAS/SAÍDAS</option>
                                        <option value="T"
                                            <?= $serie['tipo'] == 'TRANSFERÊNCIA' || $serie['tipo'] == 'T' ? 'selected' : '' ?>>
                                            TRANSFERÊNCIA</option>
                                        <option value="V"
                                            <?= $serie['tipo'] == 'VENDA/PRESTAÇÃO' || $serie['tipo'] == 'V' ? 'selected' : '' ?>>
                                            VENDA/PRESTAÇÃO</option>
                                        <option value="P"
                                            <?= $serie['tipo'] == 'PERMUTA' || $serie['tipo'] == 'P' ? 'selected' : '' ?>>
                                            PERMUTA</option>
                                        <option value="A"
                                            <?= $serie['tipo'] == 'AJUSTE' || $serie['tipo'] == 'A' ? 'selected' : '' ?>>
                                            AJUSTE</option>
                                        <option value="B"
                                            <?= $serie['tipo'] == 'NOTA DÉBITO' || $serie['tipo'] == 'B' ? 'selected' : '' ?>>
                                            NOTA DÉBITO</option>
                                        <option value="R"
                                            <?= $serie['tipo'] == 'NOTA CRÉDITO' || $serie['tipo'] == 'R' ? 'selected' : '' ?>>
                                            NOTA CRÉDITO</option>
                                    </select>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Configurações -->
                    <div class="col-12">
                        <div class="card card-modern p-3">
                            <h6 class="mb-3"><i class="bi bi-bar-chart"></i> Configurações</h6>

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Tributos</label>
                                    <select class="form-select" name="tribut" disabled>
                                        <option value="S"
                                            <?= $serie['tribut'] == 'SIM' || $serie['tribut'] == 'S' ? 'selected' : '' ?>>
                                            SIM</option>
                                        <option value="N"
                                            <?= $serie['tribut'] == 'NÃO' || $serie['tribut'] == 'N' ? 'selected' : '' ?>>
                                            NÃO</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Consd. Origem</label>
                                    <select class="form-select" name="considerar_origem" disabled>
                                        <option value="S"
                                            <?= $serie['considerar_origem'] == 'SIM' || $serie['considerar_origem'] == 'S' ? 'selected' : '' ?>>
                                            SIM</option>
                                        <option value="N"
                                            <?= $serie['considerar_origem'] == 'NÃO' || $serie['considerar_origem'] == 'N' ? 'selected' : '' ?>>
                                            NÃO</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Produtos</label>
                                    <select class="form-select" name="produtos" disabled>
                                        <option value="S"
                                            <?= $serie['produtos'] == 'SIM' || $serie['produtos'] == 'S' ? 'selected' : '' ?>>
                                            SIM</option>
                                        <option value="N"
                                            <?= $serie['produtos'] == 'NÃO' || $serie['produtos'] == 'N' ? 'selected' : '' ?>>
                                            NÃO</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Serviços</label>
                                    <select class="form-select" name="servicos" disabled>
                                        <option value="S"
                                            <?= $serie['servicos'] == 'SIM' || $serie['servicos'] == 'S' ? 'selected' : '' ?>>
                                            SIM</option>
                                        <option value="N"
                                            <?= $serie['servicos'] == 'NÃO' || $serie['servicos'] == 'N' ? 'selected' : '' ?>>
                                            NÃO</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Gera Financeiro</label>
                                    <select class="form-select" name="gera_financeiro" disabled>
                                        <option value="S"
                                            <?= $serie['gera_financeiro'] == 'SIM' || $serie['gera_financeiro'] == 'S' ? 'selected' : '' ?>>
                                            SIM</option>
                                        <option value="N"
                                            <?= $serie['gera_financeiro'] == 'NÃO' || $serie['gera_financeiro'] == 'N' ? 'selected' : '' ?>>
                                            NÃO</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Referenciar</label>
                                    <select class="form-select" name="referenciar" disabled>
                                        <option value="S"
                                            <?= $serie['referenciar'] == 'SIM' || $serie['referenciar'] == 'S' ? 'selected' : '' ?>>
                                            SIM</option>
                                        <option value="N"
                                            <?= $serie['referenciar'] == 'NÃO' || $serie['referenciar'] == 'N' ? 'selected' : '' ?>>
                                            NÃO</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Comissão</label>
                                    <select class="form-select" name="comissao" disabled>
                                        <option value="S"
                                            <?= $serie['comissao'] == 'SIM' || $serie['comissao'] == 'S' ? 'selected' : '' ?>>
                                            SIM</option>
                                        <option value="N"
                                            <?= $serie['comissao'] == 'NÃO' || $serie['comissao'] == 'N' ? 'selected' : '' ?>>
                                            NÃO</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Setor</label>
                                    <select class="form-select" name="setor" disabled>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($setores as $st): ?>
                                        <option value="<?= e($st['codigo']) ?>"
                                            <?= $serie['setor'] == $st['codigo'] ? 'selected' : '' ?>>
                                            <?= e($st['nome']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Tabela Preços</label>
                                    <select class="form-select" name="tab_precos" disabled>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($tab_precos as $tp): ?>
                                        <option value="<?= e($tp['codigo']) ?>"
                                            <?= $serie['tab_precos'] == $tp['codigo'] ? 'selected' : '' ?>>
                                            <?= e($tp['descricao']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configurações NFe -->
                    <div class="col-12 col-lg-6">
                        <div class="card card-modern p-3">
                            <h6 class="mb-3"><i class="bi bi-gear"></i> Cfop</h6>

                            <div class="row g-3">
                                <?php for($i=1; $i<=6; $i++): 
                                    $campo = "cfop0$i"; 
                                    $valor = $serie[$campo];
                                ?>
                                <div class="col-12">
                                    <div class="row">
                                        <div class="col-12">
                                            <label class="form-label">CFOP 0<?= $i ?></label>
                                            <select class="form-select" name="<?= $campo ?>" disabled>
                                                <option value="">Selecione...</option>
                                                <?php foreach ($cfops as $cf): ?>
                                                <option value="<?= e($cf['codigo']) ?>"
                                                    <?= $valor == $cf['codigo'] ? 'selected' : '' ?>>
                                                    <?= e($cf['cfop']) ?> - <?= e($cf['descricao']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>

                        </div>
                    </div>

                    <!-- Séries -->
                    <div class="col-12 col-lg-6">
                        <div class="card card-modern p-3">
                            <h6 class="mb-3"><i class="bi bi-hash"></i> Séries</h6>

                            <div class="row g-3">
                                <?php for($i=1; $i<=6; $i++): 
                                    $campo = "serie$i";
                                    $valor = $serie[$campo];
                                ?>
                                <div class="col-12">
                                    <div class="row">
                                        <div class="col-12">
                                            <label class="form-label">Série <?= $i ?></label>
                                            <select class="form-select" name="<?= $campo ?>" disabled>
                                                <option value="">Selecione...</option>
                                                <?php foreach ($series_list as $sr): ?>
                                                <option value="<?= e($sr['codigo']) ?>"
                                                    <?= $valor == $sr['codigo'] ? 'selected' : '' ?>>
                                                    <?= e($sr['filial']) ?> - <?= e($sr['nome']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>





                    <!-- Dados Adicionais -->

                    <div class="col-12">
                        <div class="card card-modern p-3">
                            <h6 class="mb-3"><i class="bi bi-sticky"></i> Dados Adicionais</h6>
                            <textarea class="form-control" name="dados_adicionais" rows="4"
                                readonly><?= e($serie['dados_adicionais']) ?></textarea>
                        </div>
                    </div>




                    <!-- Botão Voltar -->
                    <div class="col-12">
                        <a href="transacoes.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Voltar para Lista
                        </a>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php require_once '../menu/footer.php'; ?>
    </div>

    <script src="../assets/plugins/jquery/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous">
    </script>
    <script src="../js/adminlte.js"></script>
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(document).ready(function() {
        let modoEdicao = false;
        const codigoSerie = <?= $codigo_tra ?>;
        const filialSerie = '<?= $filial ?>'; // Adicionado para enviar no POST

        // Objeto para armazenar os valores originais
        let dadosOriginais = {};

        function capturarValoresOriginais() {
            dadosOriginais = {};
            $('input, select, textarea').each(function() {
                const nome = $(this).attr('name');
                if (nome) {
                    dadosOriginais[nome] = String($(this).val() || '').trim();
                }
            });
        }

        capturarValoresOriginais();

        // --- NOVO: Sincroniza Mod. Sintegra com Modelo ---
        $('select[name="mod_sintegra"]').on('change', function() {
            const valorSelecionado = $(this).val();
            $('input[name="modelo"]').val(valorSelecionado);
        });
        // -------------------------------------------------

        function toggleEdicao(habilitar) {
            modoEdicao = habilitar;

            if (!habilitar) {
                // Restaura valores se cancelar
                $('input, select, textarea').each(function() {
                    const nome = $(this).attr('name');
                    if (nome && dadosOriginais.hasOwnProperty(nome)) {
                        $(this).val(dadosOriginais[nome]);
                    }
                });
            }

            // Definição dos campos 
            const camposInput = [
                'natureza', 'nome', 'serie', 'modelo',
                'prim_nf', 'ult_nf',
                'max_vend', 'max_prod', 'max_serv', 'max_parc',
                'dados_adicionais'
            ];

            const camposSelect = [
                'ativa', 'filial', 'tipo',
                'tribut', 'considerar_origem', 'produtos', 'servicos', 'gera_financeiro', 'referenciar',
                'comissao', 'setor', 'tab_precos',
                'cfop01', 'cfop02', 'cfop03', 'cfop04', 'cfop05', 'cfop06',
                'serie1', 'serie2', 'serie3', 'serie4', 'serie5', 'serie6'
            ];

            camposInput.forEach(campo => {
                const elemento = $(`[name="${campo}"]`);
                if (elemento.length) elemento.prop('readonly', !habilitar);
            });

            camposSelect.forEach(campo => {
                const elemento = $(`select[name="${campo}"]`);
                if (elemento.length) elemento.prop('disabled', !habilitar);
            });

            if (habilitar) {
                $('#btnEditar').addClass('d-none');
                $('#btnSalvar, #btnCancelar').removeClass('d-none');
            } else {
                $('#btnEditar').removeClass('d-none');
                $('#btnSalvar, #btnCancelar').addClass('d-none');
            }
        }

        $('#btnEditar').on('click', function() {
            toggleEdicao(true);
        });

        $('#btnCancelar').on('click', function() {
            Swal.fire({
                title: 'Cancelar edição?',
                text: 'As alterações não salvas serão perdidas.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, cancelar',
                cancelButtonText: 'Não'
            }).then((result) => {
                if (result.isConfirmed) {
                    toggleEdicao(false);
                }
            });
        });

        $('#btnSalvar').on('click', function() {
            salvarAlteracoes();
        });

        function salvarAlteracoes() {
            let dadosParaEnvio = {
                codigo: codigoSerie,
                filial: filialSerie
            };
            let houveAlteracao = false;

            $('input, select, textarea').each(function() {
                const nome = $(this).attr('name');
                if (nome && nome !== 'codigo') {
                    const valorAtual = String($(this).val() || '').trim();
                    const valorOriginal = dadosOriginais[nome];

                    if (valorAtual !== valorOriginal) {
                        dadosParaEnvio[nome] = valorAtual;
                        houveAlteracao = true;
                    }
                }
            });

            if (!houveAlteracao) {
                Swal.fire('Sem alterações', 'Nenhum campo foi modificado.', 'info');
                return;
            }

            Swal.fire({
                title: 'Salvar alterações?',
                text: 'Confirma a atualização dos dados modificados?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, salvar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    enviarDados(dadosParaEnvio);
                }
            });
        }

        function enviarDados(formData) {
            Swal.fire({
                title: 'Salvando...',
                text: 'Por favor, aguarde.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'atualizar_transacao.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        capturarValoresOriginais();
                        toggleEdicao(false);
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Erro!', response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire('Erro!', 'Erro ao salvar: ' + error, 'error');
                }
            });
        }
    });
    </script>
</body>

</html>