<?php
/*
 * Autor: Felix Straßer
 */
// Startet die aktuelle Sitzung, damit die gespeicherten Login-Daten gelöscht werden können.
session_start();
session_unset();
session_destroy();

// Nach dem Logout wird der Benutzer wieder zur Startseite geschickt.
header('Location: index.php');
exit;
