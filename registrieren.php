<?php
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $anmeldename = $_POST['anmeldename'];
    $passwort = $_POST['passwort'];

    if ($anmeldename == "" || $passwort == "") {
        echo "Bitte alles ausfüllen!";
    } else {

        $passwortHash = password_hash($passwort, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare(
            $connection,
            "INSERT INTO teamchef (anmeldename, passwort) VALUES (?, ?)"
        );

        mysqli_stmt_bind_param($stmt, "ss", $anmeldename, $passwortHash);
        mysqli_stmt_execute($stmt);

        echo "Registrierung erfolgreich!";
    }
}
?>

<form method="POST">
    Anmeldename: <input type="text" name="anmeldename"><br>
    Passwort: <input type="password" name="passwort"><br>
    <button type="submit">Registrieren</button>
</form>