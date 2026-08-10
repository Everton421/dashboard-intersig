<?php
require_once '../config/config.php';
require_once '../config/session.php'; 


$sql = "SELECT codigo, razao_social as nome, nome_fantasia as apelido, cidade,
 fis_jur ,CASE ativ_empr
      WHEN 'D' THEN 'DISTRIBUIDORA'
      WHEN 'I' THEN 'INDÚSTRIA'
      WHEN 'O' THEN 'O.N.G'
      WHEN 'M' THEN 'ÓRGÃO MUNICIPAL'
      WHEN 'E' THEN 'ÓRGÃO ESTADUAL'
      WHEN 'F' THEN 'PRESTADOR SERVIÇO'
      WHEN 'R' THEN 'REVENDEDOR'
      WHEN 'U' THEN 'OUTRO'
      WHEN 'T' THEN 'PRODUTOR RURAL'
    END AS atividade,
     CASE regime_tribut 
        WHEN 'N' THEN 'NORMAL'
        WHEN 'S' THEN 'SIMPLES'
        ELSE 'SIMPLES'
    END AS regime_tribut,
     CASE contrib 
        WHEN 'N' THEN 'NÃO'
        WHEN 'S' THEN 'SIM'
        ELSE 'ISENTO'
    END AS contrib,
      CASE para_consumo 
        WHEN 'N' THEN 'NÃO'
        WHEN 'S' THEN 'SIM'
        ELSE 'NÃO'
    END AS consumidor_final,

cnpj as cpf, inscricao as  rg, estado
FROM ".$banco_publico.".cad_forn 
WHERE ativo = 'S'";

$results = $dash->query($sql);
if ($results !== false && $results->num_rows > 0) {
    $orders = array();
    while ($row = $results->fetch_assoc()) {
        $orders[] = $row;
    }

    
    header('Content-Type: application/json');


    echo json_encode($orders);
} else {
    
    echo json_encode(array());
}

$dash->close();
?>