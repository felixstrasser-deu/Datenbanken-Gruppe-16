<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Include-Modul für Trainingsauswertung eines Fahrers.
 */
if (!defined('TEAMCHEF_DASHBOARD')) {
    header('Location: teamchef_dashboard.php');
    exit;
}

if (($dashboardPhase ?? '') === 'process') {
    $auswertungStatistik = array();
    $auswertungFahrer = '';
    $auswertungZiel = '';
    $auswertungVon = '';
    $auswertungBis = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $taskAction === 'auswertung_anzeigen') {
        $auswertungFahrer = post_value('auswertung_fahrer');
        $auswertungZiel = post_value('auswertung_trainingsziel');
        $auswertungVon = post_value('auswertung_von');
        $auswertungBis = post_value('auswertung_bis');

        if ($auswertungFahrer === '') {
            $fehler = 'Bitte einen Fahrer für die Auswertung auswählen.';
        } elseif ($auswertungVon !== '' && $auswertungBis !== '' && $auswertungVon > $auswertungBis) {
            $fehler = 'Das Von-Datum darf nicht nach dem Bis-Datum liegen.';
        } else {
            $stats = new TrainingStats((int) $auswertungFahrer, $auswertungZiel, $auswertungVon, $auswertungBis);

            if ($stats->loadFromDatabase($connection, $teamRaw)) {
                $auswertungStatistik = $stats->getMonatsStatistik();
            } else {
                $fehler = 'Auswertung konnte nicht geladen werden.';
            }
        }
    }

    $auswertungFahrerListe = array();
    $fahrerStmt = mysqli_prepare($connection, 'SELECT Mitarbeiter_ID, Name FROM Fahrer WHERE Team = ? ORDER BY Name');
    if ($fahrerStmt) {
        mysqli_stmt_bind_param($fahrerStmt, 's', $teamRaw);
        mysqli_stmt_execute($fahrerStmt);
        mysqli_stmt_bind_result($fahrerStmt, $fahrerId, $fahrerName);

        while (mysqli_stmt_fetch($fahrerStmt)) {
            $auswertungFahrerListe[] = array('Mitarbeiter_ID' => $fahrerId, 'Name' => $fahrerName);
        }

        mysqli_stmt_close($fahrerStmt);
    }

    $auswertungZiele = array();
    $zielResult = mysqli_query($connection, 'SELECT Trainingsziel FROM Trainingsziel ORDER BY Trainingsziel');
    if ($zielResult) {
        while ($row = mysqli_fetch_assoc($zielResult)) {
            $auswertungZiele[] = $row['Trainingsziel'];
        }
    }
}

if (($dashboardPhase ?? '') === 'render') {
?>
<hr>
<h3 id="auswertung">Auswertung anzeigen</h3>
<form method="post" action="teamchef_dashboard.php#auswertung">
    <input type="hidden" name="task_action" value="auswertung_anzeigen">

    <label for="auswertung_fahrer">Fahrer:</label><br>
    <select name="auswertung_fahrer" id="auswertung_fahrer" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($auswertungFahrerListe as $fahrerOption) { ?>
            <option value="<?php echo e($fahrerOption['Mitarbeiter_ID']); ?>" <?php if ((string) $auswertungFahrer === (string) $fahrerOption['Mitarbeiter_ID']) echo 'selected'; ?>>
                <?php echo e($fahrerOption['Mitarbeiter_ID'] . ' - ' . $fahrerOption['Name']); ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

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

    <label for="auswertung_von">Von optional:</label><br>
    <input type="date" name="auswertung_von" id="auswertung_von" value="<?php echo e($auswertungVon); ?>">
    <br><br>

    <label for="auswertung_bis">Bis optional:</label><br>
    <input type="date" name="auswertung_bis" id="auswertung_bis" value="<?php echo e($auswertungBis); ?>">
    <br><br>

    <button type="submit">Auswerten</button>
</form>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $taskAction === 'auswertung_anzeigen' && $fehler === '') { ?>
    <h4>Ergebnis</h4>
    <?php if (count($auswertungStatistik) === 0) { ?>
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
            <?php foreach ($auswertungStatistik as $monat => $werte) { ?>
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
<?php } ?>
