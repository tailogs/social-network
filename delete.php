<?php
require 'inc/db.php';
require 'inc/auth.php';
require_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : null;
$comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : null;

if ($post_id === null && $comment_id === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан post_id или comment_id']);
    exit;
}

try {
    if ($post_id !== null) {
        // Проверяем, принадлежит ли пост пользователю
        $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
        if (!$post || $post['user_id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Вы не можете удалить этот пост']);
            exit;
        }

        // Удаляем лайки, связанные с постом
        $stmt = $pdo->prepare("DELETE FROM likes WHERE post_id = ? AND comment_id IS NULL");
        $stmt->execute([$post_id]);

        // Удаляем комментарии, связанные с постом
        $stmt = $pdo->prepare("DELETE FROM likes WHERE comment_id IN (SELECT id FROM comments WHERE post_id = ?)");
        $stmt->execute([$post_id]);

        $stmt = $pdo->prepare("DELETE FROM comments WHERE post_id = ?");
        $stmt->execute([$post_id]);

        // Удаляем пост
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);

        echo json_encode(['success' => true, 'type' => 'post']);
    } elseif ($comment_id !== null) {
        // Проверяем, принадлежит ли комментарий пользователю
        $stmt = $pdo->prepare("SELECT user_id, post_id FROM comments WHERE id = ?");
        $stmt->execute([$comment_id]);
        $comment = $stmt->fetch();
        if (!$comment || $comment['user_id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Вы не можете удалить этот комментарий']);
            exit;
        }

        // Удаляем лайки, связанные с комментарием и его ответами
        $stmt = $pdo->prepare("DELETE FROM likes WHERE comment_id = ? OR comment_id IN (SELECT id FROM comments WHERE parent_id = ?)");
        $stmt->execute([$comment_id, $comment_id]);

        // Удаляем ответы на комментарий
        $stmt = $pdo->prepare("DELETE FROM comments WHERE parent_id = ?");
        $stmt->execute([$comment_id]);

        // Удаляем сам комментарий
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$comment_id]);

        echo json_encode(['success' => true, 'type' => 'comment', 'post_id' => $comment['post_id']]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
}