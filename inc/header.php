<?php
require_once 'db.php';
require_once 'auth.php';
require_login();
$pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?")->execute([$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Соцсеть</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="bg-dark text-white">
<div class="d-flex">
    <?php include 'sidebar.php'; ?>
    <div class="content" style="margin-left: 250px; width: calc(100% - 250px);">
<script>
$(function() {
    // Периодический опрос для обновления боковой панели
    function updateSidebar() {
        $.ajax({
            url: 'get_unread_chats.php',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success && data.chats) {
                    const sidebar = $('.sidebar');
                    const ul = sidebar.find('ul.list-unstyled').length ? sidebar.find('ul.list-unstyled') : $('<ul class="list-unstyled"></ul>');
                    
                    // Очищаем текущий список чатов
                    ul.empty();
                    
                    // Проверяем, есть ли уже сообщение "Нет активных чатов"
                    const noChatsMessage = sidebar.find('p.no-chats-message');
                    
                    if (data.chats.length === 0) {
                        // Добавляем сообщение, только если его ещё нет
                        if (!noChatsMessage.length) {
                            sidebar.find('h4').after('<p class="no-chats-message">Нет активных чатов.</p>');
                        }
                    } else {
                        // Удаляем сообщение "Нет чатов", если оно есть
                        noChatsMessage.remove();
                        data.chats.forEach(chat => {
                            const li = $(`
                                <li class="mb-2">
                                    <a href="messages.php?user_id=${chat.id}" class="text-white text-decoration-none d-flex align-items-center">
                                        <img src="Uploads/${chat.avatar}" class="rounded-circle me-2" width="30" height="30" alt="avatar">
                                        <span>${chat.username}</span>
                                        ${chat.unread_count > 0 ? `<span class="badge bg-danger ms-2">${chat.unread_count}</span>` : ''}
                                    </a>
                                </li>
                            `);
                            ul.append(li);
                        });
                    }
                    
                    if (!sidebar.find('ul.list-unstyled').length) {
                        sidebar.append(ul);
                    }
                }
            },
            error: function() {
                console.log('Ошибка при обновлении боковой панели');
            },
            complete: function() {
                setTimeout(updateSidebar, 5000); // Обновление каждые 5 секунд
            }
        });
    }

    // Запускаем опрос только если пользователь не на странице messages.php
    if (!window.location.pathname.includes('messages.php')) {
        updateSidebar();
    }
});
</script>