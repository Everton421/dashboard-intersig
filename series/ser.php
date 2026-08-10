<?php 
require_once '../config/config.php';
require_once '../config/session.php'; 

date_default_timezone_set('America/Sao_Paulo');
mysqli_set_charset($dash, "utf8");

// Recebe o codigo_ser via GET de forma segura
$codigo_ser = isset($_GET['codigo_ser']) ? intval($_GET['codigo_ser']) : 0;

// Variáveis iniciais
$serie = null;

// Buscar dados da série
if ($codigo_ser > 0) {
    $sql = "SELECT 
        s.codigo, 
        s.filial,
        s.descricao,
        CONCAT(s.filial, ' - ', e.razao_social) AS filial_nome,
        s.nome, 
        s.serie, 
        s.modelo,
        s.dados_adicionais1,
        s.dados_adicionais2, 
        s.dados_adicionais3, 
        s.dados_adicionais4, 
        s.dados_adicionais5, 
        CASE s.num_auto
        WHEN 0 THEN 'AUTOMÁTICO'
        WHEN 1 THEN 'AUTO. (BASEADO NOS PEDIDOS)'
        WHEN 2 THEN 'AUTO. E EDITÁVEL'
        WHEN 3 THEN 'PERSONALIZADA'
        ELSE s.num_auto
        END AS num_auto,
        CASE s.repetir 
        WHEN 'S' THEN 'SIM'
         WHEN 'N' THEN 'NÃO' 
         ELSE s.repetir 
         END AS repetir,
        s.prim_nf, 
        s.ult_nf,
        CASE s.origem 
            WHEN 'P' THEN 'PRÓPRIA'
            WHEN 'T' THEN 'TERCEIRO'
            ELSE s.origem
        END AS origem,
        CASE s.gerar
        WHEN 'S' THEN 'SIM'
        WHEN 'N' THEN 'NÃO'
        ELSE s.gerar
        END AS gerar,
        s.max_vend, 
        s.max_prod, 
        s.max_serv, 
        s.max_parc, 
        s.ativa, 
        CASE s.consid_sped 
            WHEN 'S' THEN 'SIM' 
            WHEN 'N' THEN 'NÃO' 
            ELSE s.consid_sped 
        END AS consid_sped,
        CAST(s.dados_adicionais AS CHAR(5000) CHARACTER SET utf8) AS dados_adicionais,
        CASE s.mod_sintegra
      WHEN '01' THEN '01 - MODELO 1A'
      WHEN '02' THEN '02 - CONS. MODELO 02'
      WHEN '04' THEN '04 - PRODUTOR'
      WHEN '06' THEN '06 - ENERGIA ELETRICA'
      WHEN '07' THEN '07 - SERVICO TRANSPORTE'
      WHEN '08' THEN '08 - CONHECIMENTO TRANSPORTE RODOVIARIO'
      WHEN '09' THEN '09 - CONHECIMENTO TRANSPORTE AQUAVIARIO'
      WHEN '10' THEN '10 - CONHECIMENTO TRANSPORTE AEREO'
      WHEN '11' THEN '11 - CONHECIMENTO TRANSPORTE FERROVIARIO'
      WHEN '13' THEN '13 - BILHETE TRANSPORTE RODOVIARIO'
      WHEN '14' THEN '14 - BILHETE TRANSPORTE AQUAVIARIO'
      WHEN '15' THEN '15 - BILHETE TRANSPORTE AEREO'
      WHEN '16' THEN '16 - BILHETE TRANSPORTE FERROVIARIO'
      WHEN '17' THEN '17 - DESPACHO TRANSPORTE'
      WHEN '18' THEN '18 - RESUMO DE MOVIMENTO DIARIO'
      WHEN '20' THEN '20 - ORDEM DE COLETA DE CARGAS'
      WHEN '21' THEN '21 - NOTA DE SERVIÇO DE COMUNICAÇÃO'
      WHEN '22' THEN '22 - NOTA DE SERVIÇO DE TELECOMUNICAÇÕES'
      WHEN '24' THEN '24 - AUTORIZACAO DE CARREGAMENTO'
      WHEN '25' THEN '25 - MANIFESTO DE CARGA'
      WHEN '33' THEN '33 - CUPOM FISCAL MODELO 2B,2C OU 2D' 
      WHEN '44' THEN '44 - NOTA FISCAL ELETRONICA - NFSE'
      WHEN '55' THEN '55 - NOTA FISCAL ELETRONICA - NF-E'
      WHEN '57' THEN '57 - CONHECIMENTEO DE TRANSPORTE- CT-E'
      WHEN '58' THEN '58 - MANIFESTO DE TRANSPORTE - MDF-E'
      WHEN '65' THEN '65 - NOTA FISCAL ELETRONICA - NFC-E'
      ELSE 'Não especificado'
     END AS mod_sintegra,

        CASE s.emissao_nfe
            WHEN 1 THEN 'Normal'
            WHEN 2 THEN 'Contingência FS-IA'
            WHEN 3 THEN 'Contingência SCAN'
            WHEN 4 THEN 'Contingência DEPEC'
            WHEN 5 THEN 'Contingência FS-DA'
            WHEN 6 THEN 'Contingência SVC-AN'
            WHEN 7 THEN 'Contingência SVC-RS'
            WHEN 9 THEN 'Contingência off-line NFC-E'
            ELSE 'Não especificado'
        END AS emissao_nfe
    FROM " . $banco_vendas . ".series s
    LEFT JOIN " . $banco_vendas . ".empresas e ON (e.filial = s.filial)
    WHERE s.codigo = ?
    LIMIT 1";
    
    $stmt = $dash->prepare($sql);
    $stmt->bind_param("i", $codigo_ser);
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

// Função helper para escapar output
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>

<!doctype html>
<html lang="pt-br">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Intersig | Série Fiscal</title>
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
                        <h3 class="mb-0">Detalhes da Série</h3>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="../dashboard/dashboard.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="series.php">Séries</a></li>
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
                    <h6 class="mb-1"><i class="bi bi-exclamation-triangle"></i> Série não encontrada</h6>
                    <p class="mb-0">Nenhuma série foi encontrada para o código <strong><?= e($codigo_ser) ?></strong>.
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
                                    <small class="text-muted">Informações básicas da série fiscal</small>
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

                            <div class="row g-3">
                                <div class="col-md-1">
                                    <label class="form-label">Código</label>
                                    <input class="form-control" value="<?= e($serie['codigo']) ?>" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Descrição</label>
                                    <input class="form-control" name="descricao" value="<?= e($serie['descricao']) ?>"
                                        readonly>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Nome</label>
                                    <input class="form-control" name="nome" value="<?= e($serie['nome']) ?>" readonly>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Série</label>
                                    <input class="form-control" name="serie" value="<?= e($serie['serie']) ?>" readonly>
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
                            </div>
                        </div>
                    </div>

                    <!-- Informações Fiscais -->
                    <div class="col-12 col-lg-6">
                        <div class="card card-modern p-3">
                            <h6 class="mb-3"><i class="bi bi-gear"></i> Configurações NFe</h6>

                            <div class="row g-3">
                                <div class="col-10">
                                    <label class="form-label">Mod. Sintegra</label>
                                    <select class="form-select" name="mod_sintegra" disabled>
                                        <option value="01"
                                            <?= strpos($serie['mod_sintegra'], '01') !== false ? 'selected' : '' ?>>01 -
                                            MODELO 1A</option>
                                        <option value="02"
                                            <?= strpos($serie['mod_sintegra'], '02') !== false ? 'selected' : '' ?>>02 -
                                            CONS. MODELO 02</option>
                                        <option value="04"
                                            <?= strpos($serie['mod_sintegra'], '04') !== false ? 'selected' : '' ?>>04 -
                                            PRODUTOR</option>
                                        <option value="06"
                                            <?= strpos($serie['mod_sintegra'], '06') !== false ? 'selected' : '' ?>>06 -
                                            ENERGIA ELETRICA</option>
                                        <option value="07"
                                            <?= strpos($serie['mod_sintegra'], '07') !== false ? 'selected' : '' ?>>07 -
                                            SERVICO TRANSPORTE</option>
                                        <option value="08"
                                            <?= strpos($serie['mod_sintegra'], '08') !== false ? 'selected' : '' ?>>08 -
                                            CONHECIMENTO TRANSPORTE RODOVIARIO</option>
                                        <option value="09"
                                            <?= strpos($serie['mod_sintegra'], '09') !== false ? 'selected' : '' ?>>09 -
                                            CONHECIMENTO TRANSPORTE AQUAVIARIO</option>
                                        <option value="10"
                                            <?= strpos($serie['mod_sintegra'], '10') !== false ? 'selected' : '' ?>>10 -
                                            CONHECIMENTO TRANSPORTE AEREO</option>
                                        <option value="11"
                                            <?= strpos($serie['mod_sintegra'], '11') !== false ? 'selected' : '' ?>>11 -
                                            CONHECIMENTO TRANSPORTE FERROVIARIO</option>
                                        <option value="13"
                                            <?= strpos($serie['mod_sintegra'], '13') !== false ? 'selected' : '' ?>>13 -
                                            BILHETE TRANSPORTE RODOVIARIO</option>
                                        <option value="14"
                                            <?= strpos($serie['mod_sintegra'], '14') !== false ? 'selected' : '' ?>>14 -
                                            BILHETE TRANSPORTE AQUAVIARIO</option>
                                        <option value="15"
                                            <?= strpos($serie['mod_sintegra'], '15') !== false ? 'selected' : '' ?>>15 -
                                            BILHETE TRANSPORTE AEREO</option>
                                        <option value="16"
                                            <?= strpos($serie['mod_sintegra'], '16') !== false ? 'selected' : '' ?>>16 -
                                            BILHETE TRANSPORTE FERROVIARIO</option>
                                        <option value="17"
                                            <?= strpos($serie['mod_sintegra'], '17') !== false ? 'selected' : '' ?>>17 -
                                            DESPACHO TRANSPORTE</option>
                                        <option value="18"
                                            <?= strpos($serie['mod_sintegra'], '18') !== false ? 'selected' : '' ?>>18 -
                                            RESUMO DE MOVIMENTO DIARIO</option>
                                        <option value="20"
                                            <?= strpos($serie['mod_sintegra'], '20') !== false ? 'selected' : '' ?>>20 -
                                            ORDEM DE COLETA DE CARGAS</option>
                                        <option value="21"
                                            <?= strpos($serie['mod_sintegra'], '21') !== false ? 'selected' : '' ?>>21 -
                                            NOTA DE SERVIÇO DE COMUNICAÇÃO</option>
                                        <option value="22"
                                            <?= strpos($serie['mod_sintegra'], '22') !== false ? 'selected' : '' ?>>22 -
                                            NOTA DE SERVIÇO DE TELECOMUNICAÇÕES</option>
                                        <option value="24"
                                            <?= strpos($serie['mod_sintegra'], '24') !== false ? 'selected' : '' ?>>24 -
                                            AUTORIZACAO DE CARREGAMENTO</option>
                                        <option value="25"
                                            <?= strpos($serie['mod_sintegra'], '25') !== false ? 'selected' : '' ?>>25 -
                                            MANIFESTO DE CARGA</option>
                                        <option value="33"
                                            <?= strpos($serie['mod_sintegra'], '33') !== false ? 'selected' : '' ?>>33 -
                                            CUPOM FISCAL MODELO 2B,2C OU 2D</option>
                                        <option value="44"
                                            <?= strpos($serie['mod_sintegra'], '44') !== false ? 'selected' : '' ?>>44 -
                                            NOTA FISCAL ELETRONICA - NFSE</option>
                                        <option value="55"
                                            <?= strpos($serie['mod_sintegra'], '55') !== false ? 'selected' : '' ?>>55 -
                                            NOTA FISCAL ELETRONICA - NF-E</option>
                                        <option value="57"
                                            <?= strpos($serie['mod_sintegra'], '57') !== false ? 'selected' : '' ?>>57 -
                                            CONHECIMENTEO DE TRANSPORTE- CT-E</option>
                                        <option value="58"
                                            <?= strpos($serie['mod_sintegra'], '58') !== false ? 'selected' : '' ?>>58 -
                                            MANIFESTO DE TRANSPORTE - MDF-E</option>
                                        <option value="65"
                                            <?= strpos($serie['mod_sintegra'], '65') !== false ? 'selected' : '' ?>>65 -
                                            NOTA FISCAL ELETRONICA - NFC-E</option>
                                    </select>
                                </div>
                                <div class="col-2">
                                    <label class="form-label">Modelo</label>
                                    <input class="form-control" name="modelo" value="<?= e($serie['modelo']) ?>"
                                        readonly>
                                </div>


                            </div>
                            <div class="row g-3">
                                <div class="col-7">
                                    <label class="form-label">Emissão NFe</label>
                                    <select class="form-select" name="emissao_nfe" disabled>
                                        <option value="1"
                                            <?= strpos($serie['emissao_nfe'], 'Normal') !== false ? 'selected' : '' ?>>
                                            Normal</option>
                                        <option value="2"
                                            <?= strpos($serie['emissao_nfe'], 'FS-IA') !== false ? 'selected' : '' ?>>
                                            Contingência FS-IA</option>
                                        <option value="3"
                                            <?= strpos($serie['emissao_nfe'], 'SCAN') !== false ? 'selected' : '' ?>>
                                            Contingência SCAN</option>
                                        <option value="4"
                                            <?= strpos($serie['emissao_nfe'], 'DEPEC') !== false ? 'selected' : '' ?>>
                                            Contingência DEPEC</option>
                                        <option value="5"
                                            <?= strpos($serie['emissao_nfe'], 'FS-DA') !== false ? 'selected' : '' ?>>
                                            Contingência FS-DA</option>
                                        <option value="6"
                                            <?= strpos($serie['emissao_nfe'], 'SVC-AN') !== false ? 'selected' : '' ?>>
                                            Contingência SVC-AN</option>
                                        <option value="7"
                                            <?= strpos($serie['emissao_nfe'], 'SVC-RS') !== false ? 'selected' : '' ?>>
                                            Contingência SVC-RS</option>
                                        <option value="9"
                                            <?= strpos($serie['emissao_nfe'], 'off-line') !== false ? 'selected' : '' ?>>
                                            Contingência off-line NFC-E</option>
                                    </select>
                                </div>
                                <div class="col-2">
                                    <label class="form-label">Gerar</label>
                                    <select class="form-select" name="gerar" disabled>
                                        <option value="S"
                                            <?= $serie['gerar'] == 'SIM' || $serie['gerar'] == 'S' ? 'selected' : '' ?>>
                                            SIM</option>
                                        <option value="N"
                                            <?= $serie['gerar'] == 'NÃO' || $serie['gerar'] == 'N' ? 'selected' : '' ?>>
                                            NÃO</option>
                                    </select>
                                </div>

                                <div class="col-3">
                                    <label class="form-label">Considera SPED</label>
                                    <select class="form-select" name="consid_sped" disabled>
                                        <option value="S"
                                            <?= $serie['consid_sped'] == 'SIM' || $serie['consid_sped'] == 'S' ? 'selected' : '' ?>>
                                            SIM</option>
                                        <option value="N"
                                            <?= $serie['consid_sped'] == 'NÃO' || $serie['consid_sped'] == 'N' ? 'selected' : '' ?>>
                                            NÃO</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Numeração -->
                    <div class="col-12 col-lg-6">
                        <div class="card card-modern p-3">
                            <h6 class="mb-3"><i class="bi bi-hash"></i> Numeração</h6>

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Origem</label>
                                    <select class="form-select" name="origem" disabled>
                                        <option value="P"
                                            <?= $serie['origem'] == 'PRÓPRIA' || $serie['origem'] == 'P' ? 'selected' : '' ?>>
                                            PRÓPRIA</option>
                                        <option value="T"
                                            <?= $serie['origem'] == 'TERCEIRO' || $serie['origem'] == 'T' ? 'selected' : '' ?>>
                                            TERCEIRO</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Primeira NF</label>
                                    <input class="form-control" name="prim_nf" value="<?= e($serie['prim_nf']) ?>"
                                        readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Última NF</label>
                                    <input class="form-control" name="ult_nf" value="<?= e($serie['ult_nf']) ?>"
                                        readonly>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Número Automático</label>
                                    <select class="form-select" name="num_auto" disabled>
                                        <option value="0"
                                            <?= strpos($serie['num_auto'], 'AUTOMÁTICO') !== false || $serie['num_auto'] == '0' ? 'selected' : '' ?>>
                                            AUTOMÁTICO</option>
                                        <option value="1"
                                            <?= strpos($serie['num_auto'], 'BASEADO NOS PEDIDOS') !== false || $serie['num_auto'] == '1' ? 'selected' : '' ?>>
                                            AUTO. (BASEADO NOS PEDIDOS)</option>
                                        <option value="2"
                                            <?= strpos($serie['num_auto'], 'EDITÁVEL') !== false || $serie['num_auto'] == '2' ? 'selected' : '' ?>>
                                            AUTO. E EDITÁVEL</option>
                                        <option value="3"
                                            <?= strpos($serie['num_auto'], 'PERSONALIZADA') !== false || $serie['num_auto'] == '3' ? 'selected' : '' ?>>
                                            PERSONALIZADA</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Repetir</label>
                                    <select class="form-select" name="repetir" disabled>
                                        <option value="S"
                                            <?= $serie['repetir'] == 'SIM' || $serie['repetir'] == 'S' ? 'selected' : '' ?>>
                                            SIM</option>
                                        <option value="N"
                                            <?= $serie['repetir'] == 'NÃO' || $serie['repetir'] == 'N' ? 'selected' : '' ?>>
                                            NÃO</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- Limites -->
                    <div class="col-12">
                        <div class="card card-modern p-3">
                            <h6 class="mb-3"><i class="bi bi-bar-chart"></i> Limites Máximos</h6>

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Máx. Vendas</label>
                                    <input class="form-control" name="max_vend" value="<?= e($serie['max_vend']) ?>"
                                        readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Máx. Produtos</label>
                                    <input class="form-control" name="max_prod" value="<?= e($serie['max_prod']) ?>"
                                        readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Máx. Serviços</label>
                                    <input class="form-control" name="max_serv" value="<?= e($serie['max_serv']) ?>"
                                        readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Máx. Parcelas</label>
                                    <input class="form-control" name="max_parc" value="<?= e($serie['max_parc']) ?>"
                                        readonly>
                                </div>
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


                    <!-- Campos Adicionais -->
                    <?php if(!empty($serie['dados_adcionais1']) || !empty($serie['dados_adcionais2']) || 
                             !empty($serie['dados_adcionais3']) || !empty($serie['dados_adcionais4']) || 
                             !empty($serie['dados_adcionais5'])): ?>
                    <div class="col-12">
                        <div class="card card-modern p-3">
                            <h6 class="mb-3"><i class="bi bi-list-ul"></i> Campos Adicionais</h6>

                            <div class="row g-3">
                                <?php if(!empty($serie['dados_adcionais1'])): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Dados Adicionais 1</label>
                                    <input class="form-control" value="<?= e($serie['dados_adcionais1']) ?>" readonly>
                                </div>
                                <?php endif; ?>

                                <?php if(!empty($serie['dados_adcionais2'])): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Dados Adicionais 2</label>
                                    <input class="form-control" value="<?= e($serie['dados_adcionais2']) ?>" readonly>
                                </div>
                                <?php endif; ?>

                                <?php if(!empty($serie['dados_adcionais3'])): ?>
                                <div class="col-md-4">
                                    <label class="form-label">Dados Adicionais 3</label>
                                    <input class="form-control" value="<?= e($serie['dados_adcionais3']) ?>" readonly>
                                </div>
                                <?php endif; ?>

                                <?php if(!empty($serie['dados_adcionais4'])): ?>
                                <div class="col-md-4">
                                    <label class="form-label">Dados Adicionais 4</label>
                                    <input class="form-control" value="<?= e($serie['dados_adcionais4']) ?>" readonly>
                                </div>
                                <?php endif; ?>

                                <?php if(!empty($serie['dados_adcionais5'])): ?>
                                <div class="col-md-4">
                                    <label class="form-label">Dados Adicionais 5</label>
                                    <input class="form-control" value="<?= e($serie['dados_adcionais5']) ?>" readonly>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Botão Voltar -->
                    <div class="col-12">
                        <a href="series.php" class="btn btn-secondary">
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
        const codigoSerie = <?= $codigo_ser ?>;

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

            // Definição dos campos (mantive igual ao seu)
            const camposInput = [
                'descricao', 'nome', 'serie', 'modelo', // Modelo está aqui, então ficará editável
                'prim_nf', 'ult_nf',
                'max_vend', 'max_prod', 'max_serv', 'max_parc',
                'dados_adicionais'
            ];

            const camposSelect = [
                'ativa', 'emissao_nfe', 'gerar', 'consid_sped', 'origem', 'repetir',
                'mod_sintegra', 'num_auto', 'filial'
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
                codigo: codigoSerie
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
                url: 'atualizar_serie.php',
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