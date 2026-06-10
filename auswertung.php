<?php
/*
 * Autor: Magdalena Hamm
 * Dieses Modul besteht aus zwei Teilen:
 * In der process-Phase werden Fahrer, Trainingsziele und bei Bedarf Statistiken geladen.
 * In der render-Phase werden Formular und Ergebnis-Tabelle ausgegeben.
 */

// Schutz vor direktem Aufruf: Das Modul darf nur über das Teamchef-Dashboard geladen werden.
if (!defined('TEAMCHEF_DASHBOARD')) {
    header('Location: teamchef_dashboard.php');
    exit;
}

// In der process-Phase werden benötigte Daten geladen und Formularaktionen verarbeitet.
// Standardwerte für Formularfilter und spätere Auswertung initialisieren.
if (($dashboardPhase) === 'process') {
    $auswertungStatistiken = array();
    $auswertungZiel = '';
    $auswertungVon = '';
    $auswertungBis = '';
    $auswertungWurdeAngefordert = false;

    // Alle Fahrer des aktuellen Teams laden, damit für jeden Fahrer eine Statistik berechnet werden kann.
    $auswertungFahrerListe = array();
    $fahrerStmt = mysqli_prepare($connection, 'SELECT Mitarbeiter_ID, Name FROM Fahrer WHERE Team = ? ORDER BY Name');
    
    // Fahrer des Teams per Prepared Statement laden und als Array für die spätere Auswertung speichern.
    if ($fahrerStmt) {
        mysqli_stmt_bind_param($fahrerStmt, 's', $teamRaw);
        mysqli_stmt_execute($fahrerStmt);
        mysqli_stmt_bind_result($fahrerStmt, $fahrerId, $fahrerName);

        while (mysqli_stmt_fetch($fahrerStmt)) {
            $auswertungFahrerListe[] = array('Mitarbeiter_ID' => $fahrerId, 'Name' => $fahrerName);
        }

        mysqli_stmt_close($fahrerStmt);
    }

    // Trainingsziele für das Auswahlfeld laden.
    $auswertungZiele = array();
    $zielResult = mysqli_query($connection, 'SELECT Trainingsziel FROM Trainingsziel ORDER BY Trainingsziel');
    if ($zielResult) {
        while ($row = mysqli_fetch_assoc($zielResult)) {
            $auswertungZiele[] = $row['Trainingsziel'];
        }
    }

    // Vom Benutzer gesetzte Filter aus dem Formular übernehmen.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $taskAction === 'auswertung_anzeigen') {
        $auswertungWurdeAngefordert = true;
        $auswertungZiel = post_value('auswertung_trainingsziel');
        $auswertungVon = post_value('auswertung_von');
        $auswertungBis = post_value('auswertung_bis');

        // Ungültigen Zeitraum verhindern: Startdatum darf nicht nach dem Enddatum liegen.
        if ($auswertungVon !== '' && $auswertungBis !== '' && $auswertungVon > $auswertungBis) {
            $fehler = 'Das Von-Datum darf nicht nach dem Bis-Datum liegen.';
        } else {

            // Für jeden Fahrer ein Statistikobjekt mit den gewählten Filtern erstellen.
            foreach ($auswertungFahrerListe as $fahrerEintrag) {
                $stats = new TrainingStats((int) $fahrerEintrag['Mitarbeiter_ID'], $auswertungZiel, $auswertungVon, $auswertungBis);

                if (!$stats->loadFromDatabase($connection, $teamRaw)) {
                    $fehler = 'Auswertung konnte nicht geladen werden.';
                    break;
                }

                // Berechnete Monatsstatistik zusammen mit den Fahrerdaten für die Ausgabe speichern.
                $auswertungStatistiken[] = array(
                    'fahrer' => $fahrerEintrag,
                    'monate' => $stats->getMonatsStatistik(),
                );
            }
        }
    }
}

// In der render-Phase wird das Formular und bei Bedarf die Ergebnis-Tabelle ausgegeben.
if (($dashboardPhase) === 'render') {
?>
<hr>
<h3 id="auswertung">Auswertung anzeigen</h3>

<!-- Versteckte Steuerfelder, damit das Dashboard erkennt, welches Modul verarbeitet werden soll. -->
<form method="post" action="teamchef_dashboard.php?bereich=auswertung#auswertung">
    <input type="hidden" name="bereich" value="auswertung">
    <input type="hidden" name="task_action" value="auswertung_anzeigen">

    <!-- Trainingsziele dynamisch aus der Datenbank als Auswahloptionen anzeigen. -->
    <label for="auswertung_trainingsziel">Trainingsziel:</label><br>
    <select name="auswertung_trainingsziel" id="auswertung_trainingsziel">
        <option value="">Alle Ziele</option>
        <?php foreach ($auswertungZiele as $zielOption) { ?>
            <option value="<?php echo e($zielOption); ?>" <?php if ($auswertungZiel === $zielOption) echo 'selected'; ?>>
                <?php echo e($zielOption); ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

    <!-- Optionale Datumsfilter anzeigen und bereits eingegebene Werte wieder einsetzen. -->
    <label for="auswertung_von">Von optional:</label><br>
    <input type="date" name="auswertung_von" id="auswertung_von" value="<?php echo e($auswertungVon); ?>">
    <br><br>

    <label for="auswertung_bis">Bis optional:</label><br>
    <input type="date" name="auswertung_bis" id="auswertung_bis" value="<?php echo e($auswertungBis); ?>">
    <br><br>

    <!-- Ergebnis nur anzeigen, nachdem das Formular erfolgreich abgeschickt wurde. -->
    <button type="submit">Auswerten</button>
</form>

<?php if ($auswertungWurdeAngefordert && $fehler === '') { ?>
    <h4>Ergebnis</h4>

    <!-- Prüfen, ob mindestens ein Fahrer Trainingsdaten für die gewählten Filter hat. -->
    <?php
    $hatTrainingswerte = false;
    foreach ($auswertungStatistiken as $fahrerStatistik) {
        if (count($fahrerStatistik['monate']) > 0) {
            $hatTrainingswerte = true;
            break;
        }
    }
    ?>
    <?php if (!$hatTrainingswerte) { ?>
        <p>Keine Trainings gefunden.</p>

    <!-- Für jeden Fahrer und jeden vorhandenen Trainingsmonat eine Tabellenzeile ausgeben. -->
    <?php } else { ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <tr>
                <th>Fahrer</th>
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
            <?php foreach ($auswertungStatistiken as $fahrerStatistik) { ?>
                <?php foreach ($fahrerStatistik['monate'] as $monat => $werte) { ?>

                    <!-- Statistische Kennzahlen mit zwei Nachkommastellen im deutschen Zahlenformat ausgeben. -->
                    <tr>
                        <td><?php echo e($fahrerStatistik['fahrer']['Mitarbeiter_ID'] . ' - ' . $fahrerStatistik['fahrer']['Name']); ?></td>
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
            <?php } ?>
        </table>
    <?php } ?>
<?php } ?>
<?php } ?>