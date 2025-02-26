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

// Obtener ID del usuario desde la URL
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    $_SESSION['error'] = "ID de usuario inválido";
    header("Location: gestion_usuarios.php");
    exit;
}

$user_id = (int)$_GET['id'];

try {
    // Obtener datos del usuario
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();

    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: gestion_usuarios.php");
    exit;
}

// Procesar actualización si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($user_id)) {
    try {
        $conn->begin_transaction();

        // Validar campos requeridos
        $required_fields = ['nombre_usuario', 'email', 'rol'];
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
        $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE (nombre_usuario = ? OR email = ?) AND id != ?");
        $stmt_check->bind_param("ssi", $_POST['nombre_usuario'], $_POST['email'], $user_id);
        $stmt_check->execute();

        if ($stmt_check->get_result()->num_rows) {
            throw new Exception("El nombre de usuario o email ya está en uso");
        }

        // Actualizar usuario
        $stmt_update = $conn->prepare("UPDATE usuarios SET nombre_usuario = ?, email = ?, rol = ? WHERE id = ?");
        $stmt_update->bind_param("sssi", $_POST['nombre_usuario'], $_POST['email'], $_POST['rol'], $user_id);

        if (!$stmt_update->execute()) {
            throw new Exception("Error al actualizar el usuario: " . $stmt_update->error);
        }

        $conn->commit();
        $_SESSION['success'] = "Usuario actualizado correctamente";
        header("Location: gestion_usuarios.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: edit_user.php?id=$user_id");
        exit;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✏️ Editar Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>✏️ Editar Usuario</h2>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="nombre_usuario" class="form-label">Nombre de Usuario</label>
            <input type="text" id="nombre_usuario" name="nombre_usuario" class="form-control" value="<?= htmlspecialchars($usuario['nombre_usuario']) ?>" required>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($usuario['email']) ?>" required>
        </div>

        <div class="mb-3">
            <label for="rol" class="form-label">Rol</label>
            <select id="rol" name="rol" class="form-select" required>
                <option value="us" <?= $usuario['rol'] == 'us' ? 'selected' : '' ?>>Usuario</option>
                <option value="ad" <?= $usuario['rol'] == 'ad' ? 'selected' : '' ?>>Administrador</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">💾 Guardar Cambios</button>
        <a href="gestion_usuarios.php" class="btn btn-secondary">🚫 Cancelar</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>