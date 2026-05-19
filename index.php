<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
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

$anzahlTeams = 0;
$anzahlFahrer = 0;
$anzahlRennen = 0;
$anzahlTrainings = 0;

$result = mysqli_query($connection, 'SELECT COUNT(*) AS anzahl FROM Team');
if ($result && ($row = mysqli_fetch_assoc($result))) {
    $anzahlTeams = $row['anzahl'];
}

$result = mysqli_query($connection, 'SELECT COUNT(*) AS anzahl FROM Fahrer');
if ($result && ($row = mysqli_fetch_assoc($result))) {
    $anzahlFahrer = $row['anzahl'];
}

$result = mysqli_query($connection, 'SELECT COUNT(*) AS anzahl FROM Radrennen');
if ($result && ($row = mysqli_fetch_assoc($result))) {
    $anzahlRennen = $row['anzahl'];
}

$result = mysqli_query($connection, 'SELECT COUNT(*) AS anzahl FROM Training');
if ($result && ($row = mysqli_fetch_assoc($result))) {
    $anzahlTrainings = $row['anzahl'];
}

$letzteTrainings = mysqli_query(
    $connection,
    'SELECT Training.Datum, Training.Kilometer, Training.Trainingsziel, Fahrer.Name
     FROM Training
     INNER JOIN Fahrer ON Training.Mitarbeiter = Fahrer.Mitarbeiter_ID
     ORDER BY Training.Datum DESC
     LIMIT 5'
);
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

<hr>

<h2>Übersicht</h2>

<table border="1" cellpadding="8" cellspacing="0">
    <tr>
        <th>Teams</th>
        <th>Fahrer</th>
        <th>Rennen</th>
        <th>Trainings</th>
    </tr>
    <tr>
        <td><?php echo e($anzahlTeams); ?></td>
        <td><?php echo e($anzahlFahrer); ?></td>
        <td><?php echo e($anzahlRennen); ?></td>
        <td><?php echo e($anzahlTrainings); ?></td>
    </tr>
</table>

<br>

<h2>Letzte Trainings</h2>

<?php if (!$letzteTrainings || mysqli_num_rows($letzteTrainings) == 0) { ?>
    <p>Noch keine Trainings vorhanden.</p>
<?php } else { ?>
    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>Datum</th>
            <th>Fahrer</th>
            <th>Kilometer</th>
            <th>Trainingsziel</th>
        </tr>

        <?php while ($row = mysqli_fetch_assoc($letzteTrainings)) { ?>
            <tr>
                <td><?php echo e($row['Datum']); ?></td>
                <td><?php echo e($row['Name']); ?></td>
                <td><?php echo e($row['Kilometer']); ?></td>
                <td><?php echo e($row['Trainingsziel']); ?></td>
            </tr>
        <?php } ?>
    </table>
<?php } ?>

</body>
</html>
