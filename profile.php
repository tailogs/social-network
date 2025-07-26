<?php
require 'inc/db.php';
require 'inc/auth.php';
require_login();

// Проверяем, просматривается ли профиль другого пользователя
$view_user_id = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : $_SESSION['user_id'];
$is_own_profile = $view_user_id == $_SESSION['user_id'];

// Обновляем last_active только для текущего пользователя
$pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?")->execute([$_SESSION['user_id']]);

// Получаем данные пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$view_user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php');
    exit;
}

// Определяем статус онлайн/офлайн (5 минут = 300 секунд)
$is_online = (strtotime($user['last_active']) > (time() - 300));

// Получаем количество подписчиков и подписок
$stmt_followers = $pdo->prepare("SELECT COUNT(*) AS count FROM followers WHERE following_id = ?");
$stmt_followers->execute([$view_user_id]);
$followers_count = $stmt_followers->fetch()['count'];

$stmt_following = $pdo->prepare("SELECT COUNT(*) AS count FROM followers WHERE follower_id = ?");
$stmt_following->execute([$view_user_id]);
$following_count = $stmt_following->fetch()['count'];

// Проверяем, подписан ли текущий пользователь на просматриваемого
$stmt_is_following = $pdo->prepare("SELECT COUNT(*) AS count FROM followers WHERE follower_id = ? AND following_id = ?");
$stmt_is_following->execute([$_SESSION['user_id'], $view_user_id]);
$is_following = $stmt_is_following->fetch()['count'] > 0;

// Переменные для сообщений об ошибках и успехе
$status_message = '';
$username_message = '';
$avatar_message = '';

if ($is_own_profile) {
    // Обработка изменения статуса
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
        $status = trim($_POST['status']);
        if ($status !== $user['status']) {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$status, $_SESSION['user_id']]);
            $user['status'] = $status;
            $status_message = '<div class="alert alert-success">Статус успешно обновлен!</div>';
        }
    }

    // Обработка изменения имени пользователя
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
        $new_username = trim($_POST['username']);
        if ($new_username !== $user['username']) {
            if (strlen($new_username) < 3) {
                $username_message = '<div class="alert alert-danger">Имя пользователя должно содержать минимум 3 символа.</div>';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$new_username, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $username_message = '<div class="alert alert-danger">Это имя пользователя уже занято.</div>';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $stmt->execute([$new_username, $_SESSION['user_id']]);
                    $user['username'] = $new_username;
                    $username_message = '<div class="alert alert-success">Имя пользователя успешно изменено!</div>';
                }
            }
        }
    }

    // Обработка загрузки аватара
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_extensions)) {
            $avatar_message = '<div class="alert alert-danger">Разрешены только файлы JPG, PNG или GIF.</div>';
        } elseif ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
            $avatar_message = '<div class="alert alert-danger">Файл слишком большой (максимум 5 МБ).</div>';
        } else {
            $avatar = uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], "Uploads/$avatar")) {
                if ($user['avatar'] && $user['avatar'] !== 'default.png' && file_exists("Uploads/{$user['avatar']}")) {
                    unlink("Uploads/{$user['avatar']}");
                }
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$avatar, $_SESSION['user_id']]);
                $user['avatar'] = $avatar;
                $avatar_message = '<div class="alert alert-success">Аватар успешно обновлен!</div>';
            } else {
                $avatar_message = '<div class="alert alert-danger">Ошибка при загрузке аватара.</div>';
            }
        }
    }
}

