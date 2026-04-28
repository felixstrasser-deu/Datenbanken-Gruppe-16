<?php
include 'db.php';

$meldung = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sql = "INSERT INTO Radrennen (Datum, Standort, Kilometer, Hoehenmeter, MaxSteigung)
            VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "ssddd",
        $_POST['datum'],
        $_POST['standort'],
        $_POST['kilometer'],
        $_POST['hoehenmeter'],
        $_POST['max_steigung']
    );

    if (mysqli_stmt_execute($stmt)) {
        $meldung = "Rennen wurde gespeichert!";
    } else {
        $meldung = "Fehler beim Speichern: " . mysqli_error($connection);
    }
}

$rennen = mysqli_query(
    $connection,
    "SELECT * FROM Radrennen WHERE Datum >= CURDATE() ORDER BY Datum ASC"
);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Rennen</title>
</head>
<body>
<h1>Rennen</h1>

<?php
if ($meldung) {
    echo "<p>" . htmlspecialchars($meldung) . "</p>";
}
?>

<h2>Neues Rennen anlegen</h2>

<form method="post" action="rennen.php">
    Datum:<br>
    <input type="date" name="datum" required><br><br>
    Standort:<br>
    <input type="text" name="standort" required><br><br>
    Kilometer:<br>
    <input type="number" step="0.01" name="kilometer" required><br><br>
    Hoehenmeter:<br>
    <input type="number" step="0.01" name="hoehenmeter" required><br><br>
    Maximale Steigung:<br>
    <input type="number" step="0.01" name="max_steigung" required><br><br>
    <button type="submit">Rennen speichern</button>
</form>

<h2>Zukuenftige Rennen</h2>

<table border="1" cellpadding="5">
    <tr>
        <th>ID</th>
        <th>Datum</th>
        <th>Standort</th>
        <th>Kilometer</th>
        <th>Hoehenmeter</th>
        <th>Max. Steigung</th>
        <th>Aktion</th>
    </tr>
    <?php
    if ($rennen && mysqli_num_rows($rennen) > 0) {
        while ($zeile = mysqli_fetch_assoc($rennen)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($zeile['ID']) . "</td>";
            echo "<td>" . htmlspecialchars($zeile['Datum']) . "</td>";
            echo "<td>" . htmlspecialchars($zeile['Standort']) . "</td>";
            echo "<td>" . htmlspecialchars($zeile['Kilometer']) . "</td>";
            echo "<td>" . htmlspecialchars($zeile['Hoehenmeter']) . "</td>";
            echo "<td>" . htmlspecialchars($zeile['MaxSteigung']) . "</td>";
            echo "<td><a href='anmeldung.php?rennen_id=" . urlencode($zeile['ID']) . "'>Anmelden</a></td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='7'>Keine zukuenftigen Rennen vorhanden.</td></tr>";
    }
    ?>
</table>
</body>
</html>
