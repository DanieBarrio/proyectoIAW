<?php
session_start();
require 'db.php';

$conn = conectar();

// Obtener datos necesarios para el formulario
$departamentos = $conn->query("SELECT * FROM departamento");
$tipos = $conn->query("SELECT * FROM tipo");
$horas = $conn->query("SELECT * FROM horas ORDER BY hora");

$error = '';

// Procesar formulario si se envió
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
        $departamento_id = (int)$_POST['departamento_id'];
        $profesor_id = (int)$_POST['profesor_id'];
        $acompanantes = isset($_POST['acompanantes']) ? array_map('intval', $_POST['acompanantes']) : [];

        // Validar relación departamento-profesor responsable
        $stmt_check = $conn->prepare("SELECT id FROM profesores WHERE id = ? AND id_departamento = ?");
        $stmt_check->bind_param("ii", $profesor_id, $departamento_id);
        $stmt_check->execute();

        if (!$stmt_check->get_result()->num_rows) {
            throw new Exception("El profesor responsable no pertenece al departamento seleccionado");
        }

        // Validar fechas y horas
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

        // Validar acompañantes
        if (in_array($profesor_id, $acompanantes)) {
            throw new Exception("El responsable no puede ser acompañante");
        }

        // Insertar actividad principal
        $stmt = $conn->prepare("INSERT INTO actividades (
            titulo, tipo_id, departamento_id, profesor_id,
            fecha_inicio, fecha_fin, hora_inicio_id, hora_fin_id,
            coste, total_alumnos, objetivo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "siiissiidsi",
            $_POST['titulo'],
            $_POST['tipo_id'],
            $departamento_id,
            $profesor_id,
            $fecha_inicio->format('Y-m-d'),
            $fecha_fin->format('Y-m-d'),
            $_POST['hora_inicio'],
            $_POST['hora_fin'],
            $_POST['coste'],
            $_POST['total_alumnos'],
            trim($_POST['objetivo']) // Usar trim() para eliminar espacios en blanco adicionales
        );

        if (!$stmt->execute()) {
            throw new Exception("Error al crear la actividad: " . $stmt->error);
        }

        $actividad_id = $conn->insert_id;

        // Insertar acompañantes
        if (!empty($acompanantes)) {
            $stmt_acomp = $conn->prepare("INSERT INTO acompanante (actividad_id, profesor_id) VALUES (?, ?)");

            foreach ($acompanantes as $profesor_acomp_id) {
                $stmt_acomp->bind_param("ii", $actividad_id, $profesor_acomp_id);
                $stmt_acomp->execute();
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Actividad creada correctamente";
        header("Location: index.php");
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
    <title>➕ Nueva Actividad</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>➕ Nueva Actividad</h2>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="titulo" class="form-label">Título</label>
            <input type="text" id="titulo" name="titulo" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="tipo_id" class="form-label">Tipo</label>
            <select id="tipo_id" name="tipo_id" class="form-select" required>
                <option value="" disabled selected>Seleccionar...</option>
                <?php while ($tipo = $tipos->fetch_assoc()): ?>
                <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nombre']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="departamento_id" class="form-label">Departamento</label>
            <select id="departamento_id" name="departamento_id" class="form-select" required>
                <option value="" disabled selected>Seleccionar...</option>
                <?php while ($departamento = $departamentos->fetch_assoc()): ?>
                <option value="<?= $departamento['id'] ?>"><?= htmlspecialchars($departamento['nombre']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="profesor_id" class="form-label">Responsable</label>
            <select id="profesor_id" name="profesor_id" class="form-select" required>
                <option value="" disabled selected>Cargando...</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="fecha_fin" class="form-label">Fecha Fin</label>
            <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="hora_inicio" class="form-label">Hora Inicio</label>
            <select id="hora_inicio" name="hora_inicio" class="form-select" required>
                <?php while ($hora = $horas->fetch_assoc()): ?>
                <option value="<?= $hora['id'] ?>"><?= htmlspecialchars($hora['hora']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="hora_fin" class="form-label">Hora Fin</label>
            <select id="hora_fin" name="hora_fin" class="form-select" required>
                <?php $horas->data_seek(0); while ($hora = $horas->fetch_assoc()): ?>
                <option value="<?= $hora['id'] ?>"><?= htmlspecialchars($hora['hora']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="coste" class="form-label">Coste (€)</label>
            <input type="number" step="0.01" id="coste" name="coste" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="total_alumnos" class="form-label">Alumnos</label>
            <input type="number" id="total_alumnos" name="total_alumnos" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="objetivo" class="form-label">Objetivo</label>
            <textarea id="objetivo" name="objetivo" class="form-control" rows="3" required></textarea>
        </div>

        <div class="mb-3">
            <label for="acompanantes" class="form-label">Acompañantes</label>
            <select id="acompanantes" name="acompanantes[]" class="form-select" multiple>
                <option value="" disabled selected>Seleccionar...</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">💾 Guardar Actividad</button>
        <a href="index.php" class="btn btn-secondary">🚫 Cancelar</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('departamento_id').addEventListener('change', function () {
    const departamentoId = this.value;
    const profesorSelect = document.getElementById('profesor_id');
    const acompanantesSelect = document.getElementById('acompanantes');

    fetch(`get_profesores.php?departamento_id=${departamentoId}`)
        .then(response => response.json())
        .then(data => {
            profesorSelect.innerHTML = '<option value="" disabled selected>Seleccionar...</option>';
            data.forEach(profesor => {
                const option = document.createElement('option');
                option.value = profesor.id;
                option.textContent = profesor.nombre;
                profesorSelect.appendChild(option);

                const accompOption = option.cloneNode(true);
                acompanantesSelect.appendChild(accompOption);
            });
        })
        .catch(error => console.error('Error:', error));
});
</script>
</body>
</html>