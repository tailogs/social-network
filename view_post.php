<?php
require 'inc/db.php';
require 'inc/auth.php';
require_login();

if (!isset($_GET['post_id']) || !is_numeric($_GET['post_id'])) {
    exit('Неверный идентификатор поста');
}

$post_id = (int)$_GET['post_id'];

// Получаем пост с автором
$stmt = $pdo->prepare("
    SELECT posts.*, users.username, users.avatar, users.status, users.last_active
    FROM posts
    JOIN users ON users.id = posts.user_id
    WHERE posts.id = ?
");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if (!$post) {
    exit('Пост не найден');
}

// Считаем лайки и дизлайки поста
$stmtLikes = $pdo->prepare("
    SELECT 
        SUM(is_dislike = 0) AS likes,
        SUM(is_dislike = 1) AS dislikes
    FROM likes
    WHERE post_id = ? AND comment_id IS NULL
");
$stmtLikes->execute([$post_id]);
$likes = $stmtLikes->fetch();

// Получаем комментарии и их авторов
function renderComments($comments, $pdo) {
    foreach ($comments as $comment) {
        echo '<div class="comment mb-2 ps-4 border-start border-2 border-light" data-comment-id="' . $comment['id'] . '">';
        echo '<a href="profile.php?user_id=' . $comment['user_id'] . '">';
        echo '<img src="Uploads/' . htmlspecialchars($comment['avatar'] ?? 'default.png') . '" width="30" height="30" class="rounded-circle me-2" alt="avatar">';
        echo '</a>';
        echo '<div>';
        echo '<a href="profile.php?user_id=' . $comment['user_id'] . '" class="text-white text-decoration-none">';
        echo '<strong>' . htmlspecialchars($comment['username']) . '</strong>';
        echo '</a>';
        echo '<span class="badge ' . (strtotime($comment['last_active']) > (time() - 300) ? 'bg-success' : 'bg-secondary') . ' ms-2">';
        echo (strtotime($comment['last_active']) > (time() - 300)) ? 'Онлайн' : 'Офлайн';
        echo '</span>';
        if ($comment['status']) {
            echo '<small class="d-block text-muted">' . htmlspecialchars($comment['status']) . '</small>';
        }
        if ($comment['user_id'] != $_SESSION['user_id']) {
            echo '<a href="messages.php?user_id=' . $comment['user_id'] . '" class="btn btn-sm btn-outline-light ms-2">Написать</a>';
        } else {
            echo '<form class="delete-comment-form d-inline ms-2">';
            echo '<input type="hidden" name="comment_id" value="' . $comment['id'] . '">';
            echo '<button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>';
            echo '</form>';
        }
        echo '</div>';
        echo '<div>' . nl2br(htmlspecialchars($comment['content'])) . '</div>';
        echo '<small class="text-muted">' . $comment['created_at'] . '</small>';

        $stmtLikes = $pdo->prepare("SELECT SUM(is_dislike = 0) AS likes, SUM(is_dislike = 1) AS dislikes FROM likes WHERE comment_id = ?");
        $stmtLikes->execute([$comment['id']]);
        $counts = $stmtLikes->fetch();

        echo '<div class="mt-1">';
        echo '<form method="post" action="like.php" class="like-form d-inline me-2">';
        echo '<input type="hidden" name="comment_id" value="' . $comment['id'] . '">';
        echo '<button class="btn btn-sm btn-outline-light">👍 ' . ((int)$counts['likes']) . '</button>';
        echo '</form>';
        echo '<form method="post" action="like.php" class="like-form d-inline">';
        echo '<input type="hidden" name="comment_id" value="' . $comment['id'] . '">';
        echo '<input type="hidden" name="dislike" value="1">';
        echo '<button class="btn btn-sm btn-outline-light">👎 ' . ((int)$counts['dislikes']) . '</button>';
        echo '</form>';
        echo '</div>';

        $stmtReplies = $pdo->prepare("SELECT c.*, u.username, u.avatar, u.status, u.last_active FROM comments c JOIN users u ON c.user_id = u.id WHERE c.parent_id = ? ORDER BY c.created_at ASC");
        $stmtReplies->execute([$comment['id']]);
        $replies = $stmtReplies->fetchAll();

        if ($replies) {
            echo '<div class="replies ms-4 mt-2">';
            renderComments($replies, $pdo);
            echo '</div>';
        }

        echo '<form action="comment.php" method="post" class="mt-2">';
        echo '<input type="hidden" name="post_id" value="' . $comment['post_id'] . '">';
        echo '<input type="hidden" name="parent_id" value="' . $comment['id'] . '">';
        echo '<textarea name="content" class="form-control form-control-sm" rows="2" placeholder="Ответить" required></textarea>';
        echo '<button type="submit" class="btn btn-sm btn-outline-light mt-1">Ответить</button>';
        echo '</form>';

        echo '</div>';
    }
}

// Получаем корневые комментарии
$stmtComments = $pdo->prepare("SELECT c.*, u.username, u.avatar, u.status, u.last_active FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? AND c.parent_id IS NULL ORDER BY c.created_at ASC");
$stmtComments->execute([$post_id]);
$comments = $stmtComments->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Пост #<?= $post_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">
<div class="container py-4">
    <a href="index.php" class="btn btn-secondary mb-3">Назад</a>

    <div class="card bg-secondary text-white mb-3" data-post-id="<?= $post_id ?>">
        <div class="card-body">
            <div class="d-flex align-items-center mb-2">
                <a href="profile.php?user_id=<?= $post['user_id'] ?>">
                    <img src="Uploads/<?= htmlspecialchars($post['avatar'] ?? 'default.png') ?>" class="rounded-circle me-2" width="40" height="40" alt="avatar">
                </a>
                <div>
                    <a href="profile.php?user_id=<?= $post['user_id'] ?>" class="text-white text-decoration-none">
                        <strong><?= htmlspecialchars($post['username']) ?></strong>
                    </a>
                    <span class="badge <?= (strtotime($post['last_active']) > (time() - 300)) ? 'bg-success' : 'bg-secondary' ?> ms-2">
                        <?= (strtotime($post['last_active']) > (time() - 300)) ? 'Онлайн' : 'Офлайн' ?>
                    </span>
                    <?php if ($post['status']): ?>
                        <small class="d-block text-muted"><?= htmlspecialchars($post['status']) ?></small>
                    <?php endif; ?>
                </div>
                <span class="ms-auto text-muted"><?= $post['created_at'] ?></span>
                <?php if ($post['user_id'] != $_SESSION['user_id']): ?>
                    <a href="messages.php?user_id=<?= $post['user_id'] ?>" class="btn btn-sm btn-outline-light ms-2">Написать</a>
                <?php else: ?>
                    <form class="delete-post-form d-inline ms-2">
                        <input type="hidden" name="post_id" value="<?= $post_id ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                    </form>
                <?php endif; ?>
            </div>
            <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>

            <div class="mt-1">
                <form method="post" action="like.php" class="like-post-form d-inline me-2">
                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                    <button class="btn btn-sm btn-outline-light">👍 <?= (int)$likes['likes'] ?></button>
                </form>
                <form method="post" action="like.php" class="dislike-post-form d-inline">
                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                    <input type="hidden" name="dislike" value="1">
                    <button class="btn btn-sm btn-outline-light">👎 <?= (int)$likes['dislikes'] ?></button>
                </form>
            </div>
        </div>
    </div>

    <h4>Комментарии</h4>
    <?php renderComments($comments, $pdo); ?>

    <form action="comment.php" method="post" class="mt-3">
        <input type="hidden" name="post_id" value="<?= $post_id ?>">
        <textarea name="content" class="form-control" rows="3" placeholder="Добавить комментарий" required></textarea>
        <button type="submit" class="btn btn-primary mt-2">Комментировать</button>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function() {
    $('.like-post-form, .dislike-post-form, .like-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        $.post('like.php', form.serialize(), function(data) {
            if (data.likes !== undefined && data.dislikes !== undefined) {
                if (form.hasClass('like-post-form') || form.hasClass('dislike-post-form')) {
                    const card = form.closest('.card');
                    card.find('.like-post-form button').html('👍 ' + data.likes);
                    card.find('.dislike-post-form button').html('👎 ' + data.dislikes);
                } else {
                    // Для комментариев
                    const isDislike = form.find('input[name="dislike"]').length > 0;
                    form.find('button').html((isDislike ? '👎 ' : '👍 ') + (isDislike ? data.dislikes : data.likes));
                }
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
                    window.location.href = 'index.php'; // Перенаправление на главную
                } else {
                    alert('Ошибка: ' + (data.error || 'Не удалось удалить пост'));
                }
            }, 'json').fail(function() {
                alert('Ошибка сервера');
            });
        }
    });

    // Удаление комментариев
    $('.delete-comment-form').on('submit', function(e) {
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
});
</script>
</body>
</html>