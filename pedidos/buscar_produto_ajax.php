<?php
require_once '../config/config.php';
require_once '../config/session.php'; 

header('Content-Type: application/json; charset=utf-8');
mysqli_set_charset($dash, "utf8");

$q = $_GET['term'] ?? $_GET['q'] ?? '';
$q = trim($q);

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$q = mysqli_real_escape_string($dash, $q);

$sql = "SELECT c.codigo, c.descricao, 0 as preco,  un.sigla AS sigla
        FROM {$banco_publico}.cad_prod c
        LEFT JOIN	{$banco_publico}.unid_prod un  ON (c.codigo = un.produto)
        WHERE (c.codigo LIKE '%$q%' OR c.descricao LIKE '%$q%')
        AND un.padr_ent = 'S' 
        ORDER BY c.descricao 
        LIMIT 15";

$result = mysqli_query($dash, $sql);
$data = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'id' => $row['codigo'],
            'value' => $row['codigo'] . ' - ' . $row['descricao'],
            'label' => $row['codigo'] . ' - ' . $row['descricao'],
            'text' => $row['codigo'] . ' - ' . $row['descricao'],
            'sigla' => $row['sigla'],
            'preco' => (float)$row['preco'],
            'descricao' => $row['descricao']
        ];
    }
}
echo json_encode($data);
exit;
