<?php
/*
 * Autor: Johnny Germar
 * Dashboard-Rahmen für Rennveranstalter. Rennen und Ergebnisse werden eingebunden.
 */
// session_start öffnet die Sitzung, damit die Login-Daten verfügbar sind.
session_start();
// require bindet die Datenbankverbindung und die allgemeinen Hilfsfunktionen ein.
require 'db.php';
require 'functions.php';

// require_role erlaubt den Zugriff nur für angemeldete Veranstalter.
require_role('veranstalter');

// Name des aktuell angemeldeten Veranstalters aus der Session holen.
$nameRaw = (string) ($_SESSION['name'] ?? '');
$meldung = '';
$fehler = '';

// Die Konstante zeigt den Modulen, dass sie über das Dashboard geladen wurden.
define('VERANSTALTER_DASHBOARD', true);
// In diesem Array stehen alle Module, die im Dashboard erscheinen sollen.
$veranstalterModuleFiles = array(
    'rennen' => 'rennen.php',
    'ergebnisse' => 'ergebnisse.php',
);

// In der ersten Phase verarbeiten die Module Formulare und laden Daten.
$dashboardPhase = 'process';
foreach ($veranstalterModuleFiles as $moduleFile) 
{
    // include führt den PHP-Code des jeweiligen Moduls an dieser Stelle aus.
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

<!-- Meldungen und Fehler werden gemeinsam durchlaufen und sicher ausgegeben. -->
<?php foreach (array($meldung, $fehler) as $hinweis) { ?>
    <?php if ($hinweis !== '') { ?>
        <!-- e schützt den ausgegebenen Text vor ausführbarem HTML-Code. -->
        <p><strong><?php echo e($hinweis); ?></strong></p>
    <?php } ?>
<?php } ?>

<?php
// In der zweiten Phase geben die eingebundenen Module ihr HTML aus.
$dashboardPhase = 'render';
foreach ($veranstalterModuleFiles as $moduleFile) 
{
    include $moduleFile;
}
?>

<p><a href="logout.php">Logout</a></p>
</body>
</html>
