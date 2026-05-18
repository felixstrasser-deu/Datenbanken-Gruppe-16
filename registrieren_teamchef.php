<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Alternative Registrierungsseite für ein neues Team mit Teamchef.
 */
session_start();
require 'db.php';
require 'functions.php';

mysqli_set_charset($connection, 'utf8mb4');

$meldung = '';
$fehler = '';
$teamname = post_value('teamname');
$loginname = post_value('loginname');
$name = post_value('name');
$vorname = post_value('vorname');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kennwort = post_value('kennwort');

    if ($teamname === '' || $loginname === '' || $name === '' || $vorname === '' || $kennwort === '') {
        $fehler = 'Bitte alle Felder ausfüllen.';
    } elseif (team_exists($connection, $teamname)) {
        $fehler = 'Dieses Team existiert bereits.';
    } elseif (loginname_exists($connection, $loginname)) {
        $fehler = 'Dieser Loginname existiert bereits.';
    } elseif (create_team_with_chef($connection, $teamname, $loginname, $name, $vorname, $kennwort)) {
        $meldung = 'Team und Teamchef wurden registriert.';
        $teamname = '';
        $loginname = '';
        $name = '';
        $vorname = '';
    } else {
        $fehler = 'Registrierung konnte nicht gespeichert werden: ' . mysqli_error($connection);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Teamchef registrieren</title>
</head>
<body>
<h1>Teamchef registrieren</h1>

<?php if ($meldung !== '') { ?>
    <p style="color: green;"><?php echo e($meldung); ?></p>
<?php } ?>
<?php if ($fehler !== '') { ?>
    <p style="color: red;"><?php echo e($fehler); ?></p>
<?php } ?>

<form method="post" action="registrieren_teamchef.php">
    <label for="teamname">Teamname:</label><br>
    <input type="text" name="teamname" id="teamname" maxlength="46" value="<?php echo e($teamname); ?>" required>
    <br><br>

    <label for="loginname">Loginname:</label><br>
    <input type="text" name="loginname" id="loginname" maxlength="46" value="<?php echo e($loginname); ?>" required>
    <br><br>

    <label for="name">Name:</label><br>
    <input type="text" name="name" id="name" maxlength="46" value="<?php echo e($name); ?>" required>
    <br><br>

    <label for="vorname">Vorname:</label><br>
    <input type="text" name="vorname" id="vorname" maxlength="46" value="<?php echo e($vorname); ?>" required>
    <br><br>

    <label for="kennwort">Passwort:</label><br>
    <input type="password" name="kennwort" id="kennwort" required>
    <br><br>

    <button type="submit">Registrieren</button>
</form>

<p><a href="index.php">Zur Startseite</a></p>
</body>
</html>
