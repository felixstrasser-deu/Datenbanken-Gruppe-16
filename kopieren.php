<?php
/*
 * Autor: Felix Straßer
 * Include-Modul zum Kopieren von Anmeldungen.
 */

// Schutz vor direktem Aufruf: Das Modul darf nur über das Teamchef-Dashboard geladen werden.
if (!defined('TEAMCHEF_DASHBOARD')) {
    header('Location: teamchef_dashboard.php');
    exit;
}

// In der Process-Phase werden Kopieraktionen verarbeitet und Auswahllisten geladen.
if (($dashboardPhase) === 'process') {
    // Verarbeitet das Formular zum Kopieren von Anmeldungen zwischen zwei Rennen.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $taskAction === 'kopieren_speichern') {
        // post_value liest das Formularfeld und filter_var prüft auf eine ganze Zahl.
        $quelle = filter_var(post_value('kopieren_quelle'), FILTER_VALIDATE_INT);
        $ziel = filter_var(post_value('kopieren_ziel'), FILTER_VALIDATE_INT);

        if ($quelle === false || $ziel === false || $quelle <= 0 || $ziel <= 0) {
            $fehler = 'Bitte Quell- und Zielrennen auswählen.';
        } elseif ($quelle === $ziel) {
            $fehler = 'Quelle und Ziel müssen unterschiedliche Rennen sein.';
        // zukünftigeRennen prüft, ob das Zielrennen noch nicht vorbei ist.
        } elseif (!zukuenftigeRennen($connection, $ziel)) {
            $fehler = 'Das Zielrennen muss ein zukünftiges Rennen sein.';
        } else {
            // mysqli_prepare bereitet die Abfrage mit Platzhaltern sicher vor.
            $sourceStmt = mysqli_prepare($connection, 'SELECT Mitarbeiter FROM Anmeldung WHERE Radrennen = ? AND Team = ? ORDER BY Startnummer');
            if ($sourceStmt) {
                // bind_param setzt Renn-ID und Team in die Platzhalter ein.
                mysqli_stmt_bind_param($sourceStmt, 'is', $quelle, $team);
                // execute führt die vorbereitete Abfrage aus.
                mysqli_stmt_execute($sourceStmt);
                // bind_result verbindet die Ergebnisspalte mit der Variable.
                mysqli_stmt_bind_result($sourceStmt, $mitarbeiterId);

                // Leeres Array für die gefundenen Fahrer-IDs erstellen.
                $fahrerIds = array();
                // fetch liest die gefundenen Fahrer nacheinander aus.
                while (mysqli_stmt_fetch($sourceStmt)) {
                    $fahrerIds[] = (int) $mitarbeiterId;
                }

                // close beendet das Statement nach der Benutzung.
                mysqli_stmt_close($sourceStmt);

                // count zählt die gefundenen Fahrer-IDs.
                if (count($fahrerIds) === 0) {
                    $fehler = 'Für das Quellrennen gibt es keine Anmeldungen dieses Teams.';
                } else {
                    $kopiert = 0;
                    $uebersprungen = 0;

                    foreach ($fahrerIds as $fahrerId) {
                        // fahrerAnmelden speichert den Fahrer beim Zielrennen mit neuer Startnummer.
                        if (fahrerAnmelden($connection, $ziel, $team, $fahrerId)) {
                            $kopiert++;
                        } else {
                            $uebersprungen++;
                        }
                    }

                    if ($fehler === '') {
                        $meldung = $kopiert . ' Anmeldungen wurden kopiert.';
                        if ($uebersprungen > 0) {
                            $meldung .= ' ' . $uebersprungen . ' bereits vorhandene Anmeldungen wurden übersprungen.';
                        }
                    }
                }
            } else {
                $fehler = 'Quellrennen konnte nicht gelesen werden.';
            }
        }
    }

    // Alle Rennen für die Auswahl des Quellrennens laden.
    $kopierenAlleRennen = array();
    // mysqli_query führt die SQL-Abfrage direkt aus.
    $alleRennenResult = mysqli_query($connection, 'SELECT `Renn_ID`, Datum, Standort FROM Radrennen ORDER BY Datum DESC, `Renn_ID` DESC');
    if ($alleRennenResult) {
        // fetch_assoc liefert jede Zeile als Array mit den Spaltennamen.
        while ($row = mysqli_fetch_assoc($alleRennenResult)) {
            $kopierenAlleRennen[] = $row;
        }
    }

    // Für das Ziel werden nur heutige und zukünftige Rennen geladen.
    $kopierenZielRennen = array();
    $zielRennenResult = mysqli_query($connection, 'SELECT `Renn_ID`, Datum, Standort FROM Radrennen WHERE Datum >= CURDATE() ORDER BY Datum, `Renn_ID`');
    if ($zielRennenResult) {
        while ($row = mysqli_fetch_assoc($zielRennenResult)) {
            $kopierenZielRennen[] = $row;
        }
    }
}

// In der Render-Phase wird das Formular für Quelle und Zielrennen angezeigt.
if (($dashboardPhase) === 'render') {
?>
<hr>
<h3 id="kopieren">Anmeldungen kopieren</h3>
<form method="post" action="teamchef_dashboard.php#kopieren">
    <input type="hidden" name="bereich" value="kopieren">
    <input type="hidden" name="task_action" value="kopieren_speichern">

    <label for="kopieren_quelle">Anmeldungen aus Rennen:</label><br>
    <select name="kopieren_quelle" id="kopieren_quelle" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($kopierenAlleRennen as $rennenEintrag) { ?>
            <!-- e gibt die Daten sicher im HTML aus. -->
            <option value="<?php echo e($rennenEintrag['Renn_ID']); ?>">
                <?php echo e($rennenEintrag['Renn_ID'] . ' - ' . $rennenEintrag['Datum'] . ' - ' . $rennenEintrag['Standort']); ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

    <label for="kopieren_ziel">Kopieren nach zukünftigem Rennen:</label><br>
    <select name="kopieren_ziel" id="kopieren_ziel" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($kopierenZielRennen as $rennenEintrag) { ?>
            <option value="<?php echo e($rennenEintrag['Renn_ID']); ?>">
                <?php echo e($rennenEintrag['Renn_ID'] . ' - ' . $rennenEintrag['Datum'] . ' - ' . $rennenEintrag['Standort']); ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

    <button type="submit">Anmeldungen kopieren</button>
</form>
<?php } ?>
