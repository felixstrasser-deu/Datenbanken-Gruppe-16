<?php
include 'db.php';

mysqli_set_charset($connection, 'utf8mb4');

$meldung = '';
$fehler = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $datum = trim($_POST['datum']);
    $kilometer = trim($_POST['kilometer']);
    $trainingsziel = trim($_POST['trainingsziel']);
    $mitarbeiter = trim($_POST['mitarbeiter']);

    if ($datum == '' || $kilometer == '' || $trainingsziel == '' || $mitarbeiter == '') {
        $fehler = 'Bitte alle Felder ausfüllen.';
    } elseif (!is_numeric($kilometer) || $kilometer <= 0) {
        $fehler = 'Kilometer muss groesser als 0 sein.';
    } elseif (!is_numeric($mitarbeiter)) {
        $fehler = 'Ungültiger Fahrer.';
    }

    if ($fehler == '') {
        $sql = 'CALL TrainingSpeichern(?, ?, ?, ?, @status, @meldung)';
        $stmt = mysqli_prepare($connection, $sql);

        if ($stmt != false) {
            mysqli_stmt_bind_param($stmt, 'sdsi', $datum, $kilometer, $trainingsziel, $mitarbeiter);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            while (mysqli_more_results($connection)) {
                mysqli_next_result($connection);
            }

            $result = mysqli_query($connection, 'SELECT @status AS status, @meldung AS meldung');
            $row = mysqli_fetch_assoc($result);

            if ($row['status'] == 'OK') {
                $meldung = $row['meldung'];
            } else {
                $fehler = $row['meldung'];
            }
        }
    }
}

$fahrer = mysqli_query($connection, 'SELECT Mitarbeiter_ID, Name, Team FROM Fahrer ORDER BY Name');
$trainingsziele = mysqli_query($connection, 'SELECT Trainingsziel FROM Trainingsziel ORDER BY Trainingsziel');

if ($fahrer == false || $trainingsziele == false) {
    $fehler = 'Daten konnten nicht geladen werden: ' . mysqli_error($connection);
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Training erfassen</title>
</head>
<body>

<h2>Training erfassen</h2>

<?php if ($meldung != '') { ?>
    <p style="color: green;"><?php echo htmlspecialchars($meldung); ?></p>
<?php } ?>

<?php if ($fehler != '') { ?>
    <p style="color: red;"><?php echo htmlspecialchars($fehler); ?></p>
<?php } ?>

<form method="post" action="training.php">
    <label for="mitarbeiter">Fahrer:</label><br>
    <select name="mitarbeiter" id="mitarbeiter" required>
        <option value="">Bitte waehlen</option>
        <?php if ($fahrer != false) { ?>
            <?php while ($row = mysqli_fetch_assoc($fahrer)) { ?>
                <option value="<?php echo htmlspecialchars($row['Mitarbeiter_ID']); ?>">
                    <?php echo htmlspecialchars($row['Mitarbeiter_ID'] . ' - ' . $row['Name'] . ' (' . $row['Team'] . ')'); ?>
                </option>
            <?php } ?>
        <?php } ?>
    </select>

    <br><br>

    <label for="datum">Datum:</label><br>
    <input type="date" name="datum" id="datum" required>

    <br><br>

    <label for="kilometer">Kilometer:</label><br>
    <input type="number" step="0.01" min="0.01" name="kilometer" id="kilometer" required>

    <br><br>

    <label for="trainingsziel">Trainingsziel:</label><br>
    <select name="trainingsziel" id="trainingsziel" required>
        <option value="">Bitte wählen</option>
        <?php if ($trainingsziele != false) { ?>
            <?php while ($row = mysqli_fetch_assoc($trainingsziele)) { ?>
                <option value="<?php echo htmlspecialchars($row['Trainingsziel']); ?>">
                    <?php echo htmlspecialchars($row['Trainingsziel']); ?>
                </option>
            <?php } ?>
        <?php } ?>
    </select>

    <br><br>

    <button type="submit">Training speichern</button>
</form>

</body>
</html>
