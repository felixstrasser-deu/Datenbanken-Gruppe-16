<?php
/*
 * Autor: Magdalena Hamm
 * Include-Modul für Ergebniserfassung.
 */

// Schutz vor direktem Aufruf: Das Modul darf nur über das Teamchef-Dashboard geladen werden.
if (!defined('VERANSTALTER_DASHBOARD')) {
    header('Location: veranstalter_dashboard.php');
    exit;
}

// In der process-Phase werden benötigte Daten geladen und Formularaktionen verarbeitet.
if ($dashboardPhase === 'process') {
    $ergebnisRennen = post_value('ergebnis_rennen') ?: get_value('ergebnis_rennen');

    // Übermittelte Renn-ID und tabellarische Eingaben aus dem POST-Formular auslesen, wenn Speicherformular abgesendet wird.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_value('aktion') === 'ergebnisse_speichern') {
        $rennenId = filter_var($ergebnisRennen, FILTER_VALIDATE_INT);
        $startnummern = $_POST['startnummer'] ?? array();
        $platzierungen = $_POST['platzierung'] ?? array();
        $fahrtzeiten = $_POST['fahrtzeit'] ?? array();

        // Eine gültige Renn-ID muss eine positive Ganzzahl sein.
        if ($rennenId === false || $rennenId <= 0) {
            $fehler = 'Bitte ein gültiges Rennen auswählen.';
        }

        // Jede Tabellenzeile muss eine positive Platzierung und eine Fahrtzeit im Format HH:MM:SS enthalten.
        if ($fehler === '') {
            foreach ($startnummern as $i => $startnummer) {
                $platzierung = filter_var($platzierungen[$i] ?? '', FILTER_VALIDATE_INT);
                $fahrtzeit = trim((string) ($fahrtzeiten[$i] ?? ''));
                $fahrtzeitTeile = explode(':', $fahrtzeit);
                $fahrtzeitGueltig = preg_match('/^\d{1,3}:[0-5]\d:[0-5]\d$/', $fahrtzeit) === 1
                    && (int) $fahrtzeitTeile[0] <= 24
                    && $fahrtzeit !== '00:00:00';

                if ($platzierung === false || $platzierung <= 0 || !$fahrtzeitGueltig) {
                    $fehler = 'Bitte für jeden Fahrer Platzierung und Fahrtzeit gültig eintragen.';
                    break;
                }
            }
        }

        // Alle Ergebnis-Updates werden als Transaktion ausgeführt, damit sie nur gemeinsam gespeichert werden.
        if ($fehler === '') {
            mysqli_begin_transaction($connection);
            $ok = true;

            // Ergebnis nur für das gewählte Rennen, die Startnummer und den aktuellen Veranstalter speichern.
            foreach ($startnummern as $i => $startnummerRaw) {
                $startnummer = filter_var($startnummerRaw, FILTER_VALIDATE_INT);
                $platzierung = filter_var($platzierungen[$i], FILTER_VALIDATE_INT);
                $fahrtzeit = trim((string) $fahrtzeiten[$i]);

                $sql = 'UPDATE Anmeldung, Radrennen
                        SET Anmeldung.Platzierung = ?, Anmeldung.Fahrtzeit = ?
                        WHERE Anmeldung.Radrennen = Radrennen.`Renn_ID`
                        AND Anmeldung.Radrennen = ?
                        AND Anmeldung.Startnummer = ?
                        AND Radrennen.VName = ?
                        AND Anmeldung.Platzierung = 0
                        AND Anmeldung.Fahrtzeit = \'00:00:00\'';
                $stmt = mysqli_prepare($connection, $sql);

                if (!$stmt) {
                    $ok = false;
                    break;
                }

                // Nach dem Update muss genau ein Datensatz betroffen sein, sonst gilt die Speicherung als fehlgeschlagen.
                mysqli_stmt_bind_param($stmt, 'isiis', $platzierung, $fahrtzeit, $rennenId, $startnummer, $nameRaw);
                $ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
                mysqli_stmt_close($stmt);

                if (!$ok) {
                    break;
                }
            }

            // Bei vollständigem Erfolg werden alle Updates übernommen, sonst wird alles rückgängig gemacht.
            if ($ok) {
                mysqli_commit($connection);
                $meldung = 'Ergebnisse wurden gespeichert.';
            } else {
                mysqli_rollback($connection);
                $fehler = 'Ergebnisse konnten nicht gespeichert werden oder wurden bereits erfasst.';
            }
        }
    }

    // Alle Rennen des aktuellen Veranstalters für das Auswahlfeld laden.
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

    // Alle angemeldeten Fahrer für das gewählte Rennen laden und dem aktuellen Veranstalter zuordnen.
    $ergebnisAnmeldungen = array();
    $rennenNummer = filter_var($ergebnisRennen, FILTER_VALIDATE_INT);
    if ($rennenNummer !== false && $rennenNummer > 0) {
        $stmt = mysqli_prepare(
            $connection,
            'SELECT Anmeldung.Startnummer, Anmeldung.Platzierung, Anmeldung.Fahrtzeit,
                    Fahrer.Mitarbeiter_ID, Fahrer.Name, Fahrer.Team
             FROM Anmeldung, Fahrer, Radrennen
             WHERE Anmeldung.Team = Fahrer.Team
             AND Anmeldung.Mitarbeiter = Fahrer.Mitarbeiter_ID
             AND Anmeldung.Radrennen = Radrennen.`Renn_ID`
             AND Anmeldung.Radrennen = ?
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

// In der render-Phase wird das Formular zur Ergebniserfassung angezeigt.
if ($dashboardPhase === 'render') {
?>
<hr>
<h3 id="ergebnisse">Ergebnisse erfassen</h3>

<!-- Formular zur Auswahl eines Rennens; per GET wird nur die Anzeige der passenden Anmeldungen geladen. -->
<form method="get" action="veranstalter_dashboard.php#ergebnisse">
    <input type="hidden" name="bereich" value="ergebnisse">
    <label for="ergebnis_rennen">Rennen:</label><br>
    <select name="ergebnis_rennen" id="ergebnis_rennen" required>
        <option value="">Bitte wählen</option>

        <!-- Alle Rennen des Veranstalters werden als Dropdown-Optionen ausgegeben.-->
        <?php foreach ($veranstalterRennen as $rennenEintrag) { ?>
            <option value="<?php echo e($rennenEintrag['Renn_ID']); ?>" <?php if ((string) $ergebnisRennen === (string) $rennenEintrag['Renn_ID']) echo 'selected'; ?>>
                <?php echo e($rennenEintrag['Renn_ID'] . ' - ' . $rennenEintrag['Datum'] . ' - ' . $rennenEintrag['Standort']); ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

    <button type="submit">Anzeigen</button>
</form>

<!-- Wenn für das gewählte Rennen keine Fahrer angemeldet sind, wird eine Hinweismeldung angezeigt. -->
<?php if ($ergebnisRennen !== '') { ?>
    <?php if (count($ergebnisAnmeldungen) === 0) { ?>
        <p>Keine Anmeldungen für dieses Rennen gefunden.</p>
    
        <!-- Versteckte Felder steuern, welches Modul und welche Aktion beim Absenden verarbeitet wird. -->
        <?php } else { ?>
        <form method="post" action="veranstalter_dashboard.php?bereich=ergebnisse#ergebnisse">
            <input type="hidden" name="bereich" value="ergebnisse">
            <input type="hidden" name="aktion" value="ergebnisse_speichern">
            <input type="hidden" name="ergebnis_rennen" value="<?php echo e($ergebnisRennen); ?>">

            <!-- Startnummer, Fahrer und Team werden angezeigt; die Startnummer wird zusätzlich versteckt zum Speichern mitgesendet. -->
            <table border="1" cellpadding="5" cellspacing="0">
                <tr>
                    <th>Startnummer</th>
                    <th>Fahrer</th>
                    <th>Team</th>
                    <th>Platzierung</th>
                    <th>Fahrtzeit</th>
                </tr>

                <!-- Noch nicht erfasste Ergebnisse können eingegeben werden; bereits gespeicherte Ergebnisse werden nur angezeigt. -->
                <?php foreach ($ergebnisAnmeldungen as $row) { ?>
                    <tr>
                        <td>
                            <?php echo e($row['Startnummer']); ?>
                        </td>
                        <td><?php echo e($row['Mitarbeiter_ID'] . ' - ' . $row['Name']); ?></td>
                        <td><?php echo e($row['Team']); ?></td>

                        <?php if ((int) $row['Platzierung'] === 0 && (string) $row['Fahrtzeit'] === '00:00:00') { ?>
                            <td>
                                <input type="hidden" name="startnummer[]" value="<?php echo e($row['Startnummer']); ?>">
                                <input type="number" name="platzierung[]" min="1" required>
                            </td>
                            <td><input type="text" name="fahrtzeit[]" pattern="\d{1,3}:[0-5]\d:[0-5]\d" placeholder="01:23:45" required></td>
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
