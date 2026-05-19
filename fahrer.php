<?php
/*
 * Autor: Gruppe 16 - bitte fuer die Abgabe den verantwortlichen Namen ergaenzen.
 * Include-Modul fuer Fahrer anlegen, aendern, loeschen und anzeigen.
 */
if (!defined('TEAMCHEF_DASHBOARD')) {
    header('Location: teamchef_dashboard.php');
    exit;
}

if (($dashboardPhase ?? '') === 'process') {
    $status = get_value('status');
    if ($status === 'created') {
        $meldung = 'Fahrer wurde angelegt.';
    } elseif ($status === 'updated') {
        $meldung = 'Fahrer wurde aktualisiert.';
    } elseif ($status === 'deleted') {
        $meldung = 'Fahrer wurde geloescht.';
    } elseif ($status === 'exists') {
        $fehler = 'Mitarbeiter-ID ist bereits vergeben.';
    } elseif ($status === 'notfound') {
        $fehler = 'Fahrer wurde nicht gefunden.';
    } elseif ($status === 'error') {
        $fehler = 'Aktion konnte nicht ausgefuehrt werden.';
    }

    $formMode = 'create';
    $form = array(
        'mitarbeiter_id' => '',
        'name' => '',
        'strasse' => '',
        'hausnr' => '',
        'plz' => '',
        'ort' => '',
        'telnr' => '',
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = post_value('fahrer_action');

        if ($action === 'delete') {
            $deleteId = filter_var(post_value('delete_mitarbeiter_id'), FILTER_VALIDATE_INT);

            if ($deleteId === false || $deleteId <= 0) {
                header('Location: teamchef_dashboard.php?bereich=fahrer&status=error');
                exit;
            }

            $deleteStmt = mysqli_prepare($connection, 'DELETE FROM Fahrer WHERE `Mitarbeiter_ID` = ? AND `Team` = ?');
            if (!$deleteStmt) {
                header('Location: teamchef_dashboard.php?bereich=fahrer&status=error');
                exit;
            }

            mysqli_stmt_bind_param($deleteStmt, 'is', $deleteId, $teamRaw);
            mysqli_stmt_execute($deleteStmt);
            $deletedRows = mysqli_stmt_affected_rows($deleteStmt);
            mysqli_stmt_close($deleteStmt);

            header('Location: teamchef_dashboard.php?bereich=fahrer&status=' . ($deletedRows > 0 ? 'deleted' : 'notfound'));
            exit;
        }

        if ($action === 'save') {
            $formMode = post_value('form_mode') === 'edit' ? 'edit' : 'create';
            $originalIdRaw = post_value('original_mitarbeiter_id');

            $form['mitarbeiter_id'] = post_value('mitarbeiter_id');
            $form['name'] = post_value('name');
            $form['strasse'] = post_value('strasse');
            $form['hausnr'] = post_value('hausnr');
            $form['plz'] = post_value('plz');
            $form['ort'] = post_value('ort');
            $form['telnr'] = post_value('telnr');

            $errors = array();

            if ($form['name'] === '' || $form['strasse'] === '' || $form['hausnr'] === '' || $form['plz'] === '' || $form['ort'] === '' || $form['telnr'] === '') {
                $errors[] = 'Bitte alle Pflichtfelder ausfuellen.';
            }

            if (strlen($form['name']) > 46 || strlen($form['strasse']) > 46 || strlen($form['hausnr']) > 46 || strlen($form['ort']) > 46 || strlen($form['telnr']) > 46) {
                $errors[] = 'Textfelder duerfen maximal 46 Zeichen lang sein.';
            }

            $plz = $form['plz'];
            if (!preg_match('/^[0-9]{1,5}$/', $plz)) {
                $errors[] = 'PLZ muss aus maximal 5 Ziffern bestehen.';
            }

            if ($formMode === 'create') {
                $mitarbeiterId = filter_var($form['mitarbeiter_id'], FILTER_VALIDATE_INT);
                if ($mitarbeiterId === false || $mitarbeiterId <= 0) {
                    $errors[] = 'Mitarbeiter-ID muss eine positive Ganzzahl sein.';
                }
            } else {
                $mitarbeiterId = filter_var($originalIdRaw, FILTER_VALIDATE_INT);
                if ($mitarbeiterId === false || $mitarbeiterId <= 0) {
                    $errors[] = 'Ungueltige Mitarbeiter-ID fuer Bearbeitung.';
                }
                $form['mitarbeiter_id'] = $originalIdRaw;
            }

            if (count($errors) === 0) {
                list($ok, $message) = save_fahrer($connection, $formMode, $teamRaw, $mitarbeiterId, $form['name'], $form['strasse'], $form['hausnr'], $plz, $form['ort'], $form['telnr']);

                if ($ok) {
                    header('Location: teamchef_dashboard.php?bereich=fahrer&status=' . ($formMode === 'create' ? 'created' : 'updated'));
                    exit;
                }

                if ($message === 'Mitarbeiter-ID ist bereits vergeben.') {
                    header('Location: teamchef_dashboard.php?bereich=fahrer&status=exists');
                    exit;
                }

                $fehler = $message;
            } else {
                $fehler = implode(' ', $errors);
            }
        }
    }

    $editIdRaw = get_value('edit');
    if ($editIdRaw !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $editId = filter_var($editIdRaw, FILTER_VALIDATE_INT);

        if ($editId !== false && $editId > 0) {
            $editSql = 'SELECT `Mitarbeiter_ID`, `Name`, `Straße` AS Strasse, `HausNr`, `PLZ`, `Ort`, `TelNr` FROM Fahrer WHERE `Mitarbeiter_ID` = ? AND `Team` = ? LIMIT 1';
            $editStmt = mysqli_prepare($connection, $editSql);

            if ($editStmt) {
                mysqli_stmt_bind_param($editStmt, 'is', $editId, $teamRaw);
                mysqli_stmt_execute($editStmt);
                mysqli_stmt_bind_result($editStmt, $dbId, $dbName, $dbStrasse, $dbHausnr, $dbPlz, $dbOrt, $dbTelnr);

                if (mysqli_stmt_fetch($editStmt)) {
                    $formMode = 'edit';
                    $form['mitarbeiter_id'] = (string) $dbId;
                    $form['name'] = (string) $dbName;
                    $form['strasse'] = (string) $dbStrasse;
                    $form['hausnr'] = (string) $dbHausnr;
                    $form['plz'] = (string) $dbPlz;
                    $form['ort'] = (string) $dbOrt;
                    $form['telnr'] = (string) $dbTelnr;
                } else {
                    $fehler = 'Fahrer wurde nicht gefunden.';
                }

                mysqli_stmt_close($editStmt);
            } else {
                $fehler = 'Fahrer konnte nicht geladen werden.';
            }
        } else {
            $fehler = 'Ungueltige Bearbeitungs-ID.';
        }
    }

    $fahrer = array();
    $fahrerSql = 'SELECT `Mitarbeiter_ID`, `Name`, `Straße` AS Strasse, `HausNr`, `PLZ`, `Ort`, `TelNr` FROM Fahrer WHERE `Team` = ? ORDER BY `Name`';
    $fahrerStmt = mysqli_prepare($connection, $fahrerSql);

    if ($fahrerStmt) {
        mysqli_stmt_bind_param($fahrerStmt, 's', $teamRaw);
        mysqli_stmt_execute($fahrerStmt);
        mysqli_stmt_bind_result($fahrerStmt, $dbId, $dbName, $dbStrasse, $dbHausnr, $dbPlz, $dbOrt, $dbTelnr);

        while (mysqli_stmt_fetch($fahrerStmt)) {
            $fahrer[] = array(
                'Mitarbeiter_ID' => $dbId,
                'Name' => $dbName,
                'Strasse' => $dbStrasse,
                'HausNr' => $dbHausnr,
                'PLZ' => $dbPlz,
                'Ort' => $dbOrt,
                'TelNr' => $dbTelnr,
            );
        }

        mysqli_stmt_close($fahrerStmt);
    }
}

