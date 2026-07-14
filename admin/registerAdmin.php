<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();


define('ADMIN_SECRET_KEY', 'EduTecnia@Admin2025');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $claveIngresada = trim($_POST['clave_admin'] ?? '');
    $email          = trim($_POST['email']        ?? '');
    $password       = trim($_POST['password']     ?? '');
    $confirmar      = trim($_POST['confirmar']    ?? '');

    $nombreRaw = trim($_POST['nombre'] ?? '');
    $nombre    = mb_convert_case($nombreRaw, MB_CASE_TITLE, "UTF-8");

    // Validar clave secreta primero
    if ($claveIngresada !== ADMIN_SECRET_KEY) {
        $error = 'Clave de acceso incorrecta.';
    }
    elseif (empty($nombre) || empty($email) || empty($password)) {
        $error = 'Todos los campos son obligatorios.';
    }
    elseif (mb_strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    }
    elseif ($password !== $confirmar) {
        $error = 'Las contraseñas no coinciden.';
    }
    elseif (!validarCorreoInstitucional($email)) {
        $error = 'El correo debe pertenecer al dominio @cua.uam.mx';
    }
    else {
        $email = strtolower(trim($email));

        $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE correo = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'El correo ya está registrado.';
            $stmt->close();
        } else {
            $stmt->close();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $rol  = 'admin';
            $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, correo, password, rol) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $nombre, $email, $hash, $rol);

            if ($stmt->execute()) {
                $success = '¡Administrador registrado correctamente! Ya puedes iniciar sesión.';
            } else {
                $error = 'Error al registrar. Intenta de nuevo.';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Administrador — EduTecnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/sRegister.css">
</head>

<body>

    <div class="container">
        <div class="row px-3">
            <div class="col-lg-10 col-xl-9 card flex-row mx-auto px-10">
                <div class="img-left d-none d-md-flex"></div>
                <div class="card-body">

                    <h4 class="tittle text-center mt-4">Registro Administrador</h4>

                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                    <p class="text-center mt-2" style="font-size:14px;">
                        <a href="loginAdmin.php"><i class="bi bi-box-arrow-in-right me-1"></i>Ir al login de administrador</a>
                    </p>
                    <?php else: ?>

                    <div class="alert alert-warning d-flex align-items-center gap-2 mt-2" style="font-size:13px;">
                        <i class="bi bi-shield-lock-fill fs-5"></i>
                        <span>Área restringida. Se requiere clave de acceso para registrar un administrador.</span>
                    </div>

                    <form method="POST" action="" class="form-box px-3">

                        <div class="form-input">
                            <span><i class="bi bi-shield-lock"></i></span>
                            <input type="password" name="clave_admin" class="form-control"
                                placeholder="Clave de acceso admin" required
                                value="">
                        </div>

                        <div class="form-input">
                            <span><i class="bi bi-person-circle"></i></span>
                            <input type="text" name="nombre" class="form-control" placeholder="Nombre Completo"
                                value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                        </div>

                        <div class="form-input">
                            <span><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control" required
                                placeholder="nombre.apellido@cua.uam.mx"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                pattern="^[a-zA-Z0-9._%+-]+@cua\.uam\.mx$"
                                title="Debes usar un correo con dominio @cua.uam.mx">
                        </div>

                        <div class="form-input">
                            <span><i class="bi bi-key-fill"></i></span>
                            <input type="password" name="password" class="form-control"
                                placeholder="Contraseña" minlength="8" required>
                        </div>

                        <div class="form-input">
                            <span><i class="bi bi-key-fill"></i></span>
                            <input type="password" name="confirmar" class="form-control"
                                placeholder="Confirmar contraseña" minlength="8" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Registrar Administrador
                        </button>

                    </form>

                    <p class="text-center mt-3" style="font-size:14px;">
                        <a href="loginAdmin.php">Ya tines una cuenta? Iniciar Sesión</a>
                    </p>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>