<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training</title>
</head>

<body>
    <h2>Training</h2>

<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $datum = $_POST['datum'];
    $kilometer = $_POST['km'];
    $trainingsziel = $_POST['ziel'];
    $mitarbeiter = $_POST['fahrer'];
    $team = 1; // Einfach hartcodiert für Anfänger

    $sql = "INSERT INTO Training (Datum, Kilometer, Trainingsziel, Team, Mitarbeiter) VALUES ('$datum', $kilometer, '$trainingsziel', $team, '$mitarbeiter')";
    mysqli_query($connection, $sql);
    echo "<p>Training gespeichert!</p>";
}
?>

<form method="post" action="training.php">
    <label for="fahrer">Fahrer:</label>
    <select name="fahrer" id="fahrer" required>
        <option value="fahrer1">Fahrer 1</option>
        <option value="fahrer2">Fahrer 2</option>
        <option value="fahrer3">Fahrer 3</option>
    </select>
    
    <br>

    <label for="datum">Datum:</label>
    <input type="date" name="datum" id="datum" required>
    
    <br>

    <label for="dauer">Dauer (in Minuten):</label>
    <input type="number" id="dauer" name="dauer" required>
    
    <br>

    <label for="km">Kilometer:</label>
    <input type="number" id="km" name="km" required>

    <br>

    <label for="ziel">Trainingsziel:</label>
    <select id="ziel" name="ziel" required>
        <option value="ziel1">Ausdauer</option>
        <option value="ziel2">Sprintkraft</option>
        <option value="ziel3">Steigungen</option>
    </select>

    <br>

    <button type="submit">Training erfassen</button>
</form>

</body>
</html>