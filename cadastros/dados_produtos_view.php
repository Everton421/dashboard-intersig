<?php
// Inicia o buffer de saída para evitar erros de "headers already sent"
ob_start();

// Habilita a exibição de erros durante o desenvolvimento
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/config.php';
require_once '../config/session.php'; 

// Array para armazenar a resposta final
$response = [];
$http_status_code = 200; // Código de sucesso por padrão

// Verifica a conexão com o banco de dados
if ($dash->connect_error) {
    $http_status_code = 500; // Erro interno do servidor
    $response = ['error' => 'Falha na conexão com o banco de dados: ' . $dash->connect_error];
} else {
    // =========================================================================
    // CORREÇÃO CRÍTICA: Define o charset da conexão para UTF-8.
    // Isso garante que os dados com caracteres especiais (", #, ç, á)
    // sejam lidos corretamente do banco de dados.
    // =========================================================================
     {
        // A consulta SQL (está correta)
        $sql = "SELECT
    UPPER(c.codigo) AS codigo,
    UPPER(c.descricao) AS descricao,
    UPPER(p.nome) AS grupo,
    COALESCE(UPPER(c.num_fabricante), '') AS num_fabricante,
    COALESCE(UPPER(c.num_original), '') AS num_original,
    COALESCE(UPPER(c.outro_cod), '') AS outro_cod,
    COALESCE(UPPER(c.origem), '0') AS origem,
    COALESCE(UPPER(n.ncm), '0') AS class_fiscal,
    COALESCE(UPPER(n.cod_cest), '0') AS cest,
    COALESCE(UPPER(pro.sigla), '') AS unidade,
    COALESCE(c.peso, 0) AS peso,
    CASE c.tipo 
        WHEN 0 THEN 'Consumo'
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
    END AS tipo
FROM
    {$banco_publico}.cad_prod c
LEFT JOIN
    {$banco_publico}.cad_pgru p ON p.codigo = c.grupo
LEFT JOIN
    {$banco_publico}.class_fiscal n ON n.codigo = c.class_fiscal
LEFT JOIN
    {$banco_publico}.unid_prod pro ON pro.produto = c.codigo AND pro.padr_sai = 'S'
WHERE
    c.ativo = 'S'
";

        // Note que removi o UPPER() do select. O json_encode cuidará da formatação.
        // Se precisar que os dados sejam maiúsculos, pode adicionar de volta.
        
        $results = $dash->query($sql);

        if ($results === false) {
            $http_status_code = 500;
            $response = ['error' => 'Erro na consulta SQL', 'sql_error' => $dash->error];
        } else {
            $data = [];
            while ($row = $results->fetch_assoc()) {
                // A função fetch_assoc já retorna os dados prontos para serem codificados
                $data[] = $row;
            }
            $response = $data;
            $results->free(); // Libera a memória do resultado
        }
    }
    $dash->close(); // Fecha a conexão
}

// Limpa qualquer saída acidental (espaços, etc.) do buffer
ob_end_clean();

// =========================================================================
// CORREÇÃO CRÍTICA: Define o cabeçalho e as flags do json_encode
// =========================================================================

// Define o código de status HTTP (ex: 200 OK ou 500 Internal Server Error)
http_response_code($http_status_code);

// Define o tipo de conteúdo como JSON com charset UTF-8
header('Content-Type: application/json; charset=utf-8');

// Codifica a resposta para JSON
// JSON_UNESCAPED_UNICODE: Mantém caracteres como 'ç' e 'á' em vez de '\u00e7'
// JSON_INVALID_UTF8_SUBSTITUTE: Se, mesmo após tudo, um caractere UTF-8 inválido for encontrado,
// ele será substituído em vez de causar a falha da função.
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

exit();