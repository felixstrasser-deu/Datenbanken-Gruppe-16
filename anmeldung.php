<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Anmeldung von Teamfahrern zu zukünftigen Rennen.
 */
session_start();
require 'db.php';
require 'functions.php';

require_role('teamchef');
mysqli_set_charset($connection, 'utf8mb4');

$team = (string) ($_SESSION['team'] ?? '');
$meldung = '';
$fehler = '';
$rennenId = filter_var(get_value('rennen_id'), FILTER_VALIDATE_INT);
$anzahl = filter_var(get_value('anzahl'), FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_value('aktion') === 'speichern') {
    $rennenId = filter_var(post_value('rennen_id'), FILTER_VALIDATE_INT);
    $fahrerIds = $_POST['fahrer'] ?? array();
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

            mysqli_stmt_bind_param($checkStmt, 'is', $fahrerId, $team);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);
            $fahrerOk = mysqli_stmt_num_rows($checkStmt) > 0;
            mysqli_stmt_close($checkStmt);

            if (!$fahrerOk || fahrer_ist_angemeldet($connection, $rennenId, $fahrerId)) {
                $uebersprungen++;
                continue;
            }

            if (melde_fahrer_an($connection, $rennenId, $team, $fahrerId)) {
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

$rennenListe = mysqli_query(
    $connection,
    'SELECT `Renn-ID`, Datum, Standort FROM Radrennen WHERE Datum >= CURDATE() ORDER BY Datum ASC, `Renn-ID` ASC'
);

$fahrerListe = array();
$fahrerStmt = mysqli_prepare($connection, 'SELECT Mitarbeiter_ID, Name FROM Fahrer WHERE Team = ? ORDER BY Name');
if ($fahrerStmt) {
    mysqli_stmt_bind_param($fahrerStmt, 's', $team);
    mysqli_stmt_execute($fahrerStmt);
    mysqli_stmt_bind_result($fahrerStmt, $fahrerId, $fahrerName);

    while (mysqli_stmt_fetch($fahrerStmt)) {
        $fahrerListe[] = array('Mitarbeiter_ID' => $fahrerId, 'Name' => $fahrerName);
    }

    mysqli_stmt_close($fahrerStmt);
}

if ($anzahl === false || $anzahl < 1) {
    $anzahl = 0;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Fahrer anmelden</title>
</head>
<body>
<h1>Fahrer zu Rennen anmelden</h1>
<p>Team: <?php echo e($team); ?></p>

<?php if ($meldung !== '') { ?>
    <p style="color: green;"><?php echo e($meldung); ?></p>
<?php } ?>
<?php if ($fehler !== '') { ?>
    <p style="color: red;"><?php echo e($fehler); ?></p>
<?php } ?>

<h2>Rennen und Anzahl wählen</h2>
<form method="get" action="anmeldung.php">
    <label for="rennen_id">Zukünftiges Rennen:</label><br>
    <select name="rennen_id" id="rennen_id" required>
        <option value="">Bitte wählen</option>
        <?php if ($rennenListe) { ?>
            <?php while ($row = mysqli_fetch_assoc($rennenListe)) { ?>
                <option value="<?php echo e($row['Renn-ID']); ?>" <?php if ((string) $rennenId === (string) $row['Renn-ID']) echo 'selected'; ?>>
                    <?php echo e($row['Renn-ID'] . ' - ' . $row['Datum'] . ' - ' . $row['Standort']); ?>
                </option>
            <?php } ?>
        <?php } ?>
    </select>

    <br><br>

    <label for="anzahl">Anzahl Fahrer:</label><br>
    <input type="number" name="anzahl" id="anzahl" min="1" value="<?php echo $anzahl > 0 ? e($anzahl) : ''; ?>" required>

    <br><br>

    <button type="submit">Erfassung anzeigen</button>
</form>

<?php if ($rennenId !== false && $rennenId > 0 && $anzahl > 0) { ?>
    <h2>Fahrer auswählen</h2>

    <?php if (count($fahrerListe) === 0) { ?>
        <p>Für dieses Team sind keine Fahrer angelegt.</p>
    <?php } else { ?>
        <form method="post" action="anmeldung.php">
            <input type="hidden" name="aktion" value="speichern">
            <input type="hidden" name="rennen_id" value="<?php echo e($rennenId); ?>">

            <table border="1" cellpadding="5" cellspacing="0">
                <tr>
                    <th>Nr.</th>
                    <th>Fahrer</th>
                </tr>
                <?php for ($i = 0; $i < $anzahl; $i++) { ?>
                    <tr>
                        <td><?php echo e($i + 1); ?></td>
                        <td>
                            <select name="fahrer[]" required>
                                <option value="">Bitte wählen</option>
                                <?php foreach ($fahrerListe as $fahrer) { ?>
                                    <option value="<?php echo e($fahrer['Mitarbeiter_ID']); ?>">
                                        <?php echo e($fahrer['Mitarbeiter_ID'] . ' - ' . $fahrer['Name']); ?>
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

<p><a href="kopieren.php">Anmeldungen kopieren</a></p>
<p><a href="teamchef_dashboard.php">Zurück zum Dashboard</a></p>
</body>
</html>
