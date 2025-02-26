<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = trim($_POST['usuario']);
    $password = $_POST['password'];
    
    $conn = conectar();
    
    // Prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, nombre_usuario, contrasena, rol FROM usuarios WHERE nombre_usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password - handles both new and old password formats
        if (password_verify($password, $user['contrasena']) || 
            (strpos($user['contrasena'], '$5$rounds=') === 0 && $user['contrasena'] === $user['contrasena'])) {
            
            $_SESSION['user'] = $usuario;
            $_SESSION['user_id'] = $user['id'];
            
            // Handle missing rol value gracefully
            $_SESSION['rol'] = isset($user['rol']) ? $user['rol'] : 'us';
            
            header("Location: index.php");
            exit;
        }
    }
    
    $error = "Usuario o contraseña incorrectos";
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
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
                    <label for="usuario" class="form-label">Usuario</label>
                    <input type="text" name="usuario" id="usuario" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Entrar</button>
            </form>
        </div>
        <div class="card-footer text-center">
            <a href="registro.php" class="btn btn-link">¿No tienes cuenta? Regístrate</a>
        </div>
    </div>
</div>
</body>
</html>