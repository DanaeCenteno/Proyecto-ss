<?php
require_once __DIR__ . '/../administrador/config/app.php';


function iniciarSesion(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function registrarSesion(array $usuario): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $usuario['id'];
    $_SESSION['nombre']  = $usuario['nombre'];
    $_SESSION['rol']     = strtolower($usuario['rol']);
}

function estaAutenticado(): bool {
    return isset($_SESSION['user_id']);
}

function usuarioId(): int {
    return (int) ($_SESSION['user_id'] ?? 0);
}

function usuarioRol(): string {
    return $_SESSION['rol'] ?? '';
}

function usuarioNombre(): string {
    return $_SESSION['nombre'] ?? '';
}

function inicialesAvatar(string $nombre): string {
    $palabras = explode(' ', trim($nombre));
    $ini = strtoupper(substr($palabras[0], 0, 1));
    if (isset($palabras[1])) {
        $ini .= strtoupper(substr($palabras[1], 0, 1));
    }
    return $ini;
}

function redirigirSegunRol(): void {
    $id  = usuarioId();
    $rol = usuarioRol();
    if ($rol === ROL_PROFESOR) {
        header("Location: " . BASE_URL . "/profesor/dashboard.php?uid=$id");
    } else {
        header("Location: " . BASE_URL . "/estudiante/index.php?uid=$id");
    }
    exit;
}

function requiereLogin(): void {
    iniciarSesion();
    if (!estaAutenticado()) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

function requiereRol(string $rol): void {
    requiereLogin();
    if (usuarioRol() !== strtolower($rol)) {
        header("Location: " . BASE_URL . "/acceso-denegado.php");
        exit;
    }
}

function cerrarSesion(): void {
    iniciarSesion();
    $_SESSION = [];                          // limpiar variables
    session_destroy();                       // destruir sesión
}

function urlConId(string $ruta): string {
    $uid = usuarioId();
    $sep = str_contains($ruta, '?') ? '&' : '?';
    return BASE_URL . '/' . ltrim($ruta, '/') . "{$sep}uid={$uid}";
}