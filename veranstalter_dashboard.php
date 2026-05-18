<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Dashboard für Rennveranstalter inklusive Rennenanlage.
 */
session_start();
require 'db.php';
require 'functions.php';

require_role('veranstalter');
mysqli_set_charset($connection, 'utf8mb4');

$nameRaw = (string) ($_SESSION['name'] ?? '');
$meldung = '';
$fehler = '';
$hoehenmeterColumn = rennen_hoehenmeter_column($connection);
$ergebnisRennen = post_value('ergebnis_rennen') !== '' ? post_value('ergebnis_rennen') : get_value('ergebnis_rennen');

if ($hoehenmeterColumn === '') {
    $fehler = 'Hoehenmeter-Spalte konnte nicht erkannt werden.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_value('aktion') === 'rennen_speichern' && $hoehenmeterColumn !== '') {
    $datum = post_value('datum');
    $standort = post_value('standort');
    $kilometer = filter_var(post_value('kilometer'), FILTER_VALIDATE_INT);
    $hoehenmeter = filter_var(post_value('hoehenmeter'), FILTER_VALIDATE_INT);
    $maxSteigung = filter_var(post_value('max_steigung'), FILTER_VALIDATE_FLOAT);

    if ($datum === '' || $standort === '' || $kilometer === false || $hoehenmeter === false || $maxSteigung === false) {
        $fehler = 'Bitte alle Renndaten gültig ausfüllen.';
    } elseif ($kilometer <= 0 || $hoehenmeter < 0 || $maxSteigung < 0) {
        $fehler = 'Kilometer muss größer 0 sein; Höhenmeter und Steigung dürfen nicht negativ sein.';
    } elseif (strlen($standort) > 46) {
        $fehler = 'Der Standort darf maximal 46 Zeichen lang sein.';
    } else {
        mysqli_begin_transaction($connection);
        $rennenId = next_rennen_id($connection);
        $sql = 'INSERT INTO Radrennen (`Renn-ID`, `Datum`, `Standort`, `Kilometer`, ' . sql_identifier($hoehenmeterColumn) . ', `MaxSteigung`, `VName`) VALUES (?, ?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($connection, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'issiids', $rennenId, $datum, $standort, $kilometer, $hoehenmeter, $maxSteigung, $nameRaw);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($ok) {
                mysqli_commit($connection);
                $meldung = 'Rennen wurde gespeichert.';
            } else {
                mysqli_rollback($connection);
                $fehler = 'Rennen konnte nicht gespeichert werden: ' . mysqli_error($connection);
            }
        } else {
            mysqli_rollback($connection);
            $fehler = 'SQL-Anweisung konnte nicht vorbereitet werden.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_value('aktion') === 'ergebnisse_speichern') {
    $rennenId = filter_var($ergebnisRennen, FILTER_VALIDATE_INT);
    $startnummern = $_POST['startnummer'] ?? array();
    $platzierungen = $_POST['platzierung'] ?? array();
    $fahrtzeiten = $_POST['fahrtzeit'] ?? array();

    if ($rennenId === false || $rennenId <= 0) {
        $fehler = 'Bitte ein gültiges Rennen auswählen.';
    } else {
        $resultCheck = mysqli_prepare($connection, 'SELECT COUNT(*) FROM Anmeldung INNER JOIN Radrennen ON Anmeldung.Radrennen = Radrennen.`Renn-ID` WHERE Anmeldung.Radrennen = ? AND Radrennen.VName = ? AND (Anmeldung.Platzierung <> 0 OR Anmeldung.Fahrtzeit <> 0)');
        if ($resultCheck) {
            mysqli_stmt_bind_param($resultCheck, 'is', $rennenId, $nameRaw);
            mysqli_stmt_execute($resultCheck);
            mysqli_stmt_bind_result($resultCheck, $vorhandeneErgebnisse);
            mysqli_stmt_fetch($resultCheck);
            mysqli_stmt_close($resultCheck);

            if ($vorhandeneErgebnisse > 0) {
                $fehler = 'Ergebnisse für dieses Rennen wurden bereits erfasst und können nicht geändert werden.';
            }
        } else {
            $fehler = 'Ergebnisprüfung konnte nicht vorbereitet werden.';
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

            mysqli_stmt_bind_param($stmt, 'iiiis', $platzierung, $fahrtzeit, $rennenId, $startnummer, $nameRaw);
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

$kommendeRennen = array();
if ($hoehenmeterColumn !== '') {
    $rennenSql = 'SELECT `Renn-ID`, Datum, Standort, Kilometer, ' . sql_identifier($hoehenmeterColumn) . ' AS Hoehenmeter, MaxSteigung
                  FROM Radrennen
                  WHERE Datum >= CURDATE()
                  ORDER BY Datum ASC, `Renn-ID` ASC';
    $rennenResult = mysqli_query($connection, $rennenSql);

    if ($rennenResult) {
        while ($row = mysqli_fetch_assoc($rennenResult)) {
            $kommendeRennen[] = $row;
        }
    }
}

$anzahlAnmeldungen = 0;
$anmeldungResult = mysqli_query($connection, 'SELECT COUNT(*) AS anzahl FROM Anmeldung');
if ($anmeldungResult && ($anmeldungRow = mysqli_fetch_assoc($anmeldungResult))) {
    $anzahlAnmeldungen = (int) $anmeldungRow['anzahl'];
}

$veranstalterRennen = array();
$rennenListeStmt = mysqli_prepare($connection, 'SELECT `Renn-ID`, Datum, Standort FROM Radrennen WHERE VName = ? ORDER BY Datum DESC, `Renn-ID` DESC');
if ($rennenListeStmt) {
    mysqli_stmt_bind_param($rennenListeStmt, 's', $nameRaw);
    mysqli_stmt_execute($rennenListeStmt);
    mysqli_stmt_bind_result($rennenListeStmt, $dbRennenId, $dbDatum, $dbStandort);

    while (mysqli_stmt_fetch($rennenListeStmt)) {
        $veranstalterRennen[] = array('Renn-ID' => $dbRennenId, 'Datum' => $dbDatum, 'Standort' => $dbStandort);
    }

    mysqli_stmt_close($rennenListeStmt);
}

$ergebnisAnmeldungen = array();
if ($ergebnisRennen !== '') {
    $rennenNummer = filter_var($ergebnisRennen, FILTER_VALIDATE_INT);

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
            mysqli_stmt_bind_param($stmt, 'is', $rennenNummer, $nameRaw);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $startnummer, $platzierung, $fahrtzeit, $mitarbeiterId, $fahrerName, $teamName);

            while (mysqli_stmt_fetch($stmt)) {
                $ergebnisAnmeldungen[] = array(
                    'Startnummer' => $startnummer,
                    'Platzierung' => $platzierung,
                    'Fahrtzeit' => $fahrtzeit,
                    'Mitarbeiter_ID' => $mitarbeiterId,
                    'Name' => $fahrerName,
                    'Team' => $teamName,
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
    <title>Veranstalter Dashboard</title>
</head>
<body>
<h1>Veranstalter Dashboard</h1>
<p>Angemeldet als: <?php echo e($nameRaw); ?></p>

<?php if ($meldung !== '') { ?>
    <p style="color: green;"><?php echo e($meldung); ?></p>
<?php } ?>
<?php if ($fehler !== '') { ?>
    <p style="color: red;"><?php echo e($fehler); ?></p>
<?php } ?>

<h2>Moegliche Aufgaben</h2>
<table border="1" cellpadding="8" cellspacing="0">
    <tr>
        <th align="left">Bereich</th>
        <th align="left">Aktion</th>
    </tr>
    <tr>
        <td>Rennen</td>
        <td><a href="#rennenformular">Rennen verwalten / anlegen</a></td>
    </tr>
    <tr>
        <td>Ergebnisse</td>
        <td><a href="#ergebnisse">Ergebnisse erfassen</a></td>
    </tr>
</table>

<h3 id="rennenformular">Neues Rennen anlegen</h3>
<form method="post" action="veranstalter_dashboard.php">
    <input type="hidden" name="aktion" value="rennen_speichern">

    <label for="datum">Datum:</label><br>
    <input type="date" name="datum" id="datum" required>
    <br><br>

    <label for="standort">Startort:</label><br>
    <input type="text" name="standort" id="standort" maxlength="46" required>
    <br><br>

    <label for="kilometer">Kilometer:</label><br>
    <input type="number" name="kilometer" id="kilometer" min="1" required>
    <br><br>

    <label for="hoehenmeter">Hoehenmeter:</label><br>
    <input type="number" name="hoehenmeter" id="hoehenmeter" min="0" required>
    <br><br>

    <label for="max_steigung">Maximale Steigung in Prozent:</label><br>
    <input type="number" step="0.01" name="max_steigung" id="max_steigung" min="0" required>
    <br><br>

    <button type="submit">Rennen speichern</button>
</form>

<h3>Zukünftige Rennen</h3>
<?php if (count($kommendeRennen) === 0) { ?>
    <p>Keine zukünftigen Rennen vorhanden.</p>
<?php } else { ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>ID</th>
            <th>Datum</th>
            <th>Standort</th>
            <th>Kilometer</th>
            <th>Hoehenmeter</th>
            <th>Max. Steigung</th>
        </tr>
        <?php foreach ($kommendeRennen as $rennen) { ?>
            <tr>
                <td><?php echo e($rennen['Renn-ID']); ?></td>
                <td><?php echo e($rennen['Datum']); ?></td>
                <td><?php echo e($rennen['Standort']); ?></td>
                <td><?php echo e($rennen['Kilometer']); ?></td>
                <td><?php echo e($rennen['Hoehenmeter']); ?></td>
                <td><?php echo e($rennen['MaxSteigung']); ?></td>
            </tr>
        <?php } ?>
    </table>
<?php } ?>

<h3>Anmeldung</h3>
<p>Gesamtzahl Anmeldungen: <?php echo e($anzahlAnmeldungen); ?></p>

<hr>

<h3 id="ergebnisse">Ergebnisse erfassen</h3>
<form method="get" action="veranstalter_dashboard.php#ergebnisse">
    <label for="ergebnis_rennen">Rennen:</label><br>
    <select name="ergebnis_rennen" id="ergebnis_rennen" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($veranstalterRennen as $rennenEintrag) { ?>
            <option value="<?php echo e($rennenEintrag['Renn-ID']); ?>" <?php if ((string) $ergebnisRennen === (string) $rennenEintrag['Renn-ID']) echo 'selected'; ?>>
                <?php echo e($rennenEintrag['Renn-ID'] . ' - ' . $rennenEintrag['Datum'] . ' - ' . $rennenEintrag['Standort']); ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

    <button type="submit">Anzeigen</button>
</form>

<?php if ($ergebnisRennen !== '') { ?>
    <?php if (count($ergebnisAnmeldungen) === 0) { ?>
        <p>Keine Anmeldungen für dieses Rennen gefunden.</p>
    <?php } else { ?>
        <form method="post" action="veranstalter_dashboard.php#ergebnisse">
            <input type="hidden" name="aktion" value="ergebnisse_speichern">
            <input type="hidden" name="ergebnis_rennen" value="<?php echo e($ergebnisRennen); ?>">

            <table border="1" cellpadding="5" cellspacing="0">
                <tr>
                    <th>Startnummer</th>
                    <th>Fahrer</th>
                    <th>Team</th>
                    <th>Platzierung</th>
                    <th>Fahrtzeit</th>
                </tr>

                <?php foreach ($ergebnisAnmeldungen as $row) { ?>
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

            <button type="submit">Ergebnisse speichern</button>
        </form>
    <?php } ?>
<?php } ?>

<p><a href="logout.php">Logout</a></p>
</body>
</html>
