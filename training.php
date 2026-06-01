<?php
/*
 * Autor: Magdalena Hamm
 * Dieses Modul verarbeitet und rendert die Trainingserfassung im Teamchef-Dashboard.
 */

// Schutz vor direktem Aufruf: Das Modul darf nur über das Teamchef-Dashboard geladen werden.
if (!defined('TEAMCHEF_DASHBOARD')) {
    header('Location: teamchef_dashboard.php');
    exit;
}

// In der process-Phase werden Formulareingaben verarbeitet und benötigte Daten geladen.
if (($dashboardPhase ?? '') === 'process') {

    // Eingaben aus dem Formular sicher auslesen und Leerzeichen entfernen.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $taskAction === 'training_speichern') {
        // Eingaben aus dem Formular sicher auslesen und Leerzeichen entfernen.
        $datum = post_value('training_datum');
        $kilometer = post_value('training_kilometer');
        $trainingsziel = post_value('training_trainingsziel');
        $mitarbeiter = post_value('training_mitarbeiter');

        // Prüft, ob alle Pflichtfelder korrekt ausgefüllt wurden.
        if ($datum === '' || $kilometer === '' || $trainingsziel === '' || $mitarbeiter === '') {
            $fehler = 'Bitte alle Trainingsfelder ausfüllen.';
        } elseif (!is_numeric($kilometer) || $kilometer <= 0) {
            $fehler = 'Kilometer muss größer als 0 sein.';
        } elseif (!is_numeric($mitarbeiter)) {
            $fehler = 'Ungültiger Fahrer.';
        }

        // Nur wenn keine Validierungsfehler vorliegen, wird das Training gespeichert.
        if ($fehler === '') {

            // Stored Procedure zum Speichern des Trainings vorbereiten.
            $stmt = mysqli_prepare($connection, 'CALL TrainingSpeichern(?, ?, ?, ?, ?, @status, @meldung)');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sisds', $team, $mitarbeiter, $datum, $kilometer, $trainingsziel);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                while (mysqli_more_results($connection)) {
                    mysqli_next_result($connection);
                }

                $result = mysqli_query($connection, 'SELECT @status AS status, @meldung AS meldung');
                $row = mysqli_fetch_assoc($result);

                if ($row && $row['status'] === 'OK') {
                    $meldung = $row['meldung'];
                } else {
                    $fehler = $row ? $row['meldung'] : 'Training konnte nicht gespeichert werden.';
                }
            } else {
                $fehler = 'Training konnte nicht vorbereitet werden.';
            }
        }
    }

    // Fahrer des aktuellen Teams für die Auswahl laden.
    $trainingFahrer = array();
    $fahrerStmt = mysqli_prepare($connection, 'SELECT Mitarbeiter_ID, Name FROM Fahrer WHERE Team = ? ORDER BY Name');
    if ($fahrerStmt) {
        mysqli_stmt_bind_param($fahrerStmt, 's', $team);
        mysqli_stmt_execute($fahrerStmt);
        mysqli_stmt_bind_result($fahrerStmt, $fahrerId, $fahrerName);

        while (mysqli_stmt_fetch($fahrerStmt)) {
            $trainingFahrer[] = array('Mitarbeiter_ID' => $fahrerId, 'Name' => $fahrerName);
        }

        mysqli_stmt_close($fahrerStmt);
    }

    // Trainingsziele für die Auswahl laden.
    $trainingZiele = array();
    $zielResult = mysqli_query($connection, 'SELECT Trainingsziel FROM Trainingsziel ORDER BY Trainingsziel');
    if ($zielResult) {
        while ($row = mysqli_fetch_assoc($zielResult)) {
            $trainingZiele[] = $row['Trainingsziel'];
        }
    }
}

// In der render-Phase wird das Formular angezeigt.
if (($dashboardPhase ?? '') === 'render') {
?>
<hr>
<h3 id="training">Training erfassen</h3>
<form method="post" action="teamchef_dashboard.php#training">

    <!-- Versteckte Steuerfelder, damit das Dashboard erkennt, welches Modul verarbeitet werden soll -->
    <input type="hidden" name="bereich" value="training">
    <input type="hidden" name="task_action" value="training_speichern">

    <!-- Fahrer dynamisch aus der Datenbank als Auswahloptionen anzeigen -->
    <label for="training_mitarbeiter">Fahrer:</label><br>
    <select name="training_mitarbeiter" id="training_mitarbeiter" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($trainingFahrer as $fahrerOption) { ?>
            <option value="<?php echo e($fahrerOption['Mitarbeiter_ID']); ?>">
                <?php echo e($fahrerOption['Mitarbeiter_ID'] . ' - ' . $fahrerOption['Name']); ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

    <!-- Pflichtfeld für das Datum des Trainings -->
    <label for="training_datum">Datum:</label><br>
    <input type="date" name="training_datum" id="training_datum" required>
    <br><br>

    <!-- Pflichtfeld für die Trainingskilometer -->
    <label for="training_kilometer">Kilometer:</label><br>
    <input type="number" step="0.01" min="0.01" name="training_kilometer" id="training_kilometer" required>
    <br><br>

    <!-- Dropdown zur Auswahl des Trainingsziels -->
    <label for="training_trainingsziel">Trainingsziel:</label><br>
    <select name="training_trainingsziel" id="training_trainingsziel" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($trainingZiele as $zielOption) { ?>
            <option value="<?php echo e($zielOption); ?>"><?php echo e($zielOption); ?></option>
        <?php } ?>
    </select>
    <br><br>

    <button type="submit">Training speichern</button>
</form>
<?php } ?>
