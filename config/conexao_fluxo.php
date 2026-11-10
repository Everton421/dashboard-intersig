<?php 
$servidor ="26.196.46.134";
$usuario="intersig";
$senha="Ganapataye";
$dbname="sk_publico";
$dbname="sk_vendas";
$dbname="sk_financeiro";
$dbname="dashboard";

$dbname="sk_publico";
$dbname="sk_vendas";
$dbname="sk_financeiro";


$prefixe = 'sk_estoque';
$prefixp = 'sk_publico';
$prefixv = 'sk_vendas';
$prefixf = 'sk_financeiro';
$prefixd = 'dashboard';


$prefixskp = 'sk_publico';
$prefixskv = 'sk_vendas';
$prefixskf = 'sk_financeiro';


$conexao=mysqli_connect($servidor, $usuario, $senha, $dbname);
if(!$conexao){
    die("Houve erro: ".mysqli_connect_error());
}

// Define o conjunto de caracteres para utf8
mysqli_set_charset($conexao, "utf8");

?>