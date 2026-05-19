<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Registrierungs-Baustein für Rennveranstalter.
 */
$direktaufruf = !defined('INDEX_PAGE');

if ($direktaufruf) {
    header('Location: index.php');
    exit;
}

$veranstalterRegName = ($_POST['form_typ'] ?? '') === 'veranstalter_reg' ? post_value('name') : '';

if (($indexPhase ?? '') === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_typ'] ?? '') === 'veranstalter_reg') {
    $kennwort = post_value('kennwort');

    if ($veranstalterRegName === '' || $kennwort === '') {
        $veranstalterRegFehler = 'Bitte Name und Passwort eingeben.';
    } else {
        $checkSql = 'SELECT 1 FROM Rennveranstalter WHERE Name = ? LIMIT 1';
        $checkStmt = mysqli_prepare($connection, $checkSql);

        if (!$checkStmt) {
            $veranstalterRegFehler = 'Registrierung konnte nicht vorbereitet werden.';
        } else {
            mysqli_stmt_bind_param($checkStmt, 's', $veranstalterRegName);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);
            $exists = mysqli_stmt_num_rows($checkStmt) > 0;
            mysqli_stmt_close($checkStmt);

            if ($exists) {
                $veranstalterRegFehler = 'Veranstalter existiert bereits.';
            } else {
                $hash = password_hash($kennwort, PASSWORD_DEFAULT);
                $sql = 'INSERT INTO Rennveranstalter (Name, Kennwort) VALUES (?, ?)';
                $stmt = mysqli_prepare($connection, $sql);

                if (!$stmt) {
                    $veranstalterRegFehler = 'Registrierung konnte nicht vorbereitet werden.';
                } else {
                    mysqli_stmt_bind_param($stmt, 'ss', $veranstalterRegName, $hash);
                    $ok = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    if (!$ok) {
                        $veranstalterRegFehler = 'Registrierung konnte nicht gespeichert werden.';
                    } else {
                        $_SESSION['rolle'] = 'veranstalter';
                        $_SESSION['name'] = $veranstalterRegName;

                        header('Location: veranstalter_dashboard.php');
                        exit;
                    }
                }
            }
        }
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
    <input type="text" name="name" id="veranstalter_reg_name" value="<?php echo e($veranstalterRegName); ?>" required>

    <br><br>

    <label for="veranstalter_reg_passwort">Passwort:</label><br>
    <input type="password" name="kennwort" id="veranstalter_reg_passwort" required>

    <br><br>

    <button type="submit">Registrieren</button>
</form>
<?php
}

?>
