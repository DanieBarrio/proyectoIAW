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

// Procesar eliminación de usuario si se envió el ID
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    try {
        $conn->begin_transaction();

        // Validar ID de usuario
        if (!isset($_POST['user_id']) || !ctype_digit($_POST['user_id'])) {
            throw new Exception("ID de usuario inválido");
        }

        $user_id = (int)$_POST['user_id'];

        // Evitar que el administrador se elimine a sí mismo
        if ($user_id == $_SESSION['user_id']) {
            throw new Exception("No puedes eliminarte a ti mismo");
        }

        // Verificar si el usuario existe
        $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE id = ?");
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();

        if (!$stmt_check->get_result()->num_rows) {
            throw new Exception("Usuario no encontrado");
        }

        // Eliminar usuario
        $stmt_delete = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt_delete->bind_param("i", $user_id);

        if (!$stmt_delete->execute()) {
            throw new Exception("Error al eliminar el usuario: " . $stmt_delete->error);
        }

        $conn->commit();
        $_SESSION['success'] = "Usuario eliminado correctamente";
        header("Location: gestion_usuarios.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: gestion_usuarios.php");
        exit;
    }
}

// Obtener lista de usuarios
$usuarios = [];
try {
    $stmt = $conn->query("SELECT * FROM usuarios ORDER BY nombre_usuario ASC");
    while ($usuario = $stmt->fetch_assoc()) {
        $usuarios[] = [
            'id' => $usuario['id'],
            'nombre_usuario' => htmlspecialchars($usuario['nombre_usuario']),
            'email' => htmlspecialchars($usuario['email']),
            'rol' => htmlspecialchars($usuario['rol'])
        ];
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error al cargar usuarios: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👥 Gestión de Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>👥 Gestión de Usuarios</h2>

    <!-- Mostrar mensajes de éxito o error -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); endif; ?>

    <!-- Enlace para agregar nuevo usuario -->
    <a href="add_user.php" class="btn btn-primary mb-3">➕ Agregar Usuario</a>

    <!-- Tabla de usuarios -->
    <table class="table table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Nombre de Usuario</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($usuarios)): ?>
            <tr>
                <td colspan="5" class="text-center">No hay usuarios registrados</td>
            </tr>
            <?php else: ?>
            <?php foreach ($usuarios as $usuario): ?>
            <tr>
                <td><?= $usuario['id'] ?></td>
                <td><?= $usuario['nombre_usuario'] ?></td>
                <td><?= $usuario['email'] ?></td>
                <td>
                    <?php if ($usuario['rol'] === 'ad'): ?>
                    <span class="badge bg-info">Administrador</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">Usuario</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="btn-group">
                        <!-- Botón para editar usuario -->
                        <a href="edit_user.php?id=<?= $usuario['id'] ?>" class="btn btn-outline-warning btn-sm">✏️ Editar</a>

                        <!-- Botón para eliminar usuario -->
                        <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este usuario?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $usuario['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">🗑️ Eliminar</button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted">No puedes eliminarte a ti mismo</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>