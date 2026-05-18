<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Anzeige der Teamdaten für den angemeldeten Teamchef.
 */
session_start();
require 'db.php';
require 'functions.php';

require_role('teamchef');
mysqli_set_charset($connection, 'utf8mb4');

$team = (string) ($_SESSION['team'] ?? '');
$loginname = (string) ($_SESSION['loginname'] ?? '');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Team verwalten</title>
</head>
<body>
<h1>Team verwalten</h1>
<p>Team: <?php echo e($team); ?></p>
<p>Teamchef-Login: <?php echo e($loginname); ?></p>
<p>Neue Teams werden auf der Startseite zusammen mit dem Teamchef angelegt.</p>
<p><a href="teamchef_dashboard.php">Zurück zum Dashboard</a></p>
</body>
</html>
