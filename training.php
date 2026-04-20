<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training</title>
</head>

<body>
    <h2>Training</h2>

<label for="ziel">Trainingsziel:</label>
<select id="ziel" name="ziel" required>
    <option value="ziel1">Ausdauer</option>
    <option value="ziel2">Sprintkraft</option>
    <option value="ziel3">Steigungen</option>
</select>


<form method="post" action="training.php">
    <label> Fahrer:</label>
    <select name="fahrer" required>
        <option value="fahrer1">Fahrer 1</option>
        <option value="fahrer2">Fahrer 2</option>
        <option value="fahrer3">Fahrer 3</option>
    <label for="datum">Datum:</label>
    <input type="date" name="datum" required>
    
    <br>

    <label for="dauer">Dauer (in Minuten):</label>
    <input type="number" id="dauer" name="dauer" required>
    
    <br>

    <label for="kilometer">Kilometer:</label>
    <input type="number" id="km" name="km" required>

    <label for="ziel">Trainingsziel:</label>
    <select id="ziel" name="ziel" required>
        <option value="ziel1">Ausdauer</option>
        <option value="ziel2">Sprintkraft</option>
        <option value="ziel3">Steigungen</option>
    </select>

    <button type="submit">Training erfassen</button>
</form>

</body>
</html>