<?php
session_start();
include 'db.php';

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
$loginStatus = trim($_GET['login'] ?? '');
$regStatus = trim($_GET['reg'] ?? '');
$regMeldung = '';
$regFehler = '';
$regLoginname = trim($_POST['loginname'] ?? '');
$regName = trim($_POST['name'] ?? '');
$regVorname = trim($_POST['vorname'] ?? '');
$regTeam = trim($_POST['team'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_typ'] ?? '') === 'teamchef_reg') {
    $regKennwort = trim($_POST['kennwort'] ?? '');

    if ($regLoginname === '' || $regName === '' || $regVorname === '' || $regTeam === '' || $regKennwort === '') {
        $regFehler = 'Bitte alle Felder ausfüllen.';
    } else {
        $checkSql = 'SELECT 1 FROM Teamchef WHERE Team = ? LIMIT 1';
        $checkStmt = mysqli_prepare($connection, $checkSql);

        if ($checkStmt) {
            mysqli_stmt_bind_param($checkStmt, 's', $regTeam);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);

            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                $regFehler = 'Dieses Team hat bereits einen Teamchef.';
            }

            mysqli_stmt_close($checkStmt);
        } else {
            $regFehler = 'Fehler bei der Teamchef-Prüfung.';
        }

        if ($regFehler === '') {
            $hash = password_hash($regKennwort, PASSWORD_DEFAULT);
            $sql = 'INSERT INTO Teamchef (Loginname, Name, Vorname, Kennwort, Team) VALUES (?, ?, ?, ?, ?)';
            $stmt = mysqli_prepare($connection, $sql);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssss', $regLoginname, $regName, $regVorname, $hash, $regTeam);

                if (mysqli_stmt_execute($stmt)) {
                    $regMeldung = 'Registrierung erfolgreich.';
                    $regLoginname = '';
                    $regName = '';
                    $regVorname = '';
                    $regTeam = '';
                } else {
                    $regFehler = 'Fehler beim Speichern: ' . mysqli_error($connection);
                }

                mysqli_stmt_close($stmt);
            } else {
                $regFehler = 'Fehler beim Vorbereiten der SQL-Anweisung.';
            }
        }
    }
}

$result = mysqli_query($connection, 'SELECT COUNT(*) AS anzahl FROM Team');
if ($row = mysqli_fetch_assoc($result)) {
    $anzahlTeams = $row['anzahl'];
}

$result = mysqli_query($connection, 'SELECT COUNT(*) AS anzahl FROM Fahrer');
if ($row = mysqli_fetch_assoc($result)) {
    $anzahlFahrer = $row['anzahl'];
}

$result = mysqli_query($connection, 'SELECT COUNT(*) AS anzahl FROM Radrennen');
if ($row = mysqli_fetch_assoc($result)) {
    $anzahlRennen = $row['anzahl'];
}

