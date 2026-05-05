<?php
session_start();
require 'db.php';

if (!isset($_SESSION['rolle']) || $_SESSION['rolle'] !== 'teamchef') {
    header('Location: index.php');
    exit;
}

mysqli_set_charset($connection, 'utf8mb4');

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function post_value($key)
{
    return trim((string) ($_POST[$key] ?? ''));
}

$teamRaw = (string) ($_SESSION['team'] ?? '');
$loginname = e($_SESSION['loginname'] ?? '');
$team = e($teamRaw);
$meldung = '';
$fehler = '';
$strasseColumn = '';

$columnResult = mysqli_query($connection, 'SHOW COLUMNS FROM Fahrer');
if ($columnResult) {
    $columns = array();
    while ($columnRow = mysqli_fetch_assoc($columnResult)) {
        $columns[] = (string) $columnRow['Field'];
    }

    $knownColumns = array('Team', 'Mitarbeiter_ID', 'Name', 'HausNr', 'PLZ', 'Ort', 'TelNr');
    foreach ($columns as $columnName) {
        if (!in_array($columnName, $knownColumns, true)) {
            $strasseColumn = $columnName;
            break;
        }
    }

    if ($strasseColumn === '' && count($columns) >= 4) {
        $strasseColumn = $columns[3];
    }
}

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

        $plz = filter_var($form['plz'], FILTER_VALIDATE_INT);
        if ($plz === false || $plz < 0 || $plz > 99999) {
            $errors[] = 'PLZ muss eine gültige Zahl sein.';
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
            if ($formMode === 'create') {
                $insertSql = 'INSERT INTO Fahrer (`Team`, `Mitarbeiter_ID`, `Name`, ' . $strasseColumnSql . ', `HausNr`, `PLZ`, `Ort`, `TelNr`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
                $insertStmt = mysqli_prepare($connection, $insertSql);

                if ($insertStmt) {
                    mysqli_stmt_bind_param(
                        $insertStmt,
                        'sisssiss',
                        $teamRaw,
                        $mitarbeiterId,
                        $form['name'],
                        $form['strasse'],
                        $form['hausnr'],
                        $plz,
                        $form['ort'],
                        $form['telnr']
                    );

                    $ok = mysqli_stmt_execute($insertStmt);
                    $errno = mysqli_errno($connection);
                    mysqli_stmt_close($insertStmt);

                    if ($ok) {
                        header('Location: teamchef_dashboard.php?status=created');
                        exit;
                    }

                    if ($errno === 1062) {
                        header('Location: teamchef_dashboard.php?status=exists');
                        exit;
                    }
                }

                $fehler = 'Fahrer konnte nicht angelegt werden.';
            } else {
                $updateSql = 'UPDATE Fahrer SET `Name` = ?, ' . $strasseColumnSql . ' = ?, `HausNr` = ?, `PLZ` = ?, `Ort` = ?, `TelNr` = ? WHERE `Mitarbeiter_ID` = ? AND `Team` = ?';
                $updateStmt = mysqli_prepare($connection, $updateSql);

                if ($updateStmt) {
                    mysqli_stmt_bind_param(
                        $updateStmt,
                        'sssissis',
                        $form['name'],
                        $form['strasse'],
                        $form['hausnr'],
                        $plz,
                        $form['ort'],
                        $form['telnr'],
                        $mitarbeiterId,
                        $teamRaw
                    );

                    mysqli_stmt_execute($updateStmt);
                    $affected = mysqli_stmt_affected_rows($updateStmt);
                    mysqli_stmt_close($updateStmt);

                    if ($affected >= 0) {
                        header('Location: teamchef_dashboard.php?status=updated');
                        exit;
                    }
                }

                $fehler = 'Fahrer konnte nicht aktualisiert werden.';
            }
        } else {
            $fehler = implode(' ', $errors);
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

<h3>Fahrer anlegen / bearbeiten</h3>
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
    <input type="number" id="plz" name="plz" min="0" max="99999" value="<?php echo e($form['plz']); ?>" required>
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

<h3>Fahrer im Team</h3>
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

<p><a href="logout.php">Logout</a></p>
</body>
</html>
