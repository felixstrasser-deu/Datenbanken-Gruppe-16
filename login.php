<?php
if (isset($_POST['login'])) {

    $anmeldename = $_POST['login_anmeldename'];
    $passwort = $_POST['login_passwort'];

    $stmt = mysqli_prepare(
        $connection,
        "SELECT passwort FROM teamchef WHERE anmeldename = ?"
    );

    mysqli_stmt_bind_param($stmt, "s", $anmeldename);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if ($row && password_verify($passwort, $row['passwort'])) {
        echo "<p>Login erfolgreich!</p>";
    } else {
        echo "<p>Login fehlgeschlagen!</p>";
    }
}
?>

<h2>Login</h2>

<form method="POST">
    Teamchef:<br>
    <input type="text" name="login_anmeldename"><br><br>

    Passwort:<br>
    <input type="password" name="login_passwort"><br><br>

    <button type="submit" name="login">Login</button>
</form>