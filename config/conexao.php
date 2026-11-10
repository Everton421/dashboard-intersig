<?php
//syma
$servidor = "localhost";
$usuario  = "intersig";
$senha    = "Nileduz";
$dbname   = "dashboard";


$dash = mysqli_connect($servidor, $usuario, $senha, $dbname);


if (!$dash) {
    die("Falha na conexÃ£o: " . mysqli_connect_error());
}


mysqli_set_charset($dash, "utf8");
