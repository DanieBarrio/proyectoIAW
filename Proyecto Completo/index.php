<?php
session_start();
require 'db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "Debes iniciar sesión para acceder";
    header("Location: login.php");
    exit;
}

$conn = conectar();

// Procesar acción de aprobar/desaprobar actividad
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actividad_id']) && isset($_POST['aprobada'])) {
    try {
        $conn->begin_transaction();

        // Validar ID de actividad
        if (!ctype_digit($_POST['actividad_id'])) {
            throw new Exception("ID de actividad inválido");
        }

        $actividad_id = (int)$_POST['actividad_id'];
        $nuevo_estatus = (int)$_POST['aprobada']; // 1 para aprobar, 0 para desaprobar

        // Actualizar el estado de aprobación
        $stmt_update = $conn->prepare("UPDATE actividades SET aprobada = ? WHERE id = ?");
        $stmt_update->bind_param("ii", $nuevo_estatus, $actividad_id);

        if (!$stmt_update->execute()) {
            throw new Exception("Error al actualizar el estatus de la actividad: " . $stmt_update->error);
        }

        $conn->commit();
        $_SESSION['success'] = "Estatus de la actividad actualizado correctamente";
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: index.php");
        exit;
    }
}

// Obtener parámetros de ordenación
$sort_by = isset($_GET['sort_by']) ? htmlspecialchars($_GET['sort_by']) : 'fecha_inicio';
$sort_order = isset($_GET['sort_order']) && $_GET['sort_order'] === 'ASC' ? 'ASC' : 'DESC';

// Construir consulta SQL con ordenación dinámica
$query = "SELECT 
    a.id,
    a.titulo,
    t.nombre AS tipo,
    d.nombre AS departamento,
    p.nombre AS profesor,
    DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') AS fecha,
    a.coste,
    a.total_alumnos,
    a.aprobada
FROM actividades a
JOIN tipo t ON a.tipo_id = t.id
JOIN departamento d ON a.departamento_id = d.id
JOIN profesores p ON a.profesor_id = p.id
ORDER BY $sort_by $sort_order";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $actividades = [];

    while ($act = $result->fetch_assoc()) {
        $actividades[] = [
            'id' => $act['id'],
            'titulo' => htmlspecialchars($act['titulo']),
            'tipo' => htmlspecialchars($act['tipo']),
            'departamento' => htmlspecialchars($act['departamento']),
            'profesor' => htmlspecialchars($act['profesor']),
            'fecha' => htmlspecialchars($act['fecha']),
            'coste' => number_format($act['coste'], 2) . '€',
            'alumnos' => htmlspecialchars($act['total_alumnos']),
            'aprobada' => (bool)$act['aprobada']
        ];
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error al cargar actividades: " . $e->getMessage();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 Panel de Gestión de Actividades</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <!-- Barra de navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Gestión Actividades</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link text-white">
                            Bienvenido, <?= htmlspecialchars($_SESSION['user']) ?>
                            (<small>Última conexión: <?= date('d/m/Y H:i', $_SESSION['last_login'] ?? time()) ?></small>)
                        </span>
                    </li>
                    <?php if ($_SESSION['rol'] === 'ad'): ?>
                    <li class="nav-item">
                        <a href="gestion_departamentos.php" class="btn btn-info btn-sm mx-2">Departamentos</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="add_activity.php" class="btn btn-success btn-sm mx-2">➕ Nueva Actividad</a>
                    </li>
                    <li class="nav-item">
                        <a href="gestion_usuarios.php" class="btn btn-primary btn-sm mx-2">👥 Gestión de Usuarios</a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="btn btn-danger btn-sm">🔒 Salir</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Mostrar mensajes de éxito o error -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); endif; ?>

    <!-- Listado de actividades -->
    <h3>📋 Listado de Actividades</h3>
    <table class="table table-hover">
        <thead class="table-dark">
            <tr>
                <th><a href="?sort_by=titulo&sort_order=<?= $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>">Título</a></th>
                <th><a href="?sort_by=tipo&sort_order=<?= $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>">Tipo</a></th>
                <th><a href="?sort_by=departamento&sort_order=<?= $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>">Departamento</a></th>
                <th><a href="?sort_by=profesor&sort_order=<?= $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>">Responsable</a></th>
                <th><a href="?sort_by=fecha_inicio&sort_order=<?= $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>">Fecha</a></th>
                <th><a href="?sort_by=coste&sort_order=<?= $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>">Coste</a></th>
                <th><a href="?sort_by=total_alumnos&sort_order=<?= $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>">Alumnos</a></th>
                <th>Estatus</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($actividades)): ?>
            <tr>
                <td colspan="9" class="text-center">No hay actividades registradas</td>
            </tr>
            <?php else: ?>
            <?php foreach ($actividades as $act): ?>
            <tr>
                <td><?= $act['titulo'] ?></td>
                <td><?= $act['tipo'] ?></td>
                <td><?= $act['departamento'] ?></td>
                <td><?= $act['profesor'] ?></td>
                <td><?= $act['fecha'] ?></td>
                <td><?= $act['coste'] ?></td>
                <td><?= $act['alumnos'] ?></td>
                <td>
                    <?php if ($_SESSION['rol'] === 'ad'): ?>
                    <!-- Formulario para aprobar/desaprobar -->
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="actividad_id" value="<?= $act['id'] ?>">
                        <input type="hidden" name="aprobada" value="<?= $act['aprobada'] ? 0 : 1 ?>">
                        <button type="submit" class="btn btn-<?= $act['aprobada'] ? 'danger' : 'success' ?> btn-sm">
                            <?= $act['aprobada'] ? '❌ Desaprobar' : '✅ Aprobar' ?>
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="badge bg-<?= $act['aprobada'] ? 'success' : 'danger' ?>">
                        <?= $act['aprobada'] ? 'Aprobada' : 'No Aprobada' ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($_SESSION['rol'] === 'ad'): ?>
                    <div class="btn-group">
                        <a href="edit_activity.php?id=<?= $act['id'] ?>" class="btn btn-outline-warning btn-sm">✏️ Editar</a>
                        <a href="delete_activity.php?id=<?= $act['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Eliminar permanentemente esta actividad?')">🗑️ Eliminar</a>
                    </div>
                    <?php else: ?>
                    <span class="text-muted">Acción no permitida</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Botones adicionales -->
    <div class="mt-3">
        <a href="estadisticas.php" class="btn btn-primary">📊 Ver Estadísticas</a>
        <a href="index.php" class="btn btn-secondary ms-2">↩️ Recargar Página</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>