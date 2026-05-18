<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Trainingsauswertung für Teamchefs.
 */
session_start();
include 'db.php';
require 'functions.php';
require 'TrainingStats.php';

require_role('teamchef');
mysqli_set_charset($connection, 'utf8mb4');

$team = (string) ($_SESSION['team'] ?? '');
$ziel = '';
$von = '';
$bis = '';
$fahrerId = '';
$fehler = '';
$statistik = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ziel = post_value('trainingsziel');
    $von = post_value('von');
    $bis = post_value('bis');
    $fahrerId = post_value('fahrer');

    if ($von !== '' && $bis !== '' && $von > $bis) {
        $fehler = 'Das Von-Datum darf nicht nach dem Bis-Datum liegen.';
    } else {
        $fahrerFilter = $fahrerId !== '' ? (int) $fahrerId : null;
        $stats = new TrainingStats($fahrerFilter, $ziel, $von, $bis);

        if ($stats->loadFromDatabase($connection, $team)) {
            $statistik = $stats->getMonatsStatistik();
        } else {
            $fehler = 'Auswertung konnte nicht geladen werden.';
        }
    }
}

$fahrer = array();
$fahrerStmt = mysqli_prepare($connection, 'SELECT Mitarbeiter_ID, Name FROM Fahrer WHERE Team = ? ORDER BY Name');
if ($fahrerStmt) {
    mysqli_stmt_bind_param($fahrerStmt, 's', $team);
    mysqli_stmt_execute($fahrerStmt);
    mysqli_stmt_bind_result($fahrerStmt, $dbFahrerId, $dbFahrerName);

    while (mysqli_stmt_fetch($fahrerStmt)) {
        $fahrer[] = array('Mitarbeiter_ID' => $dbFahrerId, 'Name' => $dbFahrerName);
    }

    mysqli_stmt_close($fahrerStmt);
}

$trainingsziele = mysqli_query($connection, 'SELECT Trainingsziel FROM Trainingsziel ORDER BY Trainingsziel');
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Auswertung</title>
</head>
<body>

<h2>Auswertung</h2>
<p>Team: <?php echo e($team); ?></p>

<?php if ($fehler != '') { ?>
    <p style="color: red;"><?php echo e($fehler); ?></p>
<?php } ?>

<form method="post" action="auswertung.php">
    <label for="fahrer">Fahrer:</label><br>
    <select name="fahrer" id="fahrer">
        <option value="">Alle Teamfahrer</option>
        <?php foreach ($fahrer as $row) { ?>
            <option value="<?php echo e($row['Mitarbeiter_ID']); ?>" <?php if ((string) $fahrerId === (string) $row['Mitarbeiter_ID']) echo 'selected'; ?>>
                <?php echo e($row['Mitarbeiter_ID'] . ' - ' . $row['Name']); ?>
            </option>
        <?php } ?>
    </select>

    <br><br>

    <label for="trainingsziel">Trainingsziel:</label><br>
    <select name="trainingsziel" id="trainingsziel">
        <option value="">Alle Ziele</option>
        <?php if ($trainingsziele) { ?>
            <?php while ($row = mysqli_fetch_assoc($trainingsziele)) { ?>
                <option value="<?php echo e($row['Trainingsziel']); ?>" <?php if ($ziel == $row['Trainingsziel']) echo 'selected'; ?>>
                    <?php echo e($row['Trainingsziel']); ?>
                </option>
            <?php } ?>
        <?php } ?>
    </select>

    <br><br>

    <label for="von">Von optional:</label><br>
    <input type="date" name="von" id="von" value="<?php echo e($von); ?>">

    <br><br>

    <label for="bis">Bis optional:</label><br>
    <input type="date" name="bis" id="bis" value="<?php echo e($bis); ?>">

    <br><br>

    <button type="submit">Auswerten</button>
</form>

<?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && $fehler == '') { ?>
    <h3>Ergebnis</h3>

    <?php if (count($statistik) == 0) { ?>
        <p>Keine Trainings gefunden.</p>
    <?php } else { ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <tr>
                <th>Monat</th>
                <th>Anzahl</th>
                <th>Summe</th>
                <th>Durchschnitt</th>
                <th>Minimum</th>
                <th>Maximum</th>
                <th>Median</th>
                <th>25%-Quantil</th>
                <th>75%-Quantil</th>
                <th>Standardabweichung</th>
            </tr>

            <?php foreach ($statistik as $monat => $werte) { ?>
                <tr>
                    <td><?php echo e($monat); ?></td>
                    <td><?php echo e($werte['anzahl']); ?></td>
                    <td><?php echo e(number_format($werte['summe'], 2, ',', '.')); ?></td>
                    <td><?php echo e(number_format($werte['durchschnitt'], 2, ',', '.')); ?></td>
                    <td><?php echo e(number_format($werte['minimum'], 2, ',', '.')); ?></td>
                    <td><?php echo e(number_format($werte['maximum'], 2, ',', '.')); ?></td>
                    <td><?php echo e(number_format($werte['median'], 2, ',', '.')); ?></td>
                    <td><?php echo e(number_format($werte['quantil_25'], 2, ',', '.')); ?></td>
                    <td><?php echo e(number_format($werte['quantil_75'], 2, ',', '.')); ?></td>
                    <td><?php echo e(number_format($werte['standardabweichung'], 2, ',', '.')); ?></td>
                </tr>
            <?php } ?>
        </table>
    <?php } ?>
<?php } ?>

<p><a href="training.php">Training erfassen</a></p>
<p><a href="teamchef_dashboard.php">Zurück zum Dashboard</a></p>
</body>
</html>
