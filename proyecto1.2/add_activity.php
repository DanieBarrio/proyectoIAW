<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$conn = conectar();
$error = '';

// Obtener datos para selects
$departamentos = $conn->query("SELECT * FROM departamento");
$tipos = $conn->query("SELECT * FROM tipo");
$horas = $conn->query("SELECT * FROM horas ORDER BY hora");
$profesores = $conn->query("SELECT * FROM profesores"); // Todos los profesores

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->begin_transaction();

        // Validar campos requeridos
        $required = [
            'titulo', 'tipo_id', 'departamento_id', 'profesor_id',
            'fecha_inicio', 'fecha_fin', 'hora_inicio', 'hora_fin',
            'coste', 'total_alumnos', 'objetivo'
        ];
        
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Todos los campos son obligatorios");
            }
        }

        // Convertir a tipos correctos
        $departamento_id = (int)$_POST['departamento_id'];
        $profesor_id = (int)$_POST['profesor_id'];
        $acompanantes = $_POST['acompanantes'] ?? [];

        // Validar relación departamento-profesor responsable
        $stmt_check = $conn->prepare("SELECT id FROM profesores WHERE id = ? AND id_departamento = ?");
        $stmt_check->bind_param("ii", $profesor_id, $departamento_id);
        $stmt_check->execute();
        
        if (!$stmt_check->get_result()->num_rows) {
            throw new Exception("El profesor responsable no pertenece al departamento seleccionado");
        }

        // Validar fechas y horas
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = $_POST['fecha_fin'];
        $hora_inicio = $_POST['hora_inicio'];
        $hora_fin = $_POST['hora_fin'];

        if ($fecha_fin < $fecha_inicio) {
            throw new Exception("La fecha final no puede ser anterior a la inicial");
        }

        if ($fecha_fin == $fecha_inicio) {
            $hora_inicio_min = date('Hi', strtotime($hora_inicio));
            $hora_fin_min = date('Hi', strtotime($hora_fin));
            
            if ($hora_fin_min <= $hora_inicio_min) {
                throw new Exception("La hora final debe ser posterior a la inicial");
            }
        }

        // Insertar actividad
        $stmt = $conn->prepare("INSERT INTO actividades (
            titulo, tipo_id, departamento_id, profesor_id,
            fecha_inicio, fecha_fin, hora_inicio_id, hora_fin_id,
            coste, total_alumnos, objetivo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("siiissiidsi", 
            $_POST['titulo'],
            $_POST['tipo_id'],
            $departamento_id,
            $profesor_id,
            $fecha_inicio,
            $fecha_fin,
            $_POST['hora_inicio'],
            $_POST['hora_fin'],
            $_POST['coste'],
            $_POST['total_alumnos'],
            $_POST['objetivo']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al crear la actividad: " . $stmt->error);
        }

        $actividad_id = $conn->insert_id;

        // Insertar acompañantes (todos los profesores disponibles)
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
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Nueva Actividad</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
    function cargarProfesores(depId) {
        if (!depId) {
            document.getElementById('profesor').innerHTML = '<option value="">Seleccione departamento primero</option>';
            return;
        }

        fetch(`get_profesores.php?departamento_id=${depId}`)
            .then(response => response.json())
            .then(profesores => {
                const selectProfesor = document.getElementById('profesor');
                selectProfesor.innerHTML = '<option value="">Seleccione responsable</option>';
                profesores.forEach(prof => {
                    selectProfesor.innerHTML += `<option value="${prof.id}">${prof.nombre}</option>`;
                });
                selectProfesor.disabled = false;
            });
    }

    function validarFechaHora() {
        const fechaInicio = document.getElementById('fecha_inicio').value;
        const fechaFin = document.getElementById('fecha_fin').value;
        const horaInicio = document.getElementById('hora_inicio').value;
        const horaFin = document.getElementById('hora_fin').value;

        if (new Date(fechaFin) < new Date(fechaInicio)) {
            alert('La fecha final debe ser posterior a la inicial');
            return false;
        }

        if (fechaFin === fechaInicio) {
            const [hIni, mIni] = horaInicio.split(':').map(Number);
            const [hFin, mFin] = horaFin.split(':').map(Number);
            
            if ((hFin * 60 + mFin) <= (hIni * 60 + mIni)) {
                alert('La hora final debe ser posterior');
                return false;
            }
        }

        return true;
    }
    </script>
</head>
<body>
<div class="container mt-4">
    <h2 class="mb-4">➕ Nueva Actividad</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" onsubmit="return validarFechaHora()">
        <!-- Sección Principal -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">Información Básica</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">🏷️ Título</label>
                    <input type="text" name="titulo" class="form-control" required>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">📌 Tipo</label>
                        <select name="tipo_id" class="form-select" required>
                            <?php while($tipo = $tipos->fetch_assoc()): ?>
                                <option value="<?= $tipo['id'] ?>"><?= $tipo['nombre'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">🏛️ Departamento</label>
                        <select name="departamento_id" id="departamento" class="form-select" required 
                                onchange="cargarProfesores(this.value)">
                            <option value="">Seleccionar...</option>
                            <?php while($dep = $departamentos->fetch_assoc()): ?>
                                <option value="<?= $dep['id'] ?>"><?= $dep['nombre'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">👤 Responsable</label>
                        <select name="profesor_id" id="profesor" class="form-select" required disabled>
                            <option value="">Seleccione departamento primero</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección Fechas y Horas -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">⏰ Tiempo</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">📅 Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">📅 Fecha Fin</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">⏰ Hora Inicio</label>
                        <select name="hora_inicio" id="hora_inicio" class="form-select" required>
                            <?php while($hora = $horas->fetch_assoc()): ?>
                                <option value="<?= date('H:i', strtotime($hora['hora'])) ?>">
                                    <?= date('H:i', strtotime($hora['hora'])) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">⏰ Hora Fin</label>
                        <select name="hora_fin" id="hora_fin" class="form-select" required>
                            <?php $horas->data_seek(0); ?>
                            <?php while($hora = $horas->fetch_assoc()): ?>
                                <option value="<?= date('H:i', strtotime($hora['hora'])) ?>">
                                    <?= date('H:i', strtotime($hora['hora'])) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección Detalles -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">💼 Detalles</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">💰 Coste (€)</label>
                        <input type="number" step="0.01" name="coste" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">👥 Alumnos</label>
                        <input type="number" name="total_alumnos" class="form-control" min="1" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">🎯 Objetivo</label>
                    <textarea name="objetivo" class="form-control" rows="4" required></textarea>
                </div>
            </div>
        </div>

        <!-- Sección Acompañantes (Todos los profesores) -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">👥 Acompañantes</div>
            <div class="card-body">
                <div class="row">
                    <?php $profesores->data_seek(0); ?>
                    <?php while ($prof = $profesores->fetch_assoc()): ?>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       name="acompanantes[]" 
                                       value="<?= $prof['id'] ?>">
                                <label class="form-check-label">
                                    <?= $prof['nombre'] ?>
                                </label>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-success btn-lg">💾 Guardar Actividad</button>
            <a href="index.php" class="btn btn-secondary">🚫 Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>