<?php

ob_start();


ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/config.php';
require_once '../config/session.php'; 


$response = [];
$http_status_code = 200; 


if ($dash->connect_error) {
    $http_status_code = 500; 
    $response = ['error' => 'Falha na conexão com o banco de dados: ' . $dash->connect_error];
} else {

     {
        
        $sql = "SELECT
    UPPER(c.filial) AS filial,
    UPPER(c.razao_social) AS razaosocial,
    UPPER(c.nome_fantasia) AS fantasia,
    UPPER(c.nome_reduz) AS nomered,
    UPPER(c.endereco) AS endereco,
    COALESCE(UPPER(c.numero), '') AS numero,
    COALESCE(UPPER(c.complemento), '') AS complemento,
    COALESCE(UPPER(c.bairro), '') AS bairro,
    COALESCE(UPPER(c.cidade), '') AS cidade,
    COALESCE(UPPER(c.estado), '') AS uf,
    COALESCE(UPPER(c.cep), '0') AS cep,
    COALESCE(UPPER(c.cnpj), '0') AS cnpj,
    COALESCE(UPPER(c.inscricao), '0') AS inscricao,
    
    CASE c.regime_tribut 
        WHEN 'S' THEN 'SIMPLES'
        WHEN 'N' THEN 'NORMAL'
        WHEN 'SE' THEN 'SIMPLES'
      ELSE 'Tipo não especificado'
    END AS regime
FROM
    {$banco_vendas}.empresas c

WHERE
    c.ativa = 'S' ";
        $results = $dash->query($sql);

        if ($results === false) {
            $http_status_code = 500;
            $response = ['error' => 'Erro na consulta SQL', 'sql_error' => $dash->error];
        } else {
            $data = [];
            while ($row = $results->fetch_assoc()) {

                $data[] = $row;
            }
            $response = $data;
            $results->free();
        }
    }
    $dash->close();
}

ob_end_clean();


http_response_code($http_status_code);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

exit();