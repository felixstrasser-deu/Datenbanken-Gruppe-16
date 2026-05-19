<?php
/*
 * Autor: Johnny
 * Startseite mit eingebundenen Login- und Registrierungsbereichen.
 */
session_start();
require 'db.php';
require 'functions.php';

mysqli_set_charset($connection, 'utf8mb4');

if (isset($_SESSION['rolle'])) {
    if ($_SESSION['rolle'] === 'teamchef') {
        header('Location: teamchef_dashboard.php');
        exit;
    }

    if ($_SESSION['rolle'] === 'veranstalter') {
        header('Location: veranstalter_dashboard.php');
        exit;
    }
}

define('INDEX_PAGE', true);
$indexModuleFiles = array(
    'login_teamchef.php',
    'registrieren_teamchef.php',
    'veranstalter_login.php',
    'veranstalter_registrieren.php'
);

$teamchefLoginFehler = '';
$teamchefRegFehler = '';
$veranstalterLoginFehler = '';
$veranstalterRegFehler = '';

$indexPhase = 'process';
foreach ($indexModuleFiles as $moduleFile) {
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
            <?php $indexPhase = 'render'; include 'login_teamchef.php'; ?>
        </td>
        <td valign="top" width="50%">
            <?php $indexPhase = 'render'; include 'registrieren_teamchef.php'; ?>
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
            <?php $indexPhase = 'render'; include 'veranstalter_login.php'; ?>
        </td>
        <td valign="top" width="50%">
            <?php $indexPhase = 'render'; include 'veranstalter_registrieren.php'; ?>
        </td>
    </tr>
</table>

</body>
</html>
