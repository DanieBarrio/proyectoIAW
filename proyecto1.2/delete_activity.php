<?php
session_start();
require 'db.php';

// Solo permitir administradores
if (!isset($_SESSION['user']) || $_SESSION['rol'] !== 'ad') {
    header("Location: index.php");
    exit;
}

if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $actividad_id = (int)$_GET['id'];
    
    $conn = conectar();
    try {
        $conn->begin_transaction();
        
        // Eliminar registros relacionados primero
        $stmt = $conn->prepare("DELETE FROM acompanante WHERE actividad_id = ?");
        $stmt->bind_param("i", $actividad_id);
        $stmt->execute();
        
        // Eliminar la actividad principal
        $stmt = $conn->prepare("DELETE FROM actividades WHERE id = ?");
        $stmt->bind_param("i", $actividad_id);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Actividad no encontrada");
        }
        
        $conn->commit();
        $_SESSION['success'] = "Actividad eliminada correctamente";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error al eliminar: " . $e->getMessage();
    } finally {
        $conn->close();
    }
}

header("Location: index.php");
exit;
?>