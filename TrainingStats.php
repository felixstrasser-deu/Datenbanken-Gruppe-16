<?php
/*
 * Autor: Magdalena Hamm
 * Auswertungsklasse für Trainingskilometer eines Fahrers.
 */
class TrainingStats
{
    private $monatswerte = array();
    private $fahrerId;
    private $trainingsziel;
    private $von;
    private $bis;

    public function __construct($fahrerId = null, $trainingsziel = '', $von = '', $bis = '')
    {
        $this->fahrerId = $fahrerId;
        $this->trainingsziel = $trainingsziel;
        $this->von = $von;
        $this->bis = $bis;
    }

    public function setFahrerId($fahrerId) { $this->fahrerId = $fahrerId; }
    public function getFahrerId() { return $this->fahrerId; }
    public function setTrainingsziel($trainingsziel) { $this->trainingsziel = $trainingsziel; }
    public function getTrainingsziel() { return $this->trainingsziel; }
    public function setZeitraum($von, $bis) { $this->von = $von; $this->bis = $bis; }
    public function getMonatswerte() { return $this->monatswerte; }

    public function loadFromDatabase($connection, $team)
    {
        if ($this->fahrerId === null || $this->fahrerId === '') {
            return false;
        }

        $this->monatswerte = array();
        $bedingungen = array('Fahrer.Team = ?', 'Training.Mitarbeiter = ?');
        $typen = 'si';
        $werte = array($team, (int) $this->fahrerId);

        if ($this->trainingsziel !== '') {
            $bedingungen[] = 'Training.Trainingsziel = ?';
            $typen .= 's';
            $werte[] = $this->trainingsziel;
        }
        if ($this->von !== '') {
            $bedingungen[] = 'Training.Datum >= ?';
            $typen .= 's';
            $werte[] = $this->von;
        }
        if ($this->bis !== '') {
            $bedingungen[] = 'Training.Datum <= ?';
            $typen .= 's';
            $werte[] = $this->bis;
        }

        $sql = 'SELECT Training.Datum, Training.Kilometer
                FROM Training
                INNER JOIN Fahrer ON Training.Team = Fahrer.Team
                    AND Training.Mitarbeiter = Fahrer.Mitarbeiter_ID
                WHERE ' . implode(' AND ', $bedingungen) . '
                ORDER BY Training.Datum';
        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt) {
            return false;
        }

        $bindParams = array($typen);
        foreach ($werte as $key => $value) {
            $bindParams[] = &$werte[$key];
        }

        call_user_func_array(array($stmt, 'bind_param'), $bindParams);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $datum, $kilometer);

        while (mysqli_stmt_fetch($stmt)) {
            $monat = substr($datum, 0, 7);
            if (!isset($this->monatswerte[$monat])) {
                $this->monatswerte[$monat] = array();
            }
            $this->monatswerte[$monat][] = (float) $kilometer;
        }

        mysqli_stmt_close($stmt);
        return true;
    }

    public function getMonatsStatistik()
    {
        $statistik = array();

        foreach ($this->monatswerte as $monat => $werte) {
            $anzahl = count($werte);
            $statistik[$monat] = array(
                'anzahl' => $anzahl,
                'summe' => array_sum($werte),
                'durchschnitt' => array_sum($werte) / $anzahl,
                'minimum' => min($werte),
                'maximum' => max($werte),
                'median' => $this->quantil($werte, 0.5),
                'quantil_25' => $this->quantil($werte, 0.25),
                'quantil_75' => $this->quantil($werte, 0.75),
                'standardabweichung' => $this->standardabweichung($werte),
            );
        }

        ksort($statistik);
        return $statistik;
    }

    public function getMonatswert($monat)
    {
        $statistik = $this->getMonatsStatistik();
        return isset($statistik[$monat]) ? $statistik[$monat] : null;
    }

    public function quantil($werte, $q)
    {
        sort($werte, SORT_NUMERIC);
        $position = (count($werte) - 1) * $q;
        $unten = (int) floor($position);
        $oben = (int) ceil($position);

        if ($unten === $oben) {
            return $werte[$unten];
        }

        return $werte[$unten] * ($oben - $position) + $werte[$oben] * ($position - $unten);
    }

    public function standardabweichung($werte)
    {
        $durchschnitt = array_sum($werte) / count($werte);
        $summe = 0.0;

        foreach ($werte as $wert) {
            $summe += ($wert - $durchschnitt) ** 2;
        }

        return sqrt($summe / count($werte));
    }
}
