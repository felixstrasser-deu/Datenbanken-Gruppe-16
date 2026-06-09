<?php
/*
 * Autor: Felix Straßer
 * Include-Modul für Fahrer anlegen, ändern, löschen und anzeigen.
 */
// Schutz vor direktem Aufruf: Das Modul darf nur über das Teamchef-Dashboard laufen.
if (!defined('TEAMCHEF_DASHBOARD')) {
    header('Location: teamchef_dashboard.php');
    exit;
}

// In der Process-Phase werden Formularaktionen verarbeitet und Fahrerdaten geladen.
if (($dashboardPhase) === 'process') {
    $meldungen = array('created' => 'Fahrer wurde angelegt.', 'updated' => 'Fahrer wurde aktualisiert.', 'deleted' => 'Fahrer wurde gelöscht.');
    $fehlertexte = array('exists' => 'Mitarbeiter-ID ist bereits vergeben.', 'notfound' => 'Fahrer wurde nicht gefunden.', 'error' => 'Aktion konnte nicht ausgeführt werden.');
    $status = get_value('status');

    if (isset($meldungen[$status])) {
        $meldung = $meldungen[$status];
    } elseif (isset($fehlertexte[$status])) {
        $fehler = $fehlertexte[$status];
    }

    $formMode = 'create';
    $felder = array('mitarbeiter_id', 'name', 'strasse', 'hausnr', 'plz', 'ort', 'telnr');
    $form = array_fill_keys($felder, '');

    // POST-Anfragen kommen vom Fahrerformular oder vom Löschen-Button.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = post_value('fahrer_action');

        // Löscht einen Fahrer nur aus dem aktuell angemeldeten Team.
        if ($action === 'delete') {
            $deleteId = filter_var(post_value('delete_mitarbeiter_id'), FILTER_VALIDATE_INT);
            $deleteStmt = $deleteId > 0 ? mysqli_prepare($connection, 'DELETE FROM Fahrer WHERE `Mitarbeiter_ID` = ? AND `Team` = ?') : false;

            if ($deleteStmt) {
                mysqli_stmt_bind_param($deleteStmt, 'is', $deleteId, $team);
                mysqli_stmt_execute($deleteStmt);
                $status = mysqli_stmt_affected_rows($deleteStmt) > 0 ? 'deleted' : 'notfound';
                mysqli_stmt_close($deleteStmt);
            } else {
                $status = 'error';
            }

            header('Location: teamchef_dashboard.php?status=' . $status . '#fahrerformular');
            exit;
        }

        // Speichert neue Fahrer oder aktualisiert bestehende Fahrer.
        if ($action === 'save') {
            $formMode = post_value('form_mode') === 'edit' ? 'edit' : 'create';
            foreach ($felder as $feld) {
                $form[$feld] = post_value($feld);
            }

            // Pflichtfelder und einfache Plausibilität prüfen, bevor gespeichert wird.
            $errors = array();
            foreach (array('name', 'strasse', 'hausnr', 'plz', 'ort', 'telnr') as $feld) {
                if ($form[$feld] === '') {
                    $errors[] = 'Bitte alle Pflichtfelder ausfüllen.';
                    break;
                }
            }


            if (!preg_match('/^[0-9]{1,5}$/', $form['plz'])) {
                $errors[] = 'PLZ muss aus maximal 5 Ziffern bestehen.';
            }

            // Im Bearbeiten-Modus bleibt die ursprüngliche Mitarbeiter-ID maßgeblich.
            $mitarbeiterIdRaw = $formMode === 'edit' ? post_value('original_mitarbeiter_id') : $form['mitarbeiter_id'];
            $mitarbeiterId = filter_var($mitarbeiterIdRaw, FILTER_VALIDATE_INT);
            if ($mitarbeiterId === false || $mitarbeiterId <= 0) {
                $errors[] = $formMode === 'edit' ? 'Ungültige Mitarbeiter-ID für Bearbeitung.' : 'Mitarbeiter-ID muss eine positive Ganzzahl sein.';
            }
            if ($formMode === 'edit') {
                $form['mitarbeiter_id'] = $mitarbeiterIdRaw;
            }

            if (count($errors) === 0) {
                // Die eigentliche Insert-/Update-Logik liegt in der gemeinsamen Hilfsfunktion.
                list($ok, $message) = fahrerSpeichern($connection, $formMode, $team, $mitarbeiterId, $form['name'], $form['strasse'], $form['hausnr'], $form['plz'], $form['ort'], $form['telnr']);
                if ($ok || $message === 'Mitarbeiter-ID ist bereits vergeben.') {
                    $status = $ok ? ($formMode === 'create' ? 'created' : 'updated') : 'exists';
                    header('Location: teamchef_dashboard.php?status=' . $status . '#fahrerformular');
                    exit;
                }
                $fehler = $message;
            } else {
                $fehler = implode(' ', $errors);
            }
        }
    }

    // Wird eine edit-ID übergeben, werden die vorhandenen Daten ins Formular geladen.
    $editId = filter_var(get_value('edit'), FILTER_VALIDATE_INT);
    if ($editId !== false && $editId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $editStmt = mysqli_prepare($connection, 'SELECT `Mitarbeiter_ID`, `Name`, `Strasse`, `HausNr`, `PLZ`, `Ort`, `TelNr` FROM Fahrer WHERE `Mitarbeiter_ID` = ? AND `Team` = ? LIMIT 1');
        if ($editStmt) {
            mysqli_stmt_bind_param($editStmt, 'is', $editId, $team);
            mysqli_stmt_execute($editStmt);
            mysqli_stmt_bind_result($editStmt, $dbId, $dbName, $dbStrasse, $dbHausnr, $dbPlz, $dbOrt, $dbTelnr);
            if (mysqli_stmt_fetch($editStmt)) {
                $formMode = 'edit';
                $form = array('mitarbeiter_id' => $dbId, 'name' => $dbName, 'strasse' => $dbStrasse, 'hausnr' => $dbHausnr, 'plz' => $dbPlz, 'ort' => $dbOrt, 'telnr' => $dbTelnr);
            } else {
                $fehler = 'Fahrer wurde nicht gefunden.';
            }
            mysqli_stmt_close($editStmt);
        }
    }

    // Alle Fahrer des eingeloggten Teams für die Tabellenanzeige laden.
    $fahrer = array();
    $fahrerStmt = mysqli_prepare($connection, 'SELECT `Mitarbeiter_ID`, `Name`, `Strasse`, `HausNr`, `PLZ`, `Ort`, `TelNr` FROM Fahrer WHERE `Team` = ? ORDER BY `Name`');
    if ($fahrerStmt) {
        mysqli_stmt_bind_param($fahrerStmt, 's', $team);
        mysqli_stmt_execute($fahrerStmt);
        mysqli_stmt_bind_result($fahrerStmt, $dbId, $dbName, $dbStrasse, $dbHausnr, $dbPlz, $dbOrt, $dbTelnr);
        while (mysqli_stmt_fetch($fahrerStmt)) {
            $fahrer[] = array('Mitarbeiter_ID' => $dbId, 'Name' => $dbName, 'Strasse' => $dbStrasse, 'HausNr' => $dbHausnr, 'PLZ' => $dbPlz, 'Ort' => $dbOrt, 'TelNr' => $dbTelnr);
        }
        mysqli_stmt_close($fahrerStmt);
    }
}

