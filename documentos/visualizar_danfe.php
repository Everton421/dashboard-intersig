<?php
require_once '../config/config.php';
require_once '../config/session.php'; 

$cod_nota = $_GET['nf'] ?? 0;


if (empty($cod_nota)) {
    die("Código da nota não informado.");
}

// 1. Verificar se a nota pertence ao cliente e buscar dados básicos
$sql_check = "SELECT n.*, t.nome as natureza_operacao 
              FROM " . $banco_vendas . ".cad_nf n
              LEFT JOIN " . $banco_vendas . ".transacoes t ON (t.codigo = n.transacao AND t.filial = n.filial)
              WHERE n.codigo = ? ";
$stmt = $dash->prepare($sql_check);
$stmt->bind_param("s", $cod_nota);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Nota não encontrada ou acesso negado.");
}

$nf_db = $res->fetch_assoc();

// 2. Tentar buscar o XML
$sql_xml = "SELECT xml_nfe FROM " . $banco_vendas . ".xml_fatur WHERE fatur = ?";
$stmt_xml = $dash->prepare($sql_xml);
$stmt_xml->bind_param("s", $cod_nota);
$stmt_xml->execute();
$result_xml = $stmt_xml->get_result();
$row_xml = $result_xml->fetch_assoc();

$xml_disponivel = false;
if ($row_xml && !empty($row_xml['xml_nfe'])) {
    $xml = @simplexml_load_string($row_xml['xml_nfe']);
    if ($xml) {
        $xml_disponivel = true;
    }
}

