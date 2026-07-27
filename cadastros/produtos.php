<?php 
require_once '../config/config.php';
require_once '../config/session.php'; 

date_default_timezone_set('America/Sao_Paulo');
mysqli_set_charset($dash, "utf8");

// Recebe o codigo_pro via GET de forma segura
$codigo_pro = isset($_GET['codigo_pro']) ? intval($_GET['codigo_pro']) : 0;

// Variáveis iniciais
$produto = null;
$class_fiscal_list = [];
$grupos = [];
$subgrupos = [];
$marcas = [];

// Buscar dados do produto
if ($codigo_pro > 0) {
    // Query baseada no trecho que você passou, com campos adicionais (ids) para seleção
    $sql3 = "SELECT p.codigo, p.ativo, p.descricao, p.num_fabricante, p.num_original, p.outro_cod,
    CAST(p.aplicacao AS CHAR(1000) CHARACTER SET utf8) AS aplicacao, p.data_cadastro, p.data_recad,
    CONCAT(p.marca, ' ', m.descricao) AS marca, p.nivel, p.observacoes1, p.observacoes2, p.observacoes3,
    CONCAT(p.class_fiscal, ' - ', c.ncm, ' - ', c.descricao) AS ncm, un.sigla, un.padr_sai, un.padr_ent,
    un.padr_sep, p.peso, p.comprimento, p.largura, p.msg_produto, p.estoque_min, p.altura, p.qtde_vol,
    p.contr_lote_serie, CONCAT(p.grupo, ' ', g.nome) AS grupo, CONCAT(p.subgrupo, ' ', s.descricao) AS subgrupo,
    CASE p.origem
      WHEN 0 THEN '0 - NACIONAL'
      WHEN 1 THEN '1 - ESTRANGEIRA IMP. DIRETA'
      WHEN 2 THEN '2 - ESTRANGEIRA AD. INTERTO'
      WHEN 3 THEN '3 - NACIONAL IMP. SUPERIOR 40%'
      WHEN 4 THEN '4 - NACIONAL PROCESSOS PROD. BASICOS'
      WHEN 5 THEN '5 - NACIONAL IMP. INFERIROR 40%'
      WHEN 6 THEN '6 - ESTRANGEIRA, IMP. DIRETA SEM SIMILAR NACIONAL'
      WHEN 7 THEN '7 - ESTRANGEIRA, AD. MERCADO INTERNO'
      ELSE '8 - NACIONAL IMP. SUPERIOR 40%'
    END AS origem,
    CASE p.tipo
      WHEN 0 THEN 'Consum'
      WHEN 1 THEN 'Matéria-prima'
      WHEN 2 THEN 'Material de consumo'
      WHEN 3 THEN 'Material de montagem'
      WHEN 4 THEN 'Material p/ constr./refor.'
      WHEN 5 THEN 'Material p/ escritório'
      WHEN 6 THEN 'Patrimônio'
      WHEN 7 THEN 'Produto acabado'
      WHEN 8 THEN 'Produto para revenda'
      WHEN 9 THEN 'Produto para locação'
      ELSE 'Tipo não especificado'
    END AS tipo,
    -- campos brutos para facilitar selects
    p.class_fiscal AS class_fiscal_id,
    p.grupo AS grupo_id,
    p.subgrupo AS subgrupo_id,
    p.marca AS marca_id
    FROM " . $banco_publico . ".cad_prod p
    LEFT JOIN " . $banco_publico . ".class_fiscal c ON (c.codigo = p.class_fiscal)
    LEFT JOIN " . $banco_publico . ".cad_pgru g ON (g.codigo = p.grupo)
    LEFT JOIN " . $banco_publico . ".subgrupos s ON (s.codigo = p.subgrupo AND s.cod_grupo = p.grupo)
    LEFT JOIN " . $banco_publico . ".cad_pmar m ON (m.codigo = p.marca)
    LEFT JOIN " . $banco_publico . ".unid_prod un ON (un.produto = p.codigo)
    WHERE p.codigo = $codigo_pro
    LIMIT 1";
    $resultado3 = mysqli_query($dash, $sql3);
    if ($resultado3 && mysqli_num_rows($resultado3) > 0) {
        $produto = mysqli_fetch_assoc($resultado3);
    }
}