// In der Render-Phase werden Formular und Fahrerliste ausgegeben.
if (($dashboardPhase) === 'render') {
$inputs = array(
    'mitarbeiter_id' => array('Mitarbeiter-ID', 'number', '1', ''),
    'name' => array('Name', 'text', '', '50'),
    'strasse' => array('Straße', 'text', '', '50'),
    'hausnr' => array('Hausnummer', 'text', '', '50'),
    'plz' => array('PLZ', 'text', '', '5'),
    'ort' => array('Ort', 'text', '', '50'),
    'telnr' => array('Telefon', 'text', '', '50'),
);
?>
<h3 id="fahrerformular">Fahrer anlegen / bearbeiten</h3>
<form method="post" action="teamchef_dashboard.php#fahrerformular">
    <input type="hidden" name="bereich" value="fahrer">
    <input type="hidden" name="fahrer_action" value="save">
    <input type="hidden" name="form_mode" value="<?php echo e($formMode); ?>">
    <input type="hidden" name="original_mitarbeiter_id" value="<?php echo e($form['mitarbeiter_id']); ?>">

    <?php foreach ($inputs as $name => $daten) { ?>
        <label for="<?php echo e($name); ?>"><?php echo e($daten[0]); ?>:</label><br>
        <input type="<?php echo e($daten[1]); ?>" id="<?php echo e($name); ?>" name="<?php echo e($name); ?>"
               value="<?php echo e($form[$name]); ?>"
               <?php if ($daten[2] !== '') echo 'min="' . e($daten[2]) . '"'; ?>
               <?php if ($daten[3] !== '') echo 'maxlength="' . e($daten[3]) . '"'; ?>
               <?php if ($name === 'mitarbeiter_id' && $formMode === 'edit') echo 'readonly'; ?> required>
        <br><br>
    <?php } ?>

    <button type="submit"><?php echo $formMode === 'edit' ? 'Fahrer aktualisieren' : 'Fahrer anlegen'; ?></button>
    <?php if ($formMode === 'edit') { ?>
        <a href="teamchef_dashboard.php?bereich=fahrer">Abbrechen</a>
    <?php } ?>
</form>

<h3 id="fahrerliste">Fahrer im Team</h3>
<?php if (count($fahrer) === 0) { ?>
    <p>Es sind noch keine Fahrer für dieses Team angelegt.</p>
<?php } else { ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>Mitarbeiter-ID</th><th>Name</th><th>Straße</th><th>Hausnummer</th><th>PLZ</th><th>Ort</th><th>Telefon</th><th>Aktionen</th>
        </tr>
        <?php foreach ($fahrer as $eintrag) { ?>
            <tr>
                <td><?php echo e($eintrag['Mitarbeiter_ID']); ?></td>
                <td><?php echo e($eintrag['Name']); ?></td>
                <td><?php echo e($eintrag['Strasse']); ?></td>
                <td><?php echo e($eintrag['HausNr']); ?></td>
                <td><?php echo e($eintrag['PLZ']); ?></td>
                <td><?php echo e($eintrag['Ort']); ?></td>
                <td><?php echo e($eintrag['TelNr']); ?></td>
                <td>
                    <a href="teamchef_dashboard.php?bereich=fahrer&edit=<?php echo urlencode((string) $eintrag['Mitarbeiter_ID']); ?>">Bearbeiten</a>
                    <form method="post" action="teamchef_dashboard.php#fahrerformular" style="display:inline;">
                        <input type="hidden" name="bereich" value="fahrer">
                        <input type="hidden" name="fahrer_action" value="delete">
                        <input type="hidden" name="delete_mitarbeiter_id" value="<?php echo e($eintrag['Mitarbeiter_ID']); ?>">
                        <button type="submit">Löschen</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </table>
<?php } ?>
<?php } ?>
