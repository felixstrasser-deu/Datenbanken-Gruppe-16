<?php
if (isset($_POST['registrieren'])) {

    $anmeldename = $_POST['registrieren_anmeldename'];
    $passwort = $_POST['registrieren_passwort'];

    if ($anmeldename == "" || $passwort == "") {
        echo "<p>Bitte alles ausfüllen!</p>";
    } else {

        $hash = password_hash($passwort, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare(
            $connection,
            "INSERT INTO teamchef (anmeldename, passwort) VALUES (?, ?)"
        );

        mysqli_stmt_bind_param($stmt, "ss", $anmeldename, $hash);
        mysqli_stmt_execute($stmt);

        echo "<p>Registrierung erfolgreich!</p>";
    }
}
?>

<h2>Registrieren</h2>

<form method="POST">
    Neuer Teamchef:<br>
    <input type="text" name="registrieren_anmeldename"><br><br>

    Neues Passwort:<br>
    <input type="password" name="registrieren_passwort"><br><br>

    <button type="submit" name="registrieren">Registrieren</button>
</form>