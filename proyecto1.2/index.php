<?php
session_start();
require 'db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$conn = conectar();

// Agregar la columna aprobada si no existe
$conn->query("ALTER TABLE actividades ADD COLUMN IF NOT EXISTS aprobada TINYINT(1) DEFAULT 0");

// Obtener parámetros de ordenación
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'fecha_inicio';
$sort_order = isset($_GET['sort_order']) && $_GET['sort_order'] === 'ASC' ? 'ASC' : 'DESC';

// Lista de columnas permitidas para evitar SQL injection
$allowed_columns = ['titulo', 'tipo', 'departamento', 'profesor', 'fecha_inicio', 'coste', 'total_alumnos', 'aprobada'];
if (!in_array($sort_by, $allowed_columns)) {
    $sort_by = 'fecha_inicio';
}

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

$actividades = $conn->query($query);

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actividades</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Gestión Actividades</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link">
                        <?= htmlspecialchars($_SESSION['user']) ?>
                        (<?= isset($_SESSION['rol']) && $_SESSION['rol'] === 'ad' ? 'Admin' : 'Usuario' ?>)
                    </span>
                </li>
                <li class="nav-item">
                    <a href="add_activity.php" class="btn btn-success btn-sm mx-2">➕ Nueva</a>
                </li>
                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'ad'): ?>
                <li class="nav-item">
                    <a href="gestion_usuarios.php" class="btn btn-info btn-sm">👥 Usuarios</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="logout.php" class="btn btn-danger btn-sm">🔒 Salir</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-4">
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($_SESSION['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); endif; ?>
    
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">📋 Listado de Actividades</h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <!-- Columna Título -->
                            <th>
                                <a href="?sort_by=titulo&sort_order=<?= $sort_by === 'titulo' && $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white">
                                    Título
                                    <?php if ($sort_by === 'titulo'): ?>
                                        <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <!-- Columna Tipo -->
                            <th>
                                <a href="?sort_by=tipo&sort_order=<?= $sort_by === 'tipo' && $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white">
                                    Tipo
                                </a>
                            </th>
                            <!-- Columna Departamento -->
                            <th>
                                <a href="?sort_by=departamento&sort_order=<?= $sort_by === 'departamento' && $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white">
                                    Departamento
                                </a>
                            </th>
                            <!-- Columna Responsable -->
                            <th>
                                <a href="?sort_by=profesor&sort_order=<?= $sort_by === 'profesor' && $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white">
                                    Responsable
                                </a>
                            </th>
                            <!-- Columna Fecha -->
                            <th>
                                <a href="?sort_by=fecha_inicio&sort_order=<?= $sort_by === 'fecha_inicio' && $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white">
                                    Fecha
                                </a>
                            </th>
                            <!-- Columna Coste -->
                            <th>
                                <a href="?sort_by=coste&sort_order=<?= $sort_by === 'coste' && $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white">
                                    Coste
                                </a>
                            </th>
                            <!-- Columna Alumnos -->
                            <th>
                                <a href="?sort_by=total_alumnos&sort_order=<?= $sort_by === 'total_alumnos' && $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white">
                                    Alumnos
                                </a>
                            </th>
                            <!-- Columna Aprobada -->
                            <th>
                                <a href="?sort_by=aprobada&sort_order=<?= $sort_by === 'aprobada' && $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white">
                                    Aprobada
                                </a>
                            </th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($actividades && $actividades->num_rows > 0): ?>
                            <?php while ($act = $actividades->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($act['titulo']) ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($act['tipo']) ?></span></td>
                                <td><?= htmlspecialchars($act['departamento']) ?></td>
                                <td><?= htmlspecialchars($act['profesor']) ?></td>
                                <td><?= htmlspecialchars($act['fecha']) ?></td>
                                <td><?= number_format($act['coste'], 2) ?>€</td>
                                <td><?= htmlspecialchars($act['total_alumnos']) ?></td>
                                <td>
                                    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'ad'): ?>
                                        <form method="POST" action="approve_activity.php" style="display: inline;">
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
                                    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'ad'): ?>
                                    <div class="btn-group">
                                        <a href="edit_activity.php?id=<?= $act['id'] ?>" class="btn btn-outline-warning btn-sm">✏️ Editar</a>
                                        <a href="delete_activity.php?id=<?= $act['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Eliminar permanentemente esta actividad?')">🗑️ Eliminar</a>
                                    </div>
                                    <?php else: ?>
                                    <a href="view_activity.php?id=<?= $act['id'] ?>" class="btn btn-outline-primary btn-sm">👁️ Ver detalles</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">No hay actividades disponibles</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>