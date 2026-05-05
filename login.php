<?php
session_start();
require 'db.php';

mysqli_set_charset($connection, 'utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$loginname = trim($_POST['loginname'] ?? '');
$kennwort = trim($_POST['kennwort'] ?? '');

if ($loginname === '' || $kennwort === '') {
    header('Location: index.php?login=fehler');
    exit;
}

$sql = 'SELECT Loginname, Kennwort, Team FROM Teamchef WHERE Loginname = ? LIMIT 1';
$stmt = mysqli_prepare($connection, $sql);

if (!$stmt) {
    header('Location: index.php?login=fehler');
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $loginname);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $dbLoginname, $dbKennwort, $dbTeam);
$found = mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$found) {
    header('Location: index.php?login=fehler');
    exit;
}

$passwortKorrekt = password_verify($kennwort, $dbKennwort) || hash_equals($dbKennwort, $kennwort);

if (!$passwortKorrekt) {
    header('Location: index.php?login=fehler');
    exit;
}

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
