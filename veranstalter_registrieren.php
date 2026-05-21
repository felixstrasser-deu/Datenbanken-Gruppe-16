<?php
/*
 * Autor: Johnny Germar
 * Registrierungs-Baustein für Rennveranstalter.
 */
if (!defined('INDEX_PAGE')) {
    header('Location: index.php');
    exit;
}

$istVeranstalterReg = ($_POST['form_typ'] ?? '') === 'veranstalter_reg';
$veranstalterRegName = $istVeranstalterReg ? post_value('name') : '';

if (($indexPhase ?? '') === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST' && $istVeranstalterReg) {
    $kennwort = post_value('kennwort');

    if ($veranstalterRegName === '' || $kennwort === '') {
        $veranstalterRegFehler = 'Bitte Name und Passwort eingeben.';
    } else {
        $stmt = mysqli_prepare($connection, 'INSERT INTO Rennveranstalter (Name, Kennwort) VALUES (?, ?)');
        $hash = password_hash($kennwort, PASSWORD_DEFAULT);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $veranstalterRegName, $hash);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $ok = false;
        }

        if ($ok) {
            $_SESSION['rolle'] = 'veranstalter';
            $_SESSION['name'] = $veranstalterRegName;
            header('Location: veranstalter_dashboard.php');
            exit;
        }

        $veranstalterRegFehler = mysqli_errno($connection) == 1062
            ? 'Veranstalter existiert bereits.'
            : 'Registrierung konnte nicht gespeichert werden.';
    }
}

if (($indexPhase ?? '') === 'render') {
?>
<?php if ($veranstalterRegFehler !== '') { ?>
    <p><strong><?php echo e($veranstalterRegFehler); ?></strong></p>
<?php } ?>

<form method="post" action="index.php">
    <input type="hidden" name="form_typ" value="veranstalter_reg">

    <label for="veranstalter_reg_name">Name:</label><br>
    <input type="text" name="name" id="veranstalter_reg_name" maxlength="50" value="<?php echo e($veranstalterRegName); ?>" required>
    <br><br>

    <label for="veranstalter_reg_passwort">Passwort:</label><br>
    <input type="password" name="kennwort" id="veranstalter_reg_passwort" required>
    <br><br>

    <button type="submit">Registrieren</button>
</form>
<?php } ?>
