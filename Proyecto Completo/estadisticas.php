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

// Obtener estadísticas por departamento
$estadisticas_departamento = [];
try {
    $stmt = $conn->query("SELECT d.nombre AS departamento, COUNT(a.id) AS total, SUM(a.aprobada) AS aprobadas 
                         FROM actividades a 
                         JOIN departamento d ON a.departamento_id = d.id 
                         GROUP BY d.id");
    while ($row = $stmt->fetch_assoc()) {
        $estadisticas_departamento[] = [
            'departamento' => htmlspecialchars($row['departamento']),
            'total' => (int)$row['total'],
            'aprobadas' => (int)$row['aprobadas'],
            'no_aprobadas' => (int)$row['total'] - (int)$row['aprobadas']
        ];
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error al cargar estadísticas por departamento: " . $e->getMessage();
}

// Obtener estadísticas por trimestre (sin TRIMESTAMP)
$estadisticas_trimestre = [];
try {
    $stmt = $conn->query("SELECT 
        CASE 
            WHEN MONTH(fecha_inicio) IN (1, 2, 3) THEN 'Trimestre 1'
            WHEN MONTH(fecha_inicio) IN (4, 5, 6) THEN 'Trimestre 2'
            WHEN MONTH(fecha_inicio) IN (7, 8, 9) THEN 'Trimestre 3'
            WHEN MONTH(fecha_inicio) IN (10, 11, 12) THEN 'Trimestre 4'
        END AS trimestre,
        COUNT(*) AS total
    FROM actividades
    GROUP BY trimestre");
    
    while ($row = $stmt->fetch_assoc()) {
        $estadisticas_trimestre[$row['trimestre']] = (int)$row['total'];
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error al cargar estadísticas por trimestre: " . $e->getMessage();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Estadísticas de Actividades</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>📊 Estadísticas de Actividades</h2>

    <!-- Mostrar mensajes de éxito o error -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); endif; ?>

    <!-- Estadísticas por Departamento -->
    <h3>Número de Actividades por Departamento</h3>
    <table class="table table-hover">
        <thead class="table-dark">
            <tr>
                <th>Departamento</th>
                <th>Total</th>
                <th>Aprobadas</th>
                <th>No Aprobadas</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($estadisticas_departamento)): ?>
            <tr>
                <td colspan="4" class="text-center">No hay datos disponibles</td>
            </tr>
            <?php else: ?>
            <?php foreach ($estadisticas_departamento as $data): ?>
            <tr>
                <td><?= $data['departamento'] ?></td>
                <td><?= $data['total'] ?></td>
                <td><?= $data['aprobadas'] ?></td>
                <td><?= $data['no_aprobadas'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Estadísticas por Trimestre -->
    <h3>Número de Actividades por Trimestre</h3>
    <table class="table table-hover">
        <thead class="table-dark">
            <tr>
                <th>Trimestre</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($estadisticas_trimestre)): ?>
            <tr>
                <td colspan="2" class="text-center">No hay datos disponibles</td>
            </tr>
            <?php else: ?>
            <?php foreach ($estadisticas_trimestre as $trimestre => $total): ?>
            <tr>
                <td><?= htmlspecialchars($trimestre) ?></td>
                <td><?= $total ?></td>
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