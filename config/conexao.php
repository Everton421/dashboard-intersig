<?php
//syma
$servidor = "192.168.35.2"; 
$usuario  = "intersig";
$senha    = "Nileduz";
$dbname   = "dashboard"; 



$dash = mysqli_connect($servidor, $usuario, $senha, $dbname);


if (!$dash) {
    die("Falha na conexão: " . mysqli_connect_error());
}


mysqli_set_charset($dash, "utf8");