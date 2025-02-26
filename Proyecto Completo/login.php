<?php
session_start();
require 'db.php';

// Verificar si el usuario ya está autenticado
if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario']; // Ahora representa el nombre de usuario
    $password = $_POST['password'];

    $conn = conectar();

    try {
        // Consulta preparada para evitar inyecciones SQL
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE nombre_usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verificar la contraseña
            if (password_verify($password, $user['contrasena'])) {
                // Iniciar sesión: almacenar datos en la sesión
                $_SESSION['user'] = $user['nombre_usuario'];
                $_SESSION['rol'] = $user['rol'];
                $_SESSION['user_id'] = $user['id']; // Opcional: Almacenar el ID del usuario

                // Redirigir al dashboard o página principal
                header("Location: index.php");
                exit;
            }
        }

        // Si las credenciales son incorrectas
        $error = "Nombre de usuario o contraseña incorrectos";

    } catch (Exception $e) {
        $error = "Error al iniciar sesión: " . $e->getMessage();
    } finally {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="card mx-auto" style="max-width: 400px;">
        <div class="card-header bg-primary text-white">
            <h4>Acceso al sistema</h4>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label>Nombre de Usuario</label>
                    <input type="text" name="usuario" class="form-control" placeholder="Ingrese su nombre de usuario" required>
                </div>
                <div class="mb-3">
                    <label>Contraseña</label>
                    <input type="password" name="password" class="form-control" placeholder="Ingrese su contraseña" required>
                </div>
                <button class="btn btn-primary w-100">Entrar</button>
            </form>
        </div>
        <div class="d-grid gap-2">
            <a href="registro.php" class="btn btn-link">¿No tienes cuenta? Regístrate</a>
        </div>
    </div>
</div>
</body>
</html>