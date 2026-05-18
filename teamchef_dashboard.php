<?php
session_start();
require 'db.php';
require 'functions.php';
require_once 'TrainingStats.php';

if (!isset($_SESSION['rolle']) || $_SESSION['rolle'] !== 'teamchef') {
    header('Location: index.php');
    exit;
}

mysqli_set_charset($connection, 'utf8mb4');

$teamRaw = (string) ($_SESSION['team'] ?? '');
$loginname = e($_SESSION['loginname'] ?? '');
$team = e($teamRaw);
$meldung = '';
$fehler = '';
$strasseColumn = '';
$auswertungStatistik = array();
$auswertungFahrer = '';
$auswertungZiel = '';
$auswertungVon = '';
$auswertungBis = '';

$strasseColumn = fahrer_strasse_column($connection);

if ($strasseColumn === '') {
    $fehler = 'Straßen-Spalte konnte nicht erkannt werden.';
}

$strasseColumnSql = '`' . str_replace('`', '``', $strasseColumn) . '`';

$status = trim((string) ($_GET['status'] ?? ''));
if ($status === 'created') {
    $meldung = 'Fahrer wurde angelegt.';
} elseif ($status === 'updated') {
    $meldung = 'Fahrer wurde aktualisiert.';
} elseif ($status === 'deleted') {
    $meldung = 'Fahrer wurde gelöscht.';
} elseif ($status === 'exists') {
    $fehler = 'Mitarbeiter-ID ist bereits vergeben.';
} elseif ($status === 'notfound') {
    $fehler = 'Fahrer wurde nicht gefunden.';
} elseif ($status === 'error') {
    $fehler = 'Aktion konnte nicht ausgeführt werden.';
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

if ($strasseColumn !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_value('fahrer_action');

    if ($action === 'delete') {
        $deleteIdRaw = post_value('delete_mitarbeiter_id');
        $deleteId = filter_var($deleteIdRaw, FILTER_VALIDATE_INT);

        if ($deleteId === false || $deleteId <= 0) {
            header('Location: teamchef_dashboard.php?status=error');
            exit;
        }

        $deleteSql = 'DELETE FROM Fahrer WHERE `Mitarbeiter_ID` = ? AND `Team` = ?';
        $deleteStmt = mysqli_prepare($connection, $deleteSql);

        if (!$deleteStmt) {
            header('Location: teamchef_dashboard.php?status=error');
            exit;
        }

        mysqli_stmt_bind_param($deleteStmt, 'is', $deleteId, $teamRaw);
        mysqli_stmt_execute($deleteStmt);
        $deletedRows = mysqli_stmt_affected_rows($deleteStmt);
        mysqli_stmt_close($deleteStmt);

        if ($deletedRows > 0) {
            header('Location: teamchef_dashboard.php?status=deleted');
            exit;
        }

        header('Location: teamchef_dashboard.php?status=notfound');
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
            $errors[] = 'Bitte alle Pflichtfelder ausfüllen.';
        }

        if (strlen($form['name']) > 46 || strlen($form['strasse']) > 46 || strlen($form['hausnr']) > 46 || strlen($form['ort']) > 46 || strlen($form['telnr']) > 46) {
            $errors[] = 'Textfelder dürfen maximal 46 Zeichen lang sein.';
        }

        $plz = $form['plz'];
        if (!preg_match('/^[0-9]{1,5}$/', $plz)) {
            $errors[] = 'PLZ muss aus maximal 5 Ziffern bestehen.';
        }

        $mitarbeiterId = 0;
        if ($formMode === 'create') {
            $mitarbeiterId = filter_var($form['mitarbeiter_id'], FILTER_VALIDATE_INT);
            if ($mitarbeiterId === false || $mitarbeiterId <= 0) {
                $errors[] = 'Mitarbeiter-ID muss eine positive Ganzzahl sein.';
            }
        } else {
            $mitarbeiterId = filter_var($originalIdRaw, FILTER_VALIDATE_INT);
            if ($mitarbeiterId === false || $mitarbeiterId <= 0) {
                $errors[] = 'Ungültige Mitarbeiter-ID für Bearbeitung.';
            }
            $form['mitarbeiter_id'] = $originalIdRaw;
        }

        if (count($errors) === 0) {
            list($ok, $message) = save_fahrer(
                $connection,
                $formMode,
                $teamRaw,
                $mitarbeiterId,
                $form['name'],
                $form['strasse'],
                $form['hausnr'],
                $plz,
                $form['ort'],
                $form['telnr']
            );

            if ($ok) {
                header('Location: teamchef_dashboard.php?status=' . ($formMode === 'create' ? 'created' : 'updated'));
                exit;
            }

            if ($message === 'Mitarbeiter-ID ist bereits vergeben.') {
                header('Location: teamchef_dashboard.php?status=exists');
                exit;
            } else {
                $fehler = $message;
            }
        } else {
            $fehler = implode(' ', $errors);
        }
    }
}

