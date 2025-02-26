<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];
    
    $conn = conectar();
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE nombre_usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['contrasena'])) {
        $_SESSION['user'] = $usuario;
        $_SESSION['rol'] = $user['rol']; // Almacenar el rol en la sesión
        $_SESSION['user_id'] = $user['id']; // Opcional: Almacenar el ID del usuario
        header("Location: index.php");
        exit;
    }
}
    
    $error = "Credenciales incorrectas";
    $conn->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="card mx-auto" style="max-width: 400px;">
        <div class="card-header bg-primary text-white">
            <h4>Acceso al sistema</h4>
        </div>
        <div class="card-body">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label>Usuario</label>
                    <input type="text" name="usuario" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Contraseña</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100">Entrar</button>
            </form>
        </div>
        <div class="d-grid gap-2">
                    <a href="registro.php" class="btn btn-link">¿No tienes cuenta? Registrate</a>
        </div>
    </div>
</div>
</body>
</html>