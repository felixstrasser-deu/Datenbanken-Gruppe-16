<?php
include 'db.php';

mysqli_set_charset($connection, 'utf8mb4');

$meldung = '';
$rennen = '';

if (isset($_POST['rennen'])) {
    $rennen = $_POST['rennen'];
} elseif (isset($_GET['rennen'])) {
    $rennen = $_GET['rennen'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['speichern'])) {
    foreach ($_POST['startnummer'] as $i => $startnummer) {
        $platzierung = $_POST['platzierung'][$i];
        $fahrtzeit = $_POST['fahrtzeit'][$i];

        if ($platzierung != '' && $fahrtzeit != '') {
            $sql = 'UPDATE Anmeldung
                    SET Platzierung = ?, Fahrtzeit = ?
                    WHERE Startnummer = ?
                    AND Platzierung = 0
                    AND Fahrtzeit = 0';

            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, 'iii', $platzierung, $fahrtzeit, $startnummer);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    $meldung = 'Ergebnisse wurden gespeichert. Bereits vorhandene Ergebnisse wurden nicht geaendert.';
}

$rennenListe = mysqli_query($connection, 'SELECT `Renn-ID`, Datum, Standort FROM Radrennen ORDER BY Datum DESC');
$anmeldungen = false;

if ($rennen != '') {
    $rennenNummer = (int) $rennen;

    $anmeldungen = mysqli_query(
        $connection,
        'SELECT Anmeldung.Startnummer, Anmeldung.Platzierung, Anmeldung.Fahrtzeit,
                Fahrer.Mitarbeiter_ID, Fahrer.Name, Fahrer.Team
         FROM Anmeldung
         INNER JOIN Fahrer ON Anmeldung.Mitarbeiter = Fahrer.Mitarbeiter_ID
         WHERE Anmeldung.Radrennen = ' . $rennenNummer . '
         ORDER BY Anmeldung.Startnummer'
    );
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Ergebnisse erfassen</title>
</head>
<body>

<h2>Ergebnisse erfassen</h2>

<?php if ($meldung != '') { ?>
    <p style="color: green;"><?php echo htmlspecialchars($meldung); ?></p>
<?php } ?>

<form method="get" action="ergebnisse.php">
    <label for="rennen">Rennen:</label><br>
    <select name="rennen" id="rennen" required>
        <option value="">Bitte waehlen</option>
        <?php while ($row = mysqli_fetch_assoc($rennenListe)) { ?>
            <option value="<?php echo htmlspecialchars($row['Renn-ID']); ?>" <?php if ($rennen == $row['Renn-ID']) echo 'selected'; ?>>
                <?php echo htmlspecialchars($row['Renn-ID'] . ' - ' . $row['Datum'] . ' - ' . $row['Standort']); ?>
            </option>
        <?php } ?>
    </select>

    <br><br>

    <button type="submit">Anzeigen</button>
</form>

<?php if ($rennen != '') { ?>
    <h3>Fahrer des Rennens</h3>

    <?php if ($anmeldungen == false || mysqli_num_rows($anmeldungen) == 0) { ?>
        <p>Keine Anmeldungen fuer dieses Rennen gefunden.</p>
    <?php } else { ?>
        <form method="post" action="ergebnisse.php">
            <input type="hidden" name="rennen" value="<?php echo htmlspecialchars($rennen); ?>">

            <table border="1" cellpadding="5" cellspacing="0">
                <tr>
                    <th>Startnummer</th>
                    <th>Fahrer</th>
                    <th>Team</th>
                    <th>Platzierung</th>
                    <th>Fahrtzeit</th>
                </tr>

                <?php while ($row = mysqli_fetch_assoc($anmeldungen)) { ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($row['Startnummer']); ?>
                            <input type="hidden" name="startnummer[]" value="<?php echo htmlspecialchars($row['Startnummer']); ?>">
                        </td>
                        <td><?php echo htmlspecialchars($row['Mitarbeiter_ID'] . ' - ' . $row['Name']); ?></td>
                        <td><?php echo htmlspecialchars($row['Team']); ?></td>

                        <?php if ($row['Platzierung'] == 0 && $row['Fahrtzeit'] == 0) { ?>
                            <td><input type="number" name="platzierung[]" min="1"></td>
                            <td><input type="number" name="fahrtzeit[]" min="1"></td>
                        <?php } else { ?>
                            <td>
                                <?php echo htmlspecialchars($row['Platzierung']); ?>
                                <input type="hidden" name="platzierung[]" value="">
                            </td>
                            <td>
                                <?php echo htmlspecialchars($row['Fahrtzeit']); ?>
                                <input type="hidden" name="fahrtzeit[]" value="">
                            </td>
                        <?php } ?>
                    </tr>
                <?php } ?>
            </table>

            <br>

            <button type="submit" name="speichern">Ergebnisse speichern</button>
        </form>
    <?php } ?>
<?php } ?>

</body>
</html>
