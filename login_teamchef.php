<?php
/*
 * Autor: Felix Straßer
 * Login-Baustein für Teamchefs.
 */
$direktaufruf = !defined('INDEX_PAGE');

if ($direktaufruf) {
    header('Location: index.php');
    exit;
}

if (($indexPhase ?? '') === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_typ'] ?? '') === 'teamchef_login') {
    $loginname = post_value('loginname');
    $kennwort = post_value('kennwort');

    if ($loginname === '' || $kennwort === '') {
        $teamchefLoginFehler = 'Bitte Loginname und Passwort eingeben.';
    } else {
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

            $passwortKorrekt = $found && (password_verify($kennwort, $dbKennwort) || hash_equals($dbKennwort, $kennwort));

            if (!$passwortKorrekt) {
                $teamchefLoginFehler = 'Login fehlgeschlagen.';
            } else {
                $_SESSION['rolle'] = 'teamchef';
                $_SESSION['loginname'] = $dbLoginname;
                $_SESSION['team'] = $dbTeam;

                if (!password_verify($kennwort, $dbKennwort)) {
                    $hash = password_hash($kennwort, PASSWORD_DEFAULT);
                    $update = mysqli_prepare($connection, 'UPDATE Teamchef SET Kennwort = ? WHERE Loginname = ?');
                    if ($update) {
                        mysqli_stmt_bind_param($update, 'ss', $hash, $dbLoginname);
                        mysqli_stmt_execute($update);
                        mysqli_stmt_close($update);
                    }
                }

                header('Location: teamchef_dashboard.php');
                exit;
            }
        }
    }
}

if (($indexPhase ?? '') === 'render') {
?>
<?php if ($teamchefLoginFehler !== '') { ?>
    <p><strong><?php echo e($teamchefLoginFehler); ?></strong></p>
<?php } ?>

<form method="post" action="index.php">
    <input type="hidden" name="form_typ" value="teamchef_login">

    <label for="teamchef_login">Loginname:</label><br>
    <input type="text" name="loginname" id="teamchef_login" required>

    <br><br>

    <label for="teamchef_passwort">Passwort:</label><br>
    <input type="password" name="kennwort" id="teamchef_passwort" required>

    <br><br>

    <button type="submit">Login</button>
</form>
<?php
}

?>
