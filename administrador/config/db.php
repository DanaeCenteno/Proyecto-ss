<?php
$server = "localhost";
$user = "root";
$password = "";
$db = "pagina";

$conexion = new mysqli($server, $user, $password, $db);

if($conexion->connect_errno){
    die("Conexion Fallida: " . $conexion->connect_errno);
}
// echo "CONECTADO";
?>