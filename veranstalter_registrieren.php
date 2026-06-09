<?php
/*
 * Autor: Johnny Germar
 * Registrierungs-Baustein für Rennveranstalter.
 */
// defined prüft, ob die Datei über die Startseite eingebunden wurde.
if (!defined('INDEX_PAGE')) 
{
    // Direkte Aufrufe werden mit header zur Startseite umgeleitet.
    header('Location: index.php');
    exit;
}

// Prüfen, ob gerade das Veranstalter-Registrierungsformular abgeschickt wurde.
$istVeranstalterReg = ($_POST['form_typ'] ?? '') === 'veranstalter_reg';
// post_value liest den Namen nur für dieses Formular ein.
$veranstalterRegName = $istVeranstalterReg ? post_value('name') : '';

if (($indexPhase ?? '') === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST' && $istVeranstalterReg) 
{
    // Passwort aus dem Formular holen.
    $kennwort = post_value('kennwort');

    if ($veranstalterRegName === '' || $kennwort === '') {
        $veranstalterRegFehler = 'Bitte Name und Passwort eingeben.';
    } else {
        // mysqli_prepare bereitet den INSERT-Befehl mit Platzhaltern vor.
        $stmt = mysqli_prepare($connection, 'INSERT INTO Rennveranstalter (Name, Kennwort) VALUES (?, ?)');
        // password_hash speichert nicht das echte Passwort, sondern einen sicheren Hash.
        $hash = password_hash($kennwort, PASSWORD_DEFAULT);

        if ($stmt) {
            // bind_param setzt Name und Passwort-Hash in den INSERT-Befehl ein.
            mysqli_stmt_bind_param($stmt, 'ss', $veranstalterRegName, $hash);
            // execute führt den INSERT aus und liefert bei Erfolg true.
            $ok = mysqli_stmt_execute($stmt);
            // close beendet das Statement nach dem Speichern.
            mysqli_stmt_close($stmt);
        } else {
            $ok = false;
        }

        if ($ok) {
            // Nach erfolgreicher Registrierung direkt als Veranstalter anmelden.
            $_SESSION['rolle'] = 'veranstalter';
            $_SESSION['name'] = $veranstalterRegName;
            // Zum Veranstalter-Dashboard weiterleiten.
            header('Location: veranstalter_dashboard.php');
            exit;
        }

        // mysqli_errno liefert den Fehlercode 1062, wenn der Name schon vorhanden ist.
        $veranstalterRegFehler = mysqli_errno($connection) == 1062
            ? 'Veranstalter existiert bereits.'
            : 'Registrierung konnte nicht gespeichert werden.';
    }
}

if (($indexPhase ?? '') === 'render') 
{
?>
<?php if ($veranstalterRegFehler !== '') { ?>
    <!-- e gibt die Fehlermeldung sicher als Text aus. -->
    <p><strong><?php echo e($veranstalterRegFehler); ?></strong></p>
<?php } ?>

<!-- Formular zum Anlegen eines neuen Veranstalter-Kontos. -->
<form method="post" action="index.php">
    <input type="hidden" name="form_typ" value="veranstalter_reg">

    <label for="veranstalter_reg_name">Name:</label><br>
    <!-- e schützt den bereits eingegebenen Namen bei der erneuten Ausgabe. -->
    <input type="text" name="name" id="veranstalter_reg_name" maxlength="50" value="<?php echo e($veranstalterRegName); ?>" required>
    <br><br>

    <label for="veranstalter_reg_passwort">Passwort:</label><br>
    <input type="password" name="kennwort" id="veranstalter_reg_passwort" required>
    <br><br>

    <button type="submit">Registrieren</button>
</form>
<?php }