$result = mysqli_query($connection, 'SELECT COUNT(*) AS anzahl FROM Training');
if ($row = mysqli_fetch_assoc($result)) {
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

$teams = mysqli_query($connection, 'SELECT Teamname FROM Team ORDER BY Teamname');
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Verwaltung von Radrennen</title>
</head>
<body>

<h1>Verwaltung von Radrennen</h1>

<?php if ($loginStatus === 'ok') { ?>
    <p style="color: green;">Login erfolgreich.</p>
<?php } elseif ($loginStatus === 'fehler') { ?>
    <p style="color: red;">Login fehlgeschlagen.</p>
<?php } ?>

<?php if ($regStatus === 'exists') { ?>
    <p style="color: red;">Veranstalter existiert bereits.</p>
<?php } elseif ($regStatus === 'fehler') { ?>
    <p style="color: red;">Veranstalter-Registrierung fehlgeschlagen.</p>
<?php } ?>

<h2>Login und Registrierung</h2>

<table border="1" cellpadding="10" cellspacing="0">
    <tr>
        <td>
            <h3>Teamchef Login</h3>

            <form method="post" action="login.php">
                <label for="teamchef_login">Loginname:</label><br>
                <input type="text" name="loginname" id="teamchef_login" required>

                <br><br>

                <label for="teamchef_passwort">Passwort:</label><br>
                <input type="password" name="kennwort" id="teamchef_passwort" required>

                <br><br>

                <button type="submit">Login</button>
            </form>
        </td>

        <td>
            <h3>Teamchef Registrierung</h3>

            <?php if ($regMeldung !== '') { ?>
                <p style="color: green;"><?php echo htmlspecialchars($regMeldung, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php } ?>

            <?php if ($regFehler !== '') { ?>
                <p style="color: red;"><?php echo htmlspecialchars($regFehler, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php } ?>

            <form method="post" action="index.php">
                <input type="hidden" name="form_typ" value="teamchef_reg">
                <label for="reg_loginname">Loginname:</label><br>
                <input type="text" name="loginname" id="reg_loginname" value="<?php echo htmlspecialchars($regLoginname, ENT_QUOTES, 'UTF-8'); ?>" required>

                <br><br>

                <label for="reg_name">Name:</label><br>
                <input type="text" name="name" id="reg_name" value="<?php echo htmlspecialchars($regName, ENT_QUOTES, 'UTF-8'); ?>" required>

                <br><br>

                <label for="reg_vorname">Vorname:</label><br>
                <input type="text" name="vorname" id="reg_vorname" value="<?php echo htmlspecialchars($regVorname, ENT_QUOTES, 'UTF-8'); ?>" required>

                <br><br>

                <label for="reg_team">Team:</label><br>
                <select name="team" id="reg_team" required>
                    <option value="">Bitte wählen</option>
                    <?php while ($row = mysqli_fetch_assoc($teams)) { ?>
                        <option value="<?php echo htmlspecialchars($row['Teamname'], ENT_QUOTES, 'UTF-8'); ?>" <?php if ($regTeam === $row['Teamname']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($row['Teamname']); ?>
                        </option>
                    <?php } ?>
                </select>

                <br><br>

                <label for="reg_passwort">Passwort:</label><br>
                <input type="password" name="kennwort" id="reg_passwort" required>

                <br><br>

                <button type="submit">Registrieren</button>
            </form>
        </td>
    </tr>

    <tr>
        <td>
            <h3>Veranstalter Login</h3>

            <form method="post" action="veranstalter_login.php">
                <label for="veranstalter_name">Name:</label><br>
                <input type="text" name="name" id="veranstalter_name" required>

                <br><br>

                <label for="veranstalter_passwort">Passwort:</label><br>
                <input type="password" name="kennwort" id="veranstalter_passwort" required>

                <br><br>

                <button type="submit">Veranstalter Login</button>
            </form>
        </td>

        <td>
            <h3>Veranstalter Registrierung</h3>

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

<h2>Übersicht</h2>

<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>Teams</th>
        <th>Fahrer</th>
        <th>Rennen</th>
        <th>Trainings</th>
    </tr>
    <tr>
        <td><?php echo htmlspecialchars($anzahlTeams); ?></td>
        <td><?php echo htmlspecialchars($anzahlFahrer); ?></td>
        <td><?php echo htmlspecialchars($anzahlRennen); ?></td>
        <td><?php echo htmlspecialchars($anzahlTrainings); ?></td>
    </tr>
</table>

<h2>Letzte Trainings</h2>

<?php if (mysqli_num_rows($letzteTrainings) == 0) { ?>
    <p>Noch keine Trainings vorhanden.</p>
<?php } else { ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>Datum</th>
            <th>Fahrer</th>
            <th>Kilometer</th>
            <th>Trainingsziel</th>
        </tr>

        <?php while ($row = mysqli_fetch_assoc($letzteTrainings)) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['Datum']); ?></td>
                <td><?php echo htmlspecialchars($row['Name']); ?></td>
                <td><?php echo htmlspecialchars($row['Kilometer']); ?></td>
                <td><?php echo htmlspecialchars($row['Trainingsziel']); ?></td>
            </tr>
        <?php } ?>
    </table>
<?php } ?>

<h2>Bearbeiten</h2>

<h3>Team und Fahrer</h3>
<ul>
    <li><a href="team.php">Team verwalten</a></li>
    <li><a href="fahrer.php">Fahrer verwalten</a></li>
</ul>

<h3>Rennen und Anmeldung</h3>
<ul>
    <li><a href="rennen.php">Rennen verwalten</a></li>
    <li><a href="anmeldung.php">Anmeldung</a></li>
    <li><a href="kopieren.php">Anmeldung kopieren</a></li>
</ul>

<h3>Training, Ergebnisse und Auswertung</h3>
<ul>
    <li><a href="training.php">Training erfassen</a></li>
    <li><a href="ergebnisse.php">Ergebnisse erfassen</a></li>
    <li><a href="auswertung.php">Auswertung anzeigen</a></li>
</ul>

</body>
</html>
