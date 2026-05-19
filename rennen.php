<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Include-Modul für Rennen verwalten und anlegen.
 */
if (!defined('VERANSTALTER_DASHBOARD')) {
    header('Location: veranstalter_dashboard.php');
    exit;
}

if (($dashboardPhase ?? '') === 'process') {
    $hoehenmeterColumn = rennen_hoehenmeter_column($connection);

    if ($hoehenmeterColumn === '') {
        $fehler = 'Höhenmeter-Spalte konnte nicht erkannt werden.';
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
}

if (($dashboardPhase ?? '') === 'render') {
?>
<h3 id="rennenformular">Neues Rennen anlegen</h3>
<form method="post" action="veranstalter_dashboard.php#rennenformular">
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

    <label for="hoehenmeter">Höhenmeter:</label><br>
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
            <th>Höhenmeter</th>
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
<?php } ?>