// Получаем посты пользователя
$stmtPosts = $pdo->prepare("
    SELECT posts.*, 
           (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.comment_id IS NULL AND is_dislike = 0) AS likes,
           (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.comment_id IS NULL AND is_dislike = 1) AS dislikes
    FROM posts
    WHERE user_id = ?
    ORDER BY posts.created_at DESC
");
$stmtPosts->execute([$view_user_id]);
$posts = $stmtPosts->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Профиль <?= htmlspecialchars($user['username']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-dark text-white">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= $is_own_profile ? 'Привет, ' : 'Профиль ' ?><?= htmlspecialchars($user['username']) ?></h2>
        <div>
            <a href="index.php" class="btn btn-sm btn-secondary">Лента</a>
            <a href="users.php" class="btn btn-sm btn-secondary">Пользователи</a>
            <?php if (!$is_own_profile): ?>
                <a href="messages.php?user_id=<?= $view_user_id ?>" class="btn btn-sm btn-outline-light">Написать</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-sm btn-danger">Выйти</a>
        </div>
    </div>

    <div class="mb-4">
        <?php if ($user['avatar']): ?>
            <img src="Uploads/<?= htmlspecialchars($user['avatar']) ?>" width="100" class="rounded-circle mb-3" alt="avatar">
        <?php else: ?>
            <img src="Uploads/default.png" width="100" class="rounded-circle mb-3" alt="default avatar">
        <?php endif; ?>
        <p><strong>Статус:</strong> <span class="badge <?= $is_online ? 'bg-success' : 'bg-secondary' ?>">
            <?= $is_online ? 'Онлайн' : 'Офлайн' ?>
        </span></p>
        <p><strong>Текущий статус:</strong> <?= $user['status'] ? htmlspecialchars($user['status']) : 'Не указан' ?></p>
        <p><strong>Подписчики:</strong> <?= $followers_count ?></p>
        <p><strong>Подписки:</strong> <?= $following_count ?></p>
        <p><strong>Создан:</strong> <?= $user['created_at'] ?></p>
        <p><strong>Последняя активность:</strong> <?= $user['last_active'] ?></p>
        <?php if (!$is_own_profile): ?>
            <form method="post" action="follow.php" class="follow-form mt-2">
                <input type="hidden" name="user_id" value="<?= $view_user_id ?>">
                <button type="submit" class="btn btn-sm <?= $is_following ? 'btn-outline-danger' : 'btn-outline-primary' ?>">
                    <?= $is_following ? 'Отписаться' : 'Подписаться' ?>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($is_own_profile): ?>
        <!-- Форма для изменения имени пользователя -->
        <form method="post" class="mb-4">
            <div class="input-group">
                <input type="text" name="username" class="form-control" placeholder="Новое имя пользователя" maxlength="255" value="<?= htmlspecialchars($user['username']) ?>" required>
                <button type="submit" class="btn btn-primary">Изменить имя</button>
            </div>
            <?php if ($username_message): ?>
                <?= $username_message ?>
            <?php endif; ?>
        </form>

        <!-- Форма для изменения статуса -->
        <form method="post" class="mb-4">
            <div class="input-group">
                <input type="text" name="status" class="form-control" placeholder="Введите новый статус" maxlength="255" value="<?= htmlspecialchars($user['status'] ?? '') ?>">
                <button type="submit" class="btn btn-primary">Обновить статус</button>
            </div>
            <?php if ($status_message): ?>
                <?= $status_message ?>
            <?php endif; ?>
        </form>

        <!-- Форма для загрузки аватара -->
        <form method="post" enctype="multipart/form-data" class="mb-4">
            <div class="input-group">
                <input type="file" name="avatar" class="form-control" accept="image/jpeg,image/png,image/gif" required>
                <button type="submit" class="btn btn-primary">Обновить аватар</button>
            </div>
            <?php if ($avatar_message): ?>
                <?= $avatar_message ?>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <h3>Посты</h3>
    <?php if (empty($posts)): ?>
        <p>Нет постов.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div class="card bg-secondary text-white mb-3" data-post-id="<?= $post['id'] ?>">
                <div class="card-body">
                    <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                    <span class="text-muted"><?= $post['created_at'] ?></span>
                    <?php if ($is_own_profile): ?>
                        <form class="delete-post-form d-inline ms-2">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                    <?php endif; ?>

                    <div class="mt-2">
                        <form method="post" action="like.php" class="like-post-form d-inline me-2">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <button class="btn btn-sm btn-outline-light">👍 <?= (int)$post['likes'] ?></button>
                        </form>
                        <form method="post" action="like.php" class="dislike-post-form d-inline">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <input type="hidden" name="dislike" value="1">
                            <button class="btn btn-sm btn-outline-light">👎 <?= (int)$post['dislikes'] ?></button>
                        </form>
                    </div>

                    <button class="btn btn-sm btn-outline-light show-comments-btn mt-3" data-post-id="<?= $post['id'] ?>">Показать комментарии</button>
                    <div class="comments-container mt-3" data-post-id="<?= $post['id'] ?>" style="display:none;"></div>
                </div>
            </div>
        <?php endforeach ?>
    <?php endif ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function() {
    // Лайки/дизлайки постов
    $('.like-post-form, .dislike-post-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        $.post('like.php', form.serialize(), function(data) {
            if (data.likes !== undefined && data.dislikes !== undefined) {
                const postDiv = form.closest('[data-post-id]');
                postDiv.find('.like-post-form button').html('👍 ' + data.likes);
                postDiv.find('.dislike-post-form button').html('👎 ' + data.dislikes);
            }
        }, 'json');
    });

    // Удаление постов
    $('.delete-post-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const postId = form.find('input[name="post_id"]').val();
        if (confirm('Вы уверены, что хотите удалить этот пост?')) {
            $.post('delete.php', { post_id: postId }, function(data) {
                if (data.success && data.type === 'post') {
                    form.closest('.card').fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert('Ошибка: ' + (data.error || 'Не удалось удалить пост'));
                }
            }, 'json').fail(function() {
                alert('Ошибка сервера');
            });
        }
    });

    // Подписка/отписка
    $('.follow-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const button = form.find('button');
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

    // Загрузка комментариев
    $('.show-comments-btn').on('click', function() {
        const btn = $(this);
        const postId = btn.data('post-id');
        const container = $('.comments-container[data-post-id="' + postId + '"]');

        if (container.is(':visible')) {
            container.slideUp();
            btn.text('Показать комментарии');
            return;
        }

        if (container.data('loaded')) {
            container.slideDown();
            btn.text('Скрыть комментарии');
            return;
        }

        btn.prop('disabled', true).text('Загрузка...');

        $.get('comments.php', { post_id: postId }, function(data) {
            container.html(data);
            container.data('loaded', true);
            container.slideDown();
            btn.text('Скрыть комментарии');

            container.find('.like-form').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                $.post('like.php', form.serialize(), function(data) {
                    if (data.likes !== undefined && data.dislikes !== undefined) {
                        const isDislike = form.find('input[name="dislike"]').length > 0;
                        if (isDislike) {
                            form.find('button').html('👎 ' + data.dislikes);
                        } else {
                            form.find('button').html('👍 ' + data.likes);
                        }
                    }
                }, 'json');
            });

            // Обработчик для динамически загруженных комментариев
            container.find('.delete-comment-form').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                const commentId = form.find('input[name="comment_id"]').val();
                if (confirm('Вы уверены, что хотите удалить этот комментарий?')) {
                    $.post('delete.php', { comment_id: commentId }, function(data) {
                        if (data.success && data.type === 'comment') {
                            form.closest('.comment').fadeOut(300, function() { $(this).remove(); });
                        } else {
                            alert('Ошибка: ' + (data.error || 'Не удалось удалить комментарий'));
                        }
                    }, 'json').fail(function() {
                        alert('Ошибка сервера');
                    });
                }
            });
        }).fail(function() {
            alert('Ошибка загрузки комментариев');
            btn.text('Показать комментарии');
        }).always(function() {
            btn.prop('disabled', false);
        });
    });
});
</script>
</body>
</html>