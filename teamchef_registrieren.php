<?php
/*
 * Autor: Felix Straßer
 * Registrierungs-Baustein für Team und Teamchef.
 */
// Das Registrierungsmodul darf nur von der Startseite eingebunden werden.
if (!defined('INDEX_PAGE')) {
    header('Location: index.php');
    exit;
}

// Werte nur auslesen, wenn wirklich das Teamchef-Registrierungsformular abgeschickt wurde.
$istTeamchefReg = ($_POST['form_typ'] ?? '') === 'teamchef_reg';
$teamchefRegTeam = $istTeamchefReg ? post_value('teamname') : '';
$teamchefRegLoginname = $istTeamchefReg ? post_value('loginname') : '';
$teamchefRegName = $istTeamchefReg ? post_value('name') : '';
$teamchefRegVorname = $istTeamchefReg ? post_value('vorname') : '';

// Formular prüfen und bei gültigen Daten Team plus Teamchef anlegen.
if (($indexPhase ?? '') === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST' && $istTeamchefReg) {
    $kennwort = post_value('kennwort');
    $werte = array($teamchefRegTeam, $teamchefRegLoginname, $teamchefRegName, $teamchefRegVorname, $kennwort);

    if (in_array('', $werte, true)) {
        $teamchefRegFehler = 'Bitte alle Felder ausfüllen.';
    } elseif (teamExistiert($connection, $teamchefRegTeam)) {
        $teamchefRegFehler = 'Dieses Team existiert bereits.';
    } elseif (loginnameExistiert($connection, $teamchefRegLoginname)) {
        $teamchefRegFehler = 'Dieser Loginname existiert bereits.';
    } elseif (!teamMitTeamchefErstellen($connection, $teamchefRegTeam, $teamchefRegLoginname, $teamchefRegName, $teamchefRegVorname, $kennwort)) {
        $teamchefRegFehler = 'Registrierung konnte nicht gespeichert werden: ' . mysqli_error($connection);
    } else {
        // Direkt nach erfolgreicher Registrierung als Teamchef einloggen.
        $_SESSION['rolle'] = 'teamchef';
        $_SESSION['loginname'] = $teamchefRegLoginname;
        $_SESSION['team'] = $teamchefRegTeam;
        header('Location: teamchef_dashboard.php');
        exit;
    }
}

// In der Render-Phase wird das Registrierungsformular ausgegeben.
if (($indexPhase ?? '') === 'render') {
// Felddefinitionen für die Textfelder im Registrierungsformular.
$felder = array(
    array('reg_team', 'teamname', 'Teamname', $teamchefRegTeam),
    array('reg_loginname', 'loginname', 'Loginname', $teamchefRegLoginname),
    array('reg_name', 'name', 'Name', $teamchefRegName),
    array('reg_vorname', 'vorname', 'Vorname', $teamchefRegVorname),
);
?>
<?php if ($teamchefRegFehler !== '') { ?>
    <!-- Fehlermeldung aus der Registrierungsprüfung anzeigen. -->
    <p><strong><?php echo e($teamchefRegFehler); ?></strong></p>
<?php } ?>

<!-- Formular zum Anlegen eines Teams und des zugehörigen Teamchef-Logins. -->
<form method="post" action="index.php">
    <input type="hidden" name="form_typ" value="teamchef_reg">

    <?php foreach ($felder as $feld) { ?>
        <!-- Die Textfelder werden aus dem Array oben erzeugt, damit die Ausgabe nicht viermal wiederholt wird. -->
        <label for="<?php echo e($feld[0]); ?>"><?php echo e($feld[2]); ?>:</label><br>
        <input type="text" name="<?php echo e($feld[1]); ?>" id="<?php echo e($feld[0]); ?>" maxlength="50" value="<?php echo e($feld[3]); ?>" required>
        <br><br>
    <?php } ?>

    <!-- Passwort wird später vor dem Speichern gehasht. -->
    <label for="reg_passwort">Passwort:</label><br>
    <input type="password" name="kennwort" id="reg_passwort" required>
    <br><br>

    <button type="submit">Registrieren</button>
</form>
<?php }
