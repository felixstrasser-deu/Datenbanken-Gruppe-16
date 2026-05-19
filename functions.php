<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Gemeinsame Hilfsfunktionen für Authentifizierung, Ausgabe und Datenbankzugriffe.
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

function sql_identifier($name)
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function table_columns($connection, $table)
{
    $columns = array();
    $result = mysqli_query($connection, 'SHOW COLUMNS FROM ' . sql_identifier($table));

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[] = (string) $row['Field'];
        }
    }

    return $columns;
}

function find_column($connection, $table, $preferredNames, $fallbackIndex)
{
    $columns = table_columns($connection, $table);

    foreach ($preferredNames as $preferredName) {
        foreach ($columns as $column) {
            if ($column === $preferredName) {
                return $column;
            }
        }
    }

    if (isset($columns[$fallbackIndex])) {
        return $columns[$fallbackIndex];
    }

    return '';
}

function fahrer_strasse_column($connection)
{
    return find_column($connection, 'Fahrer', array('Strasse', 'Straße', 'StraÃŸe'), 3);
}

function rennen_hoehenmeter_column($connection)
{
    return find_column($connection, 'Radrennen', array('Hoehenmeter', 'Höhenmeter', 'HÃ¶henmeter'), 4);
}

function anmeldung_praemie_team_column($connection)
{
    return find_column($connection, 'Anmeldung', array('PraemieTeam', 'PrämieTeam', 'PrÃ¤mieTeam'), 3);
}

function anmeldung_praemie_veranstalter_column($connection)
{
    return find_column($connection, 'Anmeldung', array('PraemieVeranstalter', 'PrämieVeranstalter', 'PrÃ¤mieVeranstalter'), 4);
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
        return array(false, 'Stored Procedure FahrerSpeichern konnte nicht ausgeführt werden: ' . $error);
    }

    $result = mysqli_query($connection, 'SELECT @fahrer_status AS status, @fahrer_meldung AS meldung');
    if (!$result || !($row = mysqli_fetch_assoc($result))) {
        return array(false, 'Rückmeldung der Stored Procedure konnte nicht gelesen werden.');
    }

    return array($row['status'] === 'OK', $row['meldung']);
}

function next_rennen_id($connection)
{
    $result = mysqli_query($connection, 'SELECT COALESCE(MAX(`Renn-ID`), 0) + 1 AS next_id FROM Radrennen');
    if ($result && ($row = mysqli_fetch_assoc($result))) {
        return (int) $row['next_id'];
    }

    return 1;
}

function fahrer_ist_angemeldet($connection, $rennenId, $mitarbeiterId)
{
    $stmt = mysqli_prepare($connection, 'SELECT 1 FROM Anmeldung WHERE Radrennen = ? AND Mitarbeiter = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $rennenId, $mitarbeiterId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $exists;
}

function melde_fahrer_an($connection, $rennenId, $team, $mitarbeiterId)
{
    $praemieTeamColumn = anmeldung_praemie_team_column($connection);
    $praemieVeranstalterColumn = anmeldung_praemie_veranstalter_column($connection);

    if ($praemieTeamColumn === '' || $praemieVeranstalterColumn === '') {
        return false;
    }

    $sql = 'INSERT INTO Anmeldung (`Startnummer`, `Platzierung`, `Fahrtzeit`, ' . sql_identifier($praemieTeamColumn) . ', ' . sql_identifier($praemieVeranstalterColumn) . ', `Radrennen`, `Team`, `Mitarbeiter`)
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
