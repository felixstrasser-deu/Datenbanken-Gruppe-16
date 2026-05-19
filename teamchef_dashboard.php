<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Dashboard-Rahmen für Teamchefs. Die einzelnen Aufgabenbereiche werden eingebunden.
 */
session_start();
require 'db.php';
require 'functions.php';
require_once 'TrainingStats.php';

require_role('teamchef');
mysqli_set_charset($connection, 'utf8mb4');

$teamRaw = (string) ($_SESSION['team'] ?? '');
$meldung = '';
$fehler = '';
$taskAction = post_value('task_action');
$dashboardBereich = post_value('bereich') !== '' ? post_value('bereich') : get_value('bereich');

define('TEAMCHEF_DASHBOARD', true);
$teamchefModuleFiles = array(
    'fahrer' => 'fahrer.php',
    'anmeldung' => 'anmeldung.php',
    'kopieren' => 'kopieren.php',
    'training' => 'training.php',
    'auswertung' => 'auswertung.php',
);

if (!isset($teamchefModuleFiles[$dashboardBereich])) {
    $dashboardBereich = 'fahrer';
}

$dashboardPhase = 'process';
foreach ($teamchefModuleFiles as $moduleFile) {
    include $moduleFile;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Teamchef Dashboard</title>
</head>
<body>
<h1>Teamchef Dashboard</h1>
<p>Angemeldet als: <?php echo e($_SESSION['loginname'] ?? ''); ?></p>
<p>Team: <?php echo e($teamRaw); ?></p>

<nav>
    <a href="teamchef_dashboard.php?bereich=fahrer">Fahrer pflegen</a> |
    <a href="teamchef_dashboard.php?bereich=anmeldung">Fahrer anmelden</a> |
    <a href="teamchef_dashboard.php?bereich=kopieren">Anmeldungen kopieren</a> |
    <a href="teamchef_dashboard.php?bereich=training">Training erfassen</a> |
    <a href="teamchef_dashboard.php?bereich=auswertung">Auswertung</a>
</nav>
<hr>

<?php foreach (array($meldung, $fehler) as $hinweis) { ?>
    <?php if ($hinweis !== '') { ?>
        <p><strong><?php echo e($hinweis); ?></strong></p>
    <?php } ?>
<?php } ?>

<?php
$dashboardPhase = 'render';
include $teamchefModuleFiles[$dashboardBereich];
?>

<p><a href="logout.php">Logout</a></p>
</body>
</html>