// Buscar listas para selects
// class_fiscal
$sql_cf = "SELECT codigo, ncm, descricao FROM " . $banco_publico . ".class_fiscal ORDER BY codigo";
$res_cf = mysqli_query($dash, $sql_cf);
if ($res_cf) {
    while ($r = mysqli_fetch_assoc($res_cf)) {
        $class_fiscal_list[] = $r;
    }
}
// grupos
$sql_gr = "SELECT codigo, nome FROM " . $banco_publico . ".cad_pgru ORDER BY nome";
$res_gr = mysqli_query($dash, $sql_gr);
if ($res_gr) {
    while ($r = mysqli_fetch_assoc($res_gr)) {
        $grupos[] = $r;
    }
}
// subgrupos (trazemos todos e filtramos client-side)
$sql_sg = "SELECT codigo, descricao, cod_grupo FROM " . $banco_publico . ".subgrupos ORDER BY descricao";
$res_sg = mysqli_query($dash, $sql_sg);
if ($res_sg) {
    while ($r = mysqli_fetch_assoc($res_sg)) {
        $subgrupos[] = $r;
    }
}
// marcas
$sql_mk = "SELECT codigo, descricao FROM " . $banco_publico . ".cad_pmar ORDER BY descricao";
$res_mk = mysqli_query($dash, $sql_mk);
if ($res_mk) {
    while ($r = mysqli_fetch_assoc($res_mk)) {
        $marcas[] = $r;
    }
}

// Função helper para escapar output
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>

