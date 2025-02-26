<?php
session_start();
require 'db.php';

if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = trim($_POST['usuario']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    try {
        // Validaciones
        if (strlen($usuario) < 4) {
            throw new Exception("El usuario debe tener al menos 4 caracteres");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || 
            !preg_match('/^[a-zA-Z]+@iesamachado\.org$/', $email)) {
            throw new Exception("Email inválido. Debe ser del dominio @iesamachado.org y sin números");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Las contraseñas no coinciden");
        }

        $conn = conectar();

        // Verificar duplicados
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ? OR email = ?");
        $stmt->bind_param("ss", $usuario, $email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("El usuario o email ya existen");
        }

        // Crear usuario
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre_usuario, email, contrasena) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $usuario, $email, $hash);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Registro exitoso. Inicie sesión";
            header("Location: login.php");
            exit;
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    } finally {
        if (isset($conn)) $conn->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
    function validarEmail(input) {
        const regex = /^[a-zA-Z]+@iesamachado\.org$/;
        const errorElement = document.getElementById('emailError');
        
        if (!regex.test(input.value)) {
            errorElement.textContent = 'Debe ser @iesamachado.org y sin números';
            input.classList.add('is-invalid');
            return false;
        } else {
            errorElement.textContent = '';
            input.classList.remove('is-invalid');
            return true;
        }
    }

    function validarFormulario() {
        return validarEmail(document.getElementById('email')) && 
               document.getElementById('password').value === 
               document.getElementById('confirm_password').value;
    }
    </script>
</head>
<body>
<div class="container mt-5">
    <div class="card mx-auto" style="max-width: 500px;">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Registro de Usuario</h4>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" onsubmit="return validarFormulario()">
                <div class="mb-3">
                    <label>Usuario</label>
                    <input type="text" name="usuario" class="form-control" required minlength="4">
                </div>
                
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" id="email" class="form-control" 
                           pattern="[a-zA-Z]+@iesamachado\.org" required>
                    <div id="emailError" class="invalid-feedback"></div>
                </div>
                
                <div class="mb-3">
                    <label>Contraseña</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label>Confirmar Contraseña</label>
                    <input type="password" name="confirm_password" id="confirm_password" 
                           class="form-control" required>
                </div>

                <button class="btn btn-primary w-100">Registrarse</button>
                <div class="mt-3 text-center">
                    ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>