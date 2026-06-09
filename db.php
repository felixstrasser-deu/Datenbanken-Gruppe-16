<?php
/*
 * Autor: Felix Straßer
 */
// Zugangsdaten für die MySQL-Datenbank der Gruppe.
$host = "localhost";
$user = "gruppe16";
$password = "Bq+7wpK9;J$?";
$db = "gruppe16";

// Baut die Datenbankverbindung auf und stellt die Zeichenkodierung auf UTF-8.
$connection = mysqli_connect($host, $user, $password, $db);
mysqli_set_charset($connection, 'utf8mb4');
?>
