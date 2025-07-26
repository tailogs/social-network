<?php
require 'inc/header.php';

// Проверяем, указан ли пользователь для чата
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    header('Location: users.php');
    exit;
}

$receiver_id = (int)$_GET['user_id'];

// Проверяем, существует ли пользователь
$stmt = $pdo->prepare("SELECT id, username, avatar, status, last_active FROM users WHERE id = ?");
$stmt->execute([$receiver_id]);
$receiver = $stmt->fetch();

if (!$receiver || $receiver_id == $_SESSION['user_id']) {
    header('Location: users.php');
    exit;
}

// Обработка отправки сообщения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content = trim($_POST['content']);
    if ($content) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $receiver_id, $content]);
    }
    header("Location: messages.php?user_id=$receiver_id");
    exit;
}

// Получаем сообщения между текущим пользователем и получателем
$stmt = $pdo->prepare("
    SELECT m.*, u.username, u.avatar 
    FROM messages m 
    JOIN users u ON u.id = m.sender_id 
    WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) 
    ORDER BY m.created_at ASC
");
$stmt->execute([$_SESSION['user_id'], $receiver_id, $receiver_id, $_SESSION['user_id']]);
$messages = $stmt->fetchAll();

// Отмечаем сообщения как прочитанные
$pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0")
    ->execute([$_SESSION['user_id'], $receiver_id]);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Чат с <?= htmlspecialchars($receiver['username']) ?></h2>
        <div>
            <a href="users.php" class="btn btn-sm btn-secondary">К списку пользователей</a>
            <a href="index.php" class="btn btn-sm btn-secondary">Лента</a>
            <a href="profile.php" class="btn btn-sm btn-secondary">Профиль</a>
            <a href="logout.php" class="btn btn-sm btn-danger">Выйти</a>
        </div>
    </div>

    <div class="chat-container mb-4">
        <div id="messages">
            <?php if (empty($messages)): ?>
                <p>Нет сообщений.</p>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message <?= ($message['sender_id'] == $_SESSION['user_id']) ? 'mine' : 'other' ?>">
                        <div class="sender"><?= htmlspecialchars($message['username']) ?></div>
                        <div><?= nl2br(htmlspecialchars($message['content'])) ?></div>
                        <small class="text-muted"><?= $message['created_at'] ?></small>
                    </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>

    <form id="messageForm">
        <div class="input-group">
            <textarea name="content" class="form-control" rows="3" placeholder="Напишите сообщение" required></textarea>
            <button type="submit" class="btn btn-primary">Отправить</button>
        </div>
    </form>
</div>

<script>
$(function() {
    const chatContainer = $('.chat-container');
    const messagesDiv = $('#messages');
    const receiverId = <?= $receiver_id ?>;
    let lastMessageId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;
    let isPolling = false; // Флаг для предотвращения наложения опросов
    let isSending = false; // Флаг для предотвращения повторной отправки
    const displayedMessages = new Set(<?= json_encode(array_column($messages, 'id')) ?>); // Инициализация отображенных сообщений

    // Прокрутка чата вниз
    chatContainer.scrollTop(chatContainer[0].scrollHeight);

    // Отправка сообщения через AJAX
    $('#messageForm').off('submit').on('submit', function(e) { // Отключаем предыдущие обработчики
        e.preventDefault();
        if (isSending) return; // Пропускаем, если отправка уже выполняется
        isSending = true;

        const form = $(this);
        const content = form.find('textarea[name="content"]').val().trim();

        if (!content) {
            isSending = false;
            return;
        }

        $.ajax({
            url: 'send_message.php',
            method: 'POST',
            data: { receiver_id: receiverId, content: content },
            dataType: 'json',
            success: function(data) {
                if (data.success && data.message_id && !displayedMessages.has(data.message_id)) {
                    const message = $(`
                        <div class="message mine" data-message-id="${data.message_id}">
                            <div class="sender"><?= htmlspecialchars($receiver['username']) ?></div>
                            <div>${content.replace(/\n/g, '<br>')}</div>
                            <small class="text-muted">${new Date().toLocaleString()}</small>
                        </div>
                    `);
                    messagesDiv.append(message);
                    displayedMessages.add(data.message_id);
                    lastMessageId = Math.max(lastMessageId, data.message_id);
                    form.find('textarea').val('');
                    chatContainer.scrollTop(chatContainer[0].scrollHeight);
                } else if (!data.success) {
                    alert('Ошибка: ' + (data.error || 'Не удалось отправить сообщение'));
                }
            },
            error: function() {
                alert('Ошибка сервера при отправке сообщения');
            },
            complete: function() {
                isSending = false; // Сбрасываем флаг после завершения
            }
        });
    });

    // Периодический опрос новых сообщений
    function pollMessages() {
        if (isPolling) return;
        isPolling = true;

        $.ajax({
            url: 'get_messages.php',
            method: 'GET',
            data: { receiver_id: receiverId, last_id: lastMessageId },
            dataType: 'json',
            success: function(data) {
                if (data.success && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        if (!displayedMessages.has(msg.id)) {
                            const message = $(`
                                <div class="message ${msg.sender_id == <?= $_SESSION['user_id'] ?> ? 'mine' : 'other'}" data-message-id="${msg.id}">
                                    <div class="sender">${msg.username}</div>
                                    <div>${msg.content.replace(/\n/g, '<br>')}</div>
                                    <small class="text-muted">${msg.created_at}</small>
                                </div>
                            `);
                            messagesDiv.append(message);
                            displayedMessages.add(msg.id);
                        }
                    });
                    lastMessageId = data.last_id || lastMessageId;
                    chatContainer.scrollTop(chatContainer[0].scrollHeight);
                }
            },
            complete: function() {
                isPolling = false;
                setTimeout(pollMessages, 2000);
            },
            error: function() {
                setTimeout(pollMessages, 5000);
            }
        });
    }

    pollMessages();
});
</script>

<?php require 'inc/footer.php'; ?>