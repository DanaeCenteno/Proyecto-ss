<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();

// Si ya tiene sesión, mándalo a su dashboard
if (estaAutenticado()) {
    if (usuarioRol() === 'admin') {
        header('Location: admin/panelAdmin.php'); exit;
    }
    // Si tiene sesión pero no es admin, cerrarla y mostrar login
    cerrarSesion();
    iniciarSesion();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Por favor llena todos los campos.';
    } else {
        $stmt = $conexion->prepare("SELECT id, nombre, password, rol FROM usuarios WHERE correo = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$usuario) {
            $error = 'El correo no está registrado.';
        } elseif (!password_verify($password, $usuario['password'])) {
            $error = 'Contraseña incorrecta.';
        } elseif ($usuario['rol'] !== 'admin') {
            $error = 'Acceso denegado. Esta área es exclusiva para administradores.';
        } else {
            registrarSesion($usuario);
            header('Location: panelAdmin.php'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Administrador — EduTecnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/sRegister.css">
</head>
<body>

<div class="container">
    <div class="row px-3">
        <div class="col-lg-10 col-xl-9 card flex-row mx-auto px-10">
            <div class="img-left d-none d-md-flex"></div>
            <div class="card-body">

                <h4 class="tittle text-center mt-4">Acceso Administrador</h4>
                <div class="alert alert-warning d-flex align-items-center gap-2 mt-2" style="font-size:13px;">
                    <i class="bi bi-shield-lock-fill fs-5"></i>
                    <span>Área restringida. Solo cuentas con rol <strong>administrador</strong> pueden ingresar.</span>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="form-box px-3">
                    <div class="form-input">
                        <span><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control"
                               placeholder="correo@cua.uam.mx"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               required>
                    </div>

                    <div class="form-input">
                        <span><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control"
                               placeholder="Tu contraseña" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        Iniciar Sesión
                    </button>
                </form>

                <p class="text-center mt-3" style="font-size:14px;">
                    <a href="registerAdmin.php">Aun no tienes cuenta? Registrarse</a>
                </p>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>