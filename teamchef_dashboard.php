<?php
/*
 * Autor: Felix Straßer
 * Dashboard-Rahmen für Teamchefs. Die einzelnen Aufgabenbereiche werden eingebunden.
 */
session_start();

// Zentrale Abhängigkeiten für Datenbank, Hilfsfunktionen und Trainingsauswertung laden.
require 'db.php';
require 'functions.php';
require_once 'TrainingStats.php';

// Zugriff nur erlauben, wenn der eingeloggte Benutzer die Rolle Teamchef hat.
require_role('teamchef');

// Team und Meldungsvariablen werden von den eingebundenen Modulen gemeinsam genutzt.
$teamRaw = (string) ($_SESSION['team'] ?? '');
$team = $teamRaw;
$meldung = '';
$fehler = '';
$taskAction = post_value('task_action');

// Konstante verhindert, dass Dashboard-Module direkt im Browser aufgerufen werden.
define('TEAMCHEF_DASHBOARD', true);
$teamchefModuleFiles = array(
    'fahrer' => 'fahrer.php',
    'anmeldung' => 'anmeldung.php',
    'kopieren' => 'kopieren.php',
    'training' => 'training.php',
    'auswertung' => 'auswertung.php',
);

// In der Process-Phase verarbeiten die Module Formularaktionen und laden Daten.
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

<!-- Meldungen und Fehler aus der Process-Phase werden vor den Modulinhalten angezeigt. -->
<?php foreach (array($meldung, $fehler) as $hinweis) { ?>
    <?php if ($hinweis !== '') { ?>
        <p><strong><?php echo e($hinweis); ?></strong></p>
    <?php } ?>
<?php } ?>

<?php
// In der Render-Phase geben dieselben Module ihre HTML-Bereiche aus.
$dashboardPhase = 'render';
foreach ($teamchefModuleFiles as $moduleFile) {
    // Jedes Modul prüft selbst, ob es in der Render-Phase HTML ausgeben soll.
    include $moduleFile;
}
?>

<p><a href="logout.php">Logout</a></p>
</body>
</html>
