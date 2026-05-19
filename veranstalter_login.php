<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Login-Baustein für Rennveranstalter.
 */
$direktaufruf = !defined('INDEX_PAGE');

if ($direktaufruf) {
    header('Location: index.php');
    exit;
}

if (($indexPhase ?? '') === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_typ'] ?? '') === 'veranstalter_login') {
    $name = post_value('name');
    $kennwort = post_value('kennwort');

    if ($name === '' || $kennwort === '') {
        $veranstalterLoginFehler = 'Bitte Name und Passwort eingeben.';
    } else {
        $sql = 'SELECT Name, Kennwort FROM Rennveranstalter WHERE Name = ? LIMIT 1';
        $stmt = mysqli_prepare($connection, $sql);

        if (!$stmt) {
            $veranstalterLoginFehler = 'Login konnte nicht vorbereitet werden.';
        } else {
            mysqli_stmt_bind_param($stmt, 's', $name);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $dbName, $dbKennwort);
            $found = mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);

            $passwortKorrekt = $found && (password_verify($kennwort, $dbKennwort) || hash_equals($dbKennwort, $kennwort));

            if (!$passwortKorrekt) {
                $veranstalterLoginFehler = 'Login fehlgeschlagen.';
            } else {
                $_SESSION['rolle'] = 'veranstalter';
                $_SESSION['name'] = $dbName;

                if (!password_verify($kennwort, $dbKennwort)) {
                    $hash = password_hash($kennwort, PASSWORD_DEFAULT);
                    $update = mysqli_prepare($connection, 'UPDATE Rennveranstalter SET Kennwort = ? WHERE Name = ?');
                    if ($update) {
                        mysqli_stmt_bind_param($update, 'ss', $hash, $dbName);
                        mysqli_stmt_execute($update);
                        mysqli_stmt_close($update);
                    }
                }

                header('Location: veranstalter_dashboard.php');
                exit;
            }
        }
    }
}

if (($indexPhase ?? '') === 'render') {
?>
<?php if ($veranstalterLoginFehler !== '') { ?>
    <p><strong><?php echo e($veranstalterLoginFehler); ?></strong></p>
<?php } ?>

<form method="post" action="index.php">
    <input type="hidden" name="form_typ" value="veranstalter_login">

    <label for="veranstalter_name">Name:</label><br>
    <input type="text" name="name" id="veranstalter_name" required>

    <br><br>

    <label for="veranstalter_passwort">Passwort:</label><br>
    <input type="password" name="kennwort" id="veranstalter_passwort" required>

    <br><br>

    <button type="submit">Login</button>
</form>
<?php
}

?>
