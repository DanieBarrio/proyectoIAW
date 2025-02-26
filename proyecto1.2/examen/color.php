<?php
    setcookie(
        name: "bg_color",
        value: $_POST["color"] ?? "#12373d",
        expires_or_options: time() + 60
    );

    $color = $_COOKIE["bg_color"] ?? "red";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cookies</title>
    <style>
        body {
            background: <?= $color ?>
        }
    </style>
</head>
<body>
    <h1>Prueba de Cookies</h1>
    <form action="<?= $_SERVER["PHP_SELF"]?>" method="post">
        <label for="color">Color de fondo</label><br>
        <input type="color" name="color" id="color"><br>
        <button type="submit">Cambiar</button>
    </form>
</body>
</html>