$taskAction = post_value('task_action');

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $taskAction === 'anmeldung_speichern') {
    $rennenId = filter_var(post_value('anmeldung_rennen_id'), FILTER_VALIDATE_INT);
    $fahrerIds = $_POST['anmeldung_fahrer'] ?? array();
    $gespeichert = 0;
    $uebersprungen = 0;

    if ($rennenId === false || $rennenId <= 0) {
        $fehler = 'Bitte ein gültiges Rennen auswählen.';
    } else {
        foreach ($fahrerIds as $fahrerIdRaw) {
            $fahrerId = filter_var($fahrerIdRaw, FILTER_VALIDATE_INT);
            if ($fahrerId === false || $fahrerId <= 0) {
                continue;
            }

            $checkStmt = mysqli_prepare($connection, 'SELECT 1 FROM Fahrer WHERE Mitarbeiter_ID = ? AND Team = ? LIMIT 1');
            if (!$checkStmt) {
                $fehler = 'Fahrerpruefung konnte nicht vorbereitet werden.';
                break;
            }

            mysqli_stmt_bind_param($checkStmt, 'is', $fahrerId, $teamRaw);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);
            $fahrerOk = mysqli_stmt_num_rows($checkStmt) > 0;
            mysqli_stmt_close($checkStmt);

            if (!$fahrerOk || fahrer_ist_angemeldet($connection, $rennenId, $fahrerId)) {
                $uebersprungen++;
                continue;
            }

            if (melde_fahrer_an($connection, $rennenId, $teamRaw, $fahrerId)) {
                $gespeichert++;
            } else {
                $fehler = 'Mindestens eine Anmeldung konnte nicht gespeichert werden: ' . mysqli_error($connection);
                break;
            }
        }

        if ($fehler === '') {
            $meldung = $gespeichert . ' Fahrer wurden angemeldet.';
            if ($uebersprungen > 0) {
                $meldung .= ' ' . $uebersprungen . ' Eintraege wurden uebersprungen.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $taskAction === 'kopieren_speichern') {
    $quelle = filter_var(post_value('kopieren_quelle'), FILTER_VALIDATE_INT);
    $ziel = filter_var(post_value('kopieren_ziel'), FILTER_VALIDATE_INT);

    if ($quelle === false || $ziel === false || $quelle <= 0 || $ziel <= 0) {
        $fehler = 'Bitte Quell- und Zielrennen auswählen.';
    } elseif ($quelle === $ziel) {
        $fehler = 'Quelle und Ziel muessen unterschiedliche Rennen sein.';
    } else {
        $sourceStmt = mysqli_prepare($connection, 'SELECT Mitarbeiter FROM Anmeldung WHERE Radrennen = ? AND Team = ? ORDER BY Startnummer');
        if ($sourceStmt) {
            mysqli_stmt_bind_param($sourceStmt, 'is', $quelle, $teamRaw);
            mysqli_stmt_execute($sourceStmt);
            mysqli_stmt_bind_result($sourceStmt, $mitarbeiterId);

            $fahrerIds = array();
            while (mysqli_stmt_fetch($sourceStmt)) {
                $fahrerIds[] = (int) $mitarbeiterId;
            }

            mysqli_stmt_close($sourceStmt);

            if (count($fahrerIds) === 0) {
                $fehler = 'Für das Quellrennen gibt es keine Anmeldungen dieses Teams.';
            } else {
                $kopiert = 0;
                $uebersprungen = 0;

                foreach ($fahrerIds as $fahrerId) {
                    if (fahrer_ist_angemeldet($connection, $ziel, $fahrerId)) {
                        $uebersprungen++;
                        continue;
                    }

                    if (melde_fahrer_an($connection, $ziel, $teamRaw, $fahrerId)) {
                        $kopiert++;
                    } else {
                        $fehler = 'Kopieren wurde abgebrochen: ' . mysqli_error($connection);
                        break;
                    }
                }

                if ($fehler === '') {
                    $meldung = $kopiert . ' Anmeldungen wurden kopiert.';
                    if ($uebersprungen > 0) {
                        $meldung .= ' ' . $uebersprungen . ' bereits vorhandene Anmeldungen wurden uebersprungen.';
                    }
                }
            }
        } else {
            $fehler = 'Quellrennen konnte nicht gelesen werden.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $taskAction === 'auswertung_anzeigen') {
    $auswertungFahrer = post_value('auswertung_fahrer');
    $auswertungZiel = post_value('auswertung_trainingsziel');
    $auswertungVon = post_value('auswertung_von');
    $auswertungBis = post_value('auswertung_bis');

    if ($auswertungVon !== '' && $auswertungBis !== '' && $auswertungVon > $auswertungBis) {
        $fehler = 'Das Von-Datum darf nicht nach dem Bis-Datum liegen.';
    } else {
        $fahrerFilter = $auswertungFahrer !== '' ? (int) $auswertungFahrer : null;
        $stats = new TrainingStats($fahrerFilter, $auswertungZiel, $auswertungVon, $auswertungBis);

        if ($stats->loadFromDatabase($connection, $teamRaw)) {
            $auswertungStatistik = $stats->getMonatsStatistik();
        } else {
            $fehler = 'Auswertung konnte nicht geladen werden.';
        }
    }
}

$editIdRaw = trim((string) ($_GET['edit'] ?? ''));
if ($strasseColumn !== '' && $editIdRaw !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $editId = filter_var($editIdRaw, FILTER_VALIDATE_INT);

    if ($editId !== false && $editId > 0) {
        $editSql = 'SELECT `Mitarbeiter_ID`, `Name`, ' . $strasseColumnSql . ' AS Strasse, `HausNr`, `PLZ`, `Ort`, `TelNr` FROM Fahrer WHERE `Mitarbeiter_ID` = ? AND `Team` = ? LIMIT 1';
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
        $fehler = 'Ungültige Bearbeitungs-ID.';
    }
}

$fahrer = array();
if ($strasseColumn !== '') {
    $fahrerSql = 'SELECT `Mitarbeiter_ID`, `Name`, ' . $strasseColumnSql . ' AS Strasse, `HausNr`, `PLZ`, `Ort`, `TelNr` FROM Fahrer WHERE `Team` = ? ORDER BY `Name`';
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

$teamFahrerAuswahl = array();
$fahrerAuswahlStmt = mysqli_prepare($connection, 'SELECT Mitarbeiter_ID, Name FROM Fahrer WHERE Team = ? ORDER BY Name');
if ($fahrerAuswahlStmt) {
    mysqli_stmt_bind_param($fahrerAuswahlStmt, 's', $teamRaw);
    mysqli_stmt_execute($fahrerAuswahlStmt);
    mysqli_stmt_bind_result($fahrerAuswahlStmt, $auswahlId, $auswahlName);

    while (mysqli_stmt_fetch($fahrerAuswahlStmt)) {
        $teamFahrerAuswahl[] = array('Mitarbeiter_ID' => $auswahlId, 'Name' => $auswahlName);
    }

    mysqli_stmt_close($fahrerAuswahlStmt);
}

$trainingsziele = array();
$trainingszielResult = mysqli_query($connection, 'SELECT Trainingsziel FROM Trainingsziel ORDER BY Trainingsziel');
if ($trainingszielResult) {
    while ($row = mysqli_fetch_assoc($trainingszielResult)) {
        $trainingsziele[] = $row['Trainingsziel'];
    }
}

$zukuenftigeRennen = array();
$rennenResult = mysqli_query($connection, 'SELECT `Renn-ID`, Datum, Standort FROM Radrennen WHERE Datum >= CURDATE() ORDER BY Datum ASC, `Renn-ID` ASC');
if ($rennenResult) {
    while ($row = mysqli_fetch_assoc($rennenResult)) {
        $zukuenftigeRennen[] = $row;
    }
}

$alleRennen = array();
$alleRennenResult = mysqli_query($connection, 'SELECT `Renn-ID`, Datum, Standort FROM Radrennen ORDER BY Datum DESC, `Renn-ID` DESC');
if ($alleRennenResult) {
    while ($row = mysqli_fetch_assoc($alleRennenResult)) {
        $alleRennen[] = $row;
    }
}

$anmeldungRennenId = filter_var(get_value('anmeldung_rennen_id'), FILTER_VALIDATE_INT);
$anmeldungAnzahl = filter_var(get_value('anmeldung_anzahl'), FILTER_VALIDATE_INT);
if ($anmeldungAnzahl === false || $anmeldungAnzahl < 1) {
    $anmeldungAnzahl = 0;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Teamchef Dashboard</title>
</head>
<body>
<h1>Teamchef Dashboard</h1>
<p>Angemeldet als: <?php echo $loginname; ?></p>
<p>Team: <?php echo $team; ?></p>

<?php if ($meldung !== '') { ?>
    <p style="color: green;"><?php echo e($meldung); ?></p>
<?php } ?>
<?php if ($fehler !== '') { ?>
    <p style="color: red;"><?php echo e($fehler); ?></p>
<?php } ?>

<h2>Aktivitäten</h2>
<table border="1" cellpadding="8" cellspacing="0">
    <tr>
        <th align="left">Bereich</th>
        <th align="left">Aktion</th>
    </tr>
    <tr>
        <td>Team und Fahrer</td>
        <td>
            <a href="#fahrerformular">Fahrer anlegen</a> |
            <a href="#fahrerliste">Fahrer bearbeiten oder löschen</a>
        </td>
    </tr>
    <tr>
        <td>Rennen</td>
        <td>
            <a href="#anmeldung">Fahrer zu Rennen anmelden</a> |
            <a href="#kopieren">Anmeldungen kopieren</a>
        </td>
    </tr>
    <tr>
        <td>Training und Auswertung</td>
        <td>
            <a href="#training">Training erfassen</a> |
            <a href="#auswertung">Auswertung anzeigen</a>
        </td>
    </tr>
</table>

<h3 id="fahrerformular">Fahrer anlegen / bearbeiten</h3>
<form method="post" action="teamchef_dashboard.php">
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
        <a href="teamchef_dashboard.php">Abbrechen</a>
    <?php } ?>
</form>

<h3 id="fahrerliste">Fahrer im Team</h3>
<?php if (count($fahrer) === 0) { ?>
    <p>Es sind noch keine Fahrer für dieses Team angelegt.</p>
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
                    <a href="teamchef_dashboard.php?edit=<?php echo urlencode((string) $eintrag['Mitarbeiter_ID']); ?>">Bearbeiten</a>
                    <form method="post" action="teamchef_dashboard.php" style="display:inline;">
                        <input type="hidden" name="fahrer_action" value="delete">
                        <input type="hidden" name="delete_mitarbeiter_id" value="<?php echo e($eintrag['Mitarbeiter_ID']); ?>">
                        <button type="submit" onclick="return confirm('Fahrer wirklich löschen?');">Löschen</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </table>
<?php } ?>

<hr>

<h3 id="anmeldung">Fahrer zu Rennen anmelden</h3>
<form method="get" action="teamchef_dashboard.php#anmeldung">
    <label for="anmeldung_rennen_id">Zukünftiges Rennen:</label><br>
    <select name="anmeldung_rennen_id" id="anmeldung_rennen_id" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($zukuenftigeRennen as $rennenEintrag) { ?>
            <option value="<?php echo e($rennenEintrag['Renn-ID']); ?>" <?php if ((string) $anmeldungRennenId === (string) $rennenEintrag['Renn-ID']) echo 'selected'; ?>>
                <?php echo e($rennenEintrag['Renn-ID'] . ' - ' . $rennenEintrag['Datum'] . ' - ' . $rennenEintrag['Standort']); ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

    <label for="anmeldung_anzahl">Anzahl Fahrer:</label><br>
    <input type="number" name="anmeldung_anzahl" id="anmeldung_anzahl" min="1" value="<?php echo $anmeldungAnzahl > 0 ? e($anmeldungAnzahl) : ''; ?>" required>
    <br><br>

    <button type="submit">Erfassung anzeigen</button>
</form>

<?php if ($anmeldungRennenId !== false && $anmeldungRennenId > 0 && $anmeldungAnzahl > 0) { ?>
    <?php if (count($teamFahrerAuswahl) === 0) { ?>
        <p>Für dieses Team sind keine Fahrer angelegt.</p>
    <?php } else { ?>
        <form method="post" action="teamchef_dashboard.php#anmeldung">
            <input type="hidden" name="task_action" value="anmeldung_speichern">
            <input type="hidden" name="anmeldung_rennen_id" value="<?php echo e($anmeldungRennenId); ?>">
            <table border="1" cellpadding="5" cellspacing="0">
                <tr>
                    <th>Nr.</th>
                    <th>Fahrer</th>
                </tr>
                <?php for ($i = 0; $i < $anmeldungAnzahl; $i++) { ?>
                    <tr>
                        <td><?php echo e($i + 1); ?></td>
                        <td>
                            <select name="anmeldung_fahrer[]" required>
                                <option value="">Bitte wählen</option>
                                <?php foreach ($teamFahrerAuswahl as $fahrerOption) { ?>
                                    <option value="<?php echo e($fahrerOption['Mitarbeiter_ID']); ?>">
                                        <?php echo e($fahrerOption['Mitarbeiter_ID'] . ' - ' . $fahrerOption['Name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </td>
                    </tr>
                <?php } ?>
            </table>
            <br>
            <button type="submit">Anmeldungen speichern</button>
        </form>
    <?php } ?>
<?php } ?>

<hr>

<h3 id="kopieren">Anmeldungen kopieren</h3>
<form method="post" action="teamchef_dashboard.php#kopieren">
    <input type="hidden" name="task_action" value="kopieren_speichern">

    <label for="kopieren_quelle">Anmeldungen aus Rennen:</label><br>
    <select name="kopieren_quelle" id="kopieren_quelle" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($alleRennen as $rennenEintrag) { ?>
            <option value="<?php echo e($rennenEintrag['Renn-ID']); ?>">
                <?php echo e($rennenEintrag['Renn-ID'] . ' - ' . $rennenEintrag['Datum'] . ' - ' . $rennenEintrag['Standort']); ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

    <label for="kopieren_ziel">Kopieren nach zukünftigem Rennen:</label><br>
    <select name="kopieren_ziel" id="kopieren_ziel" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($zukuenftigeRennen as $rennenEintrag) { ?>
            <option value="<?php echo e($rennenEintrag['Renn-ID']); ?>">
                <?php echo e($rennenEintrag['Renn-ID'] . ' - ' . $rennenEintrag['Datum'] . ' - ' . $rennenEintrag['Standort']); ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

    <button type="submit">Anmeldungen kopieren</button>
</form>

<hr>

<h3 id="training">Training erfassen</h3>
<form method="post" action="teamchef_dashboard.php#training">
    <input type="hidden" name="task_action" value="training_speichern">

    <label for="training_mitarbeiter">Fahrer:</label><br>
    <select name="training_mitarbeiter" id="training_mitarbeiter" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($teamFahrerAuswahl as $fahrerOption) { ?>
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
        <?php foreach ($trainingsziele as $zielOption) { ?>
            <option value="<?php echo e($zielOption); ?>"><?php echo e($zielOption); ?></option>
        <?php } ?>
    </select>
    <br><br>

    <button type="submit">Training speichern</button>
</form>

<hr>

<h3 id="auswertung">Auswertung anzeigen</h3>
<form method="post" action="teamchef_dashboard.php#auswertung">
    <input type="hidden" name="task_action" value="auswertung_anzeigen">

    <label for="auswertung_fahrer">Fahrer:</label><br>
    <select name="auswertung_fahrer" id="auswertung_fahrer">
        <option value="">Alle Teamfahrer</option>
        <?php foreach ($teamFahrerAuswahl as $fahrerOption) { ?>
            <option value="<?php echo e($fahrerOption['Mitarbeiter_ID']); ?>" <?php if ((string) $auswertungFahrer === (string) $fahrerOption['Mitarbeiter_ID']) echo 'selected'; ?>>
                <?php echo e($fahrerOption['Mitarbeiter_ID'] . ' - ' . $fahrerOption['Name']); ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

    <label for="auswertung_trainingsziel">Trainingsziel:</label><br>
    <select name="auswertung_trainingsziel" id="auswertung_trainingsziel">
        <option value="">Alle Ziele</option>
        <?php foreach ($trainingsziele as $zielOption) { ?>
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

<p><a href="logout.php">Logout</a></p>
</body>
</html>
