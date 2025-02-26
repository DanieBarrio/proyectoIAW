<?php
// approve_activity.php - Add this new file to handle activity approval

session_start();
require 'db.php';

// Verificar permisos de administrador
if (!isset($_SESSION['user']) || $_SESSION['rol'] !== 'ad') {
    $_SESSION['error'] = "No tienes permisos para realizar esta acción";
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actividad_id']) && isset($_POST['aprobada'])) {
    $actividad_id = (int)$_POST['actividad_id'];
    $aprobada = (int)$_POST['aprobada'];
    
    $conn = conectar();
    
    try {
        // Actualizar estado de aprobación
        $stmt = $conn->prepare("UPDATE actividades SET aprobada = ? WHERE id = ?");
        $stmt->bind_param("ii", $aprobada, $actividad_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Estado de actividad actualizado correctamente";
        } else {
            throw new Exception("Error al actualizar: " . $conn->error);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    $conn->close();
    header("Location: index.php");
    exit;
}

// Si no hay datos POST, redirigir
header("Location: index.php");
exit;