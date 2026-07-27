<?php 
$servidor ="192.168.8.3";
$usuario="intersig";
$senha="ewqiop2017";
$dbname="mesquita_publico";
$dbname="mesquita_vendas";
$dbname="mesquita_financeiro";
$dbname="dashboard";

$dbname="jr_publico";
$dbname="jr_vendas";
$dbname="jr_financeiro";


$prefixe = 'mesquita_estoque';
$prefixp = 'mesquita_publico';
$prefixv = 'mesquita_vendas';
$prefixf = 'mesquita_financeiro';
$prefixd = 'dashboard';


$prefixjrp = 'jr_publico';
$prefixjrv = 'jr_vendas';
$prefixjrf = 'jr_financeiro';


$conexao=mysqli_connect($servidor, $usuario, $senha, $dbname);
if(!$conexao){
    die("Houve erro: ".mysqli_connect_error());
}

// Define o conjunto de caracteres para utf8
mysqli_set_charset($conexao, "utf8");

?>