<?php
/*
 * Autor: Magdalena Hamm
 */
$host = "localhost";
$user = "gruppe16";
$password = "Bq+7wpK9;J$?";
$db = "gruppe16";

$connection = mysqli_connect($host, $user, $password);
if (!$connection) {
    die("Verbindung fehlgeschlagen: " . mysqli_connect_error());
}

if (!mysqli_select_db($connection, $db)) {
    die("Datenbank konnte nicht ausgewählt werden: " . mysqli_error($connection));
}
?>
