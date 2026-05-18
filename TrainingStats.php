<?php

class TrainingStats
{
    private $monatswerte = array();
    private $fahrerId = null;
    private $trainingsziel = '';
    private $von = '';
    private $bis = '';

    public function __construct($fahrerId = null, $trainingsziel = '', $von = '', $bis = '')
    {
        $this->fahrerId = $fahrerId;
        $this->trainingsziel = $trainingsziel;
        $this->von = $von;
        $this->bis = $bis;
    }

    public function setFahrerId($fahrerId)
    {
        $this->fahrerId = $fahrerId;
    }

    public function getFahrerId()
    {
        return $this->fahrerId;
    }

    public function setTrainingsziel($trainingsziel)
    {
        $this->trainingsziel = $trainingsziel;
    }

    public function getTrainingsziel()
    {
        return $this->trainingsziel;
    }

    public function setZeitraum($von, $bis)
    {
        $this->von = $von;
        $this->bis = $bis;
    }

    public function loadFromDatabase($connection, $team)
    {
        $this->monatswerte = array();
        $bedingungen = array('Fahrer.Team = ?');
        $typen = 's';
        $werte = array($team);

        if ($this->fahrerId !== null && $this->fahrerId !== '') {
            $bedingungen[] = 'Training.Mitarbeiter = ?';
            $typen .= 'i';
            $werte[] = (int) $this->fahrerId;
        }

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
                INNER JOIN Fahrer ON Training.Mitarbeiter = Fahrer.Mitarbeiter_ID
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
            $this->addTraining($datum, $kilometer);
        }

        mysqli_stmt_close($stmt);
        return true;
    }

    public function addTraining($datum, $kilometer)
    {
        if ($kilometer < 0) {
            throw new InvalidArgumentException('Kilometer dürfen nicht negativ sein.');
        }

        $monat = substr($datum, 0, 7);

        if (!isset($this->monatswerte[$monat])) {
            $this->monatswerte[$monat] = array();
        }

        $this->monatswerte[$monat][] = $kilometer;
    }

    public function addTrainings($trainings)
    {
        foreach ($trainings as $training) {
            if (!isset($training['Datum'], $training['Kilometer'])) {
                throw new InvalidArgumentException('Training braucht Datum und Kilometer.');
            }

            $this->addTraining($training['Datum'], $training['Kilometer']);
        }
    }

    public function getMonatswerte()
    {
        return $this->monatswerte;
    }

    public function getMonatsStatistik()
    {
        $statistik = array();

        foreach ($this->monatswerte as $monat => $werte) {
            $statistik[$monat] = array(
                'anzahl' => count($werte),
                'summe' => $this->summe($werte),
                'durchschnitt' => $this->durchschnitt($werte),
                'minimum' => min($werte),
                'maximum' => max($werte),
                'median' => $this->median($werte),
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

        if (isset($statistik[$monat])) {
            return $statistik[$monat];
        }

        return null;
    }

    public function summe($werte)
    {
        return array_sum($werte);
    }

    public function durchschnitt($werte)
    {
        if (count($werte) === 0) {
            return 0.0;
        }

        return $this->summe($werte) / count($werte);
    }

    public function median($werte)
    {
        return $this->quantil($werte, 0.5);
    }

    public function quantil($werte, $q)
    {
        if (count($werte) === 0) {
            return 0.0;
        }

        if ($q < 0 || $q > 1) {
            throw new InvalidArgumentException('Quantil muss zwischen 0 und 1 liegen.');
        }

        sort($werte, SORT_NUMERIC);

        $position = (count($werte) - 1) * $q;
        $unten = (int) floor($position);
        $oben = (int) ceil($position);

        if ($unten === $oben) {
            return $werte[$unten];
        }

        $anteilOben = $position - $unten;
        $anteilUnten = 1 - $anteilOben;

        return ($werte[$unten] * $anteilUnten) + ($werte[$oben] * $anteilOben);
    }

    public function standardabweichung($werte)
    {
        $anzahl = count($werte);

        if ($anzahl === 0) {
            return 0.0;
        }

        $durchschnitt = $this->durchschnitt($werte);
        $quadratischeAbweichungen = 0.0;

        foreach ($werte as $wert) {
            $quadratischeAbweichungen += ($wert - $durchschnitt) ** 2;
        }

        return sqrt($quadratischeAbweichungen / $anzahl);
    }
}
