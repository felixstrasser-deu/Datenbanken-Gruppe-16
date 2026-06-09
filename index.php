<?php
/*
 * Autor: Johnny Germar
 * Startseite mit eingebundenen Login- und Registrierungsbereichen.
 */
// session_start öffnet die Sitzung, damit vorhandene Login-Daten gelesen werden können.
session_start();
// require bindet die Datenbankverbindung und die gemeinsamen Hilfsfunktionen ein.
require 'db.php';
require 'functions.php';

// isset prüft, ob in der Session bereits eine Benutzerrolle gespeichert ist.
if (isset($_SESSION['rolle'])) 
{
    if ($_SESSION['rolle'] === 'teamchef') 
    {
        // Bereits angemeldete Teamchefs werden direkt zu ihrem Dashboard weitergeleitet.
        header('Location: teamchef_dashboard.php');
        exit;
    }

    if ($_SESSION['rolle'] === 'veranstalter') 
    {
        // Bereits angemeldete Veranstalter werden direkt zu ihrem Dashboard weitergeleitet.
        header('Location: veranstalter_dashboard.php');
        exit;
    }
}

// Die Konstante zeigt den eingebundenen Modulen, dass sie über index.php geladen wurden.
define('INDEX_PAGE', true);

// Im Array stehen alle Login- und Registrierungsmodule der Startseite.
$indexModuleFiles = array(
    'teamchef_login.php',
    'teamchef_registrieren.php',
    'veranstalter_login.php',
    'veranstalter_registrieren.php'
);

$teamchefLoginFehler = '';
$teamchefRegFehler = '';
$veranstalterLoginFehler = '';
$veranstalterRegFehler = '';

// In der ersten Phase verarbeiten alle Module abgeschickte Formulare.
$indexPhase = 'process';
foreach ($indexModuleFiles as $moduleFile) 
{
    // include führt den PHP-Code des jeweiligen Moduls an dieser Stelle aus.
    include $moduleFile;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Verwaltung von Radrennen</title>
</head>
<body>

<h1>Verwaltung von Radrennen</h1>

<h2>Teamchef-Bereich</h2>

<table border="1" cellpadding="12" cellspacing="0" width="100%">
    <tr>
        <th align="left">Teamchef Login</th>
        <th align="left">Teamchef Registrierung</th>
    </tr>
    <tr>
        <td valign="top" width="50%">
            <!-- In der render-Phase gibt das Modul sein Loginformular aus. -->
            <?php $indexPhase = 'render'; include 'teamchef_login.php'; ?>
        </td>
        <td valign="top" width="50%">
            <!-- include bindet hier das Registrierungsformular der Teamchefs ein. -->
            <?php $indexPhase = 'render'; include 'teamchef_registrieren.php'; ?>
        </td>
    </tr>
</table>

<br>

<h2>Rennveranstalter-Bereich</h2>

<table border="1" cellpadding="12" cellspacing="0" width="100%">
    <tr>
        <th align="left">Veranstalter Login</th>
        <th align="left">Veranstalter Registrierung</th>
    </tr>
    <tr>
        <td valign="top" width="50%">
            <!-- Das Veranstalter-Login wird in der render-Phase angezeigt. -->
            <?php $indexPhase = 'render'; include 'veranstalter_login.php'; ?>
        </td>
        <td valign="top" width="50%">
            <!-- Das Veranstalter-Registrierungsformular wird hier eingebunden. -->
            <?php $indexPhase = 'render'; include 'veranstalter_registrieren.php'; ?>
        </td>
    </tr>
</table>

</body>
</html>
