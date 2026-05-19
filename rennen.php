<?php
/*
 * Autor: Gruppe 16 - bitte fuer die Abgabe den verantwortlichen Namen ergaenzen.
 * Include-Modul fuer Rennen verwalten und anlegen.
 */
if (!defined('VERANSTALTER_DASHBOARD')) {
    header('Location: veranstalter_dashboard.php');
    exit;
}

if (($dashboardPhase ?? '') === 'process') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_value('aktion') === 'rennen_speichern') {
        $datum = post_value('datum');
        $standort = post_value('standort');
        $kilometer = filter_var(post_value('kilometer'), FILTER_VALIDATE_INT);
        $hoehenmeter = filter_var(post_value('hoehenmeter'), FILTER_VALIDATE_INT);
        $maxSteigung = filter_var(post_value('max_steigung'), FILTER_VALIDATE_FLOAT);

        if ($datum === '' || $standort === '' || $kilometer === false || $hoehenmeter === false || $maxSteigung === false) {
            $fehler = 'Bitte alle Renndaten gueltig ausfuellen.';
        } elseif ($kilometer <= 0 || $hoehenmeter < 0 || $maxSteigung < 0) {
            $fehler = 'Kilometer muss groesser 0 sein; Hoehenmeter und Steigung duerfen nicht negativ sein.';
        } elseif (strlen($standort) > 46) {
            $fehler = 'Der Standort darf maximal 46 Zeichen lang sein.';
        } else {
            if (create_rennen($connection, $datum, $standort, $kilometer, $hoehenmeter, $maxSteigung, $nameRaw)) {
                $meldung = 'Rennen wurde gespeichert.';
            } else {
                $fehler = 'Rennen konnte nicht gespeichert werden: ' . mysqli_error($connection);
            }
        }
    }

    $kommendeRennen = array();
    $rennenSql = 'SELECT `Renn-ID`, Datum, Standort, Kilometer, `Höhenmeter` AS Hoehenmeter, MaxSteigung
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

if (($dashboardPhase ?? '') === 'render') {
?>
<h3 id="rennenformular">Neues Rennen anlegen</h3>
<form method="post" action="veranstalter_dashboard.php?bereich=rennen#rennenformular">
    <input type="hidden" name="bereich" value="rennen">
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

<h3>Zukuenftige Rennen</h3>
<?php if (count($kommendeRennen) === 0) { ?>
    <p>Keine zukuenftigen Rennen vorhanden.</p>
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
<?php } ?>
