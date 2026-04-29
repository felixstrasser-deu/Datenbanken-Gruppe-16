<?php

class TrainingStats
{
    private $monatswerte = array();

    public function addTraining($datum, $kilometer)
    {
        if ($kilometer < 0) {
            throw new InvalidArgumentException('Kilometer duerfen nicht negativ sein.');
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
