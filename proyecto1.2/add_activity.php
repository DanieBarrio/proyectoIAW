<?php
session_start();
require 'db.php';

// Redirección si el usuario no está logueado
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$conn = conectar();
$error = '';
$success = '';

// Obtener datos para los select
$departamentos = $conn->query("SELECT * FROM departamento ORDER BY nombre");
$tipos = $conn->query("SELECT * FROM tipo ORDER BY nombre");
$horas = $conn->query("SELECT * FROM horas ORDER BY hora");
$profesores = $conn->query("SELECT * FROM profesores ORDER BY nombre"); 

// Procesar formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Iniciamos transacción para asegurar integridad
        $conn->begin_transaction();

        // Validar campos requeridos
        $required = [
            'titulo', 'tipo_id', 'departamento_id', 'profesor_id',
            'fecha_inicio', 'fecha_fin', 'hora_inicio_id', 'hora_fin_id',
            'coste', 'total_alumnos', 'objetivo'
        ];
        
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Todos los campos son obligatorios");
            }
        }

        // Validar datos numéricos
        $departamento_id = (int)$_POST['departamento_id'];
        $tipo_id = (int)$_POST['tipo_id'];
        $profesor_id = (int)$_POST['profesor_id'];
        $hora_inicio_id = (int)$_POST['hora_inicio_id'];
        $hora_fin_id = (int)$_POST['hora_fin_id'];
        $total_alumnos = (int)$_POST['total_alumnos'];
        $coste = (float)$_POST['coste'];
        
        if ($total_alumnos <= 0) {
            throw new Exception("El número de alumnos debe ser mayor que 0");
        }
        
        if ($coste < 0) {
            throw new Exception("El coste no puede ser negativo");
        }

        // Validar que el profesor pertenezca al departamento seleccionado
        $stmt_check = $conn->prepare("SELECT id FROM profesores WHERE id = ? AND id_departamento = ?");
        $stmt_check->bind_param("ii", $profesor_id, $departamento_id);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows == 0) {
            throw new Exception("El profesor responsable debe pertenecer al departamento seleccionado");
        }

        // Validar fechas
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = $_POST['fecha_fin'];
        
        if (strtotime($fecha_fin) < strtotime($fecha_inicio)) {
            throw new Exception("La fecha fin no puede ser anterior a la fecha inicio");
        }
        
        // Si es el mismo día, validar horas
        if ($fecha_inicio == $fecha_fin && $hora_fin_id <= $hora_inicio_id) {
            throw new Exception("La hora fin debe ser posterior a la hora inicio en el mismo día");
        }

        // Insertar la actividad
        $stmt = $conn->prepare("INSERT INTO actividades (
            titulo, fecha_inicio, fecha_fin, coste, total_alumnos, 
            objetivo, hora_inicio_id, hora_fin_id, profesor_id, 
            tipo_id, departamento_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param(
            "sssdisiiii", 
            $_POST['titulo'],
            $fecha_inicio,
            $fecha_fin,
            $coste,
            $total_alumnos,
            $_POST['objetivo'],
            $hora_inicio_id,
            $hora_fin_id,
            $profesor_id,
            $tipo_id,
            $departamento_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al crear la actividad: " . $stmt->error);
        }

        $actividad_id = $conn->insert_id;

        // Insertar profesores acompañantes
        if (!empty($_POST['acompanantes'])) {
            $stmt_acomp = $conn->prepare("INSERT INTO acompañante (actividad_id, profesor_id) VALUES (?, ?)");
            
            foreach ($_POST['acompanantes'] as $acomp_id) {
                $stmt_acomp->bind_param("ii", $actividad_id, $acomp_id);
                if (!$stmt_acomp->execute()) {
                    throw new Exception("Error al añadir acompañante: " . $stmt_acomp->error);
                }
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
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Actividad - Gestión de Actividades</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        .required-field::after {
            content: " *";
            color: red;
        }
        .card-header {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active">Nueva Actividad</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-plus-circle"></i> Nueva Actividad</h1>
            <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="activityForm">
            <!-- Información básica -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-info-circle"></i> Información Básica
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="titulo" class="form-label required-field">Título de la actividad</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" required>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="tipo_id" class="form-label required-field">Tipo de actividad</label>
                            <select class="form-select" id="tipo_id" name="tipo_id" required>
                                <option value="">Seleccionar...</option>
                                <?php while($tipo = $tipos->fetch_assoc()): ?>
                                    <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars(ucfirst($tipo['nombre'])) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="departamento_id" class="form-label required-field">Departamento</label>
                            <select class="form-select" id="departamento_id" name="departamento_id" required onchange="cargarProfesores(this.value)">
                                <option value="">Seleccionar...</option>
                                <?php while($dep = $departamentos->fetch_assoc()): ?>
                                    <option value="<?= $dep['id'] ?>"><?= htmlspecialchars(ucfirst($dep['nombre'])) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="profesor_id" class="form-label required-field">Profesor responsable</label>
                            <select class="form-select" id="profesor_id" name="profesor_id" required disabled>
                                <option value="">Seleccione departamento primero</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fechas y horas -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-calendar-event"></i> Fechas y Horarios
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_inicio" class="form-label required-field">Fecha de inicio</label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="fecha_fin" class="form-label required-field">Fecha de finalización</label>
                            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="hora_inicio_id" class="form-label required-field">Hora de inicio</label>
                            <select class="form-select" id="hora_inicio_id" name="hora_inicio_id" required>
                                <option value="">Seleccionar...</option>
                                <?php $horas->data_seek(0); ?>
                                <?php while($hora = $horas->fetch_assoc()): ?>
                                    <option value="<?= $hora['id'] ?>"><?= date('H:i', strtotime($hora['hora'])) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="hora_fin_id" class="form-label required-field">Hora de finalización</label>
                            <select class="form-select" id="hora_fin_id" name="hora_fin_id" required>
                                <option value="">Seleccionar...</option>
                                <?php $horas->data_seek(0); ?>
                                <?php while($hora = $horas->fetch_assoc()): ?>
                                    <option value="<?= $hora['id'] ?>"><?= date('H:i', strtotime($hora['hora'])) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detalles adicionales -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-card-list"></i> Detalles Adicionales
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="total_alumnos" class="form-label required-field">Número de alumnos</label>
                            <input type="number" class="form-control" id="total_alumnos" name="total_alumnos" min="1" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="coste" class="form-label required-field">Coste (€)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" class="form-control" id="coste" name="coste" required>
                                <span class="input-group-text">€</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="objetivo" class="form-label required-field">Objetivos de la actividad</label>
                        <textarea class="form-control" id="objetivo" name="objetivo" rows="4" required></textarea>
                    </div>
                </div>
            </div>

            <!-- Profesores acompañantes -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-people-fill"></i> Profesores Acompañantes
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Seleccione los profesores que acompañarán en esta actividad:</p>
                    
                    <div class="row" id="acompanantesList">
                        <?php $profesores->data_seek(0); ?>
                        <?php while($prof = $profesores->fetch_assoc()): ?>
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="acompanantes[]" id="acomp<?= $prof['id'] ?>" value="<?= $prof['id'] ?>">
                                    <label class="form-check-label" for="acomp<?= $prof['id'] ?>">
                                        <?= htmlspecialchars($prof['nombre']) ?>
                                        <small class="text-muted">
                                            (<?= obtenerNombreDepartamento($conn, $prof['id_departamento']) ?>)
                                        </small>
                                    </label>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="index.php" class="btn btn-secondary me-md-2">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-save"></i> Guardar Actividad
                </button>
            </div>
        </form>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function cargarProfesores(departamentoId) {
        const selectProfesor = document.getElementById('profesor_id');
        
        // Desactivar select si no hay departamento seleccionado
        if (!departamentoId) {
            selectProfesor.innerHTML = '<option value="">Seleccione departamento primero</option>';
            selectProfesor.disabled = true;
            return;
        }
        
        // Mostrar loading
        selectProfesor.innerHTML = '<option value="">Cargando profesores...</option>';
        
        // Simular carga de profesores (como no tenemos get_profesores.php)
        // Normalmente aquí haríamos un fetch a get_profesores.php
        setTimeout(() => {
            // Esta función simula la carga de profesores del departamento
            // En una implementación real, usaríamos un endpoint AJAX
            const profesoresFiltrados = [
                <?php 
                $profesores->data_seek(0);
                while($prof = $profesores->fetch_assoc()): 
                ?>
                {
                    id: <?= $prof['id'] ?>,
                    nombre: '<?= addslashes($prof['nombre']) ?>',
                    departamento: <?= $prof['id_departamento'] ?? 'null' ?>
                },
                <?php endwhile; ?>
            ].filter(prof => prof.departamento == departamentoId);
            
            // Actualizar el select
            selectProfesor.innerHTML = '<option value="">Seleccione profesor</option>';
            profesoresFiltrados.forEach(prof => {
                const option = document.createElement('option');
                option.value = prof.id;
                option.textContent = prof.nombre;
                selectProfesor.appendChild(option);
            });
            
            // Habilitar el select
            selectProfesor.disabled = profesoresFiltrados.length === 0;
            
            // Si no hay profesores, mostrar mensaje
            if (profesoresFiltrados.length === 0) {
                selectProfesor.innerHTML = '<option value="">No hay profesores en este departamento</option>';
            }
        }, 500);
    }

    document.getElementById('activityForm').addEventListener('submit', function(e) {
        // Validar fecha y hora
        const fechaInicio = document.getElementById('fecha_inicio').value;
        const fechaFin = document.getElementById('fecha_fin').value;
        const horaInicio = document.getElementById('hora_inicio_id').value;
        const horaFin = document.getElementById('hora_fin_id').value;

        if (new Date(fechaFin) < new Date(fechaInicio)) {
            e.preventDefault();
            alert('La fecha de finalización debe ser igual o posterior a la fecha de inicio');
            return false;
        }

        if (fechaInicio === fechaFin && parseInt(horaFin) <= parseInt(horaInicio)) {
            e.preventDefault();
            alert('La hora de finalización debe ser posterior a la hora de inicio en el mismo día');
            return false;
        }

        return true;
    });
    </script>

    <?php
    // Función auxiliar para obtener el nombre del departamento
    function obtenerNombreDepartamento($conn, $departamentoId) {
        if (!$departamentoId) return 'Sin departamento';
        
        $stmt = $conn->prepare("SELECT nombre FROM departamento WHERE id = ?");
        $stmt->bind_param("i", $departamentoId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['nombre'];
        }
        
        return 'Desconocido';
    }
    ?>
</body>
</html>