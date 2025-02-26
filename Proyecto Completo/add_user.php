<?php
session_start();
require 'db.php';

// Verificar si el usuario tiene permiso de administrador
if (!isset($_SESSION['user']) || $_SESSION['rol'] !== 'ad') {
    $_SESSION['error'] = "No tienes permiso para acceder a esta página";
    header("Location: index.php");
    exit;
}

$conn = conectar();

$error = '';

// Procesar formulario si se envió
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->begin_transaction();

        // Validar campos requeridos
        $required_fields = ['nombre_usuario', 'email', 'password', 'rol'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Todos los campos son obligatorios");
            }
        }

        // Validar formato de email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Formato de email inválido");
        }

        // Validar unicidad del nombre de usuario y email
        $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ? OR email = ?");
        $stmt_check->bind_param("ss", $_POST['nombre_usuario'], $_POST['email']);
        $stmt_check->execute();

        if ($stmt_check->get_result()->num_rows) {
            throw new Exception("El nombre de usuario o email ya está en uso");
        }

        // Hash de la contraseña
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Insertar usuario
        $stmt_insert = $conn->prepare("INSERT INTO usuarios (nombre_usuario, email, contrasena, rol) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("ssss", $_POST['nombre_usuario'], $_POST['email'], $hashed_password, $_POST['rol']);

        if (!$stmt_insert->execute()) {
            throw new Exception("Error al crear el usuario: " . $stmt_insert->error);
        }

        $conn->commit();
        $_SESSION['success'] = "Usuario creado correctamente";
        header("Location: gestion_usuarios.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    } finally {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>➕ Agregar Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>➕ Agregar Usuario</h2>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="nombre_usuario" class="form-label">Nombre de Usuario</label>
            <input type="text" id="nombre_usuario" name="nombre_usuario" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Contraseña</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="rol" class="form-label">Rol</label>
            <select id="rol" name="rol" class="form-select" required>
                <option value="us">Usuario</option>
                <option value="ad">Administrador</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">💾 Guardar Usuario</button>
        <a href="gestion_usuarios.php" class="btn btn-secondary">🚫 Cancelar</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>