<!doctype html>
<html lang="pt-br">
<!--begin::Head-->

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Intersig | Dashboard</title>
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

                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="..dashboard/dashboard">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Produto</li>
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






                <?php if (!$produto): ?>
                <div class="alert alert-warning card-modern">
                    <h6 class="mb-1"><i class="bi bi-exclamation-triangle"></i> Produto não encontrado</h6>
                    <p class="mb-0">Nenhum produto foi encontrado para o código
                        <strong><?= e($codigo_pro) ?></strong>.
                        Verifique o parâmetro <code>codigo_pro</code> na URL.
                    </p>
                </div>
                <?php else: ?>

                <form id="formProduto" class="needs-validation" novalidate>
                    <input type="hidden" name="codigo_pro" value="<?= e($produto['codigo']) ?>">
                    <div class="row gy-3">
                        <!-- Dados Gerais -->
                        <div class="col-12">
                            <div class="card card-modern p-3">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <div>
                                        <h6 class="mb-0"><i class="bi bi-person-badge"></i> 🧾 Dados Gerais</h6>
                                        <small class="muted-small">Identificação e informações básicas do
                                            produto</small>
                                    </div>

                                </div>

                                <div class="row g-3 mt-2">
                                    <div class="col-md-2">
                                        <label class="form-label">Código</label>
                                        <input class="form-control" name="codigo_read"
                                            value="<?= e($produto['codigo']) ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Descrição <span class="text-muted small"
                                                title="Descrição do produto"> </span></label>
                                        <input class="form-control editable" name="descricao"
                                            value="<?= e($produto['descricao']) ?>" required disabled>
                                    </div>


                                    <div class="col-md-4">
                                        <label class="form-label">GTIN - Código barras</label>
                                        <input class="form-control editable" name="num_fabricante"
                                            value="<?= e($produto['num_fabricante']) ?>" disabled>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Referência</label>
                                        <input class="form-control editable" name="num_original"
                                            value="<?= e($produto['num_original']) ?>" disabled>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Outro código</label>
                                        <input class="form-control editable" name="outro_cod"
                                            value="<?= e($produto['outro_cod']) ?>" disabled>
                                    </div>






                                </div>
                            </div>
                        </div>
                        <!-- Informações Fiscais -->
                        <div class="col-12 col-lg-6">
                            <div class="card card-modern p-3">
                                <h6 class="mb-1"><i class="bi bi-file-earmark-text"></i> ⚙️ Informações Fiscais</h6>
                                <small class="muted-small">Dados fiscais e tributários</small>

                                <div class="mt-3">
                                    <label class="form-label">Classificação Fiscal (NCM)</label>
                                    <select name="class_fiscal_id" id="class_fiscal_id" class="form-select editable"
                                        disabled>
                                        <option value="">-- Selecionar classificação --</option>
                                        <?php foreach($class_fiscal_list as $cf): ?>
                                        <option value="<?= e($cf['codigo']) ?>"
                                            <?= ($produto['class_fiscal_id'] == $cf['codigo']) ? 'selected' : '' ?>>
                                            <?= e($cf['codigo']) ?> - <?= e($cf['ncm']) ?> -
                                            <?= e($cf['descricao']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text muted-small">
                                        Tabela <code>class_fiscal</code>.
                                        <a href="https://portalunico.siscomex.gov.br/classif/#/sumario?perfil=publico"
                                            target="_blank">
                                            Clique para mais detalhes.
                                        </a>
                                    </div>


                                    <label class="form-label mt-3">Origem</label>
                                    <select name="origem" id="origem" class="form-select editable" disabled>

                                        <option value="0"
                                            <?php echo strpos($produto['origem'], '0 -') !== false ? 'selected' : ''; ?>>
                                            0 -
                                            NACIONAL</option>
                                        <option value="1"
                                            <?php echo strpos($produto['origem'], '1 -') !== false ? 'selected' : ''; ?>>
                                            1 -
                                            ESTRANGEIRA IMP. DIRETA</option>
                                        <option value="2"
                                            <?php echo strpos($produto['origem'], '2 -') !== false ? 'selected' : ''; ?>>
                                            2 -
                                            ESTRANGEIRA AD. INTERTO</option>
                                        <option value="3"
                                            <?php echo strpos($produto['origem'], '3 -') !== false ? 'selected' : ''; ?>>
                                            3 -
                                            NACIONAL IMP. SUPERIOR 40%</option>
                                        <option value="4"
                                            <?php echo strpos($produto['origem'], '4 -') !== false ? 'selected' : ''; ?>>
                                            4 -
                                            NACIONAL PROCESSOS PROD. BASICOS</option>
                                        <option value="5"
                                            <?php echo strpos($produto['origem'], '5 -') !== false ? 'selected' : ''; ?>>
                                            5 -
                                            NACIONAL IMP. INFERIROR 40%</option>
                                        <option value="6"
                                            <?php echo strpos($produto['origem'], '6 -') !== false ? 'selected' : ''; ?>>
                                            6 -
                                            ESTRANGEIRA, IMP. DIRETA SEM SIMILAR NACIONAL</option>
                                        <option value="7"
                                            <?php echo strpos($produto['origem'], '7 -') !== false ? 'selected' : ''; ?>>
                                            7 -
                                            ESTRANGEIRA, AD. MERCADO INTERNO</option>
                                        <option value="8"
                                            <?php echo strpos($produto['origem'], '8 -') !== false ? 'selected' : ''; ?>>
                                            8 -
                                            NACIONAL IMP. SUPERIOR 40%</option>
                                    </select>

                                    <label class="form-label mt-3">Tipo</label>
                                    <select class="form-select editable" id="tipo" name="tipo" disabled>
                                        <option value="0" <?php echo $produto['tipo'] == 'Consum' ? 'selected' : ''; ?>>
                                            Consum
                                        </option>
                                        <option value="1"
                                            <?php echo $produto['tipo'] == 'Matéria-prima' ? 'selected' : ''; ?>>
                                            Matéria-prima</option>
                                        <option value="2"
                                            <?php echo $produto['tipo'] == 'Material de consumo' ? 'selected' : ''; ?>>
                                            Material
                                            de consumo</option>
                                        <option value="3"
                                            <?php echo $produto['tipo'] == 'Material de montagem' ? 'selected' : ''; ?>>
                                            Material
                                            de montagem</option>
                                        <option value="4"
                                            <?php echo $produto['tipo'] == 'Material p/ constr./refor.' ? 'selected' : ''; ?>>
                                            Material p/ constr./refor.</option>
                                        <option value="5"
                                            <?php echo $produto['tipo'] == 'Material p/ escritório' ? 'selected' : ''; ?>>
                                            Material p/ escritório</option>
                                        <option value="6"
                                            <?php echo $produto['tipo'] == 'Patrimônio' ? 'selected' : ''; ?>>
                                            Patrimônio</option>
                                        <option value="7"
                                            <?php echo $produto['tipo'] == 'Produto acabado' ? 'selected' : ''; ?>>
                                            Produto
                                            acabado</option>
                                        <option value="8"
                                            <?php echo $produto['tipo'] == 'Produto para revenda' ? 'selected' : ''; ?>>
                                            Produto
                                            para revenda</option>
                                        <option value="9"
                                            <?php echo $produto['tipo'] == 'Produto para locação' ? 'selected' : ''; ?>>
                                            Produto
                                            para locação</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Classificação e Grupo -->
                        <div class="col-12 col-lg-6">
                            <div class="card card-modern p-3">
                                <h6 class="mb-1"><i class="bi bi-tags"></i> 🏷️ Classificação e Grupo</h6>
                                <small class="muted-small">Organização comercial</small>

                                <div class="mt-3">
                                    <label class="form-label">Grupo</label>
                                    <select name="grupo_id" id="grupo_id" class="form-select editable" required
                                        disabled>
                                        <option value="">-- Selecionar grupo --</option>
                                        <?php foreach($grupos as $g): ?>
                                        <option value="<?= e($g['codigo']) ?>"
                                            <?= ($produto['grupo_id'] == $g['codigo']) ? 'selected' : '' ?>>
                                            <?= e($g['nome']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <label class="form-label mt-3">Subgrupo</label>
                                    <select name="subgrupo_id" id="subgrupo_id" class="form-select editable" required
                                        disabled>
                                        <option value="">-- Selecionar subgrupo --</option>
                                        <?php foreach($subgrupos as $s): ?>
                                        <option value="<?= e($s['codigo']) ?>"
                                            data-cod-grupo="<?= e($s['cod_grupo']) ?>"
                                            <?= ($produto['subgrupo_id'] == $s['codigo']) ? 'selected' : '' ?>>
                                            <?= e($s['descricao']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text muted-small">Subgrupos dependem do grupo. Ao mudar o
                                        grupo, o subgrupo
                                        será filtrado automaticamente.</div>



                                    <label class="form-label mt-3">Marca</label>
                                    <select name="marca_id" id="marca_id" class="form-select editable" disabled>
                                        <option value="">-- Selecionar marca --</option>
                                        <?php foreach($marcas as $m): ?>
                                        <option value="<?= e($m['codigo']) ?>"
                                            <?= ($produto['marca_id'] == $m['codigo']) ? 'selected' : '' ?>>
                                            <?= e($m['descricao']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text muted-small"></div>
                                </div>
                            </div>
                        </div>


                        <!-- Estoque e Dimensões -->
                        <div class="col-12">
                            <div class="card card-modern p-3">
                                <h6 class="mb-1"><i class="bi bi-box-seam"></i> 📦 Estoque e Dimensões</h6>
                                <small class="muted-small">Informações de estoque físico e medidas</small>

                                <div class="row g-3 mt-2">
                                    <div class="col-sm-3 col-md-2">
                                        <label class="form-label">Peso (kg)</label>
                                        <input type="number" step="0.001" class="form-control editable" name="peso"
                                            value="<?php echo htmlspecialchars($produto['peso']); ?>" disabled>
                                    </div>
                                    <div class="col-sm-3 col-md-2">
                                        <label class="form-label">Comprimento (cm)</label>
                                        <input type="number" step="0.001" class="form-control editable"
                                            name="comprimento"
                                            value="<?php echo htmlspecialchars($produto['comprimento']) ?>" disabled>
                                    </div>
                                    <div class="col-sm-3 col-md-2">
                                        <label class="form-label">Largura (cm)</label>
                                        <input type="number" step="0.001" class="form-control editable" name="largura"
                                            value="<?php echo htmlspecialchars($produto['largura']) ?>" disabled>
                                    </div>
                                    <div class="col-sm-3 col-md-2">
                                        <label class="form-label">Altura (cm)</label>
                                        <input type="number" step="0.001" class="form-control editable" name="altura"
                                            value="<?php echo htmlspecialchars($produto['altura']) ?>" disabled>
                                    </div>
                                    <div class="col-sm-3 col-md-4">
                                        <label class="form-label">Estoque mínimo</label>
                                        <input type="number" step="0.001" class="form-control editable"
                                            name="estoque_min"
                                            value="<?php echo htmlspecialchars($produto['estoque_min']) ?>" disabled>
                                    </div>
                                    <div class="col-sm-3 col-md-4">
                                        <label class="form-label">Qtde volumes</label>
                                        <input type="number" step="0.001" class="form-control editable" name="qtde_vol"
                                            value="<?php echo htmlspecialchars($produto['qtde_vol']) ?>" disabled>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Estoque e Controle -->
                        <div class="col-12">
                            <div class="card card-modern p-3">
                                <h6 class="mb-1"><i class="bi bi-box-seam"></i> 📦 Controle e Nível</h6>
                                <small class="text-muted">Controle de lote/serie e nível</small>

                                <div class="row mt-3 g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Lote/serie</label>
                                        <select class="form-select editable" id="contr_lote_serie"
                                            name="contr_lote_serie" disabled>
                                            <option value="N"
                                                <?php echo ($produto['contr_lote_serie'] == 'N' || $produto['contr_lote_serie'] == 'Não') ? 'selected' : ''; ?>>
                                                Não</option>
                                            <option value="S"
                                                <?php echo ($produto['contr_lote_serie'] == 'S' || $produto['contr_lote_serie'] == 'Sim') ? 'selected' : ''; ?>>
                                                Sim</option>
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">Nível</label>
                                        <select class="form-select editable" id="nivel" name="nivel" disabled>
                                            <option value="">Selecione o nível</option>
                                            <option value="A" <?php echo $produto['nivel'] == 'A' ? 'selected' : ''; ?>>
                                                A
                                            </option>
                                            <option value="B" <?php echo $produto['nivel'] == 'B' ? 'selected' : ''; ?>>
                                                B
                                            </option>
                                            <option value="C" <?php echo $produto['nivel'] == 'C' ? 'selected' : ''; ?>>
                                                C
                                            </option>
                                            <option value="D" <?php echo $produto['nivel'] == 'D' ? 'selected' : ''; ?>>
                                                D
                                            </option>
                                            <option value="E" <?php echo $produto['nivel'] == 'E' ? 'selected' : ''; ?>>
                                                E
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Unidade (sigla)</label>
                                        <input class="form-control" name="sigla" value="<?= e($produto['sigla']) ?>"
                                            readonly disabled>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Padrão Saída</label>
                                        <input class="form-control" name="padr_sai"
                                            value="<?= e($produto['padr_sai']) ?>" readonly disabled>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Padrão Entrada</label>
                                        <input class="form-control" name="padr_sai"
                                            value="<?= e($produto['padr_ent']) ?>" readonly disabled>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Padrão Separ.</label>
                                        <input class="form-control" name="padr_sep"
                                            value="<?= e($produto['padr_sep']) ?>" readonly disabled>
                                    </div>

                                </div>
                            </div>
                        </div>


                        <!-- Observações -->
                        <div class="col-12">
                            <div class="card card-modern p-3">
                                <h6 class="mb-1"><i class="bi bi-sticky"></i> 📝 Observações</h6>
                                <small class="muted-small">Textos livres sobre o produto</small>

                                <div class="mt-2">
                                    <label class="form-label">Observações 1</label>
                                    <textarea name="observacoes1" class="form-control editable" rows="3"
                                        disabled><?= e($produto['observacoes1']) ?></textarea>

                                    <label class="form-label mt-2">Observações 2</label>
                                    <textarea name="observacoes2" class="form-control editable" rows="2"
                                        disabled><?= e($produto['observacoes2']) ?></textarea>

                                    <label class="form-label mt-2">Observações 3</label>
                                    <textarea name="observacoes3" class="form-control editable" rows="2"
                                        disabled><?= e($produto['observacoes3']) ?></textarea>

                                    <label class="form-label mt-2">Aplicação</label>
                                    <textarea name="aplicacao" class="form-control editable" rows="3"
                                        disabled><?= e($produto['aplicacao']) ?></textarea>
                                </div>
                            </div>
                        </div>

                    </div> <!-- row -->
                    <?php if($produto['data_recad']): ?>
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle-fill"></i>
                        <strong>Última atualização:</strong>
                        <?php echo date('d/m/Y H:i:s', strtotime($produto['data_recad'])); ?>
                    </div>
                    <?php endif; ?>



                </form>

                <?php endif; ?>

            </div> <!-- container -->








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