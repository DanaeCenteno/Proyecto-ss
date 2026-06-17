<?php
$server = "localhost";
$user = "root";
$password = "";
$db = "eduforge";

$conexion = new mysqli($server, $user, $password, $db);

if($conexion->connect_errno){
    die("Conexion Fallida: " . $conexion->connect_errno);
}
// echo "CONECTADO"; // Quitar esto para que la interfaz se vea limpia
?>