<?php
require 'inc/db.php';
require 'inc/auth.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : null;
    $content = trim($_POST['content']);

    if (!$receiver_id || !$content) {
        echo json_encode(['success' => false, 'error' => 'Неверные данные']);
        exit;
    }

    // Проверяем, существует ли пользователь
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$receiver_id]);
    if (!$stmt->fetch() || $receiver_id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'Пользователь не найден']);
        exit;
    }

    try {
        // Проверяем, не было ли недавно отправлено идентичное сообщение
        $stmt = $pdo->prepare("
            SELECT id FROM messages 
            WHERE sender_id = ? AND receiver_id = ? AND content = ? AND created_at > NOW() - INTERVAL 5 SECOND
        ");
        $stmt->execute([$_SESSION['user_id'], $receiver_id, $content]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Сообщение уже отправлено']);
            exit;
        }

        // Вставляем новое сообщение
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $receiver_id, $content]);
        $message_id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'message_id' => $message_id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Неверный запрос']);
}