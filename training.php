<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Include-Modul für Trainingserfassung.
 */
if (!defined('TEAMCHEF_DASHBOARD')) {
    header('Location: teamchef_dashboard.php');
    exit;
}

if (($dashboardPhase ?? '') === 'process') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $taskAction === 'training_speichern') {
        $datum = post_value('training_datum');
        $kilometer = post_value('training_kilometer');
        $trainingsziel = post_value('training_trainingsziel');
        $mitarbeiter = post_value('training_mitarbeiter');

        if ($datum === '' || $kilometer === '' || $trainingsziel === '' || $mitarbeiter === '') {
            $fehler = 'Bitte alle Trainingsfelder ausfüllen.';
        } elseif (!is_numeric($kilometer) || $kilometer <= 0) {
            $fehler = 'Kilometer muss größer als 0 sein.';
        } elseif (!is_numeric($mitarbeiter)) {
            $fehler = 'Ungültiger Fahrer.';
        } else {
            $checkStmt = mysqli_prepare($connection, 'SELECT 1 FROM Fahrer WHERE Mitarbeiter_ID = ? AND Team = ? LIMIT 1');
            if ($checkStmt) {
                mysqli_stmt_bind_param($checkStmt, 'is', $mitarbeiter, $teamRaw);
                mysqli_stmt_execute($checkStmt);
                mysqli_stmt_store_result($checkStmt);
                $fahrerOk = mysqli_stmt_num_rows($checkStmt) > 0;
                mysqli_stmt_close($checkStmt);

                if (!$fahrerOk) {
                    $fehler = 'Dieser Fahrer gehört nicht zu deinem Team.';
                }
            } else {
                $fehler = 'Fahrer konnte nicht geprüft werden.';
            }
        }

        if ($fehler === '') {
            $stmt = mysqli_prepare($connection, 'CALL TrainingSpeichern(?, ?, ?, ?, @status, @meldung)');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sdsi', $datum, $kilometer, $trainingsziel, $mitarbeiter);
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

    $trainingFahrer = array();
    $fahrerStmt = mysqli_prepare($connection, 'SELECT Mitarbeiter_ID, Name FROM Fahrer WHERE Team = ? ORDER BY Name');
    if ($fahrerStmt) {
        mysqli_stmt_bind_param($fahrerStmt, 's', $teamRaw);
        mysqli_stmt_execute($fahrerStmt);
        mysqli_stmt_bind_result($fahrerStmt, $fahrerId, $fahrerName);

        while (mysqli_stmt_fetch($fahrerStmt)) {
            $trainingFahrer[] = array('Mitarbeiter_ID' => $fahrerId, 'Name' => $fahrerName);
        }

        mysqli_stmt_close($fahrerStmt);
    }

    $trainingZiele = array();
    $zielResult = mysqli_query($connection, 'SELECT Trainingsziel FROM Trainingsziel ORDER BY Trainingsziel');
    if ($zielResult) {
        while ($row = mysqli_fetch_assoc($zielResult)) {
            $trainingZiele[] = $row['Trainingsziel'];
        }
    }
}

if (($dashboardPhase ?? '') === 'render') {
?>
<hr>
<h3 id="training">Training erfassen</h3>
<form method="post" action="teamchef_dashboard.php#training">
    <input type="hidden" name="task_action" value="training_speichern">

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

    <label for="training_datum">Datum:</label><br>
    <input type="date" name="training_datum" id="training_datum" required>
    <br><br>

    <label for="training_kilometer">Kilometer:</label><br>
    <input type="number" step="0.01" min="0.01" name="training_kilometer" id="training_kilometer" required>
    <br><br>

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
