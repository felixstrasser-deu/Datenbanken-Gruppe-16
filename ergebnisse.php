<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Einmalige Ergebniserfassung für Rennen eines Veranstalters.
 */
session_start();
require 'db.php';
require 'functions.php';

require_role('veranstalter');
mysqli_set_charset($connection, 'utf8mb4');

$veranstalter = (string) ($_SESSION['name'] ?? '');
$meldung = '';
$fehler = '';
$rennen = post_value('rennen') !== '' ? post_value('rennen') : get_value('rennen');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['speichern'])) {
    $rennenId = filter_var($rennen, FILTER_VALIDATE_INT);
    $startnummern = $_POST['startnummer'] ?? array();
    $platzierungen = $_POST['platzierung'] ?? array();
    $fahrtzeiten = $_POST['fahrtzeit'] ?? array();

    if ($rennenId === false || $rennenId <= 0) {
        $fehler = 'Bitte ein gültiges Rennen auswählen.';
    } else {
        $resultCheck = mysqli_prepare($connection, 'SELECT COUNT(*) FROM Anmeldung INNER JOIN Radrennen ON Anmeldung.Radrennen = Radrennen.`Renn-ID` WHERE Anmeldung.Radrennen = ? AND Radrennen.VName = ? AND (Anmeldung.Platzierung <> 0 OR Anmeldung.Fahrtzeit <> 0)');
        if ($resultCheck) {
            mysqli_stmt_bind_param($resultCheck, 'is', $rennenId, $veranstalter);
            mysqli_stmt_execute($resultCheck);
            mysqli_stmt_bind_result($resultCheck, $vorhandeneErgebnisse);
            mysqli_stmt_fetch($resultCheck);
            mysqli_stmt_close($resultCheck);

            if ($vorhandeneErgebnisse > 0) {
                $fehler = 'Ergebnisse für dieses Rennen wurden bereits erfasst und können nicht geändert werden.';
            }
        } else {
            $fehler = 'Ergebnispruefung konnte nicht vorbereitet werden.';
        }
    }

    $vergebenePlaetze = array();
    if ($fehler === '') {
        foreach ($startnummern as $i => $startnummer) {
            $platzierung = filter_var($platzierungen[$i] ?? '', FILTER_VALIDATE_INT);
            $fahrtzeit = filter_var($fahrtzeiten[$i] ?? '', FILTER_VALIDATE_INT);

            if ($platzierung === false || $fahrtzeit === false || $platzierung <= 0 || $fahrtzeit <= 0) {
                $fehler = 'Bitte für jeden Fahrer Platzierung und Fahrtzeit gültig eintragen.';
                break;
            }

            if (isset($vergebenePlaetze[$platzierung])) {
                $fehler = 'Jede Platzierung darf pro Rennen nur einmal vergeben werden.';
                break;
            }

            $vergebenePlaetze[$platzierung] = true;
        }
    }

    if ($fehler === '') {
        mysqli_begin_transaction($connection);
        $ok = true;

        foreach ($startnummern as $i => $startnummerRaw) {
            $startnummer = filter_var($startnummerRaw, FILTER_VALIDATE_INT);
            $platzierung = filter_var($platzierungen[$i], FILTER_VALIDATE_INT);
            $fahrtzeit = filter_var($fahrtzeiten[$i], FILTER_VALIDATE_INT);

            $sql = 'UPDATE Anmeldung
                    INNER JOIN Radrennen ON Anmeldung.Radrennen = Radrennen.`Renn-ID`
                    SET Anmeldung.Platzierung = ?, Anmeldung.Fahrtzeit = ?
                    WHERE Anmeldung.Radrennen = ?
                    AND Anmeldung.Startnummer = ?
                    AND Radrennen.VName = ?
                    AND Anmeldung.Platzierung = 0
                    AND Anmeldung.Fahrtzeit = 0';

            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                $ok = false;
                break;
            }

            mysqli_stmt_bind_param($stmt, 'iiiis', $platzierung, $fahrtzeit, $rennenId, $startnummer, $veranstalter);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if (!$ok) {
                break;
            }
        }

        if ($ok) {
            mysqli_commit($connection);
            $meldung = 'Ergebnisse wurden gespeichert.';
        } else {
            mysqli_rollback($connection);
            $fehler = 'Ergebnisse konnten nicht gespeichert werden: ' . mysqli_error($connection);
        }
    }
}

