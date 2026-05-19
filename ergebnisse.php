<?php
/*
 * Autor: Magdalena Hamm
 * Include-Modul für Ergebniserfassung.
 */
if (!defined('VERANSTALTER_DASHBOARD')) {
    header('Location: veranstalter_dashboard.php');
    exit;
}

if (($dashboardPhase ?? '') === 'process') {
    $ergebnisRennen = post_value('ergebnis_rennen') !== '' ? post_value('ergebnis_rennen') : get_value('ergebnis_rennen');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_value('aktion') === 'ergebnisse_speichern') {
        $rennenId = filter_var($ergebnisRennen, FILTER_VALIDATE_INT);
        $startnummern = $_POST['startnummer'] ?? array();
        $platzierungen = $_POST['platzierung'] ?? array();
        $fahrtzeiten = $_POST['fahrtzeit'] ?? array();

        if ($rennenId === false || $rennenId <= 0) {
            $fehler = 'Bitte ein gültiges Rennen auswählen.';
        }

        if ($fehler === '') {
            foreach ($startnummern as $i => $startnummer) {
                $platzierung = filter_var($platzierungen[$i] ?? '', FILTER_VALIDATE_INT);
                $fahrtzeit = filter_var($fahrtzeiten[$i] ?? '', FILTER_VALIDATE_INT);

                if ($platzierung === false || $fahrtzeit === false || $platzierung <= 0 || $fahrtzeit <= 0) {
                    $fehler = 'Bitte für jeden Fahrer Platzierung und Fahrtzeit gültig eintragen.';
                    break;
                }
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
                        INNER JOIN Radrennen ON Anmeldung.Radrennen = Radrennen.`Renn_ID`
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
                $ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
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
                $fehler = 'Ergebnisse konnten nicht gespeichert werden oder wurden bereits erfasst.';
            }
        }
    }

    $veranstalterRennen = array();
    $rennenListeStmt = mysqli_prepare($connection, 'SELECT `Renn_ID`, Datum, Standort FROM Radrennen WHERE VName = ? ORDER BY Datum DESC, `Renn_ID` DESC');
    if ($rennenListeStmt) {
        mysqli_stmt_bind_param($rennenListeStmt, 's', $nameRaw);
        mysqli_stmt_execute($rennenListeStmt);
        mysqli_stmt_bind_result($rennenListeStmt, $dbRennenId, $dbDatum, $dbStandort);

        while (mysqli_stmt_fetch($rennenListeStmt)) {
            $veranstalterRennen[] = array('Renn_ID' => $dbRennenId, 'Datum' => $dbDatum, 'Standort' => $dbStandort);
        }

        mysqli_stmt_close($rennenListeStmt);
    }

    $ergebnisAnmeldungen = array();
    $rennenNummer = filter_var($ergebnisRennen, FILTER_VALIDATE_INT);
    if ($rennenNummer !== false && $rennenNummer > 0) {
        $stmt = mysqli_prepare(
            $connection,
            'SELECT Anmeldung.Startnummer, Anmeldung.Platzierung, Anmeldung.Fahrtzeit,
                    Fahrer.Mitarbeiter_ID, Fahrer.Name, Fahrer.Team
             FROM Anmeldung
             INNER JOIN Fahrer ON Anmeldung.Team = Fahrer.Team
                AND Anmeldung.Mitarbeiter = Fahrer.Mitarbeiter_ID
             INNER JOIN Radrennen ON Anmeldung.Radrennen = Radrennen.`Renn_ID`
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

if (($dashboardPhase ?? '') === 'render') {
?>
<hr>
<h3 id="ergebnisse">Ergebnisse erfassen</h3>
<form method="get" action="veranstalter_dashboard.php#ergebnisse">
    <input type="hidden" name="bereich" value="ergebnisse">
    <label for="ergebnis_rennen">Rennen:</label><br>
    <select name="ergebnis_rennen" id="ergebnis_rennen" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($veranstalterRennen as $rennenEintrag) { ?>
            <option value="<?php echo e($rennenEintrag['Renn_ID']); ?>" <?php if ((string) $ergebnisRennen === (string) $rennenEintrag['Renn_ID']) echo 'selected'; ?>>
                <?php echo e($rennenEintrag['Renn_ID'] . ' - ' . $rennenEintrag['Datum'] . ' - ' . $rennenEintrag['Standort']); ?>
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
        <form method="post" action="veranstalter_dashboard.php?bereich=ergebnisse#ergebnisse">
            <input type="hidden" name="bereich" value="ergebnisse">
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
<?php } ?>
