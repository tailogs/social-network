<?php
require 'inc/header.php';

// Получаем посты от пользователей, на которых подписан текущий пользователь, и свои посты
$stmt = $pdo->prepare("
    SELECT posts.*, users.username, users.avatar, users.status, users.last_active,
        (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.comment_id IS NULL AND is_dislike = 0) AS likes,
        (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.comment_id IS NULL AND is_dislike = 1) AS dislikes
    FROM posts
    JOIN users ON users.id = posts.user_id
    WHERE posts.user_id = ? OR posts.user_id IN (
        SELECT following_id FROM followers WHERE follower_id = ?
    )
    ORDER BY posts.created_at DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$posts = $stmt->fetchAll();

// Проверяем параметр ошибки
$error_message = '';
if (isset($_GET['error']) && $_GET['error'] === 'empty_content') {
    $error_message = '<div class="alert alert-danger">Пожалуйста, введите текст поста.</div>';
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Лента</h2>
        <div>
            <a href="users.php" class="btn btn-sm btn-secondary">Пользователи</a>
            <a href="profile.php" class="btn btn-sm btn-secondary">Профиль</a>
            <a href="logout.php" class="btn btn-sm btn-danger">Выйти</a>
        </div>
    </div>

    <!-- Отображение ошибки -->
    <?php if ($error_message): ?>
        <?= $error_message ?>
    <?php endif; ?>

    <form method="post" action="post.php" id="postForm" class="mb-4">
        <textarea name="content" class="form-control mb-2" placeholder="Что нового?" required></textarea>
        <button type="submit" class="btn btn-primary">Опубликовать</button>
    </form>

    <?php if (empty($posts)): ?>
        <p>Нет постов. Подпишитесь на кого-нибудь!</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div class="card bg-secondary text-white mb-3" data-post-id="<?= $post['id'] ?>">
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
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>

                    <div class="mt-1">
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

    // Загрузка комментариев по клику
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

<?php require 'inc/footer.php'; ?>