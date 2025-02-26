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

// Procesar eliminación de departamento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    try {
        $conn->begin_transaction();

        // Validar ID de departamento
        if (!isset($_POST['departamento_id']) || !ctype_digit($_POST['departamento_id'])) {
            throw new Exception("ID de departamento inválido");
        }

        $departamento_id = (int)$_POST['departamento_id'];

        // Verificar si existen actividades asociadas al departamento
        $stmt_check_activities = $conn->prepare("SELECT COUNT(*) AS total FROM actividades WHERE departamento_id = ?");
        $stmt_check_activities->bind_param("i", $departamento_id);
        $stmt_check_activities->execute();
        $result_check = $stmt_check_activities->get_result();
        $row = $result_check->fetch_assoc();

        if ($row['total'] > 0) {
            throw new Exception("No se puede eliminar este departamento porque tiene actividades asociadas");
        }

        // Eliminar departamento
        $stmt_delete = $conn->prepare("DELETE FROM departamento WHERE id = ?");
        $stmt_delete->bind_param("i", $departamento_id);

        if (!$stmt_delete->execute()) {
            throw new Exception("Error al eliminar el departamento: " . $stmt_delete->error);
        }

        $conn->commit();
        $_SESSION['success'] = "Departamento eliminado correctamente";
        header("Location: gestion_departamentos.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: gestion_departamentos.php");
        exit;
    }
}

// Procesar creación o edición de departamento
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->begin_transaction();

        // Validar campos requeridos
        if (empty($_POST['nombre'])) {
            throw new Exception("El nombre del departamento es obligatorio");
        }

        $nombre = trim($_POST['nombre']);

        // Verificar si el nombre ya existe
        $stmt_check = $conn->prepare("SELECT id FROM departamento WHERE nombre = ?");
        $stmt_check->bind_param("s", $nombre);
        $stmt_check->execute();

        if ($stmt_check->get_result()->num_rows > 0) {
            throw new Exception("El nombre del departamento ya está en uso");
        }

        // Insertar nuevo departamento
        $stmt_insert = $conn->prepare("INSERT INTO departamento (nombre) VALUES (?)");
        $stmt_insert->bind_param("s", $nombre);

        if (!$stmt_insert->execute()) {
            throw new Exception("Error al crear el departamento: " . $stmt_insert->error);
        }

        $conn->commit();
        $_SESSION['success'] = "Departamento creado correctamente";
        header("Location: gestion_departamentos.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: gestion_departamentos.php");
        exit;
    }
}

// Obtener lista de departamentos
$departamentos = [];
try {
    $stmt = $conn->query("SELECT * FROM departamento ORDER BY nombre ASC");
    while ($departamento = $stmt->fetch_assoc()) {
        $departamentos[] = [
            'id' => $departamento['id'],
            'nombre' => htmlspecialchars($departamento['nombre'])
        ];
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error al cargar departamentos: " . $e->getMessage();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏛️ Gestión de Departamentos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
    <body>
<div class="container mt-5">
    <h2>🏛️ Gestión de Departamentos</h2>

    <!-- Mostrar mensajes de éxito o error -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); endif; ?>

    <!-- Formulario para crear un nuevo departamento -->
    <h3>➕ Crear Nuevo Departamento</h3>
    <form method="POST" class="mb-4">
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre del Departamento</label>
            <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Ingrese el nombre del departamento" required>
        </div>
        <button type="submit" class="btn btn-primary">💾 Guardar Departamento</button>
    </form>

    <!-- Lista de departamentos -->
    <h3>📋 Listado de Departamentos</h3>
    <table class="table table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($departamentos)): ?>
            <tr>
                <td colspan="3" class="text-center">No hay departamentos registrados</td>
            </tr>
            <?php else: ?>
            <?php foreach ($departamentos as $dept): ?>
            <tr>
                <td><?= $dept['id'] ?></td>
                <td><?= $dept['nombre'] ?></td>
                <td>
                    <div class="btn-group">
                        <!-- Botón para editar departamento -->
                        <a href="edit_departamento.php?id=<?= $dept['id'] ?>" class="btn btn-outline-warning btn-sm">✏️ Editar</a>

                        <!-- Formulario para eliminar departamento -->
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este departamento?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="departamento_id" value="<?= $dept['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">🗑️ Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Enlace para volver al panel principal -->
    <a href="index.php" class="btn btn-secondary mt-3">↩️ Volver al Panel Principal</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>