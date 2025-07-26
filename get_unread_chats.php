<?php
require 'inc/db.php';
require 'inc/auth.php';
require_login();

header('Content-Type: application/json');

// Получаем список пользователей, с которыми есть переписка
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.username, u.avatar, u.last_active,
           (SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND sender_id = u.id AND is_read = 0) AS unread_count
    FROM users u
    JOIN messages m ON u.id = m.sender_id OR u.id = m.receiver_id
    WHERE (m.sender_id = ? OR m.receiver_id = ?) AND u.id != ?
    GROUP BY u.id
    ORDER BY (SELECT MAX(created_at) FROM messages WHERE sender_id = u.id OR receiver_id = u.id) DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$chat_users = $stmt->fetchAll();

$chats = [];
foreach ($chat_users as $user) {
    $chats[] = [
        'id' => $user['id'],
        'username' => htmlspecialchars($user['username']),
        'avatar' => htmlspecialchars($user['avatar'] ?? 'default.png'),
        'unread_count' => (int)$user['unread_count']
    ];
}

echo json_encode(['success' => true, 'chats' => $chats]);