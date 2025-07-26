<?php
require 'inc/db.php';
require 'inc/auth.php';
require_login();

if (!isset($_GET['post_id']) || !is_numeric($_GET['post_id'])) {
    http_response_code(400);
    echo 'Неверный post_id';
    exit;
}

$post_id = (int)$_GET['post_id'];

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
            echo '<a href="messages.php?user_id=' . $comment['user_id'] . '" class="btn btn-sm btn-primary ms-2">Написать</a>';
        } else {
            echo '<form class="delete-comment-form d-inline ms-2">';
            echo '<input type="hidden" name="comment_id" value="' . $comment['id'] . '">';
            echo '<button type="submit" class="btn btn-sm btn-danger">Удалить</button>';
            echo '</form>';
        }
        echo '</div>';
        echo '<div>' . nl2br(htmlspecialchars($comment['content'])) . '</div>';
        echo '<small class="text-muted">' . $comment['created_at'] . '</small>';

        // Лайки/дизлайки комментария
        $stmtLikes = $pdo->prepare("
            SELECT
                SUM(is_dislike = 0) AS likes,
                SUM(is_dislike = 1) AS dislikes
            FROM likes WHERE comment_id = ?
        ");
        $stmtLikes->execute([$comment['id']]);
        $counts = $stmtLikes->fetch();

        echo '<div class="mt-1">';
        echo '<form method="post" action="like.php" class="like-form d-inline me-2">';
        echo '<input type="hidden" name="comment_id" value="' . $comment['id'] . '">';
        echo '<button class="btn btn-sm btn-outline-light">👍 ' . ((int)($counts['likes'] ?? 0)) . '</button>';
        echo '</form>';
        echo '<form method="post" action="like.php" class="like-form d-inline">';
        echo '<input type="hidden" name="comment_id" value="' . $comment['id'] . '">';
        echo '<input type="hidden" name="dislike" value="1">';
        echo '<button class="btn btn-sm btn-outline-light">👎 ' . ((int)($counts['dislikes'] ?? 0)) . '</button>';
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

// Выгружаем комментарии первого уровня
$stmt = $pdo->prepare("SELECT c.*, u.username, u.avatar, u.status, u.last_active FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? AND c.parent_id IS NULL ORDER BY c.created_at ASC");
$stmt->execute([$post_id]);
$comments = $stmt->fetchAll();

renderComments($comments, $pdo); // Исправлено: $posts заменено на $comments

// Форма нового комментария под всеми комментариями
echo '<form action="comment.php" method="post" class="mt-3">';
echo '<input type="hidden" name="post_id" value="' . $post_id . '">';
echo '<textarea name="content" class="form-control" rows="3" placeholder="Оставить комментарий" required></textarea>';
echo '<button type="submit" class="btn btn-primary mt-2">Комментировать</button>';
echo '</form>';

?>