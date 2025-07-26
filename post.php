<?php
require 'inc/db.php';
require 'inc/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content']);
    if ($content) {
        try {
            $stmt = $pdo->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $content]);
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            // В случае ошибки выводим сообщение
            http_response_code(500);
            echo "Ошибка при создании поста: " . htmlspecialchars($e->getMessage());
            exit;
        }
    } else {
        // Если контент пустой, возвращаем пользователя с ошибкой
        header('Location: index.php?error=empty_content');
        exit;
    }
}

// Если запрос не POST, перенаправляем на главную
header('Location: index.php');
exit;