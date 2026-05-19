<?php
/*
 * Autor: Felix Strasser
 * Registrierungs-Baustein für Team und Teamchef.
 */
if (!defined('INDEX_PAGE')) {
    header('Location: index.php');
    exit;
}

$istTeamchefReg = ($_POST['form_typ'] ?? '') === 'teamchef_reg';
$teamchefRegTeam = $istTeamchefReg ? post_value('teamname') : '';
$teamchefRegLoginname = $istTeamchefReg ? post_value('loginname') : '';
$teamchefRegName = $istTeamchefReg ? post_value('name') : '';
$teamchefRegVorname = $istTeamchefReg ? post_value('vorname') : '';

if (($indexPhase ?? '') === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST' && $istTeamchefReg) {
    $kennwort = post_value('kennwort');
    $werte = array($teamchefRegTeam, $teamchefRegLoginname, $teamchefRegName, $teamchefRegVorname, $kennwort);

    if (in_array('', $werte, true)) {
        $teamchefRegFehler = 'Bitte alle Felder ausfüllen.';
    } elseif (max(array_map('strlen', array_slice($werte, 0, 4))) > 46) {
        $teamchefRegFehler = 'Textfelder dürfen maximal 46 Zeichen lang sein.';
    } elseif (team_exists($connection, $teamchefRegTeam)) {
        $teamchefRegFehler = 'Dieses Team existiert bereits.';
    } elseif (loginname_exists($connection, $teamchefRegLoginname)) {
        $teamchefRegFehler = 'Dieser Loginname existiert bereits.';
    } elseif (!create_team_with_chef($connection, $teamchefRegTeam, $teamchefRegLoginname, $teamchefRegName, $teamchefRegVorname, $kennwort)) {
        $teamchefRegFehler = 'Registrierung konnte nicht gespeichert werden: ' . mysqli_error($connection);
    } else {
        $_SESSION['rolle'] = 'teamchef';
        $_SESSION['loginname'] = $teamchefRegLoginname;
        $_SESSION['team'] = $teamchefRegTeam;
        header('Location: teamchef_dashboard.php');
        exit;
    }
}

if (($indexPhase ?? '') === 'render') {
$felder = array(
    array('reg_team', 'teamname', 'Teamname', $teamchefRegTeam),
    array('reg_loginname', 'loginname', 'Loginname', $teamchefRegLoginname),
    array('reg_name', 'name', 'Name', $teamchefRegName),
    array('reg_vorname', 'vorname', 'Vorname', $teamchefRegVorname),
);
?>
<?php if ($teamchefRegFehler !== '') { ?>
    <p><strong><?php echo e($teamchefRegFehler); ?></strong></p>
<?php } ?>

<form method="post" action="index.php">
    <input type="hidden" name="form_typ" value="teamchef_reg">

    <?php foreach ($felder as $feld) { ?>
        <label for="<?php echo e($feld[0]); ?>"><?php echo e($feld[2]); ?>:</label><br>
        <input type="text" name="<?php echo e($feld[1]); ?>" id="<?php echo e($feld[0]); ?>" maxlength="46" value="<?php echo e($feld[3]); ?>" required>
        <br><br>
    <?php } ?>

    <label for="reg_passwort">Passwort:</label><br>
    <input type="password" name="kennwort" id="reg_passwort" required>
    <br><br>

    <button type="submit">Registrieren</button>
</form>
<?php } ?>
