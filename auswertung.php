<?php
include 'db.php';
require 'TrainingStats.php';

mysqli_set_charset($connection, 'utf8mb4');

$ziel = '';
$von = '';
$bis = '';
$fehler = '';
$statistik = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ziel = trim($_POST['trainingsziel']);
    $von = trim($_POST['von']);
    $bis = trim($_POST['bis']);

    if ($von == '' || $bis == '') {
        $fehler = 'Bitte Zeitraum auswählen.';
    } elseif ($von > $bis) {
        $fehler = 'Das Von-Datum darf nicht nach dem Bis-Datum liegen.';
    } else {
        $stats = new TrainingStats();

        if ($ziel == '') {
            $sql = 'SELECT Datum, Kilometer FROM Training WHERE Datum >= ? AND Datum <= ? ORDER BY Datum';
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, 'ss', $von, $bis);
        } else {
            $sql = 'SELECT Datum, Kilometer FROM Training WHERE Trainingsziel = ? AND Datum >= ? AND Datum <= ? ORDER BY Datum';
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, 'sss', $ziel, $von, $bis);
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $datum, $kilometer);

        while (mysqli_stmt_fetch($stmt)) {
            $stats->addTraining($datum, $kilometer);
        }

        mysqli_stmt_close($stmt);

        $statistik = $stats->getMonatsStatistik();
    }
}

$trainingsziele = mysqli_query($connection, 'SELECT Trainingsziel FROM Trainingsziel ORDER BY Trainingsziel');
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Auswertung</title>
</head>
<body>

<h2>Auswertung</h2>

<?php if ($fehler != '') { ?>
    <p style="color: red;"><?php echo htmlspecialchars($fehler); ?></p>
<?php } ?>

<form method="post" action="auswertung.php">
    <label for="trainingsziel">Trainingsziel:</label><br>
    <select name="trainingsziel" id="trainingsziel">
        <option value="">Alle</option>
        <?php while ($row = mysqli_fetch_assoc($trainingsziele)) { ?>
            <option value="<?php echo htmlspecialchars($row['Trainingsziel']); ?>" <?php if ($ziel == $row['Trainingsziel']) echo 'selected'; ?>>
                <?php echo htmlspecialchars($row['Trainingsziel']); ?>
            </option>
        <?php } ?>
    </select>

    <br><br>

    <label for="von">Von:</label><br>
    <input type="date" name="von" id="von" value="<?php echo htmlspecialchars($von); ?>" required>

    <br><br>

    <label for="bis">Bis:</label><br>
    <input type="date" name="bis" id="bis" value="<?php echo htmlspecialchars($bis); ?>" required>

    <br><br>

    <button type="submit">Auswerten</button>
</form>

<?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && $fehler == '') { ?>
    <h3>Ergebnis</h3>

    <?php if (count($statistik) == 0) { ?>
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

            <?php foreach ($statistik as $monat => $werte) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($monat); ?></td>
                    <td><?php echo htmlspecialchars($werte['anzahl']); ?></td>
                    <td><?php echo htmlspecialchars(number_format($werte['summe'], 2, ',', '.')); ?></td>
                    <td><?php echo htmlspecialchars(number_format($werte['durchschnitt'], 2, ',', '.')); ?></td>
                    <td><?php echo htmlspecialchars(number_format($werte['minimum'], 2, ',', '.')); ?></td>
                    <td><?php echo htmlspecialchars(number_format($werte['maximum'], 2, ',', '.')); ?></td>
                    <td><?php echo htmlspecialchars(number_format($werte['median'], 2, ',', '.')); ?></td>
                    <td><?php echo htmlspecialchars(number_format($werte['quantil_25'], 2, ',', '.')); ?></td>
                    <td><?php echo htmlspecialchars(number_format($werte['quantil_75'], 2, ',', '.')); ?></td>
                    <td><?php echo htmlspecialchars(number_format($werte['standardabweichung'], 2, ',', '.')); ?></td>
                </tr>
            <?php } ?>
        </table>
    <?php } ?>
<?php } ?>

</body>
</html>
