<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Trainingserfassung für Fahrer des angemeldeten Teamchefs.
 */
session_start();
include 'db.php';
require 'functions.php';

require_role('teamchef');
mysqli_set_charset($connection, 'utf8mb4');

$meldung = '';
$fehler = '';
$team = (string) ($_SESSION['team'] ?? '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $datum = post_value('datum');
    $kilometer = post_value('kilometer');
    $trainingsziel = post_value('trainingsziel');
    $mitarbeiter = post_value('mitarbeiter');

    if ($datum == '' || $kilometer == '' || $trainingsziel == '' || $mitarbeiter == '') {
        $fehler = 'Bitte alle Felder ausfüllen.';
    } elseif (!is_numeric($kilometer) || $kilometer <= 0) {
        $fehler = 'Kilometer muss größer als 0 sein.';
    } elseif (!is_numeric($mitarbeiter)) {
        $fehler = 'Ungültiger Fahrer.';
    }

    if ($fehler == '') {
        $checkStmt = mysqli_prepare($connection, 'SELECT 1 FROM Fahrer WHERE Mitarbeiter_ID = ? AND Team = ? LIMIT 1');
        if ($checkStmt) {
            mysqli_stmt_bind_param($checkStmt, 'is', $mitarbeiter, $team);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);

            if (mysqli_stmt_num_rows($checkStmt) === 0) {
                $fehler = 'Dieser Fahrer gehört nicht zu deinem Team.';
            }

            mysqli_stmt_close($checkStmt);
        } else {
            $fehler = 'Fahrer konnte nicht geprüft werden.';
        }
    }

    if ($fehler == '') {
        $sql = 'CALL TrainingSpeichern(?, ?, ?, ?, @status, @meldung)';
        $stmt = mysqli_prepare($connection, $sql);

        if ($stmt != false) {
            mysqli_stmt_bind_param($stmt, 'sdsi', $datum, $kilometer, $trainingsziel, $mitarbeiter);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            while (mysqli_more_results($connection)) {
                mysqli_next_result($connection);
            }

            $result = mysqli_query($connection, 'SELECT @status AS status, @meldung AS meldung');
            $row = mysqli_fetch_assoc($result);

            if ($row['status'] == 'OK') {
                $meldung = $row['meldung'];
            } else {
                $fehler = $row['meldung'];
            }
        } else {
            $fehler = 'Training konnte nicht vorbereitet werden.';
        }
    }
}

$fahrer = array();
$fahrerStmt = mysqli_prepare($connection, 'SELECT Mitarbeiter_ID, Name, Team FROM Fahrer WHERE Team = ? ORDER BY Name');
if ($fahrerStmt) {
    mysqli_stmt_bind_param($fahrerStmt, 's', $team);
    mysqli_stmt_execute($fahrerStmt);
    mysqli_stmt_bind_result($fahrerStmt, $fahrerId, $fahrerName, $fahrerTeam);

    while (mysqli_stmt_fetch($fahrerStmt)) {
        $fahrer[] = array('Mitarbeiter_ID' => $fahrerId, 'Name' => $fahrerName, 'Team' => $fahrerTeam);
    }

    mysqli_stmt_close($fahrerStmt);
}

$trainingsziele = mysqli_query($connection, 'SELECT Trainingsziel FROM Trainingsziel ORDER BY Trainingsziel');

if ($trainingsziele == false) {
    $fehler = 'Daten konnten nicht geladen werden: ' . mysqli_error($connection);
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Training erfassen</title>
</head>
<body>

<h2>Training erfassen</h2>
<p>Team: <?php echo e($team); ?></p>

<?php if ($meldung != '') { ?>
    <p style="color: green;"><?php echo e($meldung); ?></p>
<?php } ?>

<?php if ($fehler != '') { ?>
    <p style="color: red;"><?php echo e($fehler); ?></p>
<?php } ?>

<form method="post" action="training.php">
    <label for="mitarbeiter">Fahrer:</label><br>
    <select name="mitarbeiter" id="mitarbeiter" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($fahrer as $row) { ?>
            <option value="<?php echo e($row['Mitarbeiter_ID']); ?>">
                <?php echo e($row['Mitarbeiter_ID'] . ' - ' . $row['Name'] . ' (' . $row['Team'] . ')'); ?>
            </option>
        <?php } ?>
    </select>

    <br><br>

    <label for="datum">Datum:</label><br>
    <input type="date" name="datum" id="datum" required>

    <br><br>

    <label for="kilometer">Kilometer:</label><br>
    <input type="number" step="0.01" min="0.01" name="kilometer" id="kilometer" required>

    <br><br>

    <label for="trainingsziel">Trainingsziel:</label><br>
    <select name="trainingsziel" id="trainingsziel" required>
        <option value="">Bitte wählen</option>
        <?php if ($trainingsziele != false) { ?>
            <?php while ($row = mysqli_fetch_assoc($trainingsziele)) { ?>
                <option value="<?php echo e($row['Trainingsziel']); ?>">
                    <?php echo e($row['Trainingsziel']); ?>
                </option>
            <?php } ?>
        <?php } ?>
    </select>

    <br><br>

    <button type="submit">Training speichern</button>
</form>

<p><a href="auswertung.php">Auswertung anzeigen</a></p>
<p><a href="teamchef_dashboard.php">Zurück zum Dashboard</a></p>
</body>
</html>
