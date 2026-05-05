<?php
session_start();
require 'db.php';

mysqli_set_charset($connection, 'utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$name = trim($_POST['name'] ?? '');
$kennwort = trim($_POST['kennwort'] ?? '');

if ($name === '' || $kennwort === '') {
    header('Location: index.php?login=fehler');
    exit;
}

$sql = 'SELECT Name, Kennwort FROM Rennveranstalter WHERE Name = ? LIMIT 1';
$stmt = mysqli_prepare($connection, $sql);

if (!$stmt) {
    header('Location: index.php?login=fehler');
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $name);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $dbName, $dbKennwort);
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
