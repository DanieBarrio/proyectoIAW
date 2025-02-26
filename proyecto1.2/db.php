<?php 
function conectar() {
    $servername = "sql308.thsite.top";
    $username = "thsi_38097478";
    $password = "your_password_here"; // Replace with actual password
    $db = "thsi_38097478_profesores";

    // Create connection with error handling
    $conn = mysqli_connect($servername, $username, $password, $db);

    // Check connection
    if (!$conn) {
        die("Error de conexión: " . mysqli_connect_error());
    }
   
    // Set character encoding
    mysqli_set_charset($conn, "utf8mb4");
    
    return $conn;
}
?>