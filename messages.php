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

// Получаем имя текущего пользователя
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();
$current_username = htmlspecialchars($current_user['username']);

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

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Чат с <?= htmlspecialchars($receiver['username']) ?></h2>
        <div>
            <a href="users.php" class="btn btn-primary">К списку пользователей</a>
            <a href="index.php" class="btn btn-primary">Лента</a>
            <a href="profile.php" class="btn btn-primary">Профиль</a>
            <a href="logout.php" class="btn btn-danger">Выйти</a>
        </div>
    </div>

    <div class="chat-container mb-4">
        <div id="messages">
            <?php if (empty($messages)): ?>
                <p>Нет сообщений.</p>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message <?= ($message['sender_id'] == $_SESSION['user_id']) ? 'mine' : 'other' ?>" data-message-id="<?= $message['id'] ?>">
                        <div class="sender"><?= htmlspecialchars($message['username']) ?></div>
                        <div>
                            <?php if ($message['content']): ?>
                                <?= nl2br(htmlspecialchars($message['content'])) ?>
                            <?php endif; ?>
                            <?php if ($message['image']): ?>
                                <img src="Uploads/<?= htmlspecialchars($message['image']) ?>" class="img-fluid mt-2" style="max-width: 200px;" alt="message image">
                            <?php endif; ?>
                        </div>
                        <small class="text-muted"><?= $message['created_at'] ?></small>
                    </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>

    <form id="messageForm" enctype="multipart/form-data">
        <div class="input-group">
            <textarea name="content" class="form-control" rows="3" placeholder="Напишите сообщение"></textarea>
            <input type="file" name="image" accept="image/jpeg,image/png,image/gif" class="form-control">
            <button type="submit" class="btn btn-primary">Отправить</button>
        </div>
    </form>
</div>

<script>
$(function() {
    const chatContainer = $('.chat-container');
    const messagesDiv = $('#messages');
    const receiverId = <?= $receiver_id ?>;
    const currentUsername = <?= json_encode($current_username) ?>;
    let lastMessageId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;
    let isPolling = false;
    let isSending = false;
    const displayedMessages = new Set(<?= json_encode(array_column($messages, 'id')) ?>);

    // Проверяем, был ли обработчик уже привязан
    if ($('#messageForm').data('handler-bound')) {
        return; // Выходим, если обработчик уже привязан
    }
    $('#messageForm').data('handler-bound', true);

    // Прокрутка чата вниз
    chatContainer.scrollTop(chatContainer[0].scrollHeight);

    // Отправка сообщения через AJAX с дебаунсингом
    let debounceTimeout;
    $('#messageForm').off('submit').on('submit', function(e) {
        e.preventDefault();
        if (isSending) return;

        // Проверка на пустое сообщение
        const content = $(this).find('textarea[name="content"]').val().trim();
        const image = $(this).find('input[name="image"]').get(0).files.length > 0;
        if (!content && !image) {
            alert('Сообщение или изображение должны быть заполнены');
            return;
        }

        isSending = true;
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => {
            const form = $(this);
            const formData = new FormData(form[0]);
            formData.append('receiver_id', receiverId);

            $.ajax({
                url: 'send_message.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(data) {
                    console.log('Отправка сообщения:', data);
                    if (data.success && data.message_id && !displayedMessages.has(data.message_id)) {
                        const content = form.find('textarea[name="content"]').val().trim();
                        let messageContent = '';
                        if (content && !data.image) {
                            messageContent = content.replace(/\n/g, '<br>');
                        }
                        const image = data.image ? `<img src="Uploads/${data.image}" class="img-fluid mt-2" style="max-width: 200px;" alt="message image">` : '';
                        const message = $(`
                            <div class="message mine" data-message-id="${data.message_id}">
                                <div class="sender">${currentUsername}</div>
                                <div>${messageContent}${image}</div>
                                <small class="text-muted">${new Date().toLocaleString()}</small>
                            </div>
                        `);
                        messagesDiv.append(message);
                        displayedMessages.add(data.message_id);
                        lastMessageId = Math.max(lastMessageId, data.message_id);
                        form.find('textarea').val('');
                        form.find('input[name="image"]').val('');
                        chatContainer.scrollTop(chatContainer[0].scrollHeight);
                    } else if (!data.success) {
                        alert('Ошибка: ' + (data.error || 'Не удалось отправить сообщение'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ошибка AJAX:', status, error, xhr.responseText);
                    alert('Ошибка сервера при отправке сообщения');
                },
                complete: function() {
                    isSending = false;
                }
            });
        }, 300); // Задержка 300 мс
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
                            let messageContent = '';
                            if (msg.content) {
                                messageContent = msg.content.replace(/\n/g, '<br>');
                            }
                            const image = msg.image ? `<img src="Uploads/${msg.image}" class="img-fluid mt-2" style="max-width: 200px;" alt="message image">` : '';
                            const message = $(`
                                <div class="message ${msg.sender_id == <?= $_SESSION['user_id'] ?> ? 'mine' : 'other'}" data-message-id="${msg.id}">
                                    <div class="sender">${msg.username}</div>
                                    <div>${messageContent}${image}</div>
                                    <small class="text-muted">${msg.created_at}</small>
                                </div>
                            `);
                            messagesDiv.append(message);
                            displayedMessages.add(msg.id);
                        }
                    });
                    lastMessageId = Math.max(lastMessageId, data.last_id); // Обновляем last_id
                    chatContainer.scrollTop(chatContainer[0].scrollHeight);
                }
            },
            complete: function() {
                isPolling = false;
                setTimeout(pollMessages, 3000); // Увеличено до 3 секунд
            },
            error: function(xhr, status, error) {
                console.error('Ошибка опроса:', status, error);
                setTimeout(pollMessages, 5000);
            }
        });
    }

    pollMessages();
});
</script>

<?php require 'inc/footer.php'; ?>