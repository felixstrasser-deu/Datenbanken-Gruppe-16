<?php
require 'db.php';

mysqli_set_charset($connection, 'utf8mb4');

$meldung = '';
$fehler = '';

$loginname = trim($_POST['loginname'] ?? '');
$name = trim($_POST['name'] ?? '');
$vorname = trim($_POST['vorname'] ?? '');
$team = trim($_POST['team'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kennwort = trim($_POST['kennwort'] ?? '');

    if ($loginname === '' || $name === '' || $vorname === '' || $team === '' || $kennwort === '') {
        $fehler = 'Bitte alle Felder ausfuellen.';
    } else {
        $hash = password_hash($kennwort, PASSWORD_DEFAULT);

        $sql = 'INSERT INTO Teamchef (Loginname, Name, Vorname, Kennwort, Team) VALUES (?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($connection, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sssss', $loginname, $name, $vorname, $hash, $team);

            if (mysqli_stmt_execute($stmt)) {
                $meldung = 'Registrierung erfolgreich. Teamchef wurde gespeichert.';
                $loginname = '';
                $name = '';
                $vorname = '';
                $team = '';
            } else {
                $fehler = 'Fehler beim Speichern: ' . mysqli_error($connection);
            }

            mysqli_stmt_close($stmt);
        } else {
            $fehler = 'Fehler beim Vorbereiten der SQL-Anweisung.';
        }
    }
}

$teams = mysqli_query($connection, 'SELECT Teamname FROM Team ORDER BY Teamname');
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Teamchef registrieren</title>
</head>
<body>
<h2>Teamchef Registrierung</h2>

<?php if ($meldung !== '') { ?>
    <p style="color: green;"><?php echo htmlspecialchars($meldung, ENT_QUOTES, 'UTF-8'); ?></p>
<?php } ?>

<?php if ($fehler !== '') { ?>
    <p style="color: red;"><?php echo htmlspecialchars($fehler, ENT_QUOTES, 'UTF-8'); ?></p>
<?php } ?>

<form method="post" action="registrieren.php">
    <label for="loginname">Loginname:</label><br>
    <input type="text" id="loginname" name="loginname" maxlength="46" value="<?php echo htmlspecialchars($loginname, ENT_QUOTES, 'UTF-8'); ?>" required>
    <br><br>

    <label for="name">Name:</label><br>
    <input type="text" id="name" name="name" maxlength="46" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" required>
    <br><br>

    <label for="vorname">Vorname:</label><br>
    <input type="text" id="vorname" name="vorname" maxlength="46" value="<?php echo htmlspecialchars($vorname, ENT_QUOTES, 'UTF-8'); ?>" required>
    <br><br>

    <label for="team">Team:</label><br>
    <select id="team" name="team" required>
        <option value="">Bitte waehlen</option>
        <?php if ($teams) { ?>
            <?php while ($row = mysqli_fetch_assoc($teams)) { ?>
                <?php $teamname = $row['Teamname']; ?>
                <option value="<?php echo htmlspecialchars($teamname, ENT_QUOTES, 'UTF-8'); ?>" <?php if ($team === $teamname) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($teamname, ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php } ?>
        <?php } ?>
    </select>
    <br><br>

    <label for="kennwort">Passwort:</label><br>
    <input type="password" id="kennwort" name="kennwort" required>
    <br><br>

    <button type="submit">Registrieren</button>
</form>
</body>
</html>