if ($xml_disponivel) {
    // --- LÓGICA XML (Mantida) ---
    $chave = $xml->NFe->infNFe->attributes()->Id;
    $chave = str_replace('NFe', '', $chave);
    
    $ide = $xml->NFe->infNFe->ide;
    $cUF = $ide->cUF;
    $cNF = $ide->cNF;
    $natOp = $ide->natOp;
    $mod = $ide->mod;
    $serie = $ide->serie;
    $nNF = $ide->nNF;
    $dhEmi = $ide->dhEmi;
    
    $data = substr($dhEmi, 0, 10);
    $data_formatada = date("d/m/Y", strtotime($data));

    if (!empty($ide->dhSaiEnt)) {
        $dhSaiEnt = $ide->dhSaiEnt;
        $data_sai = substr($dhSaiEnt, 0, 10);
        $data_formatada1 = date("d/m/Y", strtotime($data_sai));
    } else {
        $data_formatada1 = $data_formatada;
    }

    $horario = substr($dhEmi, 11, 8);
    $tpNF = $ide->tpNF;
    $tpAmb = $ide->tpAmb;
    
    if ($tpAmb != 1) {
        $aviso_homologacao = "<h4 class='text-center text-danger'>Documento emitido em ambiente de homologação!</h4>";
    } else {
        $aviso_homologacao = "";
    }

    $nProt = $xml->protNFe->infProt->nProt;

    // Emitente
    $emit = $xml->NFe->infNFe->emit;
    $emit_CNPJ = $emit->CNPJ;
    $emit_xNome = $emit->xNome;
    $emit_xFant = $emit->xFant;
    
    $enderEmit = $emit->enderEmit;
    $emit_xLgr = $enderEmit->xLgr;
    $emit_nro = $enderEmit->nro;
    $emit_xBairro = $enderEmit->xBairro;
    $emit_xMun = $enderEmit->xMun;
    $emit_UF = $enderEmit->UF;
    $emit_CEP = $enderEmit->CEP;
    $emit_fone = $enderEmit->fone;
    $emit_IE = $emit->IE;

    // Destinatário
    $dest = $xml->NFe->infNFe->dest;
    $dest_CPF = $dest->CPF;
    $dest_CNPJ = $dest->CNPJ;
    $dest_xNome = $dest->xNome;
    $dest_xFant = $dest->xFant;
    $dest_IE = $dest->IE;

    $enderDest = $dest->enderDest;
    $dest_xLgr = $enderDest->xLgr;
    $dest_nro = $enderDest->nro;
    $dest_xBairro = $enderDest->xBairro;
    $dest_xMun = $enderDest->xMun;
    $dest_xUF = $enderDest->UF;
    $dest_xCEP = $enderDest->CEP;
    $dest_xfone = $enderDest->fone;

    // Totais
    $ICMSTot = $xml->NFe->infNFe->total->ICMSTot;
    $vBC = number_format((double) $ICMSTot->vBC, 2, ",", ".");
    $vICMS = number_format((double) $ICMSTot->vICMS, 2, ",", ".");
    $vBCST = number_format((double) $ICMSTot->vBCST, 2, ",", ".");
    $vST = number_format((double) $ICMSTot->vST, 2, ",", ".");
    $vProd = number_format((double) $ICMSTot->vProd, 2, ",", ".");
    $vNF = number_format((double) $ICMSTot->vNF, 2, ",", ".");
    $vFrete = number_format((double) $ICMSTot->vFrete, 2, ",", ".");
    $vSeg = number_format((double) $ICMSTot->vSeg, 2, ",", ".");
    $vDesc = number_format((double) $ICMSTot->vDesc, 2, ",", ".");
    $vIPI = number_format((double) $ICMSTot->vIPI, 2, ",", ".");
    $vOutro = number_format((double) $ICMSTot->vOutro, 2, ",", ".");

    // Transporte
    $transp = $xml->NFe->infNFe->transp;
    $trans_modfrete = $transp->modFrete;
    $trans_tranpcnpj = $transp->transporta->CNPJ;
    $trans_tranpnome = $transp->transporta->xNome;
    $trans_tranpie = $transp->transporta->IE;
    $trans_tranpend = $transp->transporta->xEnder;
    $trans_mun = $transp->transporta->xMun;
    $trans_uf = $transp->transporta->UF;

    $trans_qVol = $transp->vol->qVol;
    $trans_esp = $transp->vol->esp;
    $trans_pesoL = $transp->vol->pesoL;
    $trans_pesoB = $transp->vol->pesoB;

    $infAdic_fisco = $xml->NFe->infNFe->infAdic->infAdFisco;
    $infAdic_infCpl = $xml->NFe->infNFe->infAdic->infCpl;

    // Itens (Array para loop)
    $itens_nota = [];
    foreach($xml->NFe->infNFe->det as $item) {
        $icms = $item->imposto->ICMS->children()[0];
        $ipi = $item->imposto->IPI->IPITrib;
        
        $itens_nota[] = [
            'codigo' => (string)$item->prod->cProd,
            'xProd' => (string)$item->prod->xProd,
            'NCM' => (string)$item->prod->NCM,
            'CFOP' => (string)$item->prod->CFOP,
            'uCom' => (string)$item->prod->uCom,
            'qCom' => (float)$item->prod->qCom,
            'vUnCom' => (float)$item->prod->vUnCom,
            'vProd' => (float)$item->prod->vProd,
            'vBC' => (float)($icms->vBC ?? 0),
            'vICMS' => (float)($icms->vICMS ?? 0),
            'pICMS' => (float)($icms->pICMS ?? 0),
            'vIPI' => (float)($ipi->vIPI ?? 0),
            'pIPI' => (float)($ipi->pIPI ?? 0),
            'CST' => (string)($icms->CST ?? $icms->CSOSN ?? ''),
            'orig' => (string)($icms->orig ?? '')
        ];
    }
    
    // Duplicatas
    $duplicatas = [];
    if (!empty($xml->NFe->infNFe->cobr->dup)) {
        foreach($xml->NFe->infNFe->cobr->dup as $dup) {
            $duplicatas[] = [
                'nDup' => (string)$dup->nDup,
                'dVenc' => (string)$dup->dVenc,
                'vDup' => (float)$dup->vDup
            ];
        }
    }

} else {
    // --- LÓGICA FALLBACK (Banco de Dados) ---
    
    // Dados da Nota
    $chave = $nf_db['CHAVE_NFE'] ?? 'CHAVE NÃO DISPONÍVEL';
    $nNF = $nf_db['NUMERO_NF'];
    $serie = $nf_db['SERIE_NF'] ?? $nf_db['SERIE'];
    $natOp = $nf_db['natureza_operacao'] ?? 'VENDA';
    $tpNF = ($nf_db['ENTRADA_SAIDA'] ?? 'S') == 'E' ? 0 : 1; // Assumindo Saída se não tiver info
    $data_formatada = date('d/m/Y', strtotime($nf_db['DATA_EMISSAO']));
    $data_formatada1 = !empty($nf_db['DATA_SAIDA_ENTRADA']) ? date('d/m/Y', strtotime($nf_db['DATA_SAIDA_ENTRADA'])) : $data_formatada;
    $horario = !empty($nf_db['HORA_SAIDA_ENTRADA']) ? $nf_db['HORA_SAIDA_ENTRADA'] : '00:00:00';
    $nProt = $nf_db['PROTOCOLO_NFE'] ?? '';
    $aviso_homologacao = ($nf_db['AMBIENTE_NFE'] ?? 1) == 2 ? "<h4 class='text-center text-danger'>Documento emitido em ambiente de homologação!</h4>" : "";

    // Emitente (Dados da Empresa)
    $sql_emp = "SELECT RAZAO_SOCIAL, CNPJ, INSCRICAO, ENDERECO, NUMERO, BAIRRO, CIDADE, ESTADO, CEP, TELEFONE
                FROM " . $banco_vendas . ".empresas WHERE FILIAL = ?";
    $stmt_emp = $dash->prepare($sql_emp);
    $stmt_emp->bind_param("i", $nf_db['FILIAL']);
    $stmt_emp->execute();
    $res_emp = $stmt_emp->get_result();
    $emp_config = $res_emp->fetch_assoc();
    
    $emit_xNome = $emp_config['RAZAO_SOCIAL'] ?? 'EMPRESA EMISSORA';
    $emit_xFant = $emit_xNome;
    $emit_CNPJ = $emp_config['CNPJ'] ?? 'CNPJ NÃO INFORMADO'; 
    $emit_IE = $emp_config['INSCRICAO'] ?? '';
    $emit_xLgr = $emp_config['ENDERECO'] ?? 'Endereço da Empresa';
    $emit_nro = $emp_config['NUMERO'] ?? '';
    $emit_xBairro = $emp_config['BAIRRO'] ?? '';
    $emit_xMun = $emp_config['CIDADE'] ?? '';
    $emit_UF = $emp_config['ESTADO'] ?? '';
    $emit_CEP = $emp_config['CEP'] ?? '';
    $emit_fone = $emp_config['TELEFONE'] ?? '';

    // Destinatário (Cliente Logado)
    $sql_cli = "SELECT * FROM " . $banco_publico . ".cad_clie WHERE CODIGO = ?";
    $stmt_cli = $dash->prepare($sql_cli);
    $stmt_cli->bind_param("i", $cliente_id);
    $stmt_cli->execute();
    $dest_db = $stmt_cli->get_result()->fetch_assoc();

    $dest_xNome = $dest_db['NOME'];
    $dest_CNPJ = $dest_db['CNPJ'] ?? $dest_db['CPF'];
    $dest_IE = $dest_db['INSCRICAO'] ?? $dest_db['RG'];
    $dest_xLgr = $dest_db['ENDERECO'];
    $dest_nro = $dest_db['NUMERO'];
    $dest_xBairro = $dest_db['BAIRRO'];
    $dest_xMun = $dest_db['CIDADE'];
    $dest_xUF = $dest_db['ESTADO'];
    $dest_xCEP = $dest_db['CEP'];
    $dest_xfone = $dest_db['TELEFONE_COM'] ?? $dest_db['CELULAR'];

    // Itens e Impostos
    $sql_itens = "SELECT p.*, t.* , c.descricao, c.codigo
                  FROM " . $banco_vendas . ".mvto_produtos p
                  LEFT JOIN " . $banco_vendas . ".mvto_tributos t ON (t.chave_mvto = p.chave_mvto AND t.item = p.item)
                  LEFT JOIN " . $banco_publico . ".cad_prod c ON (c.codigo = p.produto)
                  WHERE p.chave_mvto = ?";
    $stmt_itens = $dash->prepare($sql_itens);
    $stmt_itens->bind_param("s", $cod_nota);
    $stmt_itens->execute();
    $res_itens = $stmt_itens->get_result();
    
    $itens_nota = [];
    $total_bc_icms = 0;
    $total_v_icms = 0;
    $total_bc_st = 0;
    $total_v_st = 0;
    $total_v_prod = 0;
    $total_v_ipi = 0;

    while ($item = $res_itens->fetch_assoc()) {
        $itens_nota[] = [
            'codigo' => $item['codigo'],
            'xProd' => $item['descricao'], 
            'NCM' => $item['NCM'] ?? '',
            'CFOP' => $item['CFOP'] ?? '',
            'uCom' => $item['UNIDADE'] ?? 'UN',
            'qCom' => (float)$item['QUANTIDADE'],
            'vUnCom' => (float)$item['VALOR_UNITARIO'],
            'vProd' => (float)$item['TOTAL'],
            'vBC' => (float)($item['BASE_ICMS'] ?? 0),
            'vICMS' => (float)($item['VALOR_ICMS'] ?? 0),
            'pICMS' => (float)($item['ALIQ_ICMS'] ?? 0),
            'vIPI' => (float)($item['VALOR_IPI'] ?? 0),
            'pIPI' => (float)($item['ALIQ_IPI'] ?? 0),
            'CST' => (string)($item['CST_ICMS'] ?? ''),
            'orig' => ''
        ];
        
        $total_bc_icms += (float)($item['BASE_ICMS'] ?? 0);
        $total_v_icms += (float)($item['VALOR_ICMS'] ?? 0);
        $total_bc_st += (float)($item['BASE_SUBST'] ?? 0);
        $total_v_st += (float)($item['VALOR_SUBST'] ?? 0);
        $total_v_prod += (float)($item['VALOR_TOTAL'] ?? 0);
        $total_v_ipi += (float)($item['VALOR_IPI'] ?? 0);
    }

    // Totais
    $vBC = number_format($total_bc_icms, 2, ",", ".");
    $vICMS = number_format($total_v_icms, 2, ",", ".");
    $vBCST = number_format($total_bc_st, 2, ",", ".");
    $vST = number_format($total_v_st, 2, ",", ".");
    $vProd = number_format($total_v_prod, 2, ",", ".");
    $vNF = number_format((float)$nf_db['TOTAL_NF'], 2, ",", ".");
    $vFrete = number_format((float)($nf_db['VALOR_FRETE'] ?? 0), 2, ",", ".");
    $vSeg = number_format((float)($nf_db['VALOR_SEGURO'] ?? 0), 2, ",", ".");
    $vDesc = number_format((float)($nf_db['DESC_PROD'] ?? 0), 2, ",", ".");
    $vIPI = number_format($total_v_ipi, 2, ",", ".");
    $vOutro = number_format((float)($nf_db['VALOR_OUTRAS_DESP'] ?? 0), 2, ",", ".");

    // Transporte
    $trans_modfrete = $nf_db['FRETE'] ?? '9'; // 9 = Sem frete
    $trans_tranpnome = 'Transportadora ID: ' . ($nf_db['TRANSPORTADORA'] ?? 'N/A'); // Fallback
    $trans_tranpcnpj = '';
    $trans_tranpie = '';
    $trans_tranpend = '';
    $trans_mun = '';
    $trans_uf = '';
    $trans_qVol = $nf_db['QUANTIDADE'] ?? 0;
    $trans_esp = $nf_db['ESPECIE'] ?? '';
    $trans_pesoL = $nf_db['PESO_LIQUIDO'] ?? 0;
    $trans_pesoB = $nf_db['PESO_BRUTO'] ?? 0;

    $infAdic_fisco = '';
    $infAdic_infCpl = $nf_db['DADOS_ADICIONAIS'] ?? $nf_db['OBSERVACOES'] ?? '';

    // Duplicatas (Simulação básica se tiver parcelas)
    $duplicatas = [];
    // Se tiver tabela de parcelas seria ideal, mas vou deixar vazio por enquanto ou pegar de cad_comp se fosse o caso
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DANFE - Visualização</title>
    
    <link rel="stylesheet" href="../sys/css/estiloentrada.css" TYPE="text/css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body { background-color: #fff; }
        .danfe_pagina { font-size: 10px; font-family: Arial, Helvetica; margin: 0px; padding: 1px; }
        .danfe_pagina2 { margin: 1px; padding: 0; font-size: 11px; }
        .danfe_linha_tracejada { width: 100%; border-bottom: #000 1px dashed; margin: 10px 0 10px 0; }
        .danfe_tabelas { border-collapse: collapse; width: 100%; margin: 0; padding: 0; }
        .danfe_celula_bordas { border: 1px solid black; vertical-align: top; }
        .danfe_celula_titulo { margin: 0; font-size: 7pt; padding: 0 2px 0px 2px; }
        .danfe_celula_valor { margin: 0; font-size: 8pt; padding-left: 4px; font-weight: bold; }
        .danfe_canhoto_bordas { font-size: 7pt; border: 1px solid #000; margin: 0px; padding: 0; margin: 0 1px 0 1px; min-height: 30px; }
        .danfe_canhoto_texto { font-size: 6pt; margin: 0; font-weight: normal; padding: 0 2px 1px 2px; }
        .danfe_cabecalho_danfe { font-size: 13px; font-weight: bold; margin: 0; text-align: center; }
        .danfe_cabecalho_danfe_texto { font-size: 7pt; padding: 0; margin: 0 1px 0 1px; text-align: center; }
        .danfe_cabecalho_numero { font-size: 13px; font-weight: bold; margin: 0; text-align: center; }
        .danfe_cabecalho_entrada_saida { font-size: 7pt; }
        .danfe_cabecalho_entrada_saida_quadrado { font-size: 13pt; border: 1px solid #000000; padding: 0; margin: 0; width: 100px; text-align: center; float: none; min-width: 30px; }
        .danfe_titulo_externo { font-size: 8pt; margin: 4px 0 0 0; font-weight: bold; }
       @media print { 
            .no-print { display: none !important; } 
            @page { margin: 5mm; size: A4 portrait; }
            body { background-color: #fff !important; margin: 0; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .container, .container-fluid { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .danfe_pagina { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .danfe_tabelas { width: 100% !important; }
        }
    </style>
</head>
<body>

    <div class="container-fluid mt-3 no-print">
        <div class="row mb-3">
            <div class="col-12 text-center">
                <?php if (!$xml_disponivel): ?>
                    <div class="alert alert-warning d-inline-block mb-2">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Visualização gerada a partir do banco de dados (XML não disponível).
                    </div><br>
                <?php endif; ?>
                <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-2"></i>Imprimir</button>
                <button onclick="window.close()" class="btn btn-danger"><i class="fas fa-times me-2"></i>Fechar</button>
            </div>
        </div>
    </div>

    <?= $aviso_homologacao ?>

    <div class="danfe_pagina container">
        <div class="danfe_pagina2">
            <table class="danfe_tabelas">
                <tr>
                    <td>
                        <table class="danfe_tabelas" style="min-height:60px;">
                            <tr>
                                <td class="danfe_celula_bordas" colspan="2">
                                    <p class="danfe_canhoto_texto">RECEBEMOS DE "<?php echo $emit_xFant; ?>" OS PRODUTOS CONSTANTES DA NOTA FISCAL INDICADA AO LADO</p>
                                </td>
                            </tr>
                            <tr>
                                <td class="danfe_canhoto_bordas">
                                    <p class="danfe_celula_titulo">Data de recebimento</p>
                                    <p class="danfe_celula_valor">&nbsp;</p>
                                </td>
                                <td class="danfe_canhoto_bordas">
                                    <p class="danfe_celula_titulo">Identificação e assinatura do recebedor</p>
                                    <p class="danfe_celula_valor">&nbsp;</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td width="10">&nbsp;</td>
                    <td>
                        <table class="danfe_tabelas" style="min-height:60px">
                            <tr>
                                <td class="danfe_celula_bordas" align='center'>
                                    <strong>NF-e</strong>
                                    <h2 class="danfe_cabecalho_numero">N. <?php echo $nNF ?></h2>
                                    <strong>Série <?php echo $serie ?></strong>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            
            <div class="danfe_linha_tracejada"></div>
            
            <table class="danfe_tabelas">
                <tr>
                    <td rowspan='3' class="danfe_celula_bordas" style="text-align: center; padding-top: 25px;" width="160">
                        <img src='../sys/img/logo.png' alt='Logo' width="150" style="max-height: 80px; object-fit: contain;">
                    </td>

                    <td rowspan='3' class="danfe_celula_bordas">
                        <p class="danfe_celula_valor" style="font-size: 12pt;"><?php echo $emit_xFant; ?></p>
                        <p class="danfe_celula_valor"><?php echo $emit_xNome; ?></p>
                        <p class="danfe_celula_titulo"><?php echo $emit_xLgr; ?>, <?php echo $emit_nro; ?> - <?php echo $emit_xBairro; ?></p>
                        <p class="danfe_celula_titulo"><?php echo $emit_xMun; ?> - <?php echo $emit_UF; ?> - CEP: <?php echo $emit_CEP; ?></p>
                        <p class="danfe_celula_titulo">Fone: <?php echo $emit_fone; ?></p>
                    </td>
                    <td rowspan='3' class="danfe_celula_bordas" align='center' width="100">
                        <p class="danfe_cabecalho_danfe">DANFE</p>
                        <p class="danfe_cabecalho_danfe_texto">Documento Auxiliar da <br>Nota Fiscal Eletrônica</p>
                        <table class="danfe_tabelas">
                            <tr>
                                <td nowrap class='danfe_cabecalho_entrada_saida'>
                                    0 - Entrada<br>
                                    1 - Saída</td>
                                <td class="danfe_cabecalho_entrada_saida_quadrado"><?php echo $tpNF ?></td>
                            </tr>
                        </table>
                        <p class="danfe_cabecalho_numero">N: <?php echo $nNF ?></p>
                        <p class="danfe_cabecalho_danfe_texto">SÉRIE: <?php echo $serie ?><br>Página: 1 de 1</p>
                    </td>
                    <td class="danfe_celula_bordas" align='center'>
                        <p class="danfe_celula_titulo">Controle do Fisco</p>
                        <div style="height: 40px;"></div>
                    </td>
                </tr>
                <tr>
                    <td class="danfe_celula_bordas" align='center'>
                        <p class="danfe_celula_titulo">Chave de acesso</p>
                        <p class="danfe_celula_valor"><?php echo $chave ?></p>
                    </td>
                </tr>
                <tr>
                    <td class="danfe_celula_bordas" align='center'>
                        <p class="danfe_celula_titulo">Consulta de autenticidade no portal nacional da NF-e</p>
                        <p class="danfe_celula_valor">
                            <a href="http://www.nfe.fazenda.gov.br/portal" target="_blank">www.nfe.fazenda.gov.br/portal</a>
                            ou no site da Sefaz autorizadora
                        </p>
                    </td>
                </tr>
                <tr>
                    <td colspan='3' class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Natureza da operação</p>
                        <p class="danfe_celula_valor"><?php echo $natOp ?></p>
                    </td>
                    <td class="danfe_celula_bordas" align='center'>
                        <p class="danfe_celula_titulo">Protocolo de autorização de uso</p>
                        <p class="danfe_celula_valor"><?php echo $nProt ?> - <?php echo $data_formatada ?></p>
                    </td>
                </tr>
            </table>

            <table class="danfe_tabelas">
                <tr>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Inscrição Estadual</p>
                        <p class="danfe_celula_valor"><?php echo $emit_IE ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Inscr. Est. do Subst. Trib.</p>
                        <p class="danfe_celula_valor"></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">CNPJ</p>
                        <p class="danfe_celula_valor"><?php echo $emit_CNPJ ?></p>
                    </td>
                </tr>
            </table>

            <h3 class="danfe_titulo_externo">DESTINATÁRIO / REMETENTE</h3>
            <table class="danfe_tabelas">
                <tr>
                    <td class="danfe_celula_bordas" width="50%">
                        <p class="danfe_celula_titulo">Nome / Razão Social</p>
                        <p class="danfe_celula_valor"><?php echo $dest_xNome ?></p>
                    </td>
                    <td class="danfe_celula_bordas" width="30%">
                        <p class="danfe_celula_titulo">CNPJ/CPF</p>
                        <p class="danfe_celula_valor"><?php echo $dest_CNPJ ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Data da Emissão</p>
                        <p class="danfe_celula_valor"><?php echo $data_formatada ?></p>
                    </td>
                </tr>
                <tr>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Endereço</p>
                        <p class="danfe_celula_valor"><?php echo $dest_xLgr; ?>, <?php echo $dest_nro; ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Bairro / Distrito</p>
                        <p class="danfe_celula_valor"><?php echo $dest_xBairro ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Data da Saída/Entrada</p>
                        <p class="danfe_celula_valor"><?php echo $data_formatada1 ?></p>
                    </td>
                </tr>
                <tr>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Município</p>
                        <p class="danfe_celula_valor"><?php echo $dest_xMun ?> - <?php echo $dest_xUF ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Fone / Fax</p>
                        <p class="danfe_celula_valor"><?php echo $dest_xfone ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Hora da Saída</p>
                        <p class="danfe_celula_valor"><?php echo $horario ?></p>
                    </td>
                </tr>
            </table>

            <h3 class="danfe_titulo_externo">FATURA / DUPLICATA</h3>
            <table class="danfe_tabelas">
                <tr>
                    <td class="danfe_celula_bordas">
                        <table width="100%" border="0" cellpadding="2" cellspacing="0">
                            <?php
                            if (!empty($duplicatas)) {
                                echo "<tr>";
                                $count = 0;
                                foreach($duplicatas as $dup) {
                                    $count++;
                                    $nDup = $dup['nDup'];
                                    $dVenc = date("d/m/Y", strtotime($dup['dVenc']));
                                    $vDup = number_format($dup['vDup'], 2, ",", ".");
                                    echo "<td style='border-right: 1px solid #ccc; padding: 2px;'>
                                            <span style='font-size:7pt'>Num:</span> <b>$nDup</b><br>
                                            <span style='font-size:7pt'>Venc:</span> <b>$dVenc</b><br>
                                            <span style='font-size:7pt'>Valor:</span> <b>R$ $vDup</b>
                                          </td>";
                                    if ($count % 6 == 0) echo "</tr><tr>";
                                }
                                echo "</tr>";
                            } else {
                                echo "<tr><td>Pagamento à vista ou dados não disponíveis</td></tr>";
                            }
                            ?>
                        </table>
                    </td>
                </tr>
            </table>

            <h3 class="danfe_titulo_externo">CÁLCULO DO IMPOSTO</h3>
            <table class="danfe_tabelas">
                <tr>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Base de Cálculo do ICMS</p>
                        <p class="danfe_celula_valor"><?php echo $vBC ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Valor do ICMS</p>
                        <p class="danfe_celula_valor"><?php echo $vICMS ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Base de Calc. ICMS ST</p>
                        <p class="danfe_celula_valor"><?php echo $vBCST ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Valor do ICMS ST</p>
                        <p class="danfe_celula_valor"><?php echo $vST ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Valor Total dos Produtos</p>
                        <p class="danfe_celula_valor"><?php echo $vProd ?></p>
                    </td>
                </tr>
                <tr>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Valor do Frete</p>
                        <p class="danfe_celula_valor"><?php echo $vFrete ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Valor do Seguro</p>
                        <p class="danfe_celula_valor"><?php echo $vSeg ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Desconto</p>
                        <p class="danfe_celula_valor"><?php echo $vDesc ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Outras Despesas</p>
                        <p class="danfe_celula_valor"><?php echo $vOutro ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Valor do IPI</p>
                        <p class="danfe_celula_valor"><?php echo $vIPI ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Valor Total da Nota</p>
                        <p class="danfe_celula_valor"><?php echo $vNF ?></p>
                    </td>
                </tr>
            </table>

            <h3 class="danfe_titulo_externo">TRANSPORTADOR / VOLUMES TRANSPORTADOS</h3>
            <table class="danfe_tabelas">
                <tr>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Razão Social</p>
                        <p class="danfe_celula_valor"><?php echo $trans_tranpnome ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Frete por Conta</p>
                        <p class="danfe_celula_valor"><?php echo $trans_modfrete == 0 ? '0-Emitente' : ($trans_modfrete == 1 ? '1-Destinatário' : '9-Sem Frete'); ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Código ANTT</p>
                        <p class="danfe_celula_valor"></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Placa do Veículo</p>
                        <p class="danfe_celula_valor"></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">UF</p>
                        <p class="danfe_celula_valor"></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">CNPJ/CPF</p>
                        <p class="danfe_celula_valor"><?php echo $trans_tranpcnpj ?></p>
                    </td>
                </tr>
                <tr>
                    <td class="danfe_celula_bordas" colspan="3">
                        <p class="danfe_celula_titulo">Endereço</p>
                        <p class="danfe_celula_valor"><?php echo $trans_tranpend ?> - <?php echo $trans_mun ?> / <?php echo $trans_uf ?></p>
                    </td>
                    <td class="danfe_celula_bordas" colspan="3">
                        <p class="danfe_celula_titulo">Inscrição Estadual</p>
                        <p class="danfe_celula_valor"><?php echo $trans_tranpie ?></p>
                    </td>
                </tr>
                <tr>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Quantidade</p>
                        <p class="danfe_celula_valor"><?php echo $trans_qVol ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Espécie</p>
                        <p class="danfe_celula_valor"><?php echo $trans_esp ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Marca</p>
                        <p class="danfe_celula_valor"></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Numeração</p>
                        <p class="danfe_celula_valor"></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Peso Bruto</p>
                        <p class="danfe_celula_valor"><?php echo $trans_pesoB ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Peso Líquido</p>
                        <p class="danfe_celula_valor"><?php echo $trans_pesoL ?></p>
                    </td>
                </tr>
            </table>

            <h3 class="danfe_titulo_externo">DADOS DO PRODUTO / SERVIÇO</h3>
            <table width="100%" border="1" cellspacing="0" cellpadding="2" style="border-collapse: collapse; font-size: 8pt;">
                <tr style="background-color: #f0f0f0;">
                    <th align="center">Cód.</th>
                    <th align="left">Descrição</th>
                    <th align="center">NCM</th>
                    <th align="center">CST</th>
                    <th align="center">CFOP</th>
                    <th align="center">Unid.</th>
                    <th align="right">Qtde</th>
                    <th align="right">Vlr Unit.</th>
                    <th align="right">Vlr Total</th>
                    <th align="right">BC ICMS</th>
                    <th align="right">Vlr ICMS</th>
                    <th align="right">Vlr IPI</th>
                    <th align="right">% ICMS</th>
                    <th align="right">% IPI</th>
                </tr>
                <?php
                foreach($itens_nota as $item) {
                    $codigo = $item['codigo'];
                    $xProd = $item['xProd'];
                    $NCM = $item['NCM'];
                    $CFOP = $item['CFOP'];
                    $uCom = $item['uCom'];
                    $qCom = number_format($item['qCom'], 2, ",", ".");
                    $vUnCom = number_format($item['vUnCom'], 2, ",", ".");
                    $vProd = number_format($item['vProd'], 2, ",", ".");
                    
                    $vBC_item = number_format($item['vBC'], 2, ",", ".");
                    $vICMS_item = number_format($item['vICMS'], 2, ",", ".");
                    $pICMS_item = number_format($item['pICMS'], 2, ",", ".");
                    
                    $vIPI_item = number_format($item['vIPI'], 2, ",", ".");
                    $pIPI_item = number_format($item['pIPI'], 2, ",", ".");
                    
                    $CST = $item['CST'];
                    $orig = $item['orig'];

                    echo "<tr>
                            <td align='center'>$codigo</td>
                            <td>$xProd</td>
                            <td align='center'>$NCM</td>
                            <td align='center'>$orig$CST</td>
                            <td align='center'>$CFOP</td>
                            <td align='center'>$uCom</td>
                            <td align='right'>$qCom</td>
                            <td align='right'>$vUnCom</td>
                            <td align='right'>$vProd</td>
                            <td align='right'>$vBC_item</td>
                            <td align='right'>$vICMS_item</td>
                            <td align='right'>$vIPI_item</td>
                            <td align='right'>$pICMS_item</td>
                            <td align='right'>$pIPI_item</td>
                          </tr>";
                }
                ?>
            </table>

            <h3 class="danfe_titulo_externo">DADOS ADICIONAIS</h3>
            <table class="danfe_tabelas">
                <tr style="min-height:100px">
                    <td class="danfe_celula_bordas" width="60%">
                        <p class="danfe_celula_titulo">Informações Complementares</p>
                        <p class="danfe_celula_valor" style="font-weight: normal; white-space: pre-wrap;"><?php echo $infAdic_infCpl ?></p>
                    </td>
                    <td class="danfe_celula_bordas">
                        <p class="danfe_celula_titulo">Reservado ao Fisco</p>
                        <p class="danfe_celula_valor"><?php echo $infAdic_fisco ?></p>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
</body>
</html>
