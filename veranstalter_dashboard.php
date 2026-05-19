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

define('VERANSTALTER_DASHBOARD', true);
$veranstalterModuleFiles = array('rennen.php', 'ergebnisse.php');

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

<?php foreach (array($meldung, $fehler) as $hinweis) { ?>
    <?php if ($hinweis !== '') { ?>
        <p><strong><?php echo e($hinweis); ?></strong></p>
    <?php } ?>
<?php } ?>

<?php
$dashboardPhase = 'render';
foreach ($veranstalterModuleFiles as $moduleFile) {
    include $moduleFile;
}
?>

<p><a href="logout.php">Logout</a></p>
</body>
</html>
