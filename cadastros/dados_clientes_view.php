<?php


require_once '../config/config.php';
require_once '../config/session.php'; 




// Executa a consulta para buscar os produtos

$sql = "
SELECT 
    c.codigo, 
    c.nome, 
    c.apelido, 
    c.cidade,
    c.cpf, 
    c.rg,
      CASE c.contrib 
        WHEN 'N' THEN 'NÃO'
        WHEN 'S' THEN 'SIM'
        ELSE 'ISENTO'
    END AS contrib,

     CASE c.consumidor_final 
        WHEN 'N' THEN 'NÃO'
        WHEN 'S' THEN 'SIM'
        ELSE 'NÃO'
    END AS consumidor_final,

       CASE c.regime_tribut 
        WHEN 'N' THEN 'NORMAL'
        WHEN 'S' THEN 'SIMPLES'
        ELSE 'SIMPLES'
    END AS regime_tribut,
    
     c.fis_jur,
    c.ultima_compra
FROM ".$banco_publico.".cad_clie c 
WHERE c.ativo = 'S'
";

$results = $dash->query($sql);

if ($results->num_rows > 0) {
    $clientes = array();
    while ($row = $results->fetch_assoc()) {
        $clientes[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($clientes);
} else {
    echo json_encode([]);
}

$dash->close();
?>