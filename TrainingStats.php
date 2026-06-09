<?php
/*
 * Autor: Magdalena Hamm
 * Klasse zur statistischen Auswertung von Trainingsdaten eines Fahrers.
 */

class TrainingStats
{
    // Speichert Rohwerte, berechnete Monatsstatistik und die gesetzten Filter.
    private $monatswerte = array();
    private $monatsStatistik = array();
    private $fahrerId;
    private $trainingsziel;
    private $von;
    private $bis;

    // Initialisiert ein Statistikobjekt mit optionalen Filtern für Fahrer, Trainingsziel und Zeitraum.
    public function __construct($fahrerId = null, $trainingsziel = '', $von = '', $bis = '')
    {
        $this->fahrerId = $fahrerId;
        $this->trainingsziel = $trainingsziel;
        $this->von = $von;
        $this->bis = $bis;
    }

    // Lädt die Trainingsdaten des gewählten Fahrers, gruppiert sie nach Monat und berechnet anschließend die Monatsstatistik.
    public function loadFromDatabase($connection, $team)
    {

        // Ohne Fahrer-ID kann keine fahrerbezogene Statistik geladen werden.
        if ($this->fahrerId === null || $this->fahrerId === '') {
            return false;
        }

        // Alte Rohwerte und alte Statistik löschen, bevor neue Daten geladen werden.
        $this->monatswerte = array();
        $this->monatsStatistik = array();
        $bedingungen = array('Fahrer.Team = ?', 'Training.Mitarbeiter = ?');
        $typen = 'si';
        $werte = array($team, (int) $this->fahrerId);

        // Optionale Filter für Trainingsziel und Zeitraum ergänzen.
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

        // SQL-Abfrage mit allen gesetzten Filtern erstellen.
        $sql = 'SELECT Training.Datum, Training.Kilometer
                FROM Training, Fahrer
                WHERE Training.Team = Fahrer.Team
                AND Training.Mitarbeiter = Fahrer.Mitarbeiter_ID
                AND ' . implode(' AND ', $bedingungen) . '
                ORDER BY Training.Datum';

        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt) {
            return false;
        }

        // Dynamische Filterwerte sicher an das Prepared Statement binden.
        $bindParams = array($typen);
        foreach ($werte as $key => $value) {
            $bindParams[] = &$werte[$key];
        }

        call_user_func_array(array($stmt, 'bind_param'), $bindParams);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $datum, $kilometer);

        // Kilometer-Rohwerte nach Monat gruppieren, zum Beispiel 2026-06.
        while (mysqli_stmt_fetch($stmt)) {
            $monat = substr($datum, 0, 7);
            if (!isset($this->monatswerte[$monat])) {
                $this->monatswerte[$monat] = array();
            }
            $this->monatswerte[$monat][] = (float) $kilometer;
        }

        mysqli_stmt_close($stmt);
        $this->berechneMonatsStatistik();
        return true;
    }

    // Berechnet für jeden Monat die Kennzahlen und speichert sie in $monatsStatistik.
    private function berechneMonatsStatistik()
    {
        $this->monatsStatistik = array();

        foreach ($this->monatswerte as $monat => $werte) {
            $anzahl = count($werte);
            $this->monatsStatistik[$monat] = array(
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

        ksort($this->monatsStatistik);
    }

    // Gibt die gespeicherte Monatsstatistik für alle Monate zurück.
    public function getMonatsStatistik()
    {
        return $this->monatsStatistik;
    }

    // Berechnet ein Quantil, zum Beispiel den Median oder das 25%- bzw. 75%-Quantil.
    private function quantil($werte, $q)
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

    // Berechnet die Standardabweichung der Kilometerwerte als Maß für die Streuung.
    private function standardabweichung($werte)
    {
        $durchschnitt = array_sum($werte) / count($werte);
        $summe = 0.0;

        foreach ($werte as $wert) {
            $summe += ($wert - $durchschnitt) ** 2;
        }

        return sqrt($summe / count($werte));
    }
}
