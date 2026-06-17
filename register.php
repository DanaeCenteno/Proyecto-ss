<?php
require_once __DIR__ . '/administrador/config/db.php';
require_once __DIR__ . '/includes/auth.php';

iniciarSesion();

// Si ya tiene sesión, mándalo a su dashboard
if (estaAutenticado()) {
    redirigirSegunRol();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre    = trim($_POST['nombre']    ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = trim($_POST['password']  ?? '');
    $confirmar = trim($_POST['confirmar'] ?? '');
    $rol       = strtolower(trim($_POST['rol'] ?? ''));

    if (empty($nombre) || empty($email) || empty($password) || empty($rol)) {
        $error = 'Todos los campos son obligatorios.';
    } elseif ($password !== $confirmar) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (!in_array($rol, [ROL_ESTUDIANTE, ROL_PROFESOR])) {
        $error = 'Selecciona un perfil válido.';
    } else {

        // Verificar si el correo ya existe
        $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE correo = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'El correo ya está registrado.';
            $stmt->close();
        } else {
            $stmt->close();

            // Insertar nuevo usuario
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, correo, password, rol) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $nombre, $email, $hash, $rol);

            if ($stmt->execute()) {
                // Auto-login tras registro exitoso
                $nuevoId = $conexion->insert_id;
                registrarSesion([
                    'id'     => $nuevoId,
                    'nombre' => $nombre,
                    'rol'    => $rol,
                ]);
                redirigirSegunRol();
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
    <title>Registro — EduTecnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/sRegister.css">
</head>
<body>

<div class="container">
    <div class="row px-3">
        <div class="col-lg-10 col-xl-9 card flex-row mx-auto px-10">
            <div class="img-left d-none d-md-flex"></div>
            <div class="card-body">

                <h4 class="tittle text-center mt-4">Registro</h4>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="form-box px-3">

                    <div class="form-input">
                        <span><i class="bi bi-person-circle"></i></span>
                        <input type="text" name="nombre" class="form-control"
                               placeholder="Nombre Completo"
                               value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                               required>
                    </div>

                    <div class="form-input">
                        <span><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control"
                               placeholder="nombre.apellido@cua.uam.mx"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               required>
                    </div>

                    <div class="form-input">
                        <span><i class="bi bi-key-fill"></i></span>
                        <input type="password" name="password" class="form-control"
                               placeholder="Contraseña" required>
                    </div>

                    <div class="form-input">
                        <span><i class="bi bi-key-fill"></i></span>
                        <input type="password" name="confirmar" class="form-control"
                               placeholder="Confirmar contraseña" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Perfil</label>
                        <select name="rol" class="form-select" required>
                            <option value="" disabled selected>Selecciona tu perfil</option>
                            <option value="estudiante"   <?= ($_POST['rol'] ?? '') === 'estudiante'   ? 'selected' : '' ?>>Alumno</option>
                            <option value="profesor" <?= ($_POST['rol'] ?? '') === 'profesor' ? 'selected' : '' ?>>Profesor</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        Registrarse
                    </button>

                </form>

                <p class="text-center mt-3" style="font-size:14px;">
                    ¿Ya tienes cuenta? <a href="login.php">Iniciar sesión</a>
                </p>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>