<?php
/*
 * Autor: Gruppe 16 - bitte fuer die Abgabe den verantwortlichen Namen ergaenzen.
 * Gemeinsame Hilfsfunktionen fuer Authentifizierung, Ausgabe und Datenbankzugriffe.
 */

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function post_value($key)
{
    return trim((string) ($_POST[$key] ?? ''));
}

function get_value($key)
{
    return trim((string) ($_GET[$key] ?? ''));
}

function require_role($role)
{
    if (!isset($_SESSION['rolle']) || $_SESSION['rolle'] !== $role) {
        header('Location: index.php');
        exit;
    }
}

function team_exists($connection, $teamname)
{
    $stmt = mysqli_prepare($connection, 'SELECT 1 FROM Team WHERE Teamname = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 's', $teamname);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $exists;
}

function loginname_exists($connection, $loginname)
{
    $stmt = mysqli_prepare($connection, 'SELECT 1 FROM Teamchef WHERE Loginname = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 's', $loginname);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $exists;
}

function create_team_with_chef($connection, $teamname, $loginname, $name, $vorname, $kennwort)
{
    mysqli_begin_transaction($connection);

    $teamStmt = mysqli_prepare($connection, 'INSERT INTO Team (Teamname) VALUES (?)');
    if (!$teamStmt) {
        mysqli_rollback($connection);
        return false;
    }

    mysqli_stmt_bind_param($teamStmt, 's', $teamname);
    $teamOk = mysqli_stmt_execute($teamStmt);
    mysqli_stmt_close($teamStmt);

    if (!$teamOk) {
        mysqli_rollback($connection);
        return false;
    }

    $hash = password_hash($kennwort, PASSWORD_DEFAULT);
    $chefStmt = mysqli_prepare($connection, 'INSERT INTO Teamchef (Loginname, Name, Vorname, Kennwort, Team) VALUES (?, ?, ?, ?, ?)');
    if (!$chefStmt) {
        mysqli_rollback($connection);
        return false;
    }

    mysqli_stmt_bind_param($chefStmt, 'sssss', $loginname, $name, $vorname, $hash, $teamname);
    $chefOk = mysqli_stmt_execute($chefStmt);
    mysqli_stmt_close($chefStmt);

    if (!$chefOk) {
        mysqli_rollback($connection);
        return false;
    }

    mysqli_commit($connection);
    return true;
}

function save_fahrer($connection, $mode, $team, $mitarbeiterId, $name, $strasse, $hausnr, $plz, $ort, $telnr)
{
    $sql = 'CALL FahrerSpeichern(?, ?, ?, ?, ?, ?, ?, ?, ?, @fahrer_status, @fahrer_meldung)';
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        return array(false, 'Stored Procedure FahrerSpeichern konnte nicht vorbereitet werden: ' . mysqli_error($connection));
    }

    mysqli_stmt_bind_param($stmt, 'ssissssss', $mode, $team, $mitarbeiterId, $name, $strasse, $hausnr, $plz, $ort, $telnr);
    $ok = mysqli_stmt_execute($stmt);
    $error = mysqli_error($connection);
    mysqli_stmt_close($stmt);

    while (mysqli_more_results($connection)) {
        mysqli_next_result($connection);
    }

    if (!$ok) {
        return array(false, 'Stored Procedure FahrerSpeichern konnte nicht ausgefuehrt werden: ' . $error);
    }

    $result = mysqli_query($connection, 'SELECT @fahrer_status AS status, @fahrer_meldung AS meldung');
    if (!$result || !($row = mysqli_fetch_assoc($result))) {
        return array(false, 'Rueckmeldung der Stored Procedure konnte nicht gelesen werden.');
    }

    return array($row['status'] === 'OK', $row['meldung']);
}

function create_rennen($connection, $datum, $standort, $kilometer, $hoehenmeter, $maxSteigung, $veranstalterName)
{
    $sql = 'INSERT INTO Radrennen (`Datum`, `Standort`, `Kilometer`, `Höhenmeter`, `MaxSteigung`, `VName`) VALUES (?, ?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ssiids', $datum, $standort, $kilometer, $hoehenmeter, $maxSteigung, $veranstalterName);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

function rennen_ist_zukuenftig($connection, $rennenId)
{
    $stmt = mysqli_prepare($connection, 'SELECT 1 FROM Radrennen WHERE `Renn-ID` = ? AND Datum >= CURDATE() LIMIT 1');
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'i', $rennenId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $exists;
}

function melde_fahrer_an($connection, $rennenId, $team, $mitarbeiterId)
{
    $sql = 'INSERT INTO Anmeldung (`Startnummer`, `Platzierung`, `Fahrtzeit`, `PrämieTeam`, `PrämieVeranstalter`, `Radrennen`, `Team`, `Mitarbeiter`)
            VALUES (0, 0, 0, 0, 0, ?, ?, ?)';
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'isi', $rennenId, $team, $mitarbeiterId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

function fahrer_liste_fuer_team($connection, $team)
{
    $fahrer = array();
    $stmt = mysqli_prepare($connection, 'SELECT Mitarbeiter_ID, Name FROM Fahrer WHERE Team = ? ORDER BY Name');
    if (!$stmt) {
        return $fahrer;
    }

    mysqli_stmt_bind_param($stmt, 's', $team);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $fahrerId, $fahrerName);

    while (mysqli_stmt_fetch($stmt)) {
        $fahrer[] = array('Mitarbeiter_ID' => $fahrerId, 'Name' => $fahrerName);
    }

    mysqli_stmt_close($stmt);
    return $fahrer;
}

function trainingsziel_liste($connection)
{
    $ziele = array();
    $result = mysqli_query($connection, 'SELECT Trainingsziel FROM Trainingsziel ORDER BY Trainingsziel');

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $ziele[] = $row['Trainingsziel'];
        }
    }

    return $ziele;
}

function rennen_liste($connection, $nurZukuenftig)
{
    $rennen = array();
    $sql = 'SELECT `Renn-ID`, Datum, Standort FROM Radrennen';

    if ($nurZukuenftig) {
        $sql .= ' WHERE Datum >= CURDATE() ORDER BY Datum ASC, `Renn-ID` ASC';
    } else {
        $sql .= ' ORDER BY Datum DESC, `Renn-ID` DESC';
    }

    $result = mysqli_query($connection, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rennen[] = $row;
        }
    }

    return $rennen;
}
?>
