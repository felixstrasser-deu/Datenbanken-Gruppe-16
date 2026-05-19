<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Startseite mit Registrierung, Login und Übersicht.
 */
session_start();
include 'db.php';

if (file_exists('functions.php')) {
    require_once 'functions.php';
}

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('post_value')) {
    function post_value($key)
    {
        return trim((string) ($_POST[$key] ?? ''));
    }
}

if (!function_exists('get_value')) {
    function get_value($key)
    {
        return trim((string) ($_GET[$key] ?? ''));
    }
}

if (!function_exists('team_exists')) {
    function team_exists($connection, $teamname)
    {
        $stmt = mysqli_prepare($connection, 'SELECT 1 FROM Team WHERE Teamname = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 's', $teamname);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $exists = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return $exists;
    }
}

if (!function_exists('loginname_exists')) {
    function loginname_exists($connection, $loginname)
    {
        $stmt = mysqli_prepare($connection, 'SELECT 1 FROM Teamchef WHERE Loginname = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 's', $loginname);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $exists = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return $exists;
    }
}

if (!function_exists('create_team_with_chef')) {
    function create_team_with_chef($connection, $teamname, $loginname, $name, $vorname, $kennwort)
    {
        mysqli_begin_transaction($connection);

        $teamStmt = mysqli_prepare($connection, 'INSERT INTO Team (Teamname) VALUES (?)');
        if (!$teamStmt) {
            mysqli_rollback($connection);
            return false;
        }

        mysqli_stmt_bind_param($teamStmt, 's', $teamname);
        $teamOk = mysqli_stmt_execute($teamStmt);
        mysqli_stmt_close($teamStmt);

        if (!$teamOk) {
            mysqli_rollback($connection);
            return false;
        }

        $hash = password_hash($kennwort, PASSWORD_DEFAULT);
        $chefStmt = mysqli_prepare($connection, 'INSERT INTO Teamchef (Loginname, Name, Vorname, Kennwort, Team) VALUES (?, ?, ?, ?, ?)');
        if (!$chefStmt) {
            mysqli_rollback($connection);
            return false;
        }

        mysqli_stmt_bind_param($chefStmt, 'sssss', $loginname, $name, $vorname, $hash, $teamname);
        $chefOk = mysqli_stmt_execute($chefStmt);
        mysqli_stmt_close($chefStmt);

        if (!$chefOk) {
            mysqli_rollback($connection);
            return false;
        }

        mysqli_commit($connection);
        return true;
    }
}

mysqli_set_charset($connection, 'utf8mb4');

if (isset($_SESSION['rolle'])) {
    if ($_SESSION['rolle'] === 'teamchef') {
        header('Location: teamchef_dashboard.php');
        exit;
    }

    if ($_SESSION['rolle'] === 'veranstalter') {
        header('Location: veranstalter_dashboard.php');
        exit;
    }
}

$anzahlTeams = 0;
$anzahlFahrer = 0;
$anzahlRennen = 0;
$anzahlTrainings = 0;
$loginStatus = get_value('login');
$regStatus = get_value('reg');
$regMeldung = '';
$regFehler = '';
$regLoginname = post_value('loginname');
$regName = post_value('name');
$regVorname = post_value('vorname');
$regTeam = post_value('teamname');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_typ'] ?? '') === 'teamchef_reg') {
    $regKennwort = post_value('kennwort');

    if ($regLoginname === '' || $regName === '' || $regVorname === '' || $regTeam === '' || $regKennwort === '') {
        $regFehler = 'Bitte alle Felder ausfüllen.';
    } elseif (strlen($regLoginname) > 46 || strlen($regName) > 46 || strlen($regVorname) > 46 || strlen($regTeam) > 46) {
        $regFehler = 'Textfelder dürfen maximal 46 Zeichen lang sein.';
    } elseif (team_exists($connection, $regTeam)) {
        $regFehler = 'Dieses Team existiert bereits.';
    } elseif (loginname_exists($connection, $regLoginname)) {
        $regFehler = 'Dieser Loginname existiert bereits.';
    } elseif (create_team_with_chef($connection, $regTeam, $regLoginname, $regName, $regVorname, $regKennwort)) {
        $_SESSION['rolle'] = 'teamchef';
        $_SESSION['loginname'] = $regLoginname;
        $_SESSION['team'] = $regTeam;

        header('Location: teamchef_dashboard.php');
        exit;
    } else {
        $regFehler = 'Fehler beim Speichern: ' . mysqli_error($connection);
    }
}

$result = mysqli_query($connection, 'SELECT COUNT(*) AS anzahl FROM Team');
if ($result && ($row = mysqli_fetch_assoc($result))) {
    $anzahlTeams = $row['anzahl'];
}

$result = mysqli_query($connection, 'SELECT COUNT(*) AS anzahl FROM Fahrer');
if ($result && ($row = mysqli_fetch_assoc($result))) {
    $anzahlFahrer = $row['anzahl'];
}

$result = mysqli_query($connection, 'SELECT COUNT(*) AS anzahl FROM Radrennen');
if ($result && ($row = mysqli_fetch_assoc($result))) {
    $anzahlRennen = $row['anzahl'];
}

$result = mysqli_query($connection, 'SELECT COUNT(*) AS anzahl FROM Training');
if ($result && ($row = mysqli_fetch_assoc($result))) {
    $anzahlTrainings = $row['anzahl'];
}

$letzteTrainings = mysqli_query(
    $connection,
    'SELECT Training.Datum, Training.Kilometer, Training.Trainingsziel, Fahrer.Name
     FROM Training
     INNER JOIN Fahrer ON Training.Mitarbeiter = Fahrer.Mitarbeiter_ID
     ORDER BY Training.Datum DESC
     LIMIT 5'
);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Verwaltung von Radrennen</title>
</head>
<body>

<h1>Verwaltung von Radrennen</h1>
<p>Bitte melden Sie sich als Teamchef oder Rennveranstalter an. Neue Teams und neue Rennveranstalter können direkt auf dieser Seite registriert werden.</p>

<?php if ($loginStatus === 'ok') { ?>
    <p><strong>Login erfolgreich.</strong></p>
<?php } elseif ($loginStatus === 'fehler') { ?>
    <p><strong>Login fehlgeschlagen.</strong></p>
<?php } ?>

<?php if ($regStatus === 'exists') { ?>
    <p><strong>Veranstalter existiert bereits.</strong></p>
<?php } elseif ($regStatus === 'fehler') { ?>
    <p><strong>Veranstalter-Registrierung fehlgeschlagen.</strong></p>
<?php } ?>

<hr>

<h2>Teamchef-Bereich</h2>

<table border="1" cellpadding="12" cellspacing="0" width="100%">
    <tr>
        <th align="left">Teamchef Login</th>
        <th align="left">Teamchef Registrierung</th>
    </tr>
    <tr>
        <td valign="top" width="50%">

            <form method="post" action="login_teamchef.php">
                <label for="teamchef_login">Loginname:</label><br>
                <input type="text" name="loginname" id="teamchef_login" required>

                <br><br>

                <label for="teamchef_passwort">Passwort:</label><br>
                <input type="password" name="kennwort" id="teamchef_passwort" required>

                <br><br>

                <button type="submit">Login</button>
            </form>
        </td>

        <td valign="top" width="50%">
            <?php if ($regMeldung !== '') { ?>
                <p><strong><?php echo e($regMeldung); ?></strong></p>
            <?php } ?>

            <?php if ($regFehler !== '') { ?>
                <p><strong><?php echo e($regFehler); ?></strong></p>
            <?php } ?>

            <form method="post" action="index.php">
                <input type="hidden" name="form_typ" value="teamchef_reg">

                <label for="reg_team">Teamname:</label><br>
                <input type="text" name="teamname" id="reg_team" maxlength="46" value="<?php echo e($regTeam); ?>" required>

                <br><br>

                <label for="reg_loginname">Loginname:</label><br>
                <input type="text" name="loginname" id="reg_loginname" maxlength="46" value="<?php echo e($regLoginname); ?>" required>

                <br><br>

                <label for="reg_name">Name:</label><br>
                <input type="text" name="name" id="reg_name" maxlength="46" value="<?php echo e($regName); ?>" required>

                <br><br>

                <label for="reg_vorname">Vorname:</label><br>
                <input type="text" name="vorname" id="reg_vorname" maxlength="46" value="<?php echo e($regVorname); ?>" required>

                <br><br>

                <label for="reg_passwort">Passwort:</label><br>
                <input type="password" name="kennwort" id="reg_passwort" required>

                <br><br>

                <button type="submit">Registrieren</button>
            </form>
        </td>
    </tr>
</table>

<br>

<h2>Rennveranstalter-Bereich</h2>

<table border="1" cellpadding="12" cellspacing="0" width="100%">
    <tr>
        <th align="left">Veranstalter Login</th>
        <th align="left">Veranstalter Registrierung</th>
    </tr>
    <tr>
        <td valign="top" width="50%">
            <form method="post" action="veranstalter_login.php">
                <label for="veranstalter_name">Name:</label><br>
                <input type="text" name="name" id="veranstalter_name" required>

                <br><br>

                <label for="veranstalter_passwort">Passwort:</label><br>
                <input type="password" name="kennwort" id="veranstalter_passwort" required>

                <br><br>

                <button type="submit">Login</button>
            </form>
        </td>

        <td valign="top" width="50%">
            <form method="post" action="veranstalter_registrieren.php">
                <label for="veranstalter_reg_name">Name:</label><br>
                <input type="text" name="name" id="veranstalter_reg_name" required>

                <br><br>

                <label for="veranstalter_reg_passwort">Passwort:</label><br>
                <input type="password" name="kennwort" id="veranstalter_reg_passwort" required>

                <br><br>

                <button type="submit">Registrieren</button>
            </form>
        </td>
    </tr>
</table>

<hr>

<h2>Übersicht</h2>

<table border="1" cellpadding="8" cellspacing="0">
    <tr>
        <th>Teams</th>
        <th>Fahrer</th>
        <th>Rennen</th>
        <th>Trainings</th>
    </tr>
    <tr>
        <td><?php echo e($anzahlTeams); ?></td>
        <td><?php echo e($anzahlFahrer); ?></td>
        <td><?php echo e($anzahlRennen); ?></td>
        <td><?php echo e($anzahlTrainings); ?></td>
    </tr>
</table>

<br>

<h2>Letzte Trainings</h2>

<?php if (!$letzteTrainings || mysqli_num_rows($letzteTrainings) == 0) { ?>
    <p>Noch keine Trainings vorhanden.</p>
<?php } else { ?>
    <table border="1" cellpadding="8" cellspacing="0" width="100%">
        <tr>
            <th>Datum</th>
            <th>Fahrer</th>
            <th>Kilometer</th>
            <th>Trainingsziel</th>
        </tr>

        <?php while ($row = mysqli_fetch_assoc($letzteTrainings)) { ?>
            <tr>
                <td><?php echo e($row['Datum']); ?></td>
                <td><?php echo e($row['Name']); ?></td>
                <td><?php echo e($row['Kilometer']); ?></td>
                <td><?php echo e($row['Trainingsziel']); ?></td>
            </tr>
        <?php } ?>
    </table>
<?php } ?>

</body>
</html>