if (($dashboardPhase ?? '') === 'render') {
?>
<h3 id="fahrerformular">Fahrer anlegen / bearbeiten</h3>
<form method="post" action="teamchef_dashboard.php?bereich=fahrer">
    <input type="hidden" name="bereich" value="fahrer">
    <input type="hidden" name="fahrer_action" value="save">
    <input type="hidden" name="form_mode" value="<?php echo e($formMode); ?>">
    <input type="hidden" name="original_mitarbeiter_id" value="<?php echo e($form['mitarbeiter_id']); ?>">

    <label for="mitarbeiter_id">Mitarbeiter-ID:</label><br>
    <input type="number" id="mitarbeiter_id" name="mitarbeiter_id" min="1" value="<?php echo e($form['mitarbeiter_id']); ?>" <?php if ($formMode === 'edit') echo 'readonly'; ?> required>
    <br><br>

    <label for="name">Name:</label><br>
    <input type="text" id="name" name="name" maxlength="46" value="<?php echo e($form['name']); ?>" required>
    <br><br>

    <label for="strasse">Straße:</label><br>
    <input type="text" id="strasse" name="strasse" maxlength="46" value="<?php echo e($form['strasse']); ?>" required>
    <br><br>

    <label for="hausnr">Hausnummer:</label><br>
    <input type="text" id="hausnr" name="hausnr" maxlength="46" value="<?php echo e($form['hausnr']); ?>" required>
    <br><br>

    <label for="plz">PLZ:</label><br>
    <input type="text" id="plz" name="plz" maxlength="5" value="<?php echo e($form['plz']); ?>" required>
    <br><br>

    <label for="ort">Ort:</label><br>
    <input type="text" id="ort" name="ort" maxlength="46" value="<?php echo e($form['ort']); ?>" required>
    <br><br>

    <label for="telnr">Telefon:</label><br>
    <input type="text" id="telnr" name="telnr" maxlength="46" value="<?php echo e($form['telnr']); ?>" required>
    <br><br>

    <button type="submit"><?php echo $formMode === 'edit' ? 'Fahrer aktualisieren' : 'Fahrer anlegen'; ?></button>
    <?php if ($formMode === 'edit') { ?>
        <a href="teamchef_dashboard.php?bereich=fahrer">Abbrechen</a>
    <?php } ?>
</form>

<h3 id="fahrerliste">Fahrer im Team</h3>
<?php if (count($fahrer) === 0) { ?>
    <p>Es sind noch keine Fahrer fuer dieses Team angelegt.</p>
<?php } else { ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>Mitarbeiter-ID</th>
            <th>Name</th>
            <th>Straße</th>
            <th>Hausnummer</th>
            <th>PLZ</th>
            <th>Ort</th>
            <th>Telefon</th>
            <th>Aktionen</th>
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
                    <form method="post" action="teamchef_dashboard.php?bereich=fahrer" style="display:inline;">
                        <input type="hidden" name="bereich" value="fahrer">
                        <input type="hidden" name="fahrer_action" value="delete">
                        <input type="hidden" name="delete_mitarbeiter_id" value="<?php echo e($eintrag['Mitarbeiter_ID']); ?>">
                        <button type="submit">Loeschen</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </table>
<?php } ?>
<?php } ?>
