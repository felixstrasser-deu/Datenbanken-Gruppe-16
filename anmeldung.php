<?php
/*
 * Autor: Johnny Germar
 * Include-Modul für Fahrer-Anmeldungen zu Rennen.
 */
if (!defined('TEAMCHEF_DASHBOARD')) {
    header('Location: teamchef_dashboard.php');
    exit;
}

if (($dashboardPhase ?? '') === 'process') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $taskAction === 'anmeldung_speichern') {
        $rennenId = filter_var(post_value('anmeldung_rennen_id'), FILTER_VALIDATE_INT);
        $fahrerIds = $_POST['anmeldung_fahrer'] ?? array();
        $gespeichert = 0;
        $uebersprungen = 0;

        if ($rennenId === false || $rennenId <= 0) {
            $fehler = 'Bitte ein gültiges Rennen auswählen.';
        } elseif (!zukuenftigeRennen($connection, $rennenId)) {
            $fehler = 'Bitte ein zukünftiges Rennen auswählen.';
        } else {
            foreach ($fahrerIds as $fahrerIdRaw) {
                $fahrerId = filter_var($fahrerIdRaw, FILTER_VALIDATE_INT);
                if ($fahrerId === false || $fahrerId <= 0) {
                    continue;
                }

                if (fahrerAnmelden($connection, $rennenId, $teamRaw, $fahrerId)) {
                    $gespeichert++;
                } else {
                    $uebersprungen++;
                }
            }

            $meldung = $gespeichert . ' Fahrer wurden angemeldet.';
            if ($uebersprungen > 0) {
                $meldung .= ' ' . $uebersprungen . ' Einträge wurden übersprungen.';
            }
        }
    }

    $anmeldungFahrer = fahrerZuTeam($connection, $teamRaw);
    $anmeldungRennen = listeRennen($connection, true);
    $anmeldungRennenId = filter_var(get_value('anmeldung_rennen_id'), FILTER_VALIDATE_INT);
    $anmeldungAnzahl = filter_var(get_value('anmeldung_anzahl'), FILTER_VALIDATE_INT);
    if ($anmeldungAnzahl === false || $anmeldungAnzahl < 1) {
        $anmeldungAnzahl = 0;
    }
}

if (($dashboardPhase ?? '') === 'render') {
?>
<hr>
<h3 id="anmeldung">Fahrer zu Rennen anmelden</h3>
<form method="get" action="teamchef_dashboard.php#anmeldung">
    <input type="hidden" name="bereich" value="anmeldung">
    <label for="anmeldung_rennen_id">Zukünftiges Rennen:</label><br>
    <select name="anmeldung_rennen_id" id="anmeldung_rennen_id" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($anmeldungRennen as $rennenEintrag) { ?>
            <option value="<?php echo e($rennenEintrag['Renn_ID']); ?>" <?php if ((string) $anmeldungRennenId === (string) $rennenEintrag['Renn_ID']) echo 'selected'; ?>>
                <?php echo e($rennenEintrag['Renn_ID'] . ' - ' . $rennenEintrag['Datum'] . ' - ' . $rennenEintrag['Standort']); ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

    <label for="anmeldung_anzahl">Anzahl Fahrer:</label><br>
    <input type="number" name="anmeldung_anzahl" id="anmeldung_anzahl" min="1" value="<?php echo $anmeldungAnzahl > 0 ? e($anmeldungAnzahl) : ''; ?>" required>
    <br><br>

    <button type="submit">Erfassung anzeigen</button>
</form>

<?php if ($anmeldungRennenId !== false && $anmeldungRennenId > 0 && $anmeldungAnzahl > 0) { ?>
    <?php if (count($anmeldungFahrer) === 0) { ?>
        <p>Für dieses Team sind keine Fahrer angelegt.</p>
    <?php } else { ?>
        <form method="post" action="teamchef_dashboard.php?bereich=anmeldung#anmeldung">
            <input type="hidden" name="bereich" value="anmeldung">
            <input type="hidden" name="task_action" value="anmeldung_speichern">
            <input type="hidden" name="anmeldung_rennen_id" value="<?php echo e($anmeldungRennenId); ?>">
            <table border="1" cellpadding="5" cellspacing="0">
                <tr>
                    <th>Nr.</th>
                    <th>Fahrer</th>
                </tr>
                <?php for ($i = 0; $i < $anmeldungAnzahl; $i++) { ?>
                    <tr>
                        <td><?php echo e($i + 1); ?></td>
                        <td>
                            <select name="anmeldung_fahrer[]" required>
                                <option value="">Bitte wählen</option>
                                <?php foreach ($anmeldungFahrer as $fahrerOption) { ?>
                                    <option value="<?php echo e($fahrerOption['Mitarbeiter_ID']); ?>">
                                        <?php echo e($fahrerOption['Mitarbeiter_ID'] . ' - ' . $fahrerOption['Name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </td>
                    </tr>
                <?php } ?>
            </table>
            <br>
            <button type="submit">Anmeldungen speichern</button>
        </form>
    <?php } ?>
<?php } ?>
<?php } ?>
