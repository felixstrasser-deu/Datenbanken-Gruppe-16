<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Registrierungs-Baustein für Team und Teamchef.
 */
$direktaufruf = !defined('INDEX_PAGE');

if ($direktaufruf) {
    session_start();
    require 'db.php';
    require 'functions.php';
    mysqli_set_charset($connection, 'utf8mb4');
    $indexPhase = 'process';
}

$teamchefRegTeam = ($_POST['form_typ'] ?? '') === 'teamchef_reg' ? post_value('teamname') : '';
$teamchefRegLoginname = ($_POST['form_typ'] ?? '') === 'teamchef_reg' ? post_value('loginname') : '';
$teamchefRegName = ($_POST['form_typ'] ?? '') === 'teamchef_reg' ? post_value('name') : '';
$teamchefRegVorname = ($_POST['form_typ'] ?? '') === 'teamchef_reg' ? post_value('vorname') : '';

if (($indexPhase ?? '') === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_typ'] ?? '') === 'teamchef_reg') {
    $kennwort = post_value('kennwort');

    if ($teamchefRegTeam === '' || $teamchefRegLoginname === '' || $teamchefRegName === '' || $teamchefRegVorname === '' || $kennwort === '') {
        $teamchefRegFehler = 'Bitte alle Felder ausfüllen.';
    } elseif (strlen($teamchefRegTeam) > 46 || strlen($teamchefRegLoginname) > 46 || strlen($teamchefRegName) > 46 || strlen($teamchefRegVorname) > 46) {
        $teamchefRegFehler = 'Textfelder dürfen maximal 46 Zeichen lang sein.';
    } elseif (team_exists($connection, $teamchefRegTeam)) {
        $teamchefRegFehler = 'Dieses Team existiert bereits.';
    } elseif (loginname_exists($connection, $teamchefRegLoginname)) {
        $teamchefRegFehler = 'Dieser Loginname existiert bereits.';
    } elseif (create_team_with_chef($connection, $teamchefRegTeam, $teamchefRegLoginname, $teamchefRegName, $teamchefRegVorname, $kennwort)) {
        $_SESSION['rolle'] = 'teamchef';
        $_SESSION['loginname'] = $teamchefRegLoginname;
        $_SESSION['team'] = $teamchefRegTeam;

        header('Location: teamchef_dashboard.php');
        exit;
    } else {
        $teamchefRegFehler = 'Registrierung konnte nicht gespeichert werden: ' . mysqli_error($connection);
    }
}

if ($direktaufruf) {
    header('Location: index.php');
    exit;
}

if (($indexPhase ?? '') === 'render') {
?>
<?php if ($teamchefRegFehler !== '') { ?>
    <p><strong><?php echo e($teamchefRegFehler); ?></strong></p>
<?php } ?>

<form method="post" action="index.php">
    <input type="hidden" name="form_typ" value="teamchef_reg">

    <label for="reg_team">Teamname:</label><br>
    <input type="text" name="teamname" id="reg_team" maxlength="46" value="<?php echo e($teamchefRegTeam); ?>" required>

    <br><br>

    <label for="reg_loginname">Loginname:</label><br>
    <input type="text" name="loginname" id="reg_loginname" maxlength="46" value="<?php echo e($teamchefRegLoginname); ?>" required>

    <br><br>

    <label for="reg_name">Name:</label><br>
    <input type="text" name="name" id="reg_name" maxlength="46" value="<?php echo e($teamchefRegName); ?>" required>

    <br><br>

    <label for="reg_vorname">Vorname:</label><br>
    <input type="text" name="vorname" id="reg_vorname" maxlength="46" value="<?php echo e($teamchefRegVorname); ?>" required>

    <br><br>

    <label for="reg_passwort">Passwort:</label><br>
    <input type="password" name="kennwort" id="reg_passwort" required>

    <br><br>

    <button type="submit">Registrieren</button>
</form>
<?php
}
?>
