<?php
require 'inc/db.php';
require 'inc/auth.php';
require_login();

// Обновляем last_active
$pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?")->execute([$_SESSION['user_id']]);

// Получаем всех пользователей и статус подписки
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.avatar, u.status, u.last_active,
           (SELECT COUNT(*) FROM followers WHERE follower_id = ? AND following_id = u.id) AS is_following
    FROM users u
    WHERE u.id != ?
    ORDER BY u.username ASC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Пользователи</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-dark text-white">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Пользователи</h2>
        <div>
            <a href="index.php" class="btn btn-sm btn-secondary">Лента</a>
            <a href="profile.php" class="btn btn-sm btn-secondary">Профиль</a>
            <a href="logout.php" class="btn btn-sm btn-danger">Выйти</a>
        </div>
    </div>

    <?php if (empty($users)): ?>
        <p>Нет других пользователей.</p>
    <?php else: ?>
        <div class="row">
            <?php foreach ($users as $user): ?>
                <div class="col-md-4 mb-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <img src="Uploads/<?= htmlspecialchars($user['avatar'] ?? 'default.png') ?>" class="rounded-circle me-2" width="40" height="40" alt="avatar">
                                <div>
                                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                                    <span class="badge <?= (strtotime($user['last_active']) > (time() - 300)) ? 'bg-success' : 'bg-secondary' ?> ms-2">
                                        <?= (strtotime($user['last_active']) > (time() - 300)) ? 'Онлайн' : 'Офлайн' ?>
                                    </span>
                                    <?php if ($user['status']): ?>
                                        <small class="d-block text-muted"><?= htmlspecialchars($user['status']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <form method="post" action="follow.php" class="follow-form">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $user['is_following'] ? 'btn-outline-danger' : 'btn-outline-primary' ?>">
                                        <?= $user['is_following'] ? 'Отписаться' : 'Подписаться' ?>
                                    </button>
                                </form>
                                <a href="messages.php?user_id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-light">Написать</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function() {
    $('.follow-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const button = form.find('button');
        const userId = form.find('input[name="user_id"]').val();
        
        $.post('follow.php', form.serialize(), function(data) {
            if (data.success) {
                if (data.action === 'followed') {
                    button.removeClass('btn-outline-primary').addClass('btn-outline-danger').text('Отписаться');
                } else {
                    button.removeClass('btn-outline-danger').addClass('btn-outline-primary').text('Подписаться');
                }
            } else {
                alert('Ошибка: ' + (data.error || 'Не удалось выполнить действие'));
            }
        }, 'json').fail(function() {
            alert('Ошибка сервера');
        });
    });
});
</script>
</body>
</html>