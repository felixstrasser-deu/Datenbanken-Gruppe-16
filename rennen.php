<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Einstieg in die Rennenverwaltung.
 */
session_start();
require 'db.php';
require 'functions.php';

require_role('veranstalter');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Rennen verwalten</title>
</head>
<body>
<h1>Rennen verwalten</h1>
<p>Die Rennenanlage ist im Veranstalter-Dashboard integriert.</p>
<p><a href="veranstalter_dashboard.php">Zur Rennenverwaltung</a></p>
</body>
</html>
