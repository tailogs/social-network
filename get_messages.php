<?php
require 'inc/db.php';
require 'inc/auth.php';
require_login();

header('Content-Type: application/json');

$receiver_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : null;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if (!$receiver_id) {
    echo json_encode(['success' => false, 'error' => 'Неверный receiver_id']);
    exit;
}

// Проверяем, существует ли пользователь
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$receiver_id]);
if (!$stmt->fetch() || $receiver_id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'Пользователь не найден']);
    exit;
}

// Получаем новые сообщения
$stmt = $pdo->prepare("
    SELECT m.*, u.username
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    WHERE m.id > ? AND
          ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
    ORDER BY m.id ASC
");
$stmt->execute([$last_id, $_SESSION['user_id'], $receiver_id, $receiver_id, $_SESSION['user_id']]);
$messages = $stmt->fetchAll();

// Логирование для отладки
file_put_contents('messages_log.txt', date('Y-m-d H:i:s') . " - last_id: $last_id, messages: " . json_encode($messages) . "\n", FILE_APPEND);

// Отмечаем полученные сообщения как прочитанные
if ($messages) {
    $max_id = max(array_column($messages, 'id'));
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND id > ? AND id <= ? AND is_read = 0")
        ->execute([$_SESSION['user_id'], $receiver_id, $last_id, $max_id]);
}

echo json_encode(['success' => true, 'messages' => $messages, 'last_id' => $messages ? $max_id : $last_id]);
?>