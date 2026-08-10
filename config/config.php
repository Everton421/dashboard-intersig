<?php

// Garante que o script não seja acessado diretamente.
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    die('Acesso direto a este arquivo não é permitido.');
}

// 1. Inclui a conexão com o banco de dados.
//    É necessário para buscar as configurações.
include __DIR__ . '/conexao.php';
$empresa_nome = "CABEAR SOLUÇÕES";

// 2. Define o ID fixo da configuração
$config_id = 1;

// 3. Busca os parâmetros da configuração no banco
$result = mysqli_query($dash, "SELECT * FROM configuracoes_empresa WHERE id = " . intval($config_id));
if (!$result || mysqli_num_rows($result) == 0) {
    // A conexão `$dash` vem do arquivo 'conexao.php'
    die("Erro Crítico: Configuração com ID $config_id não encontrada ou falha na consulta: " . mysqli_error($dash));
}
// A variável $config conterá todas as configurações do banco.
$config = mysqli_fetch_assoc($result);

// 4. Prepara as variáveis que serão usadas nos seus scripts
$prefixo_banco = $config['prefixo_banco'];
$banco_vendas = mysqli_real_escape_string($dash, $prefixo_banco . '_vendas');
$banco_publico = mysqli_real_escape_string($dash, $prefixo_banco . '_publico');
$banco_financeiro = mysqli_real_escape_string($dash, $prefixo_banco . '_financeiro');
$banco_estoque = mysqli_real_escape_string($dash, $prefixo_banco . '_estoque');

// 5. Inclui a função de validação, pois ela faz parte da preparação das configurações.
function validar_lista_ids($lista) {
    if (empty($lista)) return '';
    $ids = explode(',', $lista);
    $ids_limpos = [];
    foreach ($ids as $id) {
        $trimmed_id = trim($id);
        if (is_numeric($trimmed_id) && $trimmed_id != '') {
            $ids_limpos[] = $trimmed_id;
        }
    }
    return implode(',', $ids_limpos);
}

// 6. Valida e prepara todas as listas de IDs
$transacoes = validar_lista_ids($config['transacoes_venda']);
$series = validar_lista_ids($config['series_venda']);
$transacoescompra = validar_lista_ids($config['transacoes_compra']);
$seriescompra = validar_lista_ids($config['series_compra']);
$transacoes_devolucao = validar_lista_ids($config['transacoes_devolucao']);
$series_devolucao = validar_lista_ids($config['series_devolucao']);
$transacoes_devolucaocompra = validar_lista_ids($config['transacoes_devolucaocompra']);
$series_devolucaocompra = validar_lista_ids($config['series_devolucaocompra']);
$t_venda_devolucao = validar_lista_ids($config['t_venda_devolucao']);
$s_venda_devolucao = validar_lista_ids($config['s_venda_devolucao']);
$transacao_nfe_vendas = validar_lista_ids($config['transacao_nfe_vendas']); 
$serie_nfe_vendas = validar_lista_ids($config['serie_nfe_vendas']);
$transacao_nfe_compras = validar_lista_ids($config['transacao_nfe_compras']);
$serie_nfe_compras = validar_lista_ids($config['serie_nfe_compras']);
$tabela_venda_padrao = validar_lista_ids($config['tabela_venda_padrao']);
$filiais = validar_lista_ids($config['filiais']);

// Ao final deste script, todas as variáveis ($dash, $config, $banco_vendas, $transacoes, etc.)
// estarão prontas para serem usadas no arquivo que o incluiu.

?>