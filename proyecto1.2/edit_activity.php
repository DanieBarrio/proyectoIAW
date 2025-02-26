<?php
session_start();
require 'db.php';

// Solo permitir administradores
if (!isset($_SESSION['user']) || $_SESSION['rol'] !== 'ad') {
    header("Location: index.php");
    exit;
}

$conn = conectar();
$error = '';
$success = '';

// Obtener departamentos, tipos y horas disponibles
$departamentos = $conn->query("SELECT * FROM departamento");
$tipos = $conn->query("SELECT * FROM tipo");
$horas = $conn->query("SELECT * FROM horas ORDER BY hora");

// Verificar si se proporcionó un ID válido
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    $_SESSION['error'] = "ID de actividad inválido";
    header("Location: index.php");
    exit;
}

$actividad_id = (int)$_GET['id'];

// Función para obtener profesores por departamento
function getProfesoresByDepartamento($conn, $departamento_id) {
    $stmt = $conn->prepare("SELECT id, nombre FROM profesores WHERE id_departamento = ?");
    $stmt->bind_param("i", $departamento_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Obtener datos de la actividad existente
$stmt = $conn->prepare("SELECT * FROM actividades WHERE id = ?");
$stmt->bind_param("i", $actividad_id);
$stmt->execute();
$actividad = $stmt->get_result()->fetch_assoc();

if (!$actividad) {
    $_SESSION['error'] = "Actividad no encontrada";
    header("Location: index.php");
    exit;
}

// Obtener acompañantes actuales
$stmt = $conn->prepare("SELECT profesor_id FROM acompañante WHERE actividad_id = ?");
$stmt->bind_param("i", $actividad_id);
$stmt->execute();
$result = $stmt->get_result();
$acompanantes_actuales = [];
while ($row = $result->fetch_assoc()) {
    $acompanantes_actuales[] = $row['profesor_id'];
}

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->begin_transaction();

        // Validación de campos requeridos
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
        $missing_fields = [];
        foreach ($required_fields as $field => $name) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $name;
            }
        }
        if (!empty($missing_fields)) {
            throw new Exception("Faltan campos requeridos: " . implode(', ', $missing_fields));
        }

        // Validación de tipos de datos
        if (!is_numeric($_POST['coste']) || $_POST['coste'] < 0) {
            throw new Exception("El coste debe ser un número positivo");
        }
        if (!ctype_digit($_POST['total_alumnos']) || $_POST['total_alumnos'] < 1) {
            throw new Exception("Número de alumnos inválido");
        }

        // Validación de fechas y horas
        $fecha_inicio = new DateTime($_POST['fecha_inicio']);
        $fecha_fin = new DateTime($_POST['fecha_fin']);
        $hora_inicio = new DateTime("@" . strtotime($_POST['fecha_inicio'] . " " . $_POST['hora_inicio']));
        $hora_fin = new DateTime("@" . strtotime($_POST['fecha_fin'] . " " . $_POST['hora_fin']));

        if ($fecha_fin < $fecha_inicio) {
            throw new Exception("La fecha final no puede ser anterior a la inicial");
        }
        if ($fecha_inicio == $fecha_fin && $hora_fin < $hora_inicio) {
            throw new Exception("La hora final no puede ser anterior a la hora inicial en el mismo día");
        }

        // Validación de acompañantes
        $profesor_responsable = (int)$_POST['profesor_id'];
        $acompanantes = isset($_POST['acompanantes']) ? array_map('intval', $_POST['acompanantes']) : [];
        if (in_array($profesor_responsable, $acompanantes)) {
            throw new Exception("El responsable no puede ser acompañante");
        }

        // Actualizar la actividad principal
        $stmt = $conn->prepare("UPDATE actividades SET 
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
        $stmt->bind_param(
            "siiissiidisi",
            $_POST['titulo'],
            $_POST['tipo_id'],
            $_POST['departamento_id'],
            $_POST['profesor_id'],
            $fecha_inicio->format('Y-m-d'),
            $fecha_fin->format('Y-m-d'),
            $_POST['hora_inicio'],
            $_POST['hora_fin'],
            $_POST['coste'],
            $_POST['total_alumnos'],
            $_POST['objetivo'],
            $actividad_id
        );
        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar la actividad: " . $stmt->error);
        }

        // Eliminar acompañantes actuales
        $stmt = $conn->prepare("DELETE FROM acompañante WHERE actividad_id = ?");
        $stmt->bind_param("i", $actividad_id);
        $stmt->execute();

        // Insertar nuevos acompañantes
        if (!empty($acompanantes)) {
            $stmt_acomp = $conn->prepare("INSERT INTO acompañante (actividad_id, profesor_id) VALUES (?, ?)");
            foreach ($acompanantes as $profesor_id) {
                $stmt_acomp->bind_param("ii", $actividad_id, $profesor_id);
                $stmt_acomp->execute();
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Actividad actualizada correctamente";
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Cargar profesores del departamento actual para el formulario
$profesores_departamento = getProfesoresByDepartamento($conn, $actividad['departamento_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Actividad</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function cargarProfesores() {
            const departamentoId = document.getElementById('departamento_id').value;
            const profesorSelect = document.getElementById('profesor_id');
            const acompanantesSelect = document.getElementById('acompanantes');

            fetch(`get_profesores.php?departamento_id=${departamentoId}`)
                .then(response => response.json())
                .then(profesores => {
                    profesorSelect.innerHTML = '<option value="">Seleccionar...</option>';
                    acompanantesSelect.innerHTML = '';

                    profesores.forEach(profesor => {
                        const option = document.createElement('option');
                        option.value = profesor.id;
                        option.textContent = profesor.nombre;
                        profesorSelect.appendChild(option.cloneNode(true));

                        const acompananteOption = option.cloneNode(true);
                        acompanantesSelect.appendChild(acompananteOption);
                    });
                    
                    // Establecer el profesor responsable
                    if (profesorSelect.querySelector(`option[value="${document.getElementById('current_profesor_id').value}"]`)) {
                        profesorSelect.value = document.getElementById('current_profesor_id').value;
                    }
                    
                    // Seleccionar acompañantes actuales
                    const currentAcompanantes = JSON.parse(document.getElementById('current_acompanantes').value);
                    for (const profesorId of currentAcompanantes) {
                        const option = acompanantesSelect.querySelector(`option[value="${profesorId}"]`);
                        if (option) option.selected = true;
                    }
                })
                .catch(error => console.error('Error al cargar profesores:', error));
        }
        
        // Al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            cargarProfesores();
        });
    </script>
</head>
<body>
<div class="container mt-5">
    <h2>✏️ Editar Actividad</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" onsubmit="return validarFormulario()">
        <!-- Campos ocultos para el JS -->
        <input type="hidden" id="current_profesor_id" value="<?= $actividad['profesor_id'] ?>">
        <input type="hidden" id="current_acompanantes" value='<?= json_encode($acompanantes_actuales) ?>'>
        
        <!-- Información Básica -->
        <div class="mb-3">
            <label for="titulo">Título</label>
            <input type="text" id="titulo" name="titulo" class="form-control" required value="<?= htmlspecialchars($actividad['titulo']) ?>">
        </div>
        <div class="mb-3">
            <label for="tipo_id">Tipo</label>
            <select id="tipo_id" name="tipo_id" class="form-select" required>
                <option value="">Seleccionar...</option>
                <?php while ($tipo = $tipos->fetch_assoc()): ?>
                    <option value="<?= $tipo['id'] ?>" <?= $actividad['tipo_id'] == $tipo['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tipo['nombre']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="departamento_id">Departamento</label>
            <select id="departamento_id" name="departamento_id" class="form-select" required onchange="cargarProfesores()">
                <option value="">Seleccionar...</option>
                <?php while ($dep = $departamentos->fetch_assoc()): ?>
                    <option value="<?= $dep['id'] ?>" <?= $actividad['departamento_id'] == $dep['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dep['nombre']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="profesor_id">Responsable</label>
            <select id="profesor_id" name="profesor_id" class="form-select" required>
                <option value="">Cargando...</option>
                <!-- Opciones cargadas dinámicamente -->
            </select>
        </div>

        <!-- Fechas y Horas -->
        <div class="mb-3">
            <label for="fecha_inicio">Fecha Inicio</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" required value="<?= $actividad['fecha_inicio'] ?>">
        </div>
        <div class="mb-3">
            <label for="fecha_fin">Fecha Fin</label>
            <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" required value="<?= $actividad['fecha_fin'] ?>">
        </div>
        <div class="mb-3">
            <label for="hora_inicio">Hora Inicio</label>
            <select id="hora_inicio" name="hora_inicio" class="form-select" required>
                <?php $horas->data_seek(0); while ($hora = $horas->fetch_assoc()): ?>
                    <option value="<?= $hora['id'] ?>" <?= $actividad['hora_inicio_id'] == $hora['id'] ? 'selected' : '' ?>>
                        <?= date('H:i', strtotime($hora['hora'])) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="hora_fin">Hora Fin</label>
            <select id="hora_fin" name="hora_fin" class="form-select" required>
                <?php $horas->data_seek(0); while ($hora = $horas->fetch_assoc()): ?>
                    <option value="<?= $hora['id'] ?>" <?= $actividad['hora_fin_id'] == $hora['id'] ? 'selected' : '' ?>>
                        <?= date('H:i', strtotime($hora['hora'])) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Detalles -->
        <div class="mb-3">
            <label for="coste">Coste (€)</label>
            <input type="number" step="0.01" id="coste" name="coste" class="form-control" required value="<?= $actividad['coste'] ?>">
        </div>
        <div class="mb-3">
            <label for="total_alumnos">Alumnos</label>
            <input type="number" id="total_alumnos" name="total_alumnos" class="form-control" required value="<?= $actividad['total_alumnos'] ?>">
        </div>
        <div class="mb-3">
            <label for="objetivo">Objetivo</label>
            <textarea id="objetivo" name="objetivo" class="form-control" rows="3" required><?= htmlspecialchars($actividad['objetivo']) ?></textarea>
        </div>
        <div class="mb-3">
            <label for="acompanantes">Acompañantes</label>
            <select id="acompanantes" name="acompanantes[]" class="form-select" multiple>
                <!-- Opciones cargadas dinámicamente -->
            </select>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function validarFormulario() {
        const fechaInicio = new Date(document.getElementById('fecha_inicio').value);
        const fechaFin = new Date(document.getElementById('fecha_fin').value);
        const horaInicio = document.getElementById('hora_inicio').value;
        const horaFin = document.getElementById('hora_fin').value;

        if (fechaFin < fechaInicio) {
            alert('La fecha final no puede ser anterior a la inicial.');
            return false;
        }

        if (fechaInicio.toDateString() === fechaFin.toDateString()) {
            const tiempoInicio = parseInt(horaInicio);
            const tiempoFin = parseInt(horaFin);
            if (tiempoFin < tiempoInicio) {
                alert('La hora final no puede ser anterior a la hora inicial en el mismo día.');
                return false;
            }
        }

        const responsable = parseInt(document.getElementById('profesor_id').value);
        const acompanantes = Array.from(document.querySelectorAll('#acompanantes option:checked'))
            .map(option => parseInt(option.value));

        if (acompanantes.includes(responsable)) {
            alert('El responsable no puede ser acompañante.');
            return false;
        }

        return true;
    }
</script>
</body>
</html>