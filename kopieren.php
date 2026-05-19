<?php
/*
 * Autor: Gruppe 16 - bitte für die Abgabe den verantwortlichen Namen ergänzen.
 * Include-Modul zum Kopieren von Anmeldungen.
 */
if (!defined('TEAMCHEF_DASHBOARD')) {
    header('Location: teamchef_dashboard.php');
    exit;
}

if (($dashboardPhase ?? '') === 'process') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $taskAction === 'kopieren_speichern') {
        $quelle = filter_var(post_value('kopieren_quelle'), FILTER_VALIDATE_INT);
        $ziel = filter_var(post_value('kopieren_ziel'), FILTER_VALIDATE_INT);

        if ($quelle === false || $ziel === false || $quelle <= 0 || $ziel <= 0) {
            $fehler = 'Bitte Quell- und Zielrennen auswählen.';
        } elseif ($quelle === $ziel) {
            $fehler = 'Quelle und Ziel müssen unterschiedliche Rennen sein.';
        } else {
            $sourceStmt = mysqli_prepare($connection, 'SELECT Mitarbeiter FROM Anmeldung WHERE Radrennen = ? AND Team = ? ORDER BY Startnummer');
            if ($sourceStmt) {
                mysqli_stmt_bind_param($sourceStmt, 'is', $quelle, $teamRaw);
                mysqli_stmt_execute($sourceStmt);
                mysqli_stmt_bind_result($sourceStmt, $mitarbeiterId);

                $fahrerIds = array();
                while (mysqli_stmt_fetch($sourceStmt)) {
                    $fahrerIds[] = (int) $mitarbeiterId;
                }

                mysqli_stmt_close($sourceStmt);

                if (count($fahrerIds) === 0) {
                    $fehler = 'Für das Quellrennen gibt es keine Anmeldungen dieses Teams.';
                } else {
                    $kopiert = 0;
                    $uebersprungen = 0;

                    foreach ($fahrerIds as $fahrerId) {
                        if (fahrer_ist_angemeldet($connection, $ziel, $fahrerId)) {
                            $uebersprungen++;
                            continue;
                        }

                        if (melde_fahrer_an($connection, $ziel, $teamRaw, $fahrerId)) {
                            $kopiert++;
                        } else {
                            $fehler = 'Kopieren wurde abgebrochen: ' . mysqli_error($connection);
                            break;
                        }
                    }

                    if ($fehler === '') {
                        $meldung = $kopiert . ' Anmeldungen wurden kopiert.';
                        if ($uebersprungen > 0) {
                            $meldung .= ' ' . $uebersprungen . ' bereits vorhandene Anmeldungen wurden übersprungen.';
                        }
                    }
                }
            } else {
                $fehler = 'Quellrennen konnte nicht gelesen werden.';
            }
        }
    }

    $kopierenAlleRennen = array();
    $alleRennenResult = mysqli_query($connection, 'SELECT `Renn-ID`, Datum, Standort FROM Radrennen ORDER BY Datum DESC, `Renn-ID` DESC');
    if ($alleRennenResult) {
        while ($row = mysqli_fetch_assoc($alleRennenResult)) {
            $kopierenAlleRennen[] = $row;
        }
    }

    $kopierenZielRennen = array();
    $zielRennenResult = mysqli_query($connection, 'SELECT `Renn-ID`, Datum, Standort FROM Radrennen WHERE Datum >= CURDATE() ORDER BY Datum ASC, `Renn-ID` ASC');
    if ($zielRennenResult) {
        while ($row = mysqli_fetch_assoc($zielRennenResult)) {
            $kopierenZielRennen[] = $row;
        }
    }
}

if (($dashboardPhase ?? '') === 'render') {
?>
<hr>
<h3 id="kopieren">Anmeldungen kopieren</h3>
<form method="post" action="teamchef_dashboard.php#kopieren">
    <input type="hidden" name="task_action" value="kopieren_speichern">

    <label for="kopieren_quelle">Anmeldungen aus Rennen:</label><br>
    <select name="kopieren_quelle" id="kopieren_quelle" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($kopierenAlleRennen as $rennenEintrag) { ?>
            <option value="<?php echo e($rennenEintrag['Renn-ID']); ?>">
                <?php echo e($rennenEintrag['Renn-ID'] . ' - ' . $rennenEintrag['Datum'] . ' - ' . $rennenEintrag['Standort']); ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

    <label for="kopieren_ziel">Kopieren nach zukünftigem Rennen:</label><br>
    <select name="kopieren_ziel" id="kopieren_ziel" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($kopierenZielRennen as $rennenEintrag) { ?>
            <option value="<?php echo e($rennenEintrag['Renn-ID']); ?>">
                <?php echo e($rennenEintrag['Renn-ID'] . ' - ' . $rennenEintrag['Datum'] . ' - ' . $rennenEintrag['Standort']); ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

    <button type="submit">Anmeldungen kopieren</button>
</form>
<?php } ?>
