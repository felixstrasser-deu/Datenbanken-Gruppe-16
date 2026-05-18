<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Einstieg in die Fahrerverwaltung.
 */
session_start();
require 'db.php';
require 'functions.php';

require_role('teamchef');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Fahrer verwalten</title>
</head>
<body>
<h1>Fahrer verwalten</h1>
<p>Die Fahrerverwaltung ist im Teamchef-Dashboard integriert, damit Erfassen und Aendern dieselbe Maske verwenden.</p>
<p><a href="teamchef_dashboard.php">Zur Fahrerverwaltung</a></p>
</body>
</html>
