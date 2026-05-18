<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Kopieren der Fahreranmeldungen eines Teamchefs auf ein neues Rennen.
 */
session_start();
require 'db.php';
require 'functions.php';

require_role('teamchef');
mysqli_set_charset($connection, 'utf8mb4');

$team = (string) ($_SESSION['team'] ?? '');
$meldung = '';
$fehler = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quelle = filter_var(post_value('quelle'), FILTER_VALIDATE_INT);
    $ziel = filter_var(post_value('ziel'), FILTER_VALIDATE_INT);

    if ($quelle === false || $ziel === false || $quelle <= 0 || $ziel <= 0) {
        $fehler = 'Bitte Quell- und Zielrennen auswählen.';
    } elseif ($quelle === $ziel) {
        $fehler = 'Quelle und Ziel muessen unterschiedliche Rennen sein.';
    } else {
        $sourceStmt = mysqli_prepare($connection, 'SELECT Mitarbeiter FROM Anmeldung WHERE Radrennen = ? AND Team = ? ORDER BY Startnummer');

        if ($sourceStmt) {
            mysqli_stmt_bind_param($sourceStmt, 'is', $quelle, $team);
            mysqli_stmt_execute($sourceStmt);
            mysqli_stmt_bind_result($sourceStmt, $mitarbeiterId);

            $fahrerIds = array();
            while (mysqli_stmt_fetch($sourceStmt)) {
                $fahrerIds[] = (int) $mitarbeiterId;
            }

            mysqli_stmt_close($sourceStmt);

            if (count($fahrerIds) === 0) {
                $fehler = 'Für das Quellrennen gibt es keine Anmeldungen dieses Teams.';
            } else {
                $kopiert = 0;
                $uebersprungen = 0;

                foreach ($fahrerIds as $fahrerId) {
                    if (fahrer_ist_angemeldet($connection, $ziel, $fahrerId)) {
                        $uebersprungen++;
                        continue;
                    }

                    if (melde_fahrer_an($connection, $ziel, $team, $fahrerId)) {
                        $kopiert++;
                    } else {
                        $fehler = 'Kopieren wurde abgebrochen: ' . mysqli_error($connection);
                        break;
                    }
                }

                if ($fehler === '') {
                    $meldung = $kopiert . ' Anmeldungen wurden kopiert.';
                    if ($uebersprungen > 0) {
                        $meldung .= ' ' . $uebersprungen . ' bereits vorhandene Anmeldungen wurden uebersprungen.';
                    }
                }
            }
        } else {
            $fehler = 'Quellrennen konnte nicht gelesen werden.';
        }
    }
}

$alleRennen = mysqli_query($connection, 'SELECT `Renn-ID`, Datum, Standort FROM Radrennen ORDER BY Datum DESC, `Renn-ID` DESC');
$zielRennen = mysqli_query($connection, 'SELECT `Renn-ID`, Datum, Standort FROM Radrennen WHERE Datum >= CURDATE() ORDER BY Datum ASC, `Renn-ID` ASC');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Anmeldungen kopieren</title>
</head>
<body>
<h1>Anmeldungen kopieren</h1>
<p>Team: <?php echo e($team); ?></p>

<?php if ($meldung !== '') { ?>
    <p style="color: green;"><?php echo e($meldung); ?></p>
<?php } ?>
<?php if ($fehler !== '') { ?>
    <p style="color: red;"><?php echo e($fehler); ?></p>
<?php } ?>

<form method="post" action="kopieren.php">
    <label for="quelle">Anmeldungen aus Rennen:</label><br>
    <select name="quelle" id="quelle" required>
        <option value="">Bitte wählen</option>
        <?php if ($alleRennen) { ?>
            <?php while ($row = mysqli_fetch_assoc($alleRennen)) { ?>
                <option value="<?php echo e($row['Renn-ID']); ?>">
                    <?php echo e($row['Renn-ID'] . ' - ' . $row['Datum'] . ' - ' . $row['Standort']); ?>
                </option>
            <?php } ?>
        <?php } ?>
    </select>

    <br><br>

    <label for="ziel">Kopieren nach zukünftigem Rennen:</label><br>
    <select name="ziel" id="ziel" required>
        <option value="">Bitte wählen</option>
        <?php if ($zielRennen) { ?>
            <?php while ($row = mysqli_fetch_assoc($zielRennen)) { ?>
                <option value="<?php echo e($row['Renn-ID']); ?>">
                    <?php echo e($row['Renn-ID'] . ' - ' . $row['Datum'] . ' - ' . $row['Standort']); ?>
                </option>
            <?php } ?>
        <?php } ?>
    </select>

    <br><br>

    <button type="submit">Anmeldungen kopieren</button>
</form>

<p><a href="anmeldung.php">Neue Anmeldung erfassen</a></p>
<p><a href="teamchef_dashboard.php">Zurück zum Dashboard</a></p>
</body>
</html>