$rennenListeStmt = mysqli_prepare($connection, 'SELECT `Renn-ID`, Datum, Standort FROM Radrennen WHERE VName = ? ORDER BY Datum DESC, `Renn-ID` DESC');
$rennenListe = array();
if ($rennenListeStmt) {
    mysqli_stmt_bind_param($rennenListeStmt, 's', $veranstalter);
    mysqli_stmt_execute($rennenListeStmt);
    mysqli_stmt_bind_result($rennenListeStmt, $rennenId, $datum, $standort);

    while (mysqli_stmt_fetch($rennenListeStmt)) {
        $rennenListe[] = array('Renn-ID' => $rennenId, 'Datum' => $datum, 'Standort' => $standort);
    }

    mysqli_stmt_close($rennenListeStmt);
}

$anmeldungen = array();
if ($rennen !== '') {
    $rennenNummer = filter_var($rennen, FILTER_VALIDATE_INT);

    if ($rennenNummer !== false && $rennenNummer > 0) {
        $stmt = mysqli_prepare(
            $connection,
            'SELECT Anmeldung.Startnummer, Anmeldung.Platzierung, Anmeldung.Fahrtzeit,
                    Fahrer.Mitarbeiter_ID, Fahrer.Name, Fahrer.Team
             FROM Anmeldung
             INNER JOIN Fahrer ON Anmeldung.Mitarbeiter = Fahrer.Mitarbeiter_ID
             INNER JOIN Radrennen ON Anmeldung.Radrennen = Radrennen.`Renn-ID`
             WHERE Anmeldung.Radrennen = ?
             AND Radrennen.VName = ?
             ORDER BY Anmeldung.Startnummer'
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'is', $rennenNummer, $veranstalter);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $startnummer, $platzierung, $fahrtzeit, $mitarbeiterId, $fahrerName, $team);

            while (mysqli_stmt_fetch($stmt)) {
                $anmeldungen[] = array(
                    'Startnummer' => $startnummer,
                    'Platzierung' => $platzierung,
                    'Fahrtzeit' => $fahrtzeit,
                    'Mitarbeiter_ID' => $mitarbeiterId,
                    'Name' => $fahrerName,
                    'Team' => $team,
                );
            }

            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Ergebnisse erfassen</title>
</head>
<body>

<h2>Ergebnisse erfassen</h2>

<?php if ($meldung !== '') { ?>
    <p style="color: green;"><?php echo e($meldung); ?></p>
<?php } ?>
<?php if ($fehler !== '') { ?>
    <p style="color: red;"><?php echo e($fehler); ?></p>
<?php } ?>

<form method="get" action="ergebnisse.php">
    <label for="rennen">Rennen:</label><br>
    <select name="rennen" id="rennen" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($rennenListe as $row) { ?>
            <option value="<?php echo e($row['Renn-ID']); ?>" <?php if ((string) $rennen === (string) $row['Renn-ID']) echo 'selected'; ?>>
                <?php echo e($row['Renn-ID'] . ' - ' . $row['Datum'] . ' - ' . $row['Standort']); ?>
            </option>
        <?php } ?>
    </select>

    <br><br>

    <button type="submit">Anzeigen</button>
</form>

<?php if ($rennen !== '') { ?>
    <h3>Fahrer des Rennens</h3>

    <?php if (count($anmeldungen) === 0) { ?>
        <p>Keine Anmeldungen für dieses Rennen gefunden.</p>
    <?php } else { ?>
        <form method="post" action="ergebnisse.php">
            <input type="hidden" name="rennen" value="<?php echo e($rennen); ?>">

            <table border="1" cellpadding="5" cellspacing="0">
                <tr>
                    <th>Startnummer</th>
                    <th>Fahrer</th>
                    <th>Team</th>
                    <th>Platzierung</th>
                    <th>Fahrtzeit</th>
                </tr>

                <?php foreach ($anmeldungen as $row) { ?>
                    <tr>
                        <td>
                            <?php echo e($row['Startnummer']); ?>
                            <input type="hidden" name="startnummer[]" value="<?php echo e($row['Startnummer']); ?>">
                        </td>
                        <td><?php echo e($row['Mitarbeiter_ID'] . ' - ' . $row['Name']); ?></td>
                        <td><?php echo e($row['Team']); ?></td>

                        <?php if ((int) $row['Platzierung'] === 0 && (int) $row['Fahrtzeit'] === 0) { ?>
                            <td><input type="number" name="platzierung[]" min="1" required></td>
                            <td><input type="number" name="fahrtzeit[]" min="1" required></td>
                        <?php } else { ?>
                            <td><?php echo e($row['Platzierung']); ?></td>
                            <td><?php echo e($row['Fahrtzeit']); ?></td>
                        <?php } ?>
                    </tr>
                <?php } ?>
            </table>

            <br>

            <button type="submit" name="speichern">Ergebnisse speichern</button>
        </form>
    <?php } ?>
<?php } ?>

<p><a href="veranstalter_dashboard.php">Zurück zum Dashboard</a></p>
</body>
</html>
