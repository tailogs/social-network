<?php
require 'inc/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $avatar = null;

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $avatar = uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['avatar']['tmp_name'], "uploads/$avatar");
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, avatar) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $avatar]);
        header('Location: login.php');
    } catch (PDOException $e) {
        echo "Ошибка: имя занято";
    }
}
?>

<form method="post" enctype="multipart/form-data">
    <input name="username" required placeholder="Имя пользователя"><br>
    <input name="password" type="password" required placeholder="Пароль"><br>
    <input type="file" name="avatar"><br>
    <button type="submit">Зарегистрироваться</button>
</form>
