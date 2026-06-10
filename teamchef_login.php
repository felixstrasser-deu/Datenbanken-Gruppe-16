<?php
/*
 * Autor: Felix Straßer
 * Login-Baustein für Teamchefs.
 */
$direktaufruf = !defined('INDEX_PAGE');

// Das Login-Modul darf nur über die Startseite eingebunden werden.
if ($direktaufruf) {
    header('Location: index.php');
    exit;
}

// Verarbeitet nur das Teamchef-Login-Formular in der Process-Phase der Startseite.
if (($indexPhase ?? '') === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_typ'] ?? '') === 'teamchef_login') {
    $loginname = post_value('loginname');
    $kennwort = post_value('kennwort');

    if ($loginname === '' || $kennwort === '') {
        $teamchefLoginFehler = 'Bitte Loginname und Passwort eingeben.';
    } else {
        // Loginname wird per Prepared Statement gesucht, damit keine SQL-Injection möglich ist.
        $sql = 'SELECT Loginname, Kennwort, Team FROM Teamchef WHERE Loginname = ? LIMIT 1';
        $stmt = mysqli_prepare($connection, $sql);

        if (!$stmt) {
            $teamchefLoginFehler = 'Login konnte nicht vorbereitet werden.';
        } else {
            mysqli_stmt_bind_param($stmt, 's', $loginname);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $dbLoginname, $dbKennwort, $dbTeam);
            $found = mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);

            // Das eingegebene Passwort wird gegen den gespeicherten Passwort-Hash geprüft.
            $passwortKorrekt = $found && password_verify($kennwort, $dbKennwort);

            if (!$passwortKorrekt) {
                $teamchefLoginFehler = 'Login fehlgeschlagen.';
            } else {
                // Nach erfolgreichem Login werden Rolle und Team in der Session gespeichert.
                session_regenerate_id(true);
                $_SESSION['rolle'] = 'teamchef';
                $_SESSION['loginname'] = $dbLoginname;
                $_SESSION['team'] = $dbTeam;

                header('Location: teamchef_dashboard.php');
                exit;
            }
        }
    }
}

// In der Render-Phase wird das Login-Formular ausgegeben.
if (($indexPhase ?? '') === 'render') {
?>
<?php if ($teamchefLoginFehler !== '') { ?>
    <!-- Fehlermeldung aus der Login-Prüfung anzeigen. -->
    <p><strong><?php echo e($teamchefLoginFehler); ?></strong></p>
<?php } ?>

<!-- Formular für den Teamchef-Login; form_typ ordnet den POST der richtigen Verarbeitung zu. -->
<form method="post" action="index.php">
    <input type="hidden" name="form_typ" value="teamchef_login">

    <!-- Eingabefeld für den Loginname des Teamchefs. -->
    <label for="teamchef_login">Loginname:</label><br>
    <input type="text" name="loginname" id="teamchef_login" required>

    <br><br>

    <!-- Passwortfeld bleibt im Browser verdeckt. -->
    <label for="teamchef_passwort">Passwort:</label><br>
    <input type="password" name="kennwort" id="teamchef_passwort" required>

    <br><br>

    <button type="submit">Login</button>
</form>
<?php
}

?>
