<?php
/*
 * Autor: Johnny Germar
 * Login-Baustein für Rennveranstalter.
 */
// defined prüft, ob dieses Modul über die Startseite eingebunden wurde.
$direktaufruf = !defined('INDEX_PAGE');

if ($direktaufruf) {
    // header leitet direkte Aufrufe zurück zur Startseite.
    header('Location: index.php');
    exit;
}

// Der Login wird nur in der Verarbeitungsphase und nach Absenden des passenden Formulars ausgeführt.
if (($indexPhase ?? '') === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_typ'] ?? '') === 'veranstalter_login') {
    // post_value liest die Formularwerte ein.
    $name = post_value('name');
    $kennwort = post_value('kennwort');

    if ($name === '' || $kennwort === '') {
        $veranstalterLoginFehler = 'Bitte Name und Passwort eingeben.';
    } else {
        $sql = 'SELECT Name, Kennwort FROM Rennveranstalter WHERE Name = ? LIMIT 1';
        // mysqli_prepare bereitet die Abfrage mit einem sicheren Platzhalter vor.
        $stmt = mysqli_prepare($connection, $sql);

        if (!$stmt) 
        {
            $veranstalterLoginFehler = 'Login konnte nicht vorbereitet werden.';
        } else {
            // bind_param setzt den Namen anstelle des Fragezeichens ein.
            mysqli_stmt_bind_param($stmt, 's', $name);
            // execute führt die vorbereitete Abfrage aus.
            mysqli_stmt_execute($stmt);
            // bind_result verbindet die gefundenen Spalten mit Variablen.
            mysqli_stmt_bind_result($stmt, $dbName, $dbKennwort);
            // fetch liest den gefundenen Datensatz und liefert false, wenn keiner existiert.
            $found = mysqli_stmt_fetch($stmt);
            // close beendet das Statement nach der Abfrage.
            mysqli_stmt_close($stmt);

            // password_verify vergleicht das eingegebene Passwort mit dem gespeicherten Hash.
            $passwortKorrekt = $found && password_verify($kennwort, $dbKennwort);

            if (!$passwortKorrekt) 
            {
                $veranstalterLoginFehler = 'Login fehlgeschlagen.';
            } else {
                // Neue Session-ID schützt die Sitzung nach einem erfolgreichen Login.
                session_regenerate_id(true);
                // Rolle und Name werden für spätere Seiten in der Session gespeichert.
                $_SESSION['rolle'] = 'veranstalter';
                $_SESSION['name'] = $dbName;

                // Nach dem Login zum Veranstalter-Dashboard weiterleiten.
                header('Location: veranstalter_dashboard.php');
                exit;
            }
        }
    }
}

if (($indexPhase ?? '') === 'render') 
{
?>
<?php if ($veranstalterLoginFehler !== '') { ?>
    <!-- e gibt die Fehlermeldung sicher als Text aus. -->
    <p><strong><?php echo e($veranstalterLoginFehler); ?></strong></p>
<?php } ?>

<!-- Loginformular für bereits registrierte Veranstalter. -->
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
