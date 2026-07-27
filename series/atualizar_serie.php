<?php
require_once '../config/config.php';
require_once '../config/session.php'; 

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$codigo = isset($_POST['codigo']) ? intval($_POST['codigo']) : 0;

if ($codigo <= 0) {
    echo json_encode(['success' => false, 'message' => 'Código inválido']);
    exit;
}

// 1. BUSCA OS DADOS ANTIGOS
$sql_busca = "SELECT * FROM $banco_vendas.series WHERE codigo = ?";
$stmt_busca = $dash->prepare($sql_busca);
$stmt_busca->bind_param("i", $codigo);
$stmt_busca->execute();
$resultado_busca = $stmt_busca->get_result();
$dados_antigos_raw = $resultado_busca->fetch_assoc();

if (!$dados_antigos_raw) {
    echo json_encode(['success' => false, 'message' => 'Série não encontrada para atualização.']);
    exit;
}

// --- CORREÇÃO AQUI: Força todas as chaves do banco para minúsculo ---
// Isso garante que se o banco devolver 'DESCRICAO', vire 'descricao' para bater com o HTML
$dados_antigos = array_change_key_case($dados_antigos_raw, CASE_LOWER);
// --------------------------------------------------------------------

$camposPermitidos = [
    'descricao', 'nome', 'serie', 'filial', 'modelo', 'mod_sintegra', 
    'emissao_nfe', 'gerar', 'consid_sped', 'origem', 'prim_nf', 'ult_nf', 
    'num_auto', 'repetir', 'max_vend', 'max_prod', 'max_serv', 'max_parc', 
    'dados_adicionais', 'ativa'
];

$camposParaAtualizar = [];
$tipos = "";
$valores = [];
$logAlteracoes = [];

foreach ($_POST as $chave => $valor_novo) {
    // Converte a chave do POST para minúsculo também, por segurança
    $chave_lower = strtolower($chave);

    if (in_array($chave_lower, $camposPermitidos)) {
        
        $camposParaAtualizar[] = "$chave_lower = ?";
        $tipos .= "s"; 
        $valores[] = $valor_novo;
        
        // Pega o valor antigo (agora garantido pelo array_change_key_case)
        // Se ainda for nulo/vazio, usamos string '(vazio)' para ficar claro no log
        $val_antigo_real = $dados_antigos[$chave_lower] ?? '';
        
        // Truque visual: se estiver vazio de verdade, escreve (VAZIO) no log
        $txt_antigo = ($val_antigo_real === '') ? '(VAZIO)' : $val_antigo_real;
        $txt_novo   = ($valor_novo === '')      ? '(VAZIO)' : $valor_novo;

        $logAlteracoes[] = "$chave_lower: '$txt_antigo' -> '$txt_novo'";
    }
}

if (empty($camposParaAtualizar)) {
    echo json_encode(['success' => true, 'message' => 'Nada para atualizar.']);
    exit;
}

$tipos .= "i";
$valores[] = $codigo;

$sql = "UPDATE $banco_vendas.series SET " . implode(", ", $camposParaAtualizar) . " WHERE codigo = ?";
$stmt = $dash->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Erro na preparação SQL: ' . $dash->error]);
    exit;
}

$stmt->bind_param($tipos, ...$valores);

if ($stmt->execute()) {
    
    // LOG
    $usuario_raw    = $_SESSION['user_name'] ?? 'SISTEMA';
    $computador_raw = 'WEB'; 
    $texto_mudancas = implode("; ", $logAlteracoes);
    $historico_raw  = "CODIGO: $codigo - ALTERACOES: $texto_mudancas";

    // Converte tudo para MAIÚSCULO
    $apelido_final    = mb_strtoupper($usuario_raw, 'UTF-8');
    $computador_final = mb_strtoupper($computador_raw, 'UTF-8');
    $historico_final  = mb_strtoupper($historico_raw, 'UTF-8');
    
    $data = date('Y-m-d');
    $hora = date('H:i:s');
    $acao = '2'; 

    $sql_log = "INSERT INTO $banco_vendas.log (APELIDO, COMPUTADOR, DATA, HORA, ACAO, HISTORICO) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_log = $dash->prepare($sql_log);
    $stmt_log->bind_param("ssssss", $apelido_final, $computador_final, $data, $hora, $acao, $historico_final);
    $stmt_log->execute();

    echo json_encode(['success' => true, 'message' => 'Dados atualizados com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao executar update: ' . $stmt->error]);
}
?>