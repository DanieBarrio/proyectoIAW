<?php 
function conectar() {
     $servername = "sql308.thsite.top";
    $username = "thsi_38097478";
    $password = "ekQl9JGC";
    $db = "thsi_38097478_profesores";

    // Create connection
    $conn = mysqli_connect($servername, $username, $password, $db);

    // Check connection
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
   
    return $conn;
}

?>