<?php
/*
 * Autor: Felix Straßer
 * Dashboard-Rahmen für Teamchefs. Die einzelnen Aufgabenbereiche werden eingebunden.
 */
session_start();
require 'db.php';
require 'functions.php';
require_once 'TrainingStats.php';

require_role('teamchef');

$teamRaw = (string) ($_SESSION['team'] ?? '');
$team = $teamRaw;
$meldung = '';
$fehler = '';
$taskAction = post_value('task_action');

define('TEAMCHEF_DASHBOARD', true);
$teamchefModuleFiles = array(
    'fahrer' => 'fahrer.php',
    'anmeldung' => 'anmeldung.php',
    'kopieren' => 'kopieren.php',
    'training' => 'training.php',
    'auswertung' => 'auswertung.php',
);

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

<?php foreach (array($meldung, $fehler) as $hinweis) { ?>
    <?php if ($hinweis !== '') { ?>
        <p><strong><?php echo e($hinweis); ?></strong></p>
    <?php } ?>
<?php } ?>

<?php
$dashboardPhase = 'render';
foreach ($teamchefModuleFiles as $moduleFile) {
    include $moduleFile;
}
?>

<p><a href="logout.php">Logout</a></p>
</body>
</html>
