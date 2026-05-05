<?php
session_start();
require 'db.php';

if (!isset($_SESSION['rolle']) || $_SESSION['rolle'] !== 'veranstalter') {
    header('Location: index.php');
    exit;
}

$name = htmlspecialchars($_SESSION['name'] ?? '', ENT_QUOTES, 'UTF-8');

mysqli_set_charset($connection, 'utf8mb4');

$kommendeRennen = array();
$rennenSql = 'SELECT Datum, Standort, Kilometer, MaxSteigung FROM Radrennen WHERE Datum >= CURDATE() ORDER BY Datum ASC';
$rennenResult = mysqli_query($connection, $rennenSql);

if ($rennenResult) {
    while ($row = mysqli_fetch_assoc($rennenResult)) {
        $kommendeRennen[] = $row;
    }
}

$anzahlAnmeldungen = 0;
$anmeldungResult = mysqli_query($connection, 'SELECT COUNT(*) AS anzahl FROM Anmeldung');
if ($anmeldungResult && ($anmeldungRow = mysqli_fetch_assoc($anmeldungResult))) {
    $anzahlAnmeldungen = (int) $anmeldungRow['anzahl'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Veranstalter Dashboard</title>
</head>
<body>
<h1>Veranstalter Dashboard</h1>
<p>Angemeldet als: <?php echo $name; ?></p>

<h3>Rennen</h3>
<?php if (count($kommendeRennen) === 0) { ?>
    <p>Keine zukünftigen Rennen vorhanden.</p>
<?php } else { ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>Datum</th>
            <th>Standort</th>
            <th>Kilometer</th>
            <th>Max. Steigung</th>
        </tr>
        <?php foreach ($kommendeRennen as $rennen) { ?>
            <tr>
                <td><?php echo htmlspecialchars($rennen['Datum'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($rennen['Standort'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($rennen['Kilometer'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($rennen['MaxSteigung'], ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
        <?php } ?>
    </table>
<?php } ?>

<h3>Anmeldung</h3>
<p>Gesamtzahl Anmeldungen: <?php echo htmlspecialchars((string) $anzahlAnmeldungen, ENT_QUOTES, 'UTF-8'); ?></p>

<h3>Kopierfunktion</h3>
<p>Die Kopierfunktion wird direkt hier integriert, sobald sie umgesetzt ist.</p>

<p><a href="logout.php">Logout</a></p>
</body>
</html>
