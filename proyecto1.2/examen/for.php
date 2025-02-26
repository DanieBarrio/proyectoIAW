<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['numero'])) {
        die("Error: Todos los campos son obligatorios.");
    }


    $numero = htmlspecialchars(trim($_POST['numero']));
    print "<h1>La tabla de multiplicar de el numero ". $numero . " es: </h1> <br>";
    for($multiplo=1 ; $multiplo <=10; $multiplo++){
        print "La multiplicacion de ". $numero ." por ". $multiplo . " es: " . ($numero * $multiplo)."<br>";
    }
    print "<br>";
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Multiplica</title>
</head>
<body>
    <form method="POST" action="for.php">
        <label for="numero">Numero para ver la tabla de multiplicar:</label>
        <input type="number" id="numero" name="numero"><br>
        <button type="submit">Ingresar</button>
    </form>
    <?php
        
    ?>
    
</body>
</html>
