<?php
require 'db.php';

if (!isset($_GET['departamento_id']) || !ctype_digit($_GET['departamento_id'])) {
    http_response_code(400);
    exit;
}

$departamento_id = (int)$_GET['departamento_id'];
$conn = conectar();

$stmt = $conn->prepare("SELECT id, nombre FROM profesores WHERE id_departamento = ?");
$stmt->bind_param("i", $departamento_id);
$stmt->execute();
$result = $stmt->get_result();

$profesores = [];
while ($prof = $result->fetch_assoc()) {
    $profesores[] = [
        'id' => $prof['id'],
        'nombre' => htmlspecialchars($prof['nombre'])
    ];
}

header('Content-Type: application/json');
echo json_encode($profesores);
$conn->close();
?>