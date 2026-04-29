<?php
$host = "localhost";
$user = "gruppe16";
$password = "Bq+7wpK9;J$?";
$db = "gruppe16";

$connection = mysqli_connect($host, $user, $password);
mysqli_select_db($connection, $db);

if (!$connection) {
    die("Verbindung fehlgeschlagen: " . mysqli_connect_error());
}
?>