<?php
require_once 'db.php';
require_once 'auth.php';
require_login();

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
?>

<div class="sidebar bg-secondary text-white p-3" style="width: 250px; height: 100vh; position: fixed; top: 0; left: 0; overflow-y: auto;">
    <h4 class="mb-3">Чаты</h4>
    <?php if (empty($chat_users)): ?>
        <p>Нет активных чатов.</p>
    <?php else: ?>
        <ul class="list-unstyled">
            <?php foreach ($chat_users as $user): ?>
                <li class="mb-2">
                    <a href="messages.php?user_id=<?= $user['id'] ?>" class="text-white text-decoration-none d-flex align-items-center">
                        <img src="Uploads/<?= htmlspecialchars($user['avatar'] ?? 'default.png') ?>" class="rounded-circle me-2" width="30" height="30" alt="avatar">
                        <span><?= htmlspecialchars($user['username']) ?></span>
                        <?php if ($user['unread_count'] > 0): ?>
                            <span class="badge bg-danger ms-2"><?= $user['unread_count'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>