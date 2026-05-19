<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Dashboard-Rahmen für Rennveranstalter. Rennen und Ergebnisse werden eingebunden.
 */
session_start();
require 'db.php';
require 'functions.php';

require_role('veranstalter');
mysqli_set_charset($connection, 'utf8mb4');

$nameRaw = (string) ($_SESSION['name'] ?? '');
$meldung = '';
$fehler = '';
$dashboardBereich = post_value('bereich') !== '' ? post_value('bereich') : get_value('bereich');

define('VERANSTALTER_DASHBOARD', true);
$veranstalterModuleFiles = array(
    'rennen' => 'rennen.php',
    'ergebnisse' => 'ergebnisse.php',
);

if (!isset($veranstalterModuleFiles[$dashboardBereich])) {
    $dashboardBereich = 'rennen';
}

$dashboardPhase = 'process';
foreach ($veranstalterModuleFiles as $moduleFile) {
    include $moduleFile;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Veranstalter Dashboard</title>
</head>
<body>
<h1>Veranstalter Dashboard</h1>
<p>Angemeldet als: <?php echo e($nameRaw); ?></p>

<nav>
    <a href="veranstalter_dashboard.php?bereich=rennen">Rennen anlegen</a> |
    <a href="veranstalter_dashboard.php?bereich=ergebnisse">Ergebnisse erfassen</a>
</nav>
<hr>

<?php foreach (array($meldung, $fehler) as $hinweis) { ?>
    <?php if ($hinweis !== '') { ?>
        <p><strong><?php echo e($hinweis); ?></strong></p>
    <?php } ?>
<?php } ?>

<?php
$dashboardPhase = 'render';
include $veranstalterModuleFiles[$dashboardBereich];
?>

<p><a href="logout.php">Logout</a></p>
</body>
</html>
