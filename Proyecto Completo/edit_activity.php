<?php
session_start();
require 'db.php';

// Verificar si el usuario tiene permiso para editar actividades
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
$conn = conectar();
// Obtener ID de la actividad desde la URL
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    $_SESSION['error'] = "ID de actividad inválido";
    header("Location: index.php");
    exit;
}

$actividad_id = (int)$_GET['id'];

try {
    // Obtener datos de la actividad
    $stmt = $conn->prepare("SELECT * FROM actividades WHERE id = ?");
    $stmt->bind_param("i", $actividad_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $actividad = $result->fetch_assoc();

    if (!$actividad) {
        throw new Exception("Actividad no encontrada");
    }

    // Obtener acompañantes actuales
    $stmt_acomp = $conn->prepare("SELECT profesor_id FROM acompanante WHERE actividad_id = ?");
    $stmt_acomp->bind_param("i", $actividad_id);
    $stmt_acomp->execute();
    $result_acomp = $stmt_acomp->get_result();
    $acompanantes_actuales = [];
    while ($row = $result_acomp->fetch_assoc()) {
        $acompanantes_actuales[] = $row['profesor_id'];
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: index.php");
    exit;
}

// Procesar actualización si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($actividad_id)) {
    try {
        $conn->begin_transaction();

        // Validar campos requeridos
        $required_fields = [
            'titulo' => 'Título',
            'tipo_id' => 'Tipo',
            'departamento_id' => 'Departamento',
            'profesor_id' => 'Responsable',
            'fecha_inicio' => 'Fecha inicio',
            'fecha_fin' => 'Fecha fin',
            'hora_inicio' => 'Hora inicio',
            'hora_fin' => 'Hora fin',
            'coste' => 'Coste',
            'total_alumnos' => 'Alumnos',
            'objetivo' => 'Objetivo'
        ];

        foreach ($required_fields as $field => $name) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo '$name' es obligatorio");
            }
        }

        // Convertir valores a tipos correctos
        if (!is_numeric($_POST['coste']) || $_POST['coste'] < 0) {
            throw new Exception("El coste debe ser un número positivo");
        }
        if (!ctype_digit($_POST['total_alumnos']) || $_POST['total_alumnos'] < 1) {
            throw new Exception("Número de alumnos inválido");
        }

        // Validación de fechas y horas
        $fecha_inicio = new DateTime($_POST['fecha_inicio']);
        $fecha_fin = new DateTime($_POST['fecha_fin']);
        $hora_inicio = $_POST['hora_inicio'];
        $hora_fin = $_POST['hora_fin'];

        if ($fecha_fin < $fecha_inicio) {
            throw new Exception("La fecha final no puede ser anterior a la inicial");
        }
        if ($fecha_inicio == $fecha_fin && strtotime($hora_fin) < strtotime($hora_inicio)) {
            throw new Exception("La hora final no puede ser anterior a la hora inicial en el mismo día");
        }

        $profesor_responsable = (int)$_POST['profesor_id'];
        $departamento_id = (int)$_POST['departamento_id'];
        $stmt_check_profesor = $conn->prepare("SELECT id FROM profesores WHERE id = ? AND id_departamento = ?");
        $stmt_check_profesor->bind_param("ii", $profesor_responsable, $departamento_id);
        $stmt_check_profesor->execute();

        if (!$stmt_check_profesor->get_result()->num_rows) {
            throw new Exception("El profesor responsable no pertenece al departamento seleccionado");
        }
        // Actualizar actividad
        $stmt_update = $conn->prepare("UPDATE actividades SET
            titulo = ?,
            tipo_id = ?,
            departamento_id = ?,
            profesor_id = ?,
            fecha_inicio = ?,
            fecha_fin = ?,
            hora_inicio_id = ?,
            hora_fin_id = ?,
            coste = ?,
            total_alumnos = ?,
            objetivo = ?
        WHERE id = ?");

        $stmt_update->bind_param(
            "siiissiidsii",
            $_POST['titulo'],
            $_POST['tipo_id'],
            $departamento_id,
            $profesor_responsable,
            $fecha_inicio->format('Y-m-d'),
            $fecha_fin->format('Y-m-d'),
            $_POST['hora_inicio'],
            $_POST['hora_fin'],
            $_POST['coste'],
            $_POST['total_alumnos'],
            trim($_POST['objetivo']), // Usar trim() para eliminar espacios en blanco adicionales
            $actividad_id
        );

        if (!$stmt_update->execute()) {
            throw new Exception("Error al actualizar la actividad: " . $stmt_update->error);
        }

        // Actualizar acompañantes
        $conn->query("DELETE FROM acompanante WHERE actividad_id = $actividad_id");
        $acompanantes_nuevos = isset($_POST['acompanantes']) ? array_map('intval', $_POST['acompanantes']) : [];

        if (!empty($acompanantes_nuevos)) {
            $stmt_insert_acomp = $conn->prepare("INSERT INTO acompanante (actividad_id, profesor_id) VALUES (?, ?)");

            foreach ($acompanantes_nuevos as $profesor_id) {
                if ($profesor_id === $profesor_responsable) {
                    continue; // El responsable no puede ser acompañante
                }
                $stmt_insert_acomp->bind_param("ii", $actividad_id, $profesor_id);
                $stmt_insert_acomp->execute();
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Actividad actualizada correctamente";
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: edit_activity.php?id=$actividad_id");
        exit;
    }
}

// Obtener datos para el formulario
$departamentos = $conn->query("SELECT * FROM departamento");
$tipos = $conn->query("SELECT * FROM tipo");
$horas = $conn->query("SELECT * FROM horas ORDER BY hora");

function getProfesoresByDepartamento($conn, $departamento_id) {
    $stmt = $conn->prepare("SELECT id, nombre FROM profesores WHERE id_departamento = ?");
    $stmt->bind_param("i", $departamento_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✏️ Editar Actividad</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>✏️ Editar Actividad</h2>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="titulo" class="form-label">Título</label>
            <input type="text" id="titulo" name="titulo" class="form-control" value="<?= htmlspecialchars($actividad['titulo']) ?>" required>
        </div>

        <div class="mb-3">
            <label for="tipo_id" class="form-label">Tipo</label>
            <select id="tipo_id" name="tipo_id" class="form-select" required>
                <?php while ($tipo = $tipos->fetch_assoc()): ?>
                <option value="<?= $tipo['id'] ?>" <?= $tipo['id'] == $actividad['tipo_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($tipo['nombre']) ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="departamento_id" class="form-label">Departamento</label>
            <select id="departamento_id" name="departamento_id" class="form-select" required>
                <?php while ($departamento = $departamentos->fetch_assoc()): ?>
                <option value="<?= $departamento['id'] ?>" <?= $departamento['id'] == $actividad['departamento_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($departamento['nombre']) ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="profesor_id" class="form-label">Responsable</label>
            <select id="profesor_id" name="profesor_id" class="form-select" required>
                <?php $profesores = getProfesoresByDepartamento($conn, $actividad['departamento_id']); ?>
                <?php foreach ($profesores as $profesor): ?>
                <option value="<?= $profesor['id'] ?>" <?= $profesor['id'] == $actividad['profesor_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($profesor['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" value="<?= $actividad['fecha_inicio'] ?>" required>
        </div>

        <div class="mb-3">
            <label for="fecha_fin" class="form-label">Fecha Fin</label>
            <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" value="<?= $actividad['fecha_fin'] ?>" required>
        </div>

        <div class="mb-3">
            <label for="hora_inicio" class="form-label">Hora Inicio</label>
            <select id="hora_inicio" name="hora_inicio" class="form-select" required>
                <?php while ($hora = $horas->fetch_assoc()): ?>
                <option value="<?= $hora['id'] ?>" <?= $hora['id'] == $actividad['hora_inicio_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($hora['hora']) ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="hora_fin" class="form-label">Hora Fin</label>
            <select id="hora_fin" name="hora_fin" class="form-select" required>
                <?php $horas->data_seek(0); while ($hora = $horas->fetch_assoc()): ?>
                <option value="<?= $hora['id'] ?>" <?= $hora['id'] == $actividad['hora_fin_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($hora['hora']) ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="coste" class="form-label">Coste (€)</label>
            <input type="number" step="0.01" id="coste" name="coste" class="form-control" value="<?= $actividad['coste'] ?>" required>
        </div>

        <div class="mb-3">
            <label for="total_alumnos" class="form-label">Alumnos</label>
            <input type="number" id="total_alumnos" name="total_alumnos" class="form-control" value="<?= $actividad['total_alumnos'] ?>" required>
        </div>

        <div class="mb-3">
            <label for="objetivo" class="form-label">Objetivo</label>
            <textarea id="objetivo" name="objetivo" class="form-control" rows="3" required><?= htmlspecialchars($actividad['objetivo']) ?></textarea>
        </div>

        <div class="mb-3">
            <label for="acompanantes" class="form-label">Acompañantes</label>
            <select id="acompanantes" name="acompanantes[]" class="form-select" multiple>
                <?php $profesores = getProfesoresByDepartamento($conn, $actividad['departamento_id']); ?>
                <?php foreach ($profesores as $profesor): ?>
                <option value="<?= $profesor['id'] ?>" <?= in_array($profesor['id'], $acompanantes_actuales) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($profesor['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">💾 Guardar Cambios</button>
        <a href="index.php" class="btn btn-secondary">🚫 Cancelar</a>
    </form>
</div>
<?php $conn->close(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>