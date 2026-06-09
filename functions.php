<?php
/*
 * Gemeinsame Hilfsfunktionen für Authentifizierung, Ausgabe und Datenbankzugriffe.
 */

/*Johnny Germar*/
// Gibt einen Wert sicher im HTML aus, damit kein HTML-Code ausgeführt wird.
function e($value)
{
    // htmlspecialchars ersetzt besondere HTML-Zeichen, zum Beispiel < und >.
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*Johnny Germar*/
// Liest einen Wert aus einem abgeschickten POST-Formular.
function post_value($key)
{
    // trim entfernt Leerzeichen am Anfang und Ende.
    return trim((string) ($_POST[$key] ?? ''));
}

/*Johnny Germar*/
// Liest einen Wert aus den Parametern der URL.
function get_value($key)
{
    return trim((string) ($_GET[$key] ?? ''));
}

/*Johnny Germar*/
// Prüft, ob der angemeldete Benutzer die benötigte Rolle besitzt.
function require_role($role)
{
    if (!isset($_SESSION['rolle']) || $_SESSION['rolle'] !== $role) {
        // Bei einer falschen Rolle wird zurück zur Startseite geleitet.
        header('Location: index.php');
        exit;
    }
}

/*Felix Straßer*/
// Prüft, ob ein Teamname bereits in der Datenbank vorhanden ist.
function teamExistiert($connection, $teamname)
{
    // prepare bereitet die Abfrage mit einem Platzhalter vor.
    $stmt = mysqli_prepare($connection, 'SELECT 1 FROM Team WHERE Teamname = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    // bind_param setzt den Teamnamen für das Fragezeichen ein.
    mysqli_stmt_bind_param($stmt, 's', $teamname);
    mysqli_stmt_execute($stmt);
    // store_result speichert das Ergebnis, damit die Treffer gezählt werden können.
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $exists;
}

/*Felix Straßer*/
// Prüft, ob ein Loginname bereits von einem Teamchef benutzt wird.
function loginnameExistiert($connection, $loginname)
{
    // Prueft, ob ein Loginname bereits von einem Teamchef verwendet wird.
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

/*Felix Straßer*/
// Erstellt ein neues Team und den passenden Teamchef zusammen.
function teamMitTeamchefErstellen($connection, $teamname, $loginname, $name, $vorname, $kennwort)
{
    // Eine Transaktion sorgt dafür, dass entweder beide Einträge gespeichert werden oder keiner.
    mysqli_begin_transaction($connection);

    // Zuerst wird das neue Team gespeichert.
    $teamStmt = mysqli_prepare($connection, 'INSERT INTO Team (Teamname) VALUES (?)');
    if (!$teamStmt) {
        // rollback macht alle Änderungen der Transaktion rückgängig.
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

    // Das Passwort wird nur als sicherer Hash gespeichert.
    $hash = password_hash($kennwort, PASSWORD_DEFAULT);
    // Danach wird der Teamchef mit dem neuen Team verbunden.
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

    // commit übernimmt beide Änderungen endgültig in die Datenbank.
    mysqli_commit($connection);
    return true;
}

/*Felix Straßer*/
// Speichert oder bearbeitet einen Fahrer über die Stored Procedure FahrerSpeichern.
function fahrerSpeichern($connection, $mode, $team, $mitarbeiterId, $name, $strasse, $hausnr, $plz, $ort, $telnr)
{
    // CALL ruft die Stored Procedure in der Datenbank auf.
    $sql = 'CALL FahrerSpeichern(?, ?, ?, ?, ?, ?, ?, ?, ?, @fahrer_status, @fahrer_meldung)';
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        return array(false, 'Stored Procedure FahrerSpeichern konnte nicht vorbereitet werden: ' . mysqli_error($connection));
    }

    // Die Typzeichen beschreiben Text- und Zahlenwerte der eingesetzten Parameter.
    mysqli_stmt_bind_param($stmt, 'ssissssss', $mode, $team, $mitarbeiterId, $name, $strasse, $hausnr, $plz, $ort, $telnr);
    $ok = mysqli_stmt_execute($stmt);
    // Eine mögliche Fehlermeldung wird vor dem Schließen gespeichert.
    $error = mysqli_error($connection);
    mysqli_stmt_close($stmt);

    // Zusätzliche Ergebnisse der Stored Procedure müssen entfernt werden.
    while (mysqli_more_results($connection)) {
        mysqli_next_result($connection);
    }

    if (!$ok) {
        return array(false, 'Stored Procedure FahrerSpeichern konnte nicht ausgeführt werden: ' . $error);
    }

    // Status und Meldung der Stored Procedure werden anschließend abgefragt.
    $result = mysqli_query($connection, 'SELECT @fahrer_status AS status, @fahrer_meldung AS meldung');
    if (!$result || !($row = mysqli_fetch_assoc($result))) {
        return array(false, 'Rückmeldung der Stored Procedure konnte nicht gelesen werden.');
    }

    return array($row['status'] === 'OK', $row['meldung']);
}

/*Johnny Germar*/
// Fügt ein neues Rennen in die Tabelle Radrennen ein.
function rennenErstellen($connection, $datum, $standort, $kilometer, $hoehenmeter, $maxSteigung, $veranstalterName)
{
    $sql = 'INSERT INTO Radrennen (`Datum`, `Standort`, `Kilometer`, `Hoehenmeter`, `MaxSteigung`, `VName`) VALUES (?, ?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        return false;
    }

    // bind_param setzt alle Renndaten in den vorbereiteten INSERT-Befehl ein.
    mysqli_stmt_bind_param($stmt, 'ssiids', $datum, $standort, $kilometer, $hoehenmeter, $maxSteigung, $veranstalterName);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/*Johnny Germar*/
// Prüft, ob eine Renn-ID zu einem heutigen oder zukünftigen Rennen gehört.
function zukuenftigeRennen($connection, $rennenId)
{
    $stmt = mysqli_prepare($connection, 'SELECT 1 FROM Radrennen WHERE `Renn_ID` = ? AND Datum >= CURDATE() LIMIT 1');
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

/*Johnny Germar*/
// Meldet einen Fahrer zu einem Rennen an, falls er noch nicht angemeldet ist.
function fahrerAnmelden($connection, $rennenId, $team, $mitarbeiterId)
{
    // Zuerst wird nach einer bereits vorhandenen Anmeldung gesucht.
    $checkStmt = mysqli_prepare($connection, 'SELECT 1 FROM Anmeldung WHERE Radrennen = ? AND Team = ? AND Mitarbeiter = ? LIMIT 1');
    if (!$checkStmt) {
        return false;
    }

    mysqli_stmt_bind_param($checkStmt, 'isi', $rennenId, $team, $mitarbeiterId);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_store_result($checkStmt);
    $exists = mysqli_stmt_num_rows($checkStmt) > 0;
    mysqli_stmt_close($checkStmt);

    if ($exists) {
        return false;
    }

    // Die Startnummer wird zunächst als 0 gespeichert und vom Datenbank-Trigger gesetzt.
    $sql = 'INSERT INTO Anmeldung (`Startnummer`, `Platzierung`, `Fahrtzeit`, `PraemieTeam`, `PraemieVeranstalter`, `Radrennen`, `Team`, `Mitarbeiter`)
            VALUES (0, 0, \'00:00:00\', 0, 0, ?, ?, ?)';
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'isi', $rennenId, $team, $mitarbeiterId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/*Johnny Germar*/
// Lädt alle Fahrer, die zu einem bestimmten Team gehören.
function fahrerZuTeam($connection, $team)
{
    // In diesem Array werden die gefundenen Fahrer gesammelt.
    $fahrer = array();
    $stmt = mysqli_prepare($connection, 'SELECT Mitarbeiter_ID, Name FROM Fahrer WHERE Team = ? ORDER BY Name');
    if (!$stmt) {
        return $fahrer;
    }

    mysqli_stmt_bind_param($stmt, 's', $team);
    mysqli_stmt_execute($stmt);
    // bind_result verbindet die Ergebnisspalten mit den beiden Variablen.
    mysqli_stmt_bind_result($stmt, $fahrerId, $fahrerName);

    // fetch liest die Fahrer nacheinander aus dem Ergebnis.
    while (mysqli_stmt_fetch($stmt)) {
        $fahrer[] = array('Mitarbeiter_ID' => $fahrerId, 'Name' => $fahrerName);
    }

    mysqli_stmt_close($stmt);
    return $fahrer;
}

/*Johnny Germar*/
// Liefert eine Liste aller Rennen oder nur der zukünftigen Rennen.
function listeRennen($connection, $nurZukuenftig)
{
    $rennen = array();
    $sql = 'SELECT `Renn_ID`, Datum, Standort FROM Radrennen';

    // Je nach Parameter wird die Abfrage gefiltert und anders sortiert.
    if ($nurZukuenftig) {
        $sql .= ' WHERE Datum >= CURDATE() ORDER BY Datum ASC, `Renn_ID` ASC';
    } else {
        $sql .= ' ORDER BY Datum DESC, `Renn_ID` DESC';
    }

    // query führt die fertig zusammengesetzte Abfrage aus.
    $result = mysqli_query($connection, $sql);
    if ($result) {
        // fetch_assoc liest jede Ergebniszeile als Array.
        while ($row = mysqli_fetch_assoc($result)) {
            $rennen[] = $row;
        }
    }

    return $rennen;
}
?>
