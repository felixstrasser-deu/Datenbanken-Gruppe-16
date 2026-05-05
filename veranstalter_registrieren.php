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
    header('Location: index.php?reg=fehler');
    exit;
}

$checkSql = 'SELECT 1 FROM Rennveranstalter WHERE Name = ? LIMIT 1';
$checkStmt = mysqli_prepare($connection, $checkSql);

if (!$checkStmt) {
    header('Location: index.php?reg=fehler');
    exit;
}

mysqli_stmt_bind_param($checkStmt, 's', $name);
mysqli_stmt_execute($checkStmt);
mysqli_stmt_store_result($checkStmt);
$exists = mysqli_stmt_num_rows($checkStmt) > 0;
mysqli_stmt_close($checkStmt);

if ($exists) {
    header('Location: index.php?reg=exists');
    exit;
}

$hash = password_hash($kennwort, PASSWORD_DEFAULT);
$sql = 'INSERT INTO Rennveranstalter (Name, Kennwort) VALUES (?, ?)';
$stmt = mysqli_prepare($connection, $sql);

if (!$stmt) {
    header('Location: index.php?reg=fehler');
    exit;
}

mysqli_stmt_bind_param($stmt, 'ss', $name, $hash);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    header('Location: index.php?reg=fehler');
    exit;
}

$_SESSION['rolle'] = 'veranstalter';
$_SESSION['name'] = $name;

header('Location: veranstalter_dashboard.php');
exit;
