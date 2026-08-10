<?php
require_once '../config/config.php';
echo "Banco Publico: " . $banco_publico . "<br>";
$sql = "SELECT COUNT(*) as total FROM {$banco_publico}.cad_prod WHERE ativo = 'S'";
$res = mysqli_query($dash, $sql);
if ($res) {
    echo "Total produtos ativos (S): " . mysqli_fetch_assoc($res)['total'] . "<br>";
}

$sql = "SELECT ativo, COUNT(*) as qtd FROM {$banco_publico}.cad_prod GROUP BY ativo";
$res = mysqli_query($dash, $sql);
while($row = mysqli_fetch_assoc($res)) {
    echo "Ativo " . $row['ativo'] . ": " . $row['qtd'] . "<br>";
}

$sql = "SELECT codigo, descricao, ativo FROM {$banco_publico}.cad_prod LIMIT 5";
$res = mysqli_query($dash, $sql);
if ($res) {
    while($row = mysqli_fetch_assoc($res)) {
        echo $row['codigo'] . " - " . $row['descricao'] . " [Ativo: " . $row['ativo'] . "]<br>";
    }
}